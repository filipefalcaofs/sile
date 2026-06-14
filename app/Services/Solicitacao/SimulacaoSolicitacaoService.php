<?php

namespace App\Services\Solicitacao;

use App\Enums\ResultadoViabilidade;
use App\Models\Cnae;
use App\Models\ViabilityRequest;
use App\Services\Viabilidade\ConsultaViabilidadeService;
use App\Support\Audit\AuditService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Simulação pré-protocolo da viabilidade (HU-141): itera os CNAEs da solicitação
 * (principal + complementares) e chama o ConsultaViabilidadeService (Fase 7) PELO
 * PONTO da própria solicitação (centroide do polígono do imóvel — sem
 * geocodificar de novo), PROPAGANDO o veredito do motor LOUOS por CNAE (RN-001 —
 * nunca lógica de decisão paralela). Sem polígono, degrada honesto para a via
 * CNAE (risco + Quadro 7, sem território).
 *
 * Consolida a tendência (pior caso entre os CNAEs), captura as versões das regras
 * e PERSISTE o snapshot (por CNAE) + versões + resultado + simulated_at no
 * processo (RN-003) — o protocolo (08-10) LÊ o snapshot, não reprocessa. É
 * ORIENTATIVA: não muda status nem bloqueia o protocolo (RN-002). A execução é
 * auditada (RN-002). Sem zona oficial, o veredito por CNAE é "pendente"
 * (propagado) — nunca permitido/não permitido inventado.
 */
class SimulacaoSolicitacaoService
{
    /**
     * Severidade da tendência para consolidar o pior caso entre os CNAEs: a
     * tendência mais restritiva governa o resultado consolidado da solicitação
     * (uma atividade que tende a indeferimento puxa o conjunto). "pendente" fica
     * acima dos permitidos porque é "indeterminado — precisa de análise".
     */
    private const SEVERIDADE = [
        ResultadoViabilidade::NaoPermitido->value => 3,
        ResultadoViabilidade::Pendente->value => 2,
        ResultadoViabilidade::PermitidoComCondicoes->value => 1,
        ResultadoViabilidade::Permitido->value => 0,
    ];

    public function __construct(
        private ConsultaViabilidadeService $consulta,
        private AuditService $audit,
    ) {}

    /**
     * Executa a simulação e persiste o snapshot no processo, devolvendo o
     * resultado consolidado para a etapa do wizard (08-13).
     *
     * @return array<string, mixed>
     */
    public function simulate(ViabilityRequest $request): array
    {
        $ponto = $this->centroid($request->property_polygon_geojson);
        $area = $request->used_area_m2 !== null ? (float) $request->used_area_m2 : null;

        $porCnae = [];
        $rulesVersions = [];

        foreach ($this->orderedCnaes($request) as $cnae) {
            $result = $ponto === null
                ? $this->consulta->consultarPorCnae($cnae->code, $area)
                : $this->consulta->consultarPorPontoConhecido($ponto['lat'], $ponto['lng'], $cnae->code, $area);

            $veredito = $result->vereditoLocacional();

            $porCnae[] = [
                'cnae' => $cnae->code,
                'cnae_formatado' => $cnae->formatted_code,
                'is_primary' => (bool) $cnae->pivot->is_primary,
                'tendencia' => $veredito['resultado'],
                'tendencia_label' => $veredito['label'],
                'consulta' => $result->toArray(),
            ];

            // As versões das regras são idênticas entre os CNAEs (mesmo ponto +
            // mesma vigência); a primeira é a representativa do snapshot (RN-002).
            if ($rulesVersions === []) {
                $rulesVersions = $result->versoes();
            }
        }

        $resultado = $this->consolidar($porCnae);

        $snapshot = [
            'ponto' => $ponto,
            'area_m2' => $area,
            'por_cnae' => $porCnae,
        ];

        DB::transaction(function () use ($request, $snapshot, $rulesVersions, $resultado): void {
            $request->update([
                'simulation_snapshot' => $snapshot,
                'simulation_rules_versions' => $rulesVersions,
                'simulation_resultado' => $resultado,
                'simulated_at' => now(),
            ]);

            $this->audit->log(
                logName: 'solicitacoes',
                event: 'simulacao',
                description: 'Simulação de viabilidade pré-protocolo executada (orientativa)',
                properties: [
                    'solicitacao_id' => $request->id,
                    'resultado' => $resultado,
                    'por_cnae' => array_map(
                        static fn (array $item): array => ['cnae' => $item['cnae'], 'tendencia' => $item['tendencia']],
                        $snapshot['por_cnae'],
                    ),
                    'versoes' => $rulesVersions,
                ],
                subject: $request,
                result: 'sucesso',
            );
        });

        return [
            'resultado' => $resultado,
            'resultado_label' => ResultadoViabilidade::from($resultado)->label(),
            'por_cnae' => $porCnae,
            'rules_versions' => $rulesVersions,
            'simulated_at' => $request->simulated_at?->toIso8601String(),
        ];
    }

    /**
     * CNAEs da solicitação com o principal primeiro (is_primary), depois por
     * código — ordem estável do snapshot.
     *
     * @return Collection<int, Cnae>
     */
    private function orderedCnaes(ViabilityRequest $request)
    {
        return $request->cnaes()
            ->orderByPivot('is_primary', 'desc')
            ->orderBy('code')
            ->get();
    }

    /**
     * Tendência consolidada da solicitação: o pior caso (mais restritivo) entre
     * os CNAEs. Sem CNAEs (nada a avaliar), degrada para "pendente" — honesto,
     * nunca um permitido inventado.
     *
     * @param  list<array<string, mixed>>  $porCnae
     */
    private function consolidar(array $porCnae): string
    {
        $pior = ResultadoViabilidade::Pendente->value;
        $maiorSeveridade = -1;

        foreach ($porCnae as $item) {
            $tendencia = (string) $item['tendencia'];
            $severidade = self::SEVERIDADE[$tendencia] ?? self::SEVERIDADE[ResultadoViabilidade::Pendente->value];

            if ($severidade > $maiorSeveridade) {
                $maiorSeveridade = $severidade;
                $pior = $tendencia;
            }
        }

        return $pior;
    }

    /**
     * Centroide (lat, lng) pela média dos vértices únicos do anel exterior —
     * cálculo portável em PHP (igual em SQLite e Postgres), reusando o ponto que
     * a solicitação já tem (08-06) sem geocodificar de novo. Retorna null sem
     * polígono (a simulação degrada para a via CNAE).
     *
     * @param  array<string, mixed>|null  $geojson
     * @return array{lat: float, lng: float}|null
     */
    private function centroid(?array $geojson): ?array
    {
        /** @var array<int, array<int, float|int>>|null $ring */
        $ring = $geojson['coordinates'][0] ?? null;

        if (! is_array($ring) || $ring === []) {
            return null;
        }

        // Remove o vértice de fechamento (igual ao primeiro) para não enviesar.
        if (count($ring) > 1 && $ring[0] === $ring[count($ring) - 1]) {
            array_pop($ring);
        }

        $lngs = array_map(static fn (array $point): float => (float) $point[0], $ring);
        $lats = array_map(static fn (array $point): float => (float) $point[1], $ring);

        return [
            'lat' => array_sum($lats) / count($lats),
            'lng' => array_sum($lngs) / count($lngs),
        ];
    }
}

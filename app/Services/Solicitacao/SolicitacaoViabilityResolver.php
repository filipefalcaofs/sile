<?php

namespace App\Services\Solicitacao;

use App\Enums\Fluxo;
use App\Enums\ResultadoViabilidade;
use App\Models\Cnae;
use App\Models\ViabilityRequest;
use App\Services\Viabilidade\ConsultaViabilidadeService;
use Illuminate\Database\Eloquent\Collection;

/**
 * Núcleo reutilizável da resolução de viabilidade por solicitação (09-04): itera
 * os CNAEs (principal + complementares) e chama o ConsultaViabilidadeService
 * (Fase 7) PELO PONTO da própria solicitação (centroide do polígono do imóvel —
 * sem geocodificar de novo), PROPAGANDO o veredito do motor LOUOS e o
 * encaminhamento do motor de risco por CNAE (RN-001 — nunca lógica de decisão
 * paralela). Sem polígono, degrada honesto para a via CNAE (risco + Quadro 7,
 * sem território → veredito pendente).
 *
 * Consolida a tendência (pior caso entre os CNAEs) e coleta as versões
 * representativas das regras, devolvendo um ResolvedViability — SEM persistir e
 * SEM auditar. É insumo puro de DOIS consumidores: a simulação orientativa
 * (SimulacaoSolicitacaoService, que persiste o snapshot e audita — Fase 8) e a
 * decisão autoritativa (09-05), que o reexecuta fresco e nunca confia no
 * snapshot pré-protocolo. Sem zona oficial, o veredito por CNAE é "pendente"
 * (propagado) — nunca permitido/não permitido inventado.
 */
class SolicitacaoViabilityResolver
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
    ) {}

    /**
     * Resolve a viabilidade da solicitação por CNAE e consolida o pior caso, sem
     * persistir. Cada item de por_cnae carrega o ConsultaViabilidadeResult bruto
     * (para o consumidor ler o veredito e o encaminhamento numa passada) e o seu
     * toArray (para o snapshot orientativo).
     */
    public function resolve(ViabilityRequest $request): ResolvedViability
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
                'fluxo' => $result->risco->encaminhamento['fluxo'] ?? Fluxo::Analise->value,
                'consulta' => $result,
                'consulta_array' => $result->toArray(),
            ];

            // As versões das regras são idênticas entre os CNAEs (mesmo ponto +
            // mesma vigência); a primeira é a representativa (RN-002).
            if ($rulesVersions === []) {
                $rulesVersions = $result->versoes();
            }
        }

        return new ResolvedViability(
            por_cnae: $porCnae,
            consolidado: $this->consolidar($porCnae),
            rules_versions: $rulesVersions,
            ponto: $ponto,
            area_m2: $area,
        );
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
     * polígono (a resolução degrada para a via CNAE).
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

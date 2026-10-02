<?php

namespace App\Services\Solicitacao;

use App\Enums\Fluxo;
use App\Enums\ResultadoViabilidade;
use App\Enums\ZonaFonte;
use App\Models\Cnae;
use App\Models\ViabilityRequest;
use App\Services\Geo\TerritoryResult;
use App\Services\Geo\TerritoryService;
use App\Services\Regin\ReginProtocoloSimulacaoService;
use App\Services\Risco\TipoImovel;
use App\Services\Risco\TipoImovelCatalog;
use App\Services\Viabilidade\ConsultaViabilidadeService;
use Illuminate\Database\Eloquent\Collection;

/**
 * Núcleo reutilizável da resolução de viabilidade por solicitação (09-04): itera
 * os CNAEs (principal + complementares) e chama o ConsultaViabilidadeService
 * (Fase 7) PELO PONTO da própria solicitação (`pontoDoImovel()`: centroide do
 * polígono ou ponto gravado do Cadastro Imobiliário), PROPAGANDO o veredito
 * do motor LOUOS e o encaminhamento do motor de risco por CNAE (RN-001 —
 * nunca lógica de decisão paralela). Sem ponto, degrada honesto para a via
 * CNAE (risco + enquadramento da planilha, sem território → veredito pendente).
 *
 * Consolida a tendência (pior caso entre os CNAEs) e coleta as versões
 * representativas das regras, devolvendo um ResolvedViability — SEM persistir e
 * SEM auditar. É insumo puro de DOIS consumidores: a simulação orientativa
 * (SimulacaoSolicitacaoService, que persiste o snapshot e audita — Fase 8) e a
 * decisão autoritativa (09-05), que o reexecuta fresco e nunca confia no
 * snapshot pré-protocolo. Com a zona decidida pela maior porção do polígono
 * do endereçamento (zona_fonte), o enquadramento usa essa zona no lugar da do
 * ponto. Sem zona oficial, o veredito por CNAE é "pendente"
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
        private TerritoryService $territory,
    ) {}

    /**
     * Resolve a viabilidade da solicitação por CNAE e consolida o pior caso, sem
     * persistir. Cada item de por_cnae carrega o ConsultaViabilidadeResult bruto
     * (para o consumidor ler o veredito e o encaminhamento numa passada) e o seu
     * toArray (para o snapshot orientativo).
     */
    public function resolve(ViabilityRequest $request): ResolvedViability
    {
        $ponto = $request->pontoDoImovel();
        $area = $request->used_area_m2 !== null ? (float) $request->used_area_m2 : null;
        $tipoImovel = $this->tipoImovel($request);

        $porCnae = [];
        $rulesVersions = [];
        $territorioPorArea = $ponto !== null ? $this->territorioComZonaDoLote($request, $ponto) : null;

        foreach ($this->orderedCnaes($request) as $cnae) {
            $respostas = $request->respostasDaPlanilha($cnae->code);
            $territorio = ReginProtocoloSimulacaoService::territorioDoSnapshot(
                is_array($request->simulation_snapshot) ? $request->simulation_snapshot : [],
            ) ?? $territorioPorArea;

            $result = $territorio !== null
                ? $this->consulta->consultarComTerritorio($territorio, $cnae->code, $area, $tipoImovel, $respostas)
                : ($ponto === null
                    ? $this->consulta->consultarPorCnae($cnae->code, $area, $tipoImovel, $respostas)
                    : $this->consulta->consultarPorPontoConhecido($ponto['lat'], $ponto['lng'], $cnae->code, $area, $tipoImovel, $respostas));

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
     * Com a zona decidida pela maior porção do endereçamento (art. 18, § 2º),
     * o enquadramento recebe essa zona; bairro, via e restrições continuam do
     * ponto do imóvel.
     *
     * @param  array{lat: float, lng: float}  $ponto
     */
    private function territorioComZonaDoLote(ViabilityRequest $request, array $ponto): ?TerritoryResult
    {
        if ($request->zona_fonte !== ZonaFonte::EnderecamentoMaiorPorcao || blank($request->zona_codigo)) {
            return null;
        }

        $base = $this->territory->identify($ponto['lat'], $ponto['lng']);

        return new TerritoryResult(
            bairro: $base->bairro,
            via: $base->via,
            zona: [
                'status' => 'identificado',
                'nome' => $request->zona_codigo,
                'propriedades' => ['ZONA' => $request->zona_codigo, 'fonte' => ZonaFonte::EnderecamentoMaiorPorcao->value],
                'motivo' => null,
                'versao_camada' => 'geoserver-wfs-maior-porcao',
            ],
            lote: $base->lote,
            restricoes: $base->restricoes,
        );
    }

    /**
     * CNAEs da solicitação com o principal primeiro (is_primary), depois por
     * código — ordem estável do snapshot.
     *
     * @return Collection<int, Cnae>
     */
    private function tipoImovel(ViabilityRequest $request): ?TipoImovel
    {
        if ($request->tipo_imovel === null && $request->tipo_imovel_normalized === null) {
            return null;
        }

        return TipoImovel::fromRegin(
            $request->tipo_imovel ?? $request->tipo_imovel_normalized,
            TipoImovelCatalog::vigente(),
        );
    }

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
}

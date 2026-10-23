<?php

namespace App\Services\Solicitacao;

use App\Enums\Fluxo;
use App\Enums\ResultadoViabilidade;
use App\Enums\ZonaFonte;
use App\Models\Cnae;
use App\Models\ViabilityRequest;
use App\Services\Geo\TerritoryResult;
use App\Services\Geo\TerritoryService;
use App\Services\Realty\LocalizacaoInscricaoService;
use App\Services\Risco\TipoImovel;
use App\Services\Risco\TipoImovelCatalog;
use App\Services\Tratamento\CategoriaEmpresa;
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
        private LocalizacaoInscricaoService $localizacaoInscricao,
    ) {}

    /**
     * Resolve como se a zona e a via informadas valessem para o processo, sem gravar:
     * a prévia da alteração manual mostra o que o motor decidiria.
     */
    public function resolveComTerritorioManual(ViabilityRequest $request, ?string $zona, ?string $via): ResolvedViability
    {
        $original = $request->only(['zona_manual', 'via_manual']);
        $request->setAttribute('zona_manual', $zona);
        $request->setAttribute('via_manual', $via);

        try {
            return $this->resolve($request);
        } finally {
            $request->setRawAttributes([...$request->getAttributes(), ...$original]);
        }
    }

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
        $categoriaEmpresa = CategoriaEmpresa::deNaturezaJuridica(
            $request->company?->legal_nature_code,
            $request->company?->legal_nature,
        );

        $porCnae = [];
        $rulesVersions = [];
        $territorioDoPonto = $ponto !== null ? $this->territorioDoPonto($request, $ponto) : null;
        $usoNaoResidencial = $territorioDoPonto !== null
            ? $this->localizacaoInscricao->localizar($request->property_registration)->usoNaoResidencial()
            : null;

        foreach ($this->orderedCnaes($request) as $cnae) {
            $respostas = $request->respostasDaPlanilha($cnae->code);

            $result = $territorioDoPonto !== null
                ? $this->consulta->consultarComTerritorio($territorioDoPonto, $cnae->code, $area, $tipoImovel, $respostas, $categoriaEmpresa, $usoNaoResidencial)
                : $this->consulta->consultarPorCnae($cnae->code, $area, $tipoImovel, $respostas, $categoriaEmpresa);

            $veredito = $result->vereditoLocacional();

            $porCnae[] = [
                'cnae' => $cnae->code,
                'cnae_formatado' => $cnae->formatted_code,
                'is_primary' => (bool) $cnae->pivot->is_primary,
                'tendencia' => $veredito['resultado'],
                'tendencia_label' => $veredito['label'],
                'veredito_motivo' => $veredito['motivo'] ?? null,
                'enquadramento_motivo' => $result->enquadramento->ramoResolvido()
                    ? null
                    : ($result->enquadramento->enquadramento['motivo'] ?? null),
                'pergunta_sem_resposta' => $result->enquadramento->ramoResolvido()
                    ? null
                    : ($result->enquadramento->enquadramento['pergunta_sem_resposta'] ?? null),
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
     * Território do ponto, com a zona da maior porção do lote quando ela já
     * foi gravada e com o CODLOG da inscrição no lugar da via mais próxima.
     *
     * @param  array{lat: float, lng: float}  $ponto
     */
    private function territorioDoPonto(ViabilityRequest $request, array $ponto): TerritoryResult
    {
        $base = $this->territory->identify($ponto['lat'], $ponto['lng']);

        if (in_array($request->zona_fonte, [ZonaFonte::EnderecamentoMaiorPorcao, ZonaFonte::CentralidadeLinear], true) && filled($request->zona_codigo)) {
            $base = new TerritoryResult(
                bairro: $base->bairro,
                via: $base->via,
                zona: [
                    'status' => 'identificado',
                    'nome' => $request->zona_codigo,
                    'propriedades' => ['ZONA' => $request->zona_codigo, 'fonte' => $request->zona_fonte->value],
                    'motivo' => null,
                    'versao_camada' => 'geoserver-wfs-maior-porcao',
                ],
                lote: $base->lote,
                restricoes: $base->restricoes,
            );
        }

        return TerritorioManual::aplicar(
            $this->territory->comCodLogOficial($base, $request->cod_log),
            $request->zona_manual,
            $request->via_manual,
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
        $incluidos = $request->codigosIncluidosNaComparacao();

        return $request->cnaes()
            ->when($incluidos !== null, fn ($query) => $query->whereIn('cnaes.code', $incluidos))
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

<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\RelatorioFiltersRequest;
use App\Models\ViabilityRequest;
use App\Services\Relatorios\Export\ReportExporter;
use App\Services\Relatorios\Export\ReportSource;
use App\Services\Relatorios\Export\Sources\EscritorioVirtualReportSource;
use App\Services\Relatorios\Export\Sources\ExpressoQuedaReportSource;
use App\Services\Relatorios\Export\Sources\ProdutividadeReportSource;
use App\Services\Relatorios\Export\Sources\RelatorioSedeReportSource;
use App\Services\Relatorios\Export\Sources\SolicitacoesReportSource;
use App\Services\Relatorios\Export\Sources\TempoAnaliseReportSource;
use App\Services\Relatorios\ExpressoQuedaService;
use App\Services\Relatorios\GeoBairroIndicadorService;
use App\Services\Relatorios\IndicadoresViabilidadeService;
use App\Services\Relatorios\ProdutividadeAnalistaService;
use App\Services\Relatorios\RelatorioSedeEscritorioVirtualService;
use App\Services\Relatorios\ReportFilters;
use App\Services\Relatorios\SaturacaoService;
use App\Services\Relatorios\TempoAnaliseService;
use App\Support\Audit\AuditService;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Camada HTTP dos relatórios (HU-122..131/145): controller FINO que liga os
 * serviços route-free (15-03/05/06/07) e o contrato de exportação (15-02/08) à
 * retaguarda. Cada endpoint serve a tela Inertia com dado REAL E, quando vem
 * ?formato=, delega ao {@see ReportExporter} a exportação transversal (CSV/XLSX/
 * PDF — RN-004/009), sempre auditando (RN-002/008). A produtividade nominal só
 * sai sob a permissão relatorios.produtividade.nominal (RN-007); sem ela o
 * default conservador anonimiza e restringe ao próprio analista. Gated por
 * consultar-relatorios na rota; o 403 é auditado no ponto único
 * (bootstrap/app.php). Os componentes React das telas chegam em 15-13/15-14.
 */
class RelatorioController extends Controller
{
    /**
     * Itens por página oferecidos na tela sede × abrigados (mesma convenção das
     * demais listagens server-driven da gestão).
     *
     * @var list<int>
     */
    private const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    public function __construct(
        private IndicadoresViabilidadeService $indicadores,
        private TempoAnaliseService $tempos,
        private ProdutividadeAnalistaService $produtividade,
        private ExpressoQuedaService $quedas,
        private GeoBairroIndicadorService $geoBairro,
        private SaturacaoService $saturacao,
        private RelatorioSedeEscritorioVirtualService $relatorioSede,
        private AuditService $audit,
    ) {}

    /**
     * Indicadores de viabilidade (HU-123..128). Com ?formato=, exporta o conjunto
     * de solicitações filtrado (SolicitacoesReportSource); senão audita a consulta
     * e renderiza a tela com as séries/taxas reais.
     */
    public function indicadores(RelatorioFiltersRequest $request): InertiaResponse|Response
    {
        $filtros = $request->toReportFilters();

        if ($formato = $this->formato($request)) {
            return $this->exportar(app(SolicitacoesReportSource::class), $filtros, $formato, $request);
        }

        $this->auditarConsulta('consulta-indicadores', 'Consulta dos indicadores de viabilidade', $filtros);

        return Inertia::render('gestao/relatorios/indicadores', [
            'porPeriodo' => $this->indicadores->porPeriodo($filtros),
            'porZona' => $this->indicadores->porZona($filtros),
            'porCnae' => $this->indicadores->porCnae($filtros),
            'porRisco' => $this->indicadores->porRisco($filtros),
            'taxaDeferimento' => $this->indicadores->taxaDeferimento($filtros),
            'taxaIndeferimento' => $this->indicadores->taxaIndeferimento($filtros),
            'filtros' => $filtros->aplicados(),
        ]);
    }

    /**
     * Tempo por etapa (HU-129) + relatórios SAPS de tempo. O ?relatorio= escolhe a
     * fonte de export quando há mais de uma: `escritorio-virtual` exporta as sedes
     * de escritório virtual; o default (`tempo`) exporta o detalhamento por etapa.
     */
    public function tempo(RelatorioFiltersRequest $request): InertiaResponse|Response
    {
        $filtros = $request->toReportFilters();

        if ($formato = $this->formato($request)) {
            return $this->exportar($this->fonteTempo($request), $filtros, $formato, $request);
        }

        $this->auditarConsulta('consulta-tempo', 'Consulta do relatório de tempo de análise', $filtros);

        return Inertia::render('gestao/relatorios/tempo', [
            'tempoPorEtapa' => $this->tempos->tempoPorEtapa($filtros),
            'tempoEmissaoTvl' => $this->tempos->tempoEmissaoTvl($filtros),
            'filtros' => $filtros->aplicados(),
        ]);
    }

    /**
     * Produtividade por analista (HU-130). O modo nominal (nome do analista) só é
     * liberado sob relatorios.produtividade.nominal; sem ela, o escopo cai no
     * próprio usuário e os dados saem anonimizados (RN-007). O mesmo gate define
     * o estado do ReportSource quando se exporta.
     */
    public function produtividade(RelatorioFiltersRequest $request): InertiaResponse|Response
    {
        $filtros = $request->toReportFilters();

        $nominal = (bool) $request->user()->can('relatorios.produtividade.nominal');
        $escopo = $nominal ? null : $request->user()->id;

        if ($formato = $this->formato($request)) {
            return $this->exportar(new ProdutividadeReportSource($nominal, $escopo), $filtros, $formato, $request);
        }

        $this->auditarConsulta('consulta-produtividade', 'Consulta da produtividade por analista', $filtros, $nominal);

        return Inertia::render('gestao/relatorios/produtividade', [
            'produtividade' => $this->produtividade->porAnalista($filtros, $nominal, $escopo),
            'nominal' => $nominal,
            'filtros' => $filtros->aplicados(),
        ]);
    }

    /**
     * Quedas do fluxo expresso (HU-145): taxa de resposta + série temporal +
     * ranking de motivos. Com ?formato=, exporta as quedas filtradas.
     */
    public function quedas(RelatorioFiltersRequest $request): InertiaResponse|Response
    {
        $filtros = $request->toReportFilters();

        if ($formato = $this->formato($request)) {
            return $this->exportar(app(ExpressoQuedaReportSource::class), $filtros, $formato, $request);
        }

        $this->auditarConsulta('consulta-quedas', 'Consulta do relatório de quedas do fluxo expresso', $filtros);

        return Inertia::render('gestao/relatorios/quedas', [
            'taxa' => $this->quedas->taxaRespostaExpressa($filtros),
            'serie' => $this->quedas->serieTemporal($filtros),
            'ranking' => $this->quedas->rankingMotivos($filtros),
            'filtros' => $filtros->aplicados(),
        ]);
    }

    /**
     * Painel geoeconômico por bairro (Geo BI interno — Módulo 1): distribuição
     * das solicitações por bairro com decisões e taxa de deferimento, para
     * planejamento da SEDUR. Degradação honesta enquanto a zona urbanística
     * oficial (GIS) está pendente — agrupa por bairro, nunca inventa zona. Com
     * ?formato=, exporta o conjunto de solicitações filtrado pelo contrato único
     * (SolicitacoesReportSource — RN-005); senão audita a consulta e renderiza a
     * tela com a agregação real.
     */
    public function geoBairro(RelatorioFiltersRequest $request): InertiaResponse|Response
    {
        $filtros = $request->toReportFilters();

        if ($formato = $this->formato($request)) {
            return $this->exportar(app(SolicitacoesReportSource::class), $filtros, $formato, $request);
        }

        $this->auditarConsulta('consulta-geo-bairro', 'Consulta do painel geoeconômico por bairro', $filtros);

        return Inertia::render('gestao/relatorios/geo-bairro', [
            'resumo' => $this->geoBairro->resumo($filtros),
            'porBairro' => $this->geoBairro->porBairro($filtros),
            'filtros' => $filtros->aplicados(),
        ]);
    }

    /**
     * Observatório de Saturação Locacional (Módulo 2): concentração de
     * estabelecimentos deferidos por bairro×CNAE vs capacidade recomendada
     * (parâmetro HU-014), classificando ok/saturando/saturado. Insumo de política
     * urbana da SEDUR (adensamento/externalidades). Degradação honesta: CNAE sem
     * capacidade vira "sem_capacidade"; recorte por bairro até a zona oficial
     * (GIS). Com ?formato=, exporta as solicitações filtradas (contrato único).
     */
    public function saturacao(RelatorioFiltersRequest $request): InertiaResponse|Response
    {
        $filtros = $request->toReportFilters();

        if ($formato = $this->formato($request)) {
            return $this->exportar(app(SolicitacoesReportSource::class), $filtros, $formato, $request);
        }

        $this->auditarConsulta('consulta-saturacao', 'Consulta do observatório de saturação locacional', $filtros);

        return Inertia::render('gestao/relatorios/saturacao', [
            'resumo' => $this->saturacao->resumo($filtros),
            'porBairroCnae' => $this->saturacao->porBairroCnae($filtros),
            'limiares' => $this->saturacao->limiares(),
            'filtros' => $filtros->aplicados(),
        ]);
    }

    /**
     * Relatório sede × abrigados de escritório virtual (Plano R1): agrupa, por
     * inscrição imobiliária travada, a SEDE (alvo do lock ativo) e os ABRIGADOS
     * (decisões is_virtual_office_tenant). Com ?formato=, exporta o MESMO recorte
     * pelo contrato único (RelatorioSedeReportSource — RN-005/009); senão audita a
     * consulta e renderiza a tela com o paginator projetado por `linha()`.
     */
    public function escritorioVirtual(RelatorioFiltersRequest $request): InertiaResponse|Response
    {
        $filtros = $request->toReportFilters();

        if ($formato = $this->formato($request)) {
            return $this->exportar(app(RelatorioSedeReportSource::class), $filtros, $formato, $request);
        }

        $this->auditarConsulta('consulta-escritorio-virtual', 'Consulta do relatório sede × abrigados de escritório virtual', $filtros);

        $recorte = $filtros->only(['sede', 'inscricao']);

        return Inertia::render('gestao/relatorios/escritorio-virtual', [
            'relatorio' => $this->relatorioSede
                ->consultar($recorte, $this->perPage($request))
                ->withQueryString()
                ->through(fn (ViabilityRequest $r): array => $this->relatorioSede->linha($r)),
            'filtros' => $recorte,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    /**
     * Itens por página validados contra o whitelist ({@see PER_PAGE_OPTIONS}); um
     * valor fora dele degrada para o default 15 (sem inventar recorte).
     */
    private function perPage(RelatorioFiltersRequest $request): int
    {
        $perPage = (int) $request->input('per_page');

        return in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 15;
    }

    /**
     * Fonte do export do relatório de tempo, selecionada pelo ?relatorio= (um
     * endpoint serve mais de um ReportSource): escritorio-virtual → sedes;
     * default → detalhamento por etapa.
     */
    private function fonteTempo(RelatorioFiltersRequest $request): ReportSource
    {
        return $request->string('relatorio')->toString() === 'escritorio-virtual'
            ? app(EscritorioVirtualReportSource::class)
            : app(TempoAnaliseReportSource::class);
    }

    /**
     * Delega a exportação ao contrato único (RN-009): o ReportExporter audita
     * (RN-008), valida o formato e escolhe o caminho síncrono/assíncrono.
     */
    private function exportar(ReportSource $source, ReportFilters $filtros, string $formato, RelatorioFiltersRequest $request): Response
    {
        return app(ReportExporter::class)->export($source, $filtros, $formato, $request->user());
    }

    /**
     * Formato de exportação solicitado (?formato=csv|xlsx|pdf), normalizado; sem
     * formato → null (a tela é renderizada).
     */
    private function formato(RelatorioFiltersRequest $request): ?string
    {
        $formato = $request->string('formato')->lower()->toString();

        return $formato === '' ? null : $formato;
    }

    /**
     * Auditoria da CONSULTA do relatório (RN-002). personalData marca a consulta
     * que expõe identidade (produtividade nominal — relevante para o painel LGPD).
     */
    private function auditarConsulta(string $event, string $descricao, ReportFilters $filtros, bool $personalData = false): void
    {
        $this->audit->log(
            logName: 'relatorios',
            event: $event,
            description: $descricao,
            properties: ['filtros' => $filtros->aplicados()],
            personalData: $personalData,
        );
    }
}

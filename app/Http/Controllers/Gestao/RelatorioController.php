<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\RelatorioFiltersRequest;
use App\Models\AnalysisPendency;
use App\Models\Communication;
use App\Models\Sector;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityServiceType;
use App\Services\Relatorios\ComunicacoesFalhaService;
use App\Services\Relatorios\ContingenciaRelatorioService;
use App\Services\Relatorios\Export\ReportExporter;
use App\Services\Relatorios\Export\ReportSource;
use App\Services\Relatorios\Export\Sources\ComunicacoesFalhaReportSource;
use App\Services\Relatorios\Export\Sources\ContingenciaReportSource;
use App\Services\Relatorios\Export\Sources\EscritorioVirtualReportSource;
use App\Services\Relatorios\Export\Sources\ExpressoQuedaReportSource;
use App\Services\Relatorios\Export\Sources\PendenciasReportSource;
use App\Services\Relatorios\Export\Sources\ProdutividadeReportSource;
use App\Services\Relatorios\Export\Sources\RelatorioSedeReportSource;
use App\Services\Relatorios\Export\Sources\RelatorioTempoEmissaoTvlReportSource;
use App\Services\Relatorios\Export\Sources\SlaVencimentosReportSource;
use App\Services\Relatorios\Export\Sources\SolicitacoesReportSource;
use App\Services\Relatorios\Export\Sources\TempoAnaliseReportSource;
use App\Services\Relatorios\ExpressoQuedaService;
use App\Services\Relatorios\GeoBairroIndicadorService;
use App\Services\Relatorios\IndicadoresViabilidadeService;
use App\Services\Relatorios\PendenciasRelatorioService;
use App\Services\Relatorios\ProdutividadeAnalistaService;
use App\Services\Relatorios\RelatorioSedeEscritorioVirtualService;
use App\Services\Relatorios\RelatorioTempoEmissaoTvlService;
use App\Services\Relatorios\ReportFilters;
use App\Services\Relatorios\SaturacaoService;
use App\Services\Relatorios\SlaVencimentosService;
use App\Services\Relatorios\TempoAnaliseService;
use App\Services\Relatorios\TrilhaProcessoService;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Barryvdh\DomPDF\Facade\Pdf;
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
        private RelatorioTempoEmissaoTvlService $tempoEmissaoTvl,
        private SlaVencimentosService $slaVencimentos,
        private PendenciasRelatorioService $pendenciasRelatorio,
        private TrilhaProcessoService $trilhaProcesso,
        private ContingenciaRelatorioService $contingenciaRelatorio,
        private ComunicacoesFalhaService $comunicacoesFalha,
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
     * Tempo por etapa (HU-129). A exportação é SEMPRE o detalhamento por etapa:
     * a lista de sedes de escritório virtual migrou para o endpoint do próprio
     * domínio ({@see escritorioVirtual()} com ?recorte=sedes) — um endpoint, uma
     * família de relatório.
     */
    public function tempo(RelatorioFiltersRequest $request): InertiaResponse|Response
    {
        $filtros = $request->toReportFilters();

        if ($formato = $this->formato($request)) {
            return $this->exportar(app(TempoAnaliseReportSource::class), $filtros, $formato, $request);
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
     * Painel geoeconômico (Geo BI interno — Módulo 1): distribuição das
     * solicitações por bairro canônico (oficial materializado, com o digitado
     * de fallback) e por ZONA URBANÍSTICA OFICIAL (zona_codigo materializado na
     * identificação do imóvel — GeoServer SEDUR validado em produção, Onda
     * GIS), com decisões e taxa de deferimento. Com ?formato=, exporta o
     * conjunto de solicitações filtrado pelo contrato único
     * (SolicitacoesReportSource — RN-005); senão audita a consulta e renderiza
     * a tela com a agregação real.
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
            'porZona' => $this->geoBairro->porZona($filtros),
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
     * pelo contrato único (RelatorioSedeReportSource — RN-005/009); com
     * ?recorte=sedes, exporta a LISTA PLANA de sedes EV (EscritorioVirtualReportSource
     * — SAPS RN-006: empresa/CNPJ/bairro/resultado, recorte por período). Senão
     * audita a consulta e renderiza a tela com o paginator projetado por `linha()`.
     */
    public function escritorioVirtual(RelatorioFiltersRequest $request): InertiaResponse|Response
    {
        $filtros = $request->toReportFilters();

        if ($formato = $this->formato($request)) {
            $source = $request->string('recorte')->toString() === 'sedes'
                ? app(EscritorioVirtualReportSource::class)
                : app(RelatorioSedeReportSource::class);

            return $this->exportar($source, $filtros, $formato, $request);
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
     * Relatório SAPS "Tempo de Emissão de TVL" (Tela R2), em página dedicada e
     * separada do KPI de tempo médio (`tempo`): tabela dos processos DECIDIDOS no
     * recorte com o tempo Emissão−Abertura em minutos úteis. Com ?formato=, exporta
     * o MESMO recorte pelo contrato único (RelatorioTempoEmissaoTvlReportSource —
     * RN-005/CA-R2-03); senão audita a consulta e renderiza a tela com o paginator
     * projetado por `linha()`. O dropdown de serviços vem do catálogo administrável
     * de tipos de serviço (CA-R2-04 — "Atividades em residência" quando parametrizado).
     */
    public function tempoEmissaoTvl(RelatorioFiltersRequest $request): InertiaResponse|Response
    {
        $filtros = $request->toReportFilters();

        if ($formato = $this->formato($request)) {
            return $this->exportar(app(RelatorioTempoEmissaoTvlReportSource::class), $filtros, $formato, $request);
        }

        $this->auditarConsulta('consulta-tempo-emissao-tvl', 'Consulta do relatório de tempo de emissão de TVL', $filtros);

        return Inertia::render('gestao/relatorios/tempo-emissao-tvl', [
            'relatorio' => $this->tempoEmissaoTvl
                ->consultar($filtros, $this->perPage($request))
                ->withQueryString()
                ->through(fn (ViabilityRequest $r): array => $this->tempoEmissaoTvl->linha($r)),
            'servicos' => $this->servicoOptions(),
            'filtros' => $filtros->aplicados(),
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    /**
     * SLA e vencimentos da análise (relatório operacional): processos EM
     * ANDAMENTO com prazo materializado, o mais urgente primeiro, com o resumo
     * em SQL (em andamento / vencidos / vencendo na janela parametrizável), aging,
     * atrasados por etapa e cumprimento no período. Estoque/lista ignoram datas
     * (momento atual); o período recorta só o cumprimento. Com ?formato=,
     * exporta o recorte da lista (setor/analista — RN-005).
     */
    public function slaVencimentos(RelatorioFiltersRequest $request): InertiaResponse|Response
    {
        $recebidos = $request->toReportFilters();
        $estoque = ReportFilters::fromArray($recebidos->only(['setor', 'analista']));
        $cumprimentoFiltros = $this->filtrosCumprimento($recebidos);

        if ($formato = $this->formato($request)) {
            return $this->exportar(app(SlaVencimentosReportSource::class), $estoque, $formato, $request);
        }

        $this->auditarConsulta(
            'consulta-sla-vencimentos',
            'Consulta do relatório de SLA e vencimentos',
            ReportFilters::fromArray(array_merge(
                $recebidos->aplicados(),
                $cumprimentoFiltros->only(['data_de', 'data_ate']),
            )),
        );

        return Inertia::render('gestao/relatorios/sla', [
            'resumo' => array_merge($this->slaVencimentos->resumo($estoque), [
                'aging' => $this->slaVencimentos->aging($estoque),
                'atrasados_por_etapa' => $this->slaVencimentos->atrasadosPorEtapa($estoque),
                'cumprimento' => $this->slaVencimentos->cumprimento($cumprimentoFiltros),
            ]),
            'relatorio' => $this->slaVencimentos
                ->builder($estoque)
                ->paginate($this->perPage($request))
                ->withQueryString()
                ->through(fn (ViabilityRequest $r): array => $this->slaVencimentos->linha($r)),
            'setores' => Sector::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Sector $s): array => ['value' => $s->id, 'label' => $s->name])
                ->all(),
            'analistas' => User::query()->permission('analisar-processos')->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $u): array => ['value' => $u->id, 'label' => $u->name])
                ->all(),
            'filtros' => array_merge(
                $estoque->only(['setor', 'analista']),
                [
                    'data_de' => $cumprimentoFiltros->from()?->toDateString(),
                    'data_ate' => $cumprimentoFiltros->to()?->toDateString(),
                ],
            ),
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    /**
     * Pendências/exigências (relatório operacional): abertas, vencidas,
     * respondidas e expiradas com o tempo médio de resposta do requerente, e a
     * lista das pendências do recorte (abertas pelo prazo primeiro). Com
     * ?formato=, exporta o MESMO recorte pelo contrato único
     * (PendenciasReportSource — RN-005); senão audita a consulta e renderiza a
     * tela paginada.
     */
    public function pendencias(RelatorioFiltersRequest $request): InertiaResponse|Response
    {
        $filtros = $request->toReportFilters();

        if ($formato = $this->formato($request)) {
            return $this->exportar(app(PendenciasReportSource::class), $filtros, $formato, $request);
        }

        $this->auditarConsulta('consulta-pendencias', 'Consulta do relatório de pendências e exigências', $filtros);

        return Inertia::render('gestao/relatorios/pendencias', [
            'resumo' => $this->pendenciasRelatorio->resumo($filtros),
            'relatorio' => $this->pendenciasRelatorio
                ->builder($filtros)
                ->paginate($this->perPage($request))
                ->withQueryString()
                ->through(fn (AnalysisPendency $p): array => $this->pendenciasRelatorio->linha($p)),
            'filtros' => $filtros->only(['data_de', 'data_ate', 'status_pendencia']),
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    /**
     * Atendimento em contingência (relatório gerencial — HU-148): quanto do
     * volume entra pelo canal de operador e por quê, com a participação sobre o
     * total protocolado no período e o ranking de motivos. Com ?formato=,
     * exporta o MESMO recorte pelo contrato único (ContingenciaReportSource —
     * RN-005); senão audita a consulta e renderiza a tela paginada.
     */
    public function contingencia(RelatorioFiltersRequest $request): InertiaResponse|Response
    {
        $filtros = $request->toReportFilters();

        if ($formato = $this->formato($request)) {
            return $this->exportar(app(ContingenciaReportSource::class), $filtros, $formato, $request);
        }

        $this->auditarConsulta('consulta-contingencia', 'Consulta do relatório de atendimento em contingência', $filtros);

        return Inertia::render('gestao/relatorios/contingencia', [
            'resumo' => $this->contingenciaRelatorio->resumo($filtros),
            'relatorio' => $this->contingenciaRelatorio
                ->builder($filtros)
                ->paginate($this->perPage($request))
                ->withQueryString()
                ->through(fn (ViabilityRequest $r): array => $this->contingenciaRelatorio->linha($r)),
            'filtros' => $filtros->only(['data_de', 'data_ate']),
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    /**
     * Falhas de comunicação (consulta operacional — HU-096): notificações que
     * falharam ou foram bloqueadas, por processo, com a quebra por canal. SEM
     * dados do destinatário (LGPD). Com ?formato=, exporta o MESMO recorte pelo
     * contrato único (ComunicacoesFalhaReportSource — RN-005); senão audita a
     * consulta e renderiza a tela paginada.
     */
    public function comunicacoesFalhas(RelatorioFiltersRequest $request): InertiaResponse|Response
    {
        $filtros = $request->toReportFilters();

        if ($formato = $this->formato($request)) {
            return $this->exportar(app(ComunicacoesFalhaReportSource::class), $filtros, $formato, $request);
        }

        $this->auditarConsulta('consulta-comunicacoes-falhas', 'Consulta das falhas de comunicação', $filtros);

        return Inertia::render('gestao/relatorios/comunicacoes-falhas', [
            'resumo' => $this->comunicacoesFalha->resumo($filtros),
            'relatorio' => $this->comunicacoesFalha
                ->builder($filtros)
                ->paginate($this->perPage($request))
                ->withQueryString()
                ->through(fn (Communication $c): array => $this->comunicacoesFalha->linha($c)),
            'filtros' => $filtros->only(['data_de', 'data_ate', 'canal']),
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    /**
     * Trilha de auditoria por processo (prestação de contas): busca por número
     * de protocolo e consolida as fontes reais de histórico (transições dos
     * dois eixos + activity_log + decisão) ordenadas por data. Sem protocolo,
     * renderiza a tela de busca; protocolo inexistente → trilha null (estado
     * honesto). A consulta é auditada (RN-002).
     */
    public function trilha(RelatorioFiltersRequest $request): InertiaResponse
    {
        $protocolo = $request->string('protocolo')->trim()->toString();

        $trilha = $protocolo !== '' ? $this->trilhaProcesso->trilha($protocolo) : null;

        if ($protocolo !== '') {
            $this->auditarConsulta('consulta-trilha-processo', 'Consulta da trilha de auditoria por processo', ReportFilters::fromArray(['protocolo' => $protocolo]));
        }

        return Inertia::render('gestao/relatorios/trilha', [
            'trilha' => $trilha,
            'protocolo' => $protocolo !== '' ? $protocolo : null,
        ]);
    }

    /**
     * Impressão em PDF da trilha por processo (documento de prestação de
     * contas): DomPDF sobre o Blade real, com identificação do emissor e do
     * período de emissão. Protocolo inexistente → 404 (nunca um PDF vazio
     * fingindo resultado). A impressão é auditada (RN-002).
     */
    public function trilhaImprimir(RelatorioFiltersRequest $request): Response
    {
        $protocolo = $request->string('protocolo')->trim()->toString();

        $trilha = $protocolo !== '' ? $this->trilhaProcesso->trilha($protocolo) : null;

        abort_if($trilha === null, 404, 'Processo não encontrado para o protocolo informado.');

        $this->audit->log(
            logName: 'relatorios',
            event: 'imprime-trilha-processo',
            description: "Impressão da trilha de auditoria do processo {$protocolo}",
            properties: ['protocolo' => $protocolo, 'viability_request_id' => $trilha['processo']['id']],
            result: 'sucesso',
        );

        $pdf = Pdf::loadView('documentos.trilha-processo', [
            'trilha' => $trilha,
            'emitido_em' => now(),
            'emitido_por' => $request->user()?->name,
        ])->setPaper('a4', 'portrait')->output();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="trilha-'.$protocolo.'.pdf"',
        ]);
    }

    /**
     * Opções do dropdown de serviço da Tela R2: catálogo ADMINISTRÁVEL de tipos de
     * serviço ativos (value=id, label=nome). "Atividades em residência" aparece
     * quando parametrizado nesse catálogo (CA-R2-04) — a tela não inventa opções.
     *
     * @return list<array{value: int, label: string}>
     */
    private function servicoOptions(): array
    {
        return ViabilityServiceType::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (ViabilityServiceType $t): array => ['value' => $t->id, 'label' => $t->name])
            ->all();
    }

    /**
     * Bag do cumprimento: setor/analista + período. Sem as duas datas, aplica
     * a janela parametrizável (relatorios.dashboard.janela_dias).
     */
    private function filtrosCumprimento(ReportFilters $recebidos): ReportFilters
    {
        $bag = $recebidos->only(['setor', 'analista', 'data_de', 'data_ate']);

        if ($recebidos->from() === null && $recebidos->to() === null) {
            $janela = (int) Settings::get(
                'relatorios.dashboard.janela_dias',
                config('sile.relatorios.dashboard.janela_dias', 30),
            );
            $bag['data_de'] = now()->subDays($janela)->toDateString();
            $bag['data_ate'] = now()->toDateString();
        }

        return ReportFilters::fromArray($bag);
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

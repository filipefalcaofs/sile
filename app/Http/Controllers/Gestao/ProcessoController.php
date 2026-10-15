<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\AnalysisStatus;
use App\Enums\ConclusionOutcome;
use App\Enums\ViabilityRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\AnalysisStatusRequest;
use App\Http\Resources\DecisionExplanationResource;
use App\Http\Resources\ProcessoResource;
use App\Models\Activity;
use App\Models\Sector;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityServiceType;
use App\Services\Analise\AnalysisStatusStateMachine;
use App\Services\Analise\InvalidAnalysisStatusTransitionException;
use App\Services\Analise\ProcessoQueryService;
use App\Services\Analise\TramitacaoProcessoService;
use App\Services\Auditoria\DecisionExplanationService;
use App\Services\Geo\DivergenciaLogradouroInscricao;
use App\Services\Regin\HttpReginParecerNotifier;
use App\Services\Relatorios\Export\ReportExporter;
use App\Services\Relatorios\Export\Sources\ProcessosReportSource;
use App\Services\Relatorios\ReportFilters;
use App\Support\Audit\AuditService;
use App\Support\ParecerDecisao;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Consulta de processos (HU-082) e fila do analista (HU-144) na retaguarda,
 * server-driven (espelha o ResultadoExpressoController/CaixaSetorController). O
 * index aplica os filtros completos do SAPS + analista + categoria via
 * ProcessoQueryService, pagina no servidor e, com ?formato= (csv/xlsx/pdf),
 * delega ao contrato único de exportação (HU-131/RN-009): o {@see ReportExporter}
 * exporta o conjunto filtrado (RN-005) via {@see ProcessosReportSource},
 * preservando as colunas/arquivo do CSV histórico e auditando (RN-008). O detalhe
 * (show) carrega dados/decisão/ficha/timeline como props (mini-mapa/abas
 * renderizados na UI 10-16). Tudo gated por consultar-solicitacoes (reuso —
 * decisão de 10-01) e auditado (RN-002); o 403 é auditado no ponto único
 * (bootstrap/app.php).
 */
class ProcessoController extends Controller
{
    /** Itens por página aceitos — reusa o padrão do console (ui.cnaes.per_page). */
    private const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    public function __construct(
        private ProcessoQueryService $processos,
        private AuditService $audit,
        private DecisionExplanationService $explanations,
        private DivergenciaLogradouroInscricao $divergenciaLogradouro,
    ) {}

    /**
     * Consulta filtrável e paginada (HU-082). Com ?formato= (csv/xlsx/pdf),
     * delega ao contrato único de exportação (HU-131/RN-009) o conjunto filtrado
     * via {@see ProcessosReportSource} — o ReportExporter audita (RN-008) e
     * escolhe o caminho síncrono/assíncrono. A consulta é auditada (CA-02).
     */
    public function index(Request $request): Response|HttpResponse
    {
        $filtros = $this->filtros($request);
        $ordem = $this->ordem($request);
        $consulta = $filtros + ['ordem' => $ordem];
        $periodoInvertido = $this->periodoInvertido($filtros);

        if (! $periodoInvertido && in_array($request->string('formato')->lower()->toString(), ['csv', 'xlsx', 'pdf'], true)) {
            return app(ReportExporter::class)->export(
                app(ProcessosReportSource::class),
                ReportFilters::fromArray($consulta),
                $request->string('formato')->lower()->toString(),
                $request->user(),
            );
        }

        $perPage = $this->perPage($request);

        if ($periodoInvertido) {
            $processos = new LengthAwarePaginator([], 0, $perPage, 1, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);
            $abas = [
                'todos' => 0,
                'em_analise' => 0,
                'distribuir' => 0,
                'pendencia' => 0,
                'concluido' => 0,
            ];
        } else {
            $processos = $this->processos->consulta($consulta)
                ->paginate($perPage)
                ->withQueryString()
                ->through(fn (ViabilityRequest $processo): array => (new ProcessoResource($processo))->resolve());
            $abas = $this->processos->contadoresConsulta($consulta);
        }

        $this->audit->log('analise', 'consulta-processos', 'Consulta de processos da análise técnica', [
            'filtros' => $this->filtrosPreenchidos($filtros),
            'periodo_invertido' => $periodoInvertido,
        ]);

        return Inertia::render('gestao/processos/index', [
            'processos' => $processos,
            'filtros' => $consulta + ['per_page' => $perPage],
            'erroPeriodo' => $periodoInvertido
                ? 'A data inicial não pode ser posterior à data final.'
                : null,
            'abas' => $abas,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'statusOptions' => $this->statusOptions(),
            'analysisStatusOptions' => AnalysisStatus::options(),
            'categoriaOptions' => $this->categoriaOptions(),
        ]);
    }

    /**
     * Caixa de entrada do analista (HU-144): só os processos atribuídos ao
     * usuário, ordenados por prazo (analysis_due_at) com semáforo on-the-fly e
     * contadores por status. A caixa do setor é outra tela. O parâmetro modo é
     * ignorado para um atalho antigo não reabrir a fila do setor aqui. O gestor
     * (distribuir-processos) ganha a visão agregada do setor (carga por analista
     * + processos em vermelho — CA-03). O acesso é auditado.
     */
    public function fila(Request $request): Response
    {
        $user = $request->user();
        $filtros = [];
        foreach (ProcessoQueryService::CHAVES_FILTRO_CAIXA as $chave) {
            $filtros[$chave] = $request->string($chave)->toString();
        }
        $perPage = $this->perPage($request);

        $processos = $this->processos->aplicarFiltros($this->processos->fila($user), $filtros)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (ViabilityRequest $processo): array => (new ProcessoResource($processo))->resolve());

        $this->audit->log('analise', 'consulta-fila', 'Consulta da fila de trabalho do analista', [
            'modo' => 'meus',
            'filtros' => $this->filtrosPreenchidos($filtros),
        ]);

        return Inertia::render('gestao/processos/fila', [
            'modo' => 'meus',
            'processos' => $processos,
            'contadores' => $this->processos->contadores($user),
            'visaoSetor' => $user->can('distribuir-processos') ? $this->processos->visaoSetor($user) : null,
            'filtros' => $filtros + ['per_page' => $perPage],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'servicoOptions' => $this->servicoOptions(),
            'analysisStatusOptions' => AnalysisStatus::options(),
            'categoriaOptions' => $this->categoriaOptions(),
        ]);
    }

    /**
     * Detalhe do processo (HU-082 RN-006/007): dados, decisão, ficha vigente e
     * timeline como props para a UI (mini-mapa/abas em 10-16). A consulta é
     * auditada. 404 honesto via route model binding (processo inexistente).
     */
    public function show(ViabilityRequest $viabilityRequest): Response
    {
        $viabilityRequest->load([
            'company',
            'sector:id,name',
            'assignedTo:id,name',
            'decision',
            'currentAnalysisRecord',
            'transitions',
            'encaminhamentoAnalise',
        ]);

        // personalData: leitura do detalhe do processo de um cidadão expõe dados
        // da empresa/requerente — acesso a dado pessoal de terceiro, medido pelo
        // painel LGPD (HU-102). Marcação ADITIVA, sem mudar a auditoria existente.
        $this->audit->log(
            logName: 'analise',
            event: 'consulta-processo',
            description: "Consulta do processo #{$viabilityRequest->id}",
            properties: [
                'viability_request_id' => $viabilityRequest->id,
                'protocol_number' => $viabilityRequest->protocol_number,
            ],
            subject: $viabilityRequest,
            personalData: true,
        );

        return Inertia::render('gestao/processos/show', [
            'processo' => (new ProcessoResource($viabilityRequest))->resolve(),
            'parecer' => ParecerDecisao::de($viabilityRequest->decision),
            // Explicabilidade passo a passo (HU-099): projeção PURA do
            // decision_trace gravado (RN-005), só quando há decisão — sem
            // desfecho não há explicação inventada. Prop ADITIVA sob o gate
            // consultar-solicitacoes já vigente.
            'explicacao' => $viabilityRequest->decision === null
                ? null
                : (new DecisionExplanationResource($this->explanations->explain($viabilityRequest->decision)))->resolve(),
            'timeline' => $this->timeline($viabilityRequest),
            // Texto que o parecer leva à JUCEB (gravado no processo ou o padrão da
            // decisão); null quando o processo não tem decisão ou não veio da JUCEB.
            'justificativaRegin' => $viabilityRequest->decision === null || blank($viabilityRequest->external_reference)
                ? null
                : [
                    'texto' => app(HttpReginParecerNotifier::class)->justificativaPara($viabilityRequest, $viabilityRequest->decision),
                    'personalizada' => filled($viabilityRequest->regin_justificativa),
                ],
            'setoresEncaminhamento' => Sector::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
            'destinatariosConclusao' => User::permission('concluir-analise')
                ->orderBy('name')
                ->get(['users.id', 'users.name'])
                ->map(fn (User $usuario): array => ['value' => (string) $usuario->id, 'label' => $usuario->name])
                ->all(),
            'analistasEncaminhamento' => User::query()
                ->with('sectors:id')
                ->orderBy('name')
                ->get()
                ->map(fn (User $usuario): array => [
                    'id' => $usuario->id,
                    'name' => $usuario->name,
                    'setores' => $usuario->sectors->pluck('id')->all(),
                ])
                ->all(),
            // Geometria do imóvel para o mini-mapa Leaflet (RN-006): o polígono
            // real do processo, exposto SÓ no detalhe (não infla a lista/fila). A
            // zona/via oficiais (Quadro 10) seguem pendentes na SEDUR — o mapa
            // mostra o que existe e degrada honestamente quando não há polígono.
            'geo' => [
                'poligono' => $viabilityRequest->property_polygon_geojson,
                'poligono_enderecamento' => $viabilityRequest->address_polygon_geojson,
                'ponto' => $viabilityRequest->property_polygon_geojson ? null : $viabilityRequest->pontoDoImovel(),
                'origem' => $viabilityRequest->property_location_source === null ? null : [
                    'value' => $viabilityRequest->property_location_source->value,
                    'label' => $viabilityRequest->property_location_source->label(),
                ],
                'camadas' => $viabilityRequest->camadas_incidentes,
            ],
            // Logradouro declarado × logradouro da inscrição (SEFAZ): só vem
            // preenchido quando os dois existem e divergem.
            'divergencia_endereco' => $this->divergenciaLogradouro->avaliar($viabilityRequest),
            // Próximas transições manuais oferecidas ao analista no dropdown
            // do detalhe (Tarefa 10) — vazio quando o processo ainda não tem
            // status de análise (fora do fluxo operacional) ou já está num
            // estado terminal/dirigido por evento.
            'conclusaoOpcoes' => ConclusionOutcome::options(),
            'analysisStatusProximas' => $viabilityRequest->analysis_status
                ? array_map(
                    fn (AnalysisStatus $s): array => ['value' => $s->value, 'label' => $s->label()],
                    $viabilityRequest->analysis_status->proximas(),
                )
                : [],
        ]);
    }

    /**
     * Linha do tempo do processo. A distribuição não muda o status canônico
     * (o processo já está em análise), então quem distribuiu sai da auditoria
     * da atribuição e aparece na etapa Em análise.
     *
     * @return list<array<string, mixed>>
     */
    private function timeline(ViabilityRequest $viabilityRequest): array
    {
        $distribuidor = $this->distribuidorDoProcesso($viabilityRequest);

        $status = $viabilityRequest->transitions->load('actor')->map(fn ($transicao): array => [
            'tipo' => 'status',
            'resultado' => null,
            'from' => $transicao->from_status?->value,
            'from_label' => $transicao->from_status?->label(),
            'to' => $transicao->to_status->value,
            'to_label' => $transicao->to_status->label(),
            'reason' => $transicao->reason,
            'em' => $transicao->created_at?->toIso8601String(),
            'ator' => $transicao->actor?->isAtorOcultoNaTramitacao() ? null : $transicao->actor?->name,
            'atribuido' => $transicao->to_status === ViabilityRequestStatus::EmAnalise
                ? $viabilityRequest->assignedTo?->name
                : null,
            'distribuidor' => $transicao->to_status === ViabilityRequestStatus::EmAnalise
                ? $distribuidor
                : null,
        ])->all();

        $linha = [...$status, ...app(TramitacaoProcessoService::class)->eventos($viabilityRequest)];

        usort($linha, fn (array $a, array $b): int => strcmp((string) $b['em'], (string) $a['em']));

        return $linha;
    }

    /**
     * Quem fez a última atribuição (distribuição, assunção ou redistribuição).
     * O ator gravado pelo serviço prevalece sobre o causer da sessão.
     */
    private function distribuidorDoProcesso(ViabilityRequest $viabilityRequest): ?string
    {
        $atividade = Activity::query()
            ->where('subject_type', $viabilityRequest->getMorphClass())
            ->where('subject_id', $viabilityRequest->id)
            ->whereIn('event', ['distribuir', 'assumir', 'redistribuir'])
            ->latest('id')
            ->first();

        if ($atividade === null) {
            return null;
        }

        $atorId = $atividade->properties['ator_id'] ?? $atividade->causer_id;

        if ($atorId === null) {
            return null;
        }

        $ator = User::query()->find($atorId);

        return $ator === null || $ator->isAtorOcultoNaTramitacao() ? null : $ator->name;
    }

    /**
     * Transição manual do status de análise (eixo operacional AnalysisStatus):
     * o controller restringe a seleção manual ao subconjunto
     * AnalysisStatus::proximas() (espelhando o dropdown da UI) ANTES de
     * delegar à AnalysisStatusStateMachine — os estados dirigidos por evento
     * (convite_respondido/expirado) fazem parte do grafo completo da máquina,
     * mas não podem ser setados manualmente. A guarda é ignorada quando o
     * usuário tem forcar-transicao-analise E pede o override (`force`), que aí passa a valer o
     * grafo completo validado pela state machine. `motivo` é opcional na Fase
     * 1 (a obrigatoriedade de parecer no cancelamento chega com o convite, na
     * Fase 2). Transição recusada é comunicada com flash.error — nunca
     * silenciosa.
     */
    public function atualizarStatusAnalise(
        AnalysisStatusRequest $request,
        ViabilityRequest $viabilityRequest,
        AnalysisStatusStateMachine $machine,
    ): RedirectResponse {
        $to = AnalysisStatus::from($request->string('status')->toString());
        $from = $viabilityRequest->analysis_status;
        $force = $request->boolean('force') && $request->user()->can('forcar-transicao-analise');

        if (! $force && ($from === null || ! in_array($to, $from->proximas(), true))) {
            return back()->with('error', 'Transição de situação da análise não permitida.');
        }

        if ($to === AnalysisStatus::Encaminhado) {
            $analista = User::query()->findOrFail($request->integer('user_id'));
            $pertence = $analista->sectors()->where('sectors.id', $request->integer('sector_id'))->exists();

            if (! $pertence) {
                return back()->with('error', 'O usuário escolhido não pertence ao setor informado.');
            }

            $viabilityRequest->forceFill([
                'sector_id' => $request->integer('sector_id'),
                'assigned_user_id' => $analista->id,
                'assigned_at' => now(),
            ])->save();
        }

        if ($to === AnalysisStatus::ParaConclusao) {
            $viabilityRequest->forceFill([
                'conclusao_encaminhada_para_user_id' => $request->filled('encaminhado_para_user_id')
                    ? $request->integer('encaminhado_para_user_id')
                    : null,
            ]);
        }

        try {
            $machine->transition(
                $viabilityRequest,
                $to,
                $request->user(),
                $request->string('motivo')->toString() ?: null,
                force: $force,
            );
        } catch (InvalidAnalysisStatusTransitionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Situação da análise atualizada.');
    }

    /**
     * Coleta os filtros do SAPS + analista + categoria da query string (strings
     * cruas; o ProcessoQueryService normaliza e ignora os vazios).
     *
     * @return array<string, string>
     */
    private function filtros(Request $request): array
    {
        $chaves = [
            'grupo', 'status', 'analysis_status', 'protocolo', 'bap', 'produto_tvl', 'servico', 'setor',
            'analista', 'categoria', 'inscricao', 'nome', 'cnpj', 'cep', 'logradouro',
            'bairro', 'data_de', 'data_ate', 'busca', 'fluxo',
        ];

        $filtros = [];

        foreach ($chaves as $chave) {
            $filtros[$chave] = $request->string($chave)->toString();
        }

        return $filtros;
    }

    /**
     * Só os filtros efetivamente informados (para a trilha de auditoria).
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function filtrosPreenchidos(array $filtros): array
    {
        return array_filter($filtros, fn ($valor): bool => $valor !== null && $valor !== '');
    }

    /**
     * Período invertido não pode ser ignorado em silêncio: a consulta completa
     * não roda e as datas informadas permanecem na tela.
     *
     * @param  array<string, string>  $filtros
     */
    private function periodoInvertido(array $filtros): bool
    {
        $de = $filtros['data_de'] ?? '';
        $ate = $filtros['data_ate'] ?? '';

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $de) === 1
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate) === 1
            && $de > $ate;
    }

    private function ordem(Request $request): string
    {
        $ordem = $request->string('ordem')->toString();

        return in_array($ordem, ['recentes', 'prazo', 'status'], true) ? $ordem : 'recentes';
    }

    private function perPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page');

        return in_array($perPage, self::PER_PAGE_OPTIONS, true)
            ? $perPage
            : (int) Settings::get('ui.cnaes.per_page', 15);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(
            fn (ViabilityRequestStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
            ViabilityRequestStatus::cases(),
        );
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function categoriaOptions(): array
    {
        $opcoes = [];

        foreach (ProcessoQueryService::CATEGORIAS as $value => $label) {
            $opcoes[] = ['value' => $value, 'label' => $label];
        }

        return $opcoes;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function servicoOptions(): array
    {
        return ViabilityServiceType::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($servico): array => ['value' => (string) $servico->id, 'label' => $servico->name])
            ->all();
    }
}

<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\AnalysisStatus;
use App\Enums\ViabilityRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\AnalysisStatusRequest;
use App\Http\Resources\DecisionExplanationResource;
use App\Http\Resources\ProcessoResource;
use App\Models\ViabilityRequest;
use App\Services\Analise\AnalysisStatusStateMachine;
use App\Services\Analise\InvalidAnalysisStatusTransitionException;
use App\Services\Analise\ProcessoQueryService;
use App\Services\Auditoria\DecisionExplanationService;
use App\Services\Relatorios\Export\ReportExporter;
use App\Services\Relatorios\Export\Sources\ProcessosReportSource;
use App\Services\Relatorios\ReportFilters;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        if (in_array($request->string('formato')->lower()->toString(), ['csv', 'xlsx', 'pdf'], true)) {
            return app(ReportExporter::class)->export(
                app(ProcessosReportSource::class),
                ReportFilters::fromArray($filtros),
                $request->string('formato')->lower()->toString(),
                $request->user(),
            );
        }

        $perPage = $this->perPage($request);

        $processos = $this->processos->filtered($filtros)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (ViabilityRequest $processo): array => (new ProcessoResource($processo))->resolve());

        $this->audit->log('analise', 'consulta-processos', 'Consulta de processos da análise técnica', [
            'filtros' => $this->filtrosPreenchidos($filtros),
        ]);

        return Inertia::render('gestao/processos/index', [
            'processos' => $processos,
            'filtros' => $filtros + ['per_page' => $perPage],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'statusOptions' => $this->statusOptions(),
            'analysisStatusOptions' => AnalysisStatus::options(),
            'categoriaOptions' => $this->categoriaOptions(),
        ]);
    }

    /**
     * Fila de trabalho do analista (HU-144): "meus processos" (atribuídos) ou
     * "caixa do setor" (processos do(s) setor(es) do usuário — respeita o
     * vínculo), ordenada por prazo (analysis_due_at) com semáforo on-the-fly e
     * contadores por status. O gestor (distribuir-processos) ganha a visão
     * agregada do setor (carga por analista + processos em vermelho — CA-03). O
     * acesso é auditado.
     */
    public function fila(Request $request): Response
    {
        $modo = $request->string('modo')->toString();
        $modo = in_array($modo, ['meus', 'setor'], true) ? $modo : 'meus';

        $user = $request->user();

        $processos = $this->processos->fila($user, $modo)
            ->get()
            ->map(fn (ViabilityRequest $processo): array => (new ProcessoResource($processo))->resolve())
            ->all();

        $this->audit->log('analise', 'consulta-fila', 'Consulta da fila de trabalho do analista', [
            'modo' => $modo,
        ]);

        return Inertia::render('gestao/processos/fila', [
            'modo' => $modo,
            'processos' => $processos,
            'contadores' => $this->processos->contadores($user, $modo),
            // A visão agregada do setor é só do gestor (distribuir-processos);
            // o analista recebe null e a UI (10-16) não a renderiza.
            'visaoSetor' => $user->can('distribuir-processos') ? $this->processos->visaoSetor($user) : null,
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
            // Explicabilidade passo a passo (HU-099): projeção PURA do
            // decision_trace gravado (RN-005), só quando há decisão — sem
            // desfecho não há explicação inventada. Prop ADITIVA sob o gate
            // consultar-solicitacoes já vigente.
            'explicacao' => $viabilityRequest->decision === null
                ? null
                : (new DecisionExplanationResource($this->explanations->explain($viabilityRequest->decision)))->resolve(),
            'timeline' => $viabilityRequest->transitions->map(fn ($transicao): array => [
                'from' => $transicao->from_status?->value,
                'from_label' => $transicao->from_status?->label(),
                'to' => $transicao->to_status->value,
                'to_label' => $transicao->to_status->label(),
                'reason' => $transicao->reason,
                'em' => $transicao->created_at?->toIso8601String(),
            ])->all(),
            // Geometria do imóvel para o mini-mapa Leaflet (RN-006): o polígono
            // real do processo, exposto SÓ no detalhe (não infla a lista/fila). A
            // zona/via oficiais (Quadro 10) seguem pendentes na SEDUR — o mapa
            // mostra o que existe e degrada honestamente quando não há polígono.
            'geo' => [
                'poligono' => $viabilityRequest->property_polygon_geojson,
            ],
        ]);
    }

    /**
     * Transição manual do status de análise (eixo operacional AnalysisStatus):
     * o controller FINO delega à AnalysisStatusStateMachine, que valida o
     * grafo de transições, grava a timeline e audita. Transição fora do grafo
     * é recusada com flash.error — nunca silenciosa — a menos que o usuário
     * seja gestor E peça o override (`force`), justificado pelo `motivo`.
     */
    public function atualizarStatusAnalise(
        AnalysisStatusRequest $request,
        ViabilityRequest $viabilityRequest,
        AnalysisStatusStateMachine $machine,
    ): RedirectResponse {
        $to = AnalysisStatus::from($request->string('status')->toString());
        $force = $request->boolean('force') && $request->user()->hasRole('gestor');

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
            'bairro', 'data_de', 'data_ate',
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
}

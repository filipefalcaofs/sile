<?php

namespace App\Http\Controllers\Portal;

use App\Enums\AiSuggestionType;
use App\Enums\ResultadoViabilidade;
use App\Enums\ViabilityRequestOrigin;
use App\Enums\ViabilityRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StoreSolicitacaoRequest;
use App\Models\AiSuggestion;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\DocumentRequirement;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestDocument;
use App\Models\ViabilityServiceType;
use App\Services\Ai\ResumoSolicitacaoService;
use App\Services\Solicitacao\DocumentRequirementResolver;
use App\Services\Solicitacao\DuplicateRequestDetector;
use App\Support\Representation\CurrentRepresentation;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Início do processo formal (HU-061): o cidadão CRIA o rascunho da solicitação
 * (origem portal direto + tipo de serviço + empresa). O requerente é o usuário
 * EFETIVO (representado quando "em nome de"); o created_by é o ator real. A
 * reincidência por CNPJ gera ALERTA (RN-007), nunca bloqueio. Toggle
 * features.solicitacao_viabilidade degrada de forma comunicada quando off.
 */
class SolicitacaoController extends Controller
{
    /**
     * Colunas ordenáveis e tamanhos de página aceitos — whitelists técnicas.
     * Sem parâmetro dedicado de per_page (precedente [08-03]): adicioná-lo
     * tocaria o ParameterSeeder, cujas contagens estão travadas em 48.
     */
    private const SORTABLE_COLUMNS = ['protocol_number', 'created_at'];

    private const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    private const DEFAULT_PER_PAGE = 15;

    public function __construct(
        private DuplicateRequestDetector $duplicateDetector,
        private DocumentRequirementResolver $requirementResolver,
        private ResumoSolicitacaoService $resumos,
    ) {}

    /**
     * "Minhas solicitações": lista server-driven SOMENTE das solicitações do
     * requerente efetivo (em representação, as do representado). Busca por
     * número de protocolo ou razão social da empresa; ordenação whitelistada;
     * paginação por constante. A tela é o 08-13 (aqui só o backend).
     */
    public function index(Request $request): Response
    {
        $effectiveUser = $this->effectiveUser($request);

        $sort = $request->string('sort')->toString();
        $sort = in_array($sort, self::SORTABLE_COLUMNS, true) ? $sort : 'created_at';

        $direction = $request->string('direction')->toString();
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';

        $perPage = (int) $request->input('per_page');
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::DEFAULT_PER_PAGE;

        $search = (string) $request->string('search')->trim();

        $cancelableStates = $this->cancelableStates();

        $solicitacoes = ViabilityRequest::query()
            ->where('requester_user_id', $effectiveUser->id)
            ->with(['company', 'serviceType'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->whereLike('protocol_number', "%{$search}%", caseSensitive: false)
                        ->orWhereHas('company', fn ($company) => $company
                            ->whereLike('legal_name', "%{$search}%", caseSensitive: false));
                });
            })
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (ViabilityRequest $solicitacao) => [
                'id' => $solicitacao->id,
                'protocol_number' => $solicitacao->protocol_number,
                'status' => [
                    'value' => $solicitacao->status->value,
                    'label' => $solicitacao->status->label(),
                    'public_label' => $solicitacao->status->publicLabel(),
                ],
                'service_type' => $solicitacao->serviceType?->name,
                'company' => $solicitacao->company ? [
                    'legal_name' => $solicitacao->company->legal_name,
                    'formatted_cnpj' => $solicitacao->company->formatted_cnpj,
                ] : null,
                'created_at' => $solicitacao->created_at?->toDateTimeString(),
                // Só rascunho pode continuar a edição no wizard (policy update).
                'editable' => $solicitacao->status === ViabilityRequestStatus::Rascunho,
                // A ação de cancelar só aparece nos estados canceláveis (HU-070,
                // parâmetro solicitacao.cancelamento.estados_cancelaveis).
                'cancelable' => in_array($solicitacao->status->value, $cancelableStates, true),
            ]);

        return Inertia::render('portal/solicitacoes/index', [
            'solicitacoes' => $solicitacoes,
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'solicitacaoEnabled' => Settings::enabled('solicitacao_viabilidade'),
            // Alerta de reincidência (RN-007) vindo do store — nunca bloqueia,
            // só aponta o processo anterior. Exibido uma vez após criar.
            'duplicateAlert' => $request->session()->get('duplicateAlert'),
        ]);
    }

    /**
     * Estados em que a solicitação pode ser cancelada pelo requerente (HU-070):
     * parâmetro administrável (efeito sem deploy), mesmo contrato lido pelo
     * CancelarSolicitacaoService — default honesto rascunho + protocolada.
     *
     * @return array<int, string>
     */
    private function cancelableStates(): array
    {
        /** @var array<int, string> $states */
        $states = (array) Settings::get(
            'solicitacao.cancelamento.estados_cancelaveis',
            config('sile.solicitacao.cancelamento.estados_cancelaveis', ['rascunho', 'protocolada']),
        );

        return $states;
    }

    /**
     * Cria o rascunho da solicitação em nome do usuário efetivo (em
     * representação, PARA o representado), registrando o ator real em
     * created_by. Antes de criar, alerta sobre reincidência por CNPJ (RN-007)
     * sem bloquear. Auditoria created (HasAuditoria) enriquecida com acting_for
     * pelo RecordActivityAction ([01-07]). Com o toggle desligado, degrada de
     * forma comunicada (nunca falha silenciosa).
     */
    public function store(StoreSolicitacaoRequest $request): RedirectResponse
    {
        if (! Settings::enabled('solicitacao_viabilidade')) {
            return back()->with('status', 'A criação de solicitações de viabilidade está temporariamente desativada pelo administrador.');
        }

        $effectiveUser = $this->effectiveUser($request);
        $company = Company::query()->findOrFail($request->validated('company_id'));

        // Reincidência por CNPJ (RN-007): alerta com link ao processo anterior,
        // calculado ANTES de criar para não detectar a si mesmo. Nunca bloqueia.
        $alert = $this->duplicateDetector->detect($company);

        DB::transaction(fn () => ViabilityRequest::create([
            'origin' => ViabilityRequestOrigin::Portal,
            'service_type_id' => $request->validated('service_type_id'),
            'company_id' => $company->id,
            'requester_user_id' => $effectiveUser->id,
            'created_by_user_id' => $request->user()->id,
        ]));

        $redirect = redirect()
            ->route('portal.solicitacoes.index')
            ->with('status', 'Rascunho de solicitação criado com sucesso.');

        if ($alert !== null) {
            $redirect->with('duplicateAlert', $alert);
        }

        return $redirect;
    }

    /**
     * Wizard de um NOVO rascunho (HU-061): só a etapa de início (tipo de serviço
     * + empresa do dono). Renderiza a página do wizard sem solicitação — o POST
     * em store cria o rascunho e o fluxo segue na edição (continuar preenchendo).
     */
    public function create(Request $request): Response
    {
        $effectiveUser = $this->effectiveUser($request);

        return Inertia::render('portal/solicitacoes/wizard', [
            'solicitacao' => null,
            'serviceTypes' => $this->serviceTypeOptions(),
            'companies' => $this->companyOptions($effectiveUser),
            'requisitosObrigatorios' => [],
            'requisitosFaltantes' => [],
            'anexosConfig' => $this->anexosConfig(),
            'cnaesComplementaresMax' => $this->cnaesComplementaresMax(),
            'simulacaoEnabled' => Settings::enabled('simulacao_solicitacao'),
            'solicitacaoEnabled' => Settings::enabled('solicitacao_viabilidade'),
            'territorio' => null,
            'areaAlert' => null,
        ]);
    }

    /**
     * Wizard de um rascunho EXISTENTE (HU-062..067, 141): continua o
     * preenchimento (imóvel, atividades, documentos, simulação, revisão). Só o
     * dono em rascunho (ViabilityRequestPolicy::update, CA-04). O território e o
     * alerta de área voltam por flash da etapa de imóvel (transientes); a
     * simulação vem do snapshot persistido (RN-003). Distinta da página de
     * protocolo/consulta (08-11).
     */
    public function edit(Request $request, ViabilityRequest $solicitacao): Response
    {
        Gate::authorize('update', $solicitacao);

        $solicitacao->load(['company', 'serviceType', 'cnaes', 'documents.requirement']);

        return Inertia::render('portal/solicitacoes/wizard', [
            'solicitacao' => $this->wizardDraftPayload($solicitacao),
            'serviceTypes' => [],
            'companies' => [],
            'requisitosObrigatorios' => $this->requirementResolver->required($solicitacao)
                ->map(fn (DocumentRequirement $requirement) => [
                    'id' => $requirement->id,
                    'code' => $requirement->code,
                    'name' => $requirement->name,
                    'description' => $requirement->description,
                ])->values(),
            'requisitosFaltantes' => $this->requirementResolver->missing($solicitacao)
                ->map(fn (DocumentRequirement $requirement) => [
                    'id' => $requirement->id,
                    'code' => $requirement->code,
                    'name' => $requirement->name,
                ])->values(),
            'anexosConfig' => $this->anexosConfig(),
            'cnaesComplementaresMax' => $this->cnaesComplementaresMax(),
            'simulacaoEnabled' => Settings::enabled('simulacao_solicitacao'),
            'solicitacaoEnabled' => Settings::enabled('solicitacao_viabilidade'),
            // Transientes vindos do PUT de imóvel (08-06): resumo do território
            // identificado (degrada honesto sem zona) e alerta de área×polígono.
            'territorio' => $request->session()->get('territorio'),
            'areaAlert' => $request->session()->get('areaAlert'),
            // Resumo da solicitação por IA (HU-116) para conferência pré-protocolo
            // — prop DEFERIDA (carregada sob demanda pelo card na etapa de revisão,
            // fora do load inicial). Ao resolver, dispara o resumo de forma gated e
            // idempotente (toggle ia_resumo off ou sem provedor ⇒ no-op; dedup por
            // entrada evita reprocessar) e então LÊ o ledger. Sempre SUGESTÃO de
            // conferência revisável, jamais decisão nem afirmação de desfecho
            // (RN-001/Failure Mode #1).
            'sugestoesResumo' => Inertia::optional(function () use ($request, $solicitacao): array {
                $this->resumos->processar($solicitacao, $request->user()?->id);

                return $this->sugestoesResumo($solicitacao);
            }),
        ]);
    }

    /**
     * Tipos de serviço ATIVOS (HU-061 RN-005) para o select de início.
     *
     * @return array<int, array<string, mixed>>
     */
    private function serviceTypeOptions(): array
    {
        return ViabilityServiceType::query()
            ->active()
            ->orderBy('name')
            ->get()
            ->map(fn (ViabilityServiceType $type) => [
                'value' => $type->id,
                'label' => $type->name,
            ])
            ->all();
    }

    /**
     * Empresas com vínculo ATIVO do usuário efetivo (Fase 3) — base da solicitação.
     *
     * @return array<int, array<string, mixed>>
     */
    private function companyOptions(User $user): array
    {
        return Company::query()
            ->whereHas('links', fn ($query) => $query->where('user_id', $user->id)->whereNull('ended_at'))
            ->orderBy('legal_name')
            ->get()
            ->map(fn (Company $company) => [
                'value' => $company->id,
                'label' => $company->legal_name,
                'formatted_cnpj' => $company->formatted_cnpj,
            ])
            ->all();
    }

    /**
     * Estado completo do rascunho para reidratar o wizard (consome o que os
     * backends 08-06/07/08/09 persistiram — nada simulado no front).
     *
     * @return array<string, mixed>
     */
    private function wizardDraftPayload(ViabilityRequest $solicitacao): array
    {
        return [
            'id' => $solicitacao->id,
            'protocol_number' => $solicitacao->protocol_number,
            'status' => [
                'value' => $solicitacao->status->value,
                'label' => $solicitacao->status->label(),
                'public_label' => $solicitacao->status->publicLabel(),
            ],
            'service_type' => $solicitacao->serviceType?->name,
            'service_type_id' => $solicitacao->service_type_id,
            'company' => $solicitacao->company ? [
                'id' => $solicitacao->company->id,
                'legal_name' => $solicitacao->company->legal_name,
                'formatted_cnpj' => $solicitacao->company->formatted_cnpj,
            ] : null,
            'used_area_m2' => $solicitacao->used_area_m2,
            'address' => [
                'street' => $solicitacao->address_street,
                'number' => $solicitacao->address_number,
                'complement' => $solicitacao->address_complement,
                'neighborhood' => $solicitacao->address_neighborhood,
                'zip' => $solicitacao->address_zip,
                'reference' => $solicitacao->address_reference,
            ],
            'property_polygon_geojson' => $solicitacao->property_polygon_geojson,
            'indicators' => [
                'is_virtual_office' => (bool) $solicitacao->is_virtual_office,
                'is_public_area' => (bool) $solicitacao->is_public_area,
                'has_independent_access' => (bool) $solicitacao->has_independent_access,
            ],
            'cnaes' => $solicitacao->cnaes
                ->sortByDesc(fn (Cnae $cnae) => (bool) $cnae->pivot->is_primary)
                ->values()
                ->map(fn (Cnae $cnae) => [
                    'id' => $cnae->id,
                    'formatted_code' => $cnae->formatted_code,
                    'description' => $cnae->description,
                    'is_primary' => (bool) $cnae->pivot->is_primary,
                ])
                ->all(),
            'documentos' => $solicitacao->documents
                ->map(fn (ViabilityRequestDocument $document) => [
                    'id' => $document->id,
                    'original_name' => $document->original_name,
                    'mime_type' => $document->mime_type,
                    'size' => $document->size,
                    'requirement_id' => $document->requirement_id,
                    'requirement_name' => $document->requirement?->name,
                    'download_url' => route('portal.solicitacoes.documentos.download', [$solicitacao, $document]),
                ])
                ->all(),
            'simulation' => $this->simulationPayload($solicitacao),
        ];
    }

    /**
     * Snapshot da simulação persistido (RN-003) — não reprocessa: o front reusa
     * o ResultadoViabilidade por CNAE. Null quando ainda não simulada/invalidada.
     *
     * @return array<string, mixed>|null
     */
    private function simulationPayload(ViabilityRequest $solicitacao): ?array
    {
        if ($solicitacao->simulated_at === null || $solicitacao->simulation_resultado === null) {
            return null;
        }

        return [
            'resultado' => $solicitacao->simulation_resultado,
            'resultado_label' => ResultadoViabilidade::from($solicitacao->simulation_resultado)->label(),
            'por_cnae' => $solicitacao->simulation_snapshot['por_cnae'] ?? [],
            'simulated_at' => $solicitacao->simulated_at->toIso8601String(),
        ];
    }

    /**
     * Resumo da solicitação sugerido pela IA (HU-116) para o card de conferência
     * da etapa de revisão — APENAS LEITURA do ledger ai_suggestions, escopado a
     * esta solicitação e ao tipo resumo_solicitacao, mais recentes primeiro. Não
     * decide nada: é sinal de conferência revisável, jamais afirmação de desfecho.
     *
     * @return list<array<string, mixed>>
     */
    private function sugestoesResumo(ViabilityRequest $solicitacao): array
    {
        return AiSuggestion::query()
            ->where('viability_request_id', $solicitacao->id)
            ->where('type', AiSuggestionType::ResumoSolicitacao)
            ->orderByDesc('id')
            ->get()
            ->map(fn (AiSuggestion $sugestao): array => [
                'id' => $sugestao->id,
                'type' => $sugestao->type->value,
                'type_label' => $sugestao->type->label(),
                'status' => $sugestao->status->value,
                'status_label' => $sugestao->status->label(),
                'output' => $sugestao->output,
                'created_at' => $sugestao->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Tipos e tamanho máximo aceitos no upload (HU-014) — comunicados na UI.
     *
     * @return array<string, mixed>
     */
    private function anexosConfig(): array
    {
        return [
            'max_mb' => (int) Settings::get('solicitacao.anexos.max_mb', config('sile.solicitacao.anexos.max_mb', 10)),
            'mime_permitidos' => (array) Settings::get(
                'solicitacao.anexos.mime_permitidos',
                config('sile.solicitacao.anexos.mime_permitidos', ['application/pdf', 'image/jpeg', 'image/png']),
            ),
        ];
    }

    /**
     * Limite parametrizável de CNAEs complementares (HU-065) — comunicado na UI.
     */
    private function cnaesComplementaresMax(): int
    {
        return (int) Settings::get(
            'solicitacao.cnaes_complementares.max',
            config('sile.solicitacao.cnaes_complementares.max', 99),
        );
    }

    /**
     * Usuário efetivo: o representado em representação ativa, senão o próprio
     * ([01-07]). Resolvido pelo middleware ResolveRepresentation.
     */
    private function effectiveUser(Request $request): User
    {
        return app(CurrentRepresentation::class)->grantor() ?? $request->user();
    }
}

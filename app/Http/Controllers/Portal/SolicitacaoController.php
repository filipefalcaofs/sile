<?php

namespace App\Http\Controllers\Portal;

use App\Enums\ViabilityRequestOrigin;
use App\Enums\ViabilityRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StoreSolicitacaoRequest;
use App\Models\Company;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Solicitacao\DuplicateRequestDetector;
use App\Support\Representation\CurrentRepresentation;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function __construct(private DuplicateRequestDetector $duplicateDetector) {}

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
     * Usuário efetivo: o representado em representação ativa, senão o próprio
     * ([01-07]). Resolvido pelo middleware ResolveRepresentation.
     */
    private function effectiveUser(Request $request): User
    {
        return app(CurrentRepresentation::class)->grantor() ?? $request->user();
    }
}

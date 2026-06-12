<?php

namespace App\Http\Controllers\Portal;

use App\Enums\CompanyLinkRole;
use App\Enums\CompanySource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StoreCompanyRequest;
use App\Models\Company;
use App\Models\User;
use App\Support\Representation\CurrentRepresentation;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    /**
     * Colunas ordenáveis e tamanhos de página aceitos via request —
     * whitelists técnicas de proteção; o padrão de página é parâmetro.
     */
    private const SORTABLE_COLUMNS = ['legal_name'];

    private const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    /**
     * "Minhas empresas" (HU-027): lista server-driven SOMENTE das empresas
     * com vínculo (ativo ou encerrado) do usuário efetivo — em representação,
     * as empresas do representado. Eager loading anti-N+1 do CNAE principal e
     * do vínculo do usuário; busca por razão social/fantasia (case-insensitive)
     * ou prefixo de CNPJ; paginação parametrizada (ui.companies.per_page).
     */
    public function index(Request $request): Response
    {
        $effectiveUser = $this->effectiveUser($request);

        $sort = $request->string('sort')->toString();
        $sort = in_array($sort, self::SORTABLE_COLUMNS, true) ? $sort : 'legal_name';

        $direction = $request->string('direction')->toString();
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'asc';

        $perPage = (int) $request->input('per_page');
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true)
            ? $perPage
            : (int) Settings::get('ui.companies.per_page', 15);

        $search = (string) $request->string('search')->trim();

        $companies = Company::query()
            ->whereHas('links', fn ($q) => $q->where('user_id', $effectiveUser->id))
            ->with([
                'cnaes' => fn ($q) => $q->wherePivot('is_primary', true),
                'links' => fn ($q) => $q->where('user_id', $effectiveUser->id)->latest('started_at'),
            ])
            ->when($search !== '', function ($query) use ($search) {
                $digits = preg_replace('/\D/', '', $search);

                $query->where(function ($inner) use ($search, $digits) {
                    if ($digits !== '') {
                        $inner->where('cnpj', 'like', "{$digits}%");
                    }

                    $inner->orWhereLike('legal_name', "%{$search}%", caseSensitive: false)
                        ->orWhereLike('trade_name', "%{$search}%", caseSensitive: false);
                });
            })
            ->orderBy($sort, $direction)
            ->orderBy('legal_name')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Company $company) => [
                'id' => $company->id,
                'legal_name' => $company->legal_name,
                'trade_name' => $company->trade_name,
                'formatted_cnpj' => $company->formatted_cnpj,
                'source' => [
                    'value' => $company->source->value,
                    'label' => $company->source->label(),
                ],
                'primary_cnae' => ($cnae = $company->cnaes->first()) ? [
                    'formatted_code' => $cnae->formatted_code,
                    'description' => $cnae->description,
                ] : null,
                'link' => ($link = $company->links->first()) ? [
                    'role' => $link->role->value,
                    'role_label' => $link->role->label(),
                    'active' => $link->ended_at === null,
                    'started_at' => $link->started_at?->toDateString(),
                    'ended_at' => $link->ended_at?->toDateString(),
                ] : null,
            ]);

        return Inertia::render('portal/empresas/index', [
            'companies' => $companies,
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'totalCompanies' => Company::countForUser($effectiveUser),
        ]);
    }

    /**
     * Página dedicada de cadastro (HU-023). O botão "Buscar CNPJ" só aparece
     * habilitado quando o toggle features.cnpj_lookup está ligado ([03-02]).
     */
    public function create(): Response
    {
        return Inertia::render('portal/empresas/cadastrar', [
            'cnpjLookupEnabled' => Settings::enabled('cnpj_lookup'),
        ]);
    }

    /**
     * Cria a empresa e o vínculo de responsável ATIVO na MESMA transação, em
     * nome do usuário efetivo (em representação, cria PARA o representado).
     * CNPJ duplicado é bloqueado no FormRequest com mensagem pt-BR (CA-03).
     * Auditoria created da Company e do vínculo é automática (HasAuditoria),
     * enriquecida com acting_for pelo RecordActivityAction ([01-07]).
     */
    public function store(StoreCompanyRequest $request): RedirectResponse
    {
        $effectiveUser = $this->effectiveUser($request);

        DB::transaction(function () use ($request, $effectiveUser) {
            $company = Company::create([
                ...$request->validated(),
                'source' => CompanySource::Manual,
            ]);

            $company->links()->create([
                'user_id' => $effectiveUser->id,
                'role' => CompanyLinkRole::Responsavel,
                'started_at' => now(),
            ]);
        });

        return redirect()
            ->route('portal.empresas.index')
            ->with('status', 'Empresa cadastrada com sucesso.');
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

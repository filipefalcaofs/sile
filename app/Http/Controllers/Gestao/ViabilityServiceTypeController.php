<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\StoreViabilityServiceTypeRequest;
use App\Http\Requests\Gestao\UpdateViabilityServiceTypeRequest;
use App\Models\ViabilityServiceType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manutenção dos tipos de serviço da solicitação (HU-061 RN-005) no console
 * SEDUR — dado administrável (CRUD), não registry de código. O tipo determina
 * fluxo, documentos e relatórios; a lista oficial é carga da SEDUR (seed
 * mínimo no fechamento da fase) e o cadastro recebe-a sem deploy. Listagem
 * server-driven espelhando o CnaeController; auditoria automática via
 * HasAuditoria no model (RN-002).
 */
class ViabilityServiceTypeController extends Controller
{
    /**
     * Colunas ordenáveis e tamanhos de página aceitos via request —
     * whitelists técnicas de proteção.
     */
    private const SORTABLE_COLUMNS = ['code', 'name'];

    private const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    private const DEFAULT_PER_PAGE = 15;

    public function index(Request $request): Response
    {
        $sort = $request->string('sort')->toString();
        $sort = in_array($sort, self::SORTABLE_COLUMNS, true) ? $sort : 'code';

        $direction = $request->string('direction')->toString();
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'asc';

        $perPage = (int) $request->input('per_page');
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::DEFAULT_PER_PAGE;

        $active = $request->string('active')->toString();

        $serviceTypes = ViabilityServiceType::query()
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request) {
                $term = (string) $request->string('search')->trim();

                $query->where(function ($inner) use ($term) {
                    // whereLike sem case: LIKE do PostgreSQL é case-sensitive.
                    $inner->whereLike('code', "%{$term}%", caseSensitive: false)
                        ->orWhereLike('name', "%{$term}%", caseSensitive: false);
                });
            })
            ->when(in_array($active, ['0', '1'], true), fn ($query) => $query->where('active', $active === '1'))
            ->orderBy($sort, $direction)
            ->orderBy('code')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (ViabilityServiceType $serviceType) => [
                'id' => $serviceType->id,
                'code' => $serviceType->code,
                'name' => $serviceType->name,
                'flow_hint' => $serviceType->flow_hint,
                'active' => $serviceType->active,
            ]);

        return Inertia::render('gestao/tipos-servico/index', [
            'serviceTypes' => $serviceTypes,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
                'active' => in_array($active, ['0', '1'], true) ? $active : '',
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    public function store(StoreViabilityServiceTypeRequest $request): RedirectResponse
    {
        ViabilityServiceType::create($request->validated());

        return back()->with('status', 'Tipo de serviço cadastrado com sucesso.');
    }

    /**
     * Atualiza nome, pista de fluxo e situação. O code é imutável (não consta
     * no UpdateRequest, logo o valor enviado é descartado — padrão CPF/CNAE).
     */
    public function update(UpdateViabilityServiceTypeRequest $request, ViabilityServiceType $serviceType): RedirectResponse
    {
        $serviceType->update($request->validated());

        return back()->with('status', 'Tipo de serviço atualizado com sucesso.');
    }

    /**
     * Liga/desliga o tipo de serviço. NUNCA exclui o registro: um tipo já
     * referenciado por solicitações precisa do histórico preservado — a
     * desativação apenas o retira da seleção do requerente.
     */
    public function toggleActivation(ViabilityServiceType $serviceType): RedirectResponse
    {
        $serviceType->update(['active' => ! $serviceType->active]);

        $message = $serviceType->active
            ? 'Tipo de serviço reativado.'
            : 'Tipo de serviço desativado.';

        return back()->with('status', $message);
    }
}

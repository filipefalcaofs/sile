<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\StorePropertyTypeRequest;
use App\Http\Requests\Gestao\UpdatePropertyTypeRequest;
use App\Models\PropertyType;
use App\Services\Risco\TipoImovel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manutenção dos tipos de imóvel reconhecidos do REGIN no console SEDUR —
 * dado administrável (CRUD), não registry de código. drives_rule=true injeta
 * o gatilho dados_do_processo e derruba o processo do expresso para análise;
 * aliases normalizados permitem múltiplas grafias entrar no motor. Auditoria
 * automática via HasAuditoria no model (RN-002).
 */
class PropertyTypeController extends Controller
{
    /**
     * Colunas ordenáveis e tamanhos de página aceitos via request —
     * whitelists técnicas de proteção.
     */
    private const SORTABLE_COLUMNS = ['code', 'label'];

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

        $propertyTypes = PropertyType::query()
            ->with('aliases')
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request) {
                $term = (string) $request->string('search')->trim();

                $query->where(function ($inner) use ($term) {
                    $inner->whereLike('code', "%{$term}%", caseSensitive: false)
                        ->orWhereLike('label', "%{$term}%", caseSensitive: false);
                });
            })
            ->when(in_array($active, ['0', '1'], true), fn ($query) => $query->where('active', $active === '1'))
            ->orderBy($sort, $direction)
            ->orderBy('code')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (PropertyType $propertyType) => [
                'id' => $propertyType->id,
                'code' => $propertyType->code,
                'label' => $propertyType->label,
                'drives_rule' => $propertyType->drives_rule,
                'active' => $propertyType->active,
                'aliases' => $propertyType->aliases->pluck('alias')->all(),
            ]);

        return Inertia::render('gestao/tipos-imovel/index', [
            'propertyTypes' => $propertyTypes,
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

    public function store(StorePropertyTypeRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $validated = $request->validated();
            $aliases = $validated['aliases'] ?? [];
            unset($validated['aliases']);

            $propertyType = PropertyType::create($validated);

            $this->syncAliases($propertyType, $aliases);
        });

        return back()->with('status', 'Tipo de imóvel cadastrado com sucesso.');
    }

    /**
     * Atualiza rótulo, drives_rule e situação. O code é imutável (não consta
     * no UpdateRequest, logo o valor enviado é descartado — padrão CPF/CNAE).
     */
    public function update(UpdatePropertyTypeRequest $request, PropertyType $propertyType): RedirectResponse
    {
        DB::transaction(function () use ($request, $propertyType) {
            $validated = $request->validated();
            $aliases = $validated['aliases'] ?? [];
            unset($validated['aliases']);

            $propertyType->update($validated);

            $this->syncAliases($propertyType, $aliases);
        });

        return back()->with('status', 'Tipo de imóvel atualizado com sucesso.');
    }

    /**
     * Liga/desliga o tipo de imóvel. NUNCA exclui o registro: um tipo já
     * processado pelo motor precisa do histórico preservado — a desativação
     * apenas o retira do catálogo ativo.
     */
    public function toggleActivation(PropertyType $propertyType): RedirectResponse
    {
        $propertyType->update(['active' => ! $propertyType->active]);

        $message = $propertyType->active
            ? 'Tipo de imóvel reativado.'
            : 'Tipo de imóvel desativado.';

        return back()->with('status', $message);
    }

    /**
     * Sincroniza os aliases do tipo: remove os ausentes do payload e garante
     * os presentes via firstOrCreate. A normalização ocorre no model (Attribute
     * setter), portanto os valores chegam crus e saem normalizados. Aliases
     * repetidos pós-normalização são naturalmente deduplicados pelo unique do
     * banco — inserimos apenas os distintos para evitar a exception.
     *
     * @param  list<string>  $rawAliases
     */
    private function syncAliases(PropertyType $propertyType, array $rawAliases): void
    {
        $normalized = collect($rawAliases)
            ->map(fn (string $a) => TipoImovel::normalize($a))
            ->unique()
            ->values()
            ->all();

        $propertyType->aliases()->whereNotIn('alias', $normalized)->delete();

        foreach ($normalized as $alias) {
            $propertyType->aliases()->firstOrCreate(['alias' => $alias]);
        }
    }
}

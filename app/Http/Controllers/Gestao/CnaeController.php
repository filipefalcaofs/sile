<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\StoreCnaeRequest;
use App\Http\Requests\Gestao\UpdateCnaeRequest;
use App\Models\Cnae;
use App\Services\Relatorios\Export\ReportExporter;
use App\Services\Relatorios\Export\Sources\CnaesReportSource;
use App\Services\Relatorios\ReportFilters;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class CnaeController extends Controller
{
    /**
     * Colunas ordenáveis e tamanhos de página aceitos via request —
     * whitelists técnicas de proteção; o padrão de página é parâmetro.
     */
    private const SORTABLE_COLUMNS = ['code', 'description'];

    private const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    /**
     * Listagem com busca por código (prefixo, dígitos) ou denominação
     * (HU-011 CA-01), filtro de situação, ordenação e itens por página
     * server-driven (Fase 2.4). Paginação parametrizada — nenhum valor
     * de negócio hardcoded.
     */
    public function index(Request $request): Response|HttpResponse
    {
        // HU-131/RN-009: com ?formato=, exporta o conjunto filtrado da tela pelo
        // contrato único — sem rota nova, sem reimplementar export.
        if (in_array($request->string('formato')->lower()->toString(), ['csv', 'xlsx', 'pdf'], true)) {
            return app(ReportExporter::class)->export(
                app(CnaesReportSource::class),
                ReportFilters::fromArray($request->only(['search', 'active', 'sort', 'direction'])),
                $request->string('formato')->lower()->toString(),
                $request->user(),
            );
        }

        $sort = $request->string('sort')->toString();
        $sort = in_array($sort, self::SORTABLE_COLUMNS, true) ? $sort : 'code';

        $direction = $request->string('direction')->toString();
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'asc';

        $perPage = (int) $request->input('per_page');
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true)
            ? $perPage
            : (int) Settings::get('ui.cnaes.per_page', 15);

        $active = $request->string('active')->toString();

        $cnaes = Cnae::query()
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request) {
                $term = (string) $request->string('search')->trim();
                $digits = preg_replace('/\D/', '', $term);

                $query->where(function ($inner) use ($term, $digits) {
                    if ($digits !== '') {
                        $inner->where('code', 'like', "{$digits}%");
                    }

                    // whereLike sem case: LIKE do PostgreSQL é case-sensitive.
                    $inner->orWhereLike('description', "%{$term}%", caseSensitive: false);
                });
            })
            ->when(in_array($active, ['0', '1'], true), fn ($query) => $query->where('active', $active === '1'))
            ->orderBy($sort, $direction)
            ->orderBy('code')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Cnae $cnae) => [
                'id' => $cnae->id,
                'code' => $cnae->code,
                'formatted_code' => $cnae->formatted_code,
                'description' => $cnae->description,
                'active' => $cnae->active,
                'class_code' => $cnae->class_code,
            ]);

        return Inertia::render('gestao/cnaes/index', [
            'cnaes' => $cnaes,
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

    public function store(StoreCnaeRequest $request): RedirectResponse
    {
        Cnae::create($request->validated());

        return back()->with('status', 'CNAE cadastrado com sucesso.');
    }

    public function update(UpdateCnaeRequest $request, Cnae $cnae): RedirectResponse
    {
        $cnae->update($request->validated());

        return back()->with('status', 'CNAE atualizado com sucesso.');
    }

    /**
     * Exclusão física bloqueada quando o CNAE está vinculado a empresas
     * (Fase 3): verificação amigável na aplicação + restrictOnDelete como
     * defesa no banco (company_cnae.cnae_id).
     */
    public function destroy(Cnae $cnae): RedirectResponse
    {
        if ($cnae->companies()->exists()) {
            return back()->with('error', 'CNAE vinculado a empresas não pode ser excluído.');
        }

        $cnae->delete();

        return back()->with('status', 'CNAE excluído.');
    }
}

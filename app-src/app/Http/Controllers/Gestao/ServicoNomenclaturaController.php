<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\StoreServicoNomenclaturaRequest;
use App\Http\Requests\Gestao\UpdateServicoNomenclaturaRequest;
use App\Models\ServicoNomenclatura;
use App\Models\ViabilityRequestEvento;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * De x Para dos nomes dos serviços enviados pela JUCEB: a SEDUR define o nome
 * exibido no Viabiliza e se o serviço aparece quando vem junto com outros.
 * Não há exclusão; o evento novo entra sozinho com De = Para.
 */
class ServicoNomenclaturaController extends Controller
{
    private const SORTABLE_COLUMNS = ['de', 'para'];

    private const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    private const DEFAULT_PER_PAGE = 15;

    public function index(Request $request): Response
    {
        $sort = $request->string('sort')->toString();
        $sort = in_array($sort, self::SORTABLE_COLUMNS, true) ? $sort : 'de';

        $direction = $request->string('direction')->toString();
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'asc';

        $perPage = (int) $request->input('per_page');
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::DEFAULT_PER_PAGE;

        $exibir = $request->string('exibir')->toString();

        $nomenclaturas = ServicoNomenclatura::query()
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request) {
                $term = (string) $request->string('search')->trim();

                $query->where(function ($inner) use ($term) {
                    $inner->whereLike('de', "%{$term}%", caseSensitive: false)
                        ->orWhereLike('para', "%{$term}%", caseSensitive: false)
                        ->orWhereLike('chave', '%'.ServicoNomenclatura::chaveDe($term).'%');
                });
            })
            ->when(in_array($exibir, ['0', '1'], true), fn ($query) => $query->where('exibir', $exibir === '1'))
            ->orderBy($sort, $direction)
            ->orderBy('de')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (ServicoNomenclatura $linha) => [
                'id' => $linha->id,
                'de' => $linha->de,
                'para' => $linha->para,
                'exibir' => $linha->exibir,
            ]);

        return Inertia::render('gestao/servicos-nomenclatura/index', [
            'nomenclaturas' => $nomenclaturas,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
                'exibir' => in_array($exibir, ['0', '1'], true) ? $exibir : '',
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'sugestoesDe' => ViabilityRequestEvento::query()->distinct()->orderBy('nome')->pluck('nome')->all(),
        ]);
    }

    public function store(StoreServicoNomenclaturaRequest $request): RedirectResponse
    {
        try {
            ServicoNomenclatura::create($request->validated());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['de' => 'Já existe uma linha para este serviço.']);
        }

        return back()->with('status', 'Nomenclatura cadastrada com sucesso.');
    }

    public function update(UpdateServicoNomenclaturaRequest $request, ServicoNomenclatura $servicoNomenclatura): RedirectResponse
    {
        $servicoNomenclatura->update($request->validated());

        return back()->with('status', 'Nomenclatura atualizada com sucesso.');
    }

    public function toggleExibir(ServicoNomenclatura $servicoNomenclatura): RedirectResponse
    {
        $servicoNomenclatura->update(['exibir' => ! $servicoNomenclatura->exibir]);

        return back()->with('status', $servicoNomenclatura->exibir ? 'Serviço passa a ser exibido.' : 'Serviço passa a ficar oculto.');
    }
}

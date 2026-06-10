<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\StoreCnaeRequest;
use App\Http\Requests\Gestao\UpdateCnaeRequest;
use App\Models\Cnae;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CnaeController extends Controller
{
    /**
     * Listagem com busca por código (prefixo, dígitos) ou denominação
     * (HU-011 CA-01). Paginação parametrizada — nenhum valor hardcoded.
     */
    public function index(Request $request): Response
    {
        $cnaes = Cnae::query()
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request) {
                $term = (string) $request->string('search')->trim();
                $digits = preg_replace('/\D/', '', $term);

                $query->where(function ($inner) use ($term, $digits) {
                    if ($digits !== '') {
                        $inner->where('code', 'like', "{$digits}%");
                    }

                    $inner->orWhere('description', 'like', "%{$term}%");
                });
            })
            ->orderBy('code')
            ->paginate((int) Settings::get('ui.cnaes.per_page', 15))
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
            'filters' => ['search' => $request->string('search')->toString()],
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
     * Exclusão física permitida nesta fase — o bloqueio por vínculo
     * empresarial entra com o cadastro de empresas (Fase 3).
     */
    public function destroy(Cnae $cnae): RedirectResponse
    {
        $cnae->delete();

        return back()->with('status', 'CNAE excluído.');
    }
}

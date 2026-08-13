<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\HolidayRequest;
use App\Models\Holiday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD administrável do calendário de feriados (HU-137) — dado versionado que o
 * BusinessDeadlineCalculator desconta ao medir duração em tempo útil (HU-129). O
 * administrador cria/edita/ativa-inativa o feriado pela retaguarda; a data é
 * única (sem feriado duplicado) e a inativação preserva o histórico (RN-004 —
 * não há destroy). Listagem server-driven espelhando o SectorController; a
 * auditoria (RN-002) é automática via HasAuditoria do model (created/updated).
 * Gated por manter-parametros (reuso — como setores/textos-padrão; sem permissão
 * nova); o 403 é auditado no ponto único (bootstrap/app.php). Telas em 15-13/15-14.
 */
class HolidayController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    private const DEFAULT_PER_PAGE = 15;

    public function index(Request $request): Response
    {
        $perPage = (int) $request->input('per_page');
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::DEFAULT_PER_PAGE;

        $active = $request->string('active')->toString();

        $holidays = Holiday::query()
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request) {
                $term = (string) $request->string('search')->trim();

                $query->whereLike('name', "%{$term}%", caseSensitive: false);
            })
            ->when(in_array($active, ['0', '1'], true), fn ($query) => $query->where('active', $active === '1'))
            ->orderByDesc('date')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Holiday $holiday): array => [
                'id' => $holiday->id,
                'date' => $holiday->date?->toDateString(),
                'name' => $holiday->name,
                'recurring_annually' => $holiday->recurring_annually,
                'active' => $holiday->active,
            ]);

        return Inertia::render('gestao/feriados/index', [
            'holidays' => $holidays,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'per_page' => $perPage,
                'active' => in_array($active, ['0', '1'], true) ? $active : '',
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    public function store(HolidayRequest $request): RedirectResponse
    {
        Holiday::create($request->validated());

        return back()->with('status', 'Feriado cadastrado com sucesso.');
    }

    public function update(HolidayRequest $request, Holiday $holiday): RedirectResponse
    {
        $holiday->update($request->validated());

        return back()->with('status', 'Feriado atualizado com sucesso.');
    }

    /**
     * Liga/desliga o feriado. NUNCA exclui (RN-004): inativar preserva o
     * histórico e tira o feriado do cálculo de dias úteis (HU-129) sem perder o
     * registro auditado.
     */
    public function toggleActivation(Holiday $holiday): RedirectResponse
    {
        $holiday->update(['active' => ! $holiday->active]);

        return back()->with('status', $holiday->active
            ? 'Feriado reativado.'
            : 'Feriado inativado.');
    }
}

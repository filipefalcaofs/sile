<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\ViabilityRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\SectorRequest;
use App\Http\Resources\SectorResource;
use App\Models\Sector;
use App\Support\Audit\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manutenção dos setores da SEDUR (HU-138) — a "caixa de análise" da
 * distribuição (pré-requisito de 10-07/HU-144). Dado administrável (CRUD), não
 * registry de código: o gestor/admin cria/edita o setor e vincula analistas
 * (N:N — um analista cobre vários setores, RN-005). RN-004: setor com processos
 * em aberto NÃO é excluído — só inativado (toggleActivation preserva histórico e
 * vínculo; não há destroy). Listagem server-driven espelhando o
 * ViabilityServiceTypeController; auditoria explícita (RN-002) via AuditService,
 * inclusive do vínculo (que não entra no diff de model). Telas de console: 10-17.
 */
class SectorController extends Controller
{
    /**
     * Colunas ordenáveis aceitas via request — whitelist técnica de proteção.
     */
    private const SORTABLE_COLUMNS = ['name'];

    private const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    private const DEFAULT_PER_PAGE = 15;

    public function __construct(private AuditService $audit) {}

    public function index(Request $request): Response
    {
        $sort = $request->string('sort')->toString();
        $sort = in_array($sort, self::SORTABLE_COLUMNS, true) ? $sort : 'name';

        $direction = $request->string('direction')->toString();
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'asc';

        $perPage = (int) $request->input('per_page');
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::DEFAULT_PER_PAGE;

        $active = $request->string('active')->toString();

        $sectors = Sector::query()
            ->withCount(['analysts', 'requests'])
            ->with('analysts:id,name')
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request) {
                $term = (string) $request->string('search')->trim();

                // whereLike sem case: LIKE do PostgreSQL é case-sensitive.
                $query->whereLike('name', "%{$term}%", caseSensitive: false);
            })
            ->when(in_array($active, ['0', '1'], true), fn ($query) => $query->where('active', $active === '1'))
            ->orderBy($sort, $direction)
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Sector $sector) => (new SectorResource($sector))->resolve());

        return Inertia::render('gestao/setores/index', [
            'sectors' => $sectors,
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

    public function store(SectorRequest $request): RedirectResponse
    {
        $sector = Sector::create($request->validated());

        $this->audit->log('setores', 'criar', 'Setor cadastrado', [
            'name' => $sector->name,
            'active' => $sector->active,
        ], $sector);

        return back()->with('status', 'Setor cadastrado com sucesso.');
    }

    public function update(SectorRequest $request, Sector $sector): RedirectResponse
    {
        $sector->update($request->validated());

        $this->audit->log('setores', 'atualizar', 'Setor atualizado', [
            'name' => $sector->name,
            'active' => $sector->active,
        ], $sector);

        return back()->with('status', 'Setor atualizado com sucesso.');
    }

    /**
     * Liga/desliga o setor. NUNCA exclui (RN-004): um setor com processos em
     * aberto continua consultável e mantém o vínculo — apenas deixa de receber
     * novas distribuições. Ao inativar com processos em aberto, devolve um aviso
     * (a redistribuição é operação separada, na distribuição 10-07) sem bloquear.
     */
    public function toggleActivation(Sector $sector): RedirectResponse
    {
        $openCount = 0;

        if ($sector->active) {
            $openCount = $sector->requests()
                ->whereIn('status', [
                    ViabilityRequestStatus::EmAnalise->value,
                    ViabilityRequestStatus::EmPendencia->value,
                ])
                ->count();
        }

        $sector->update(['active' => ! $sector->active]);

        $this->audit->log('setores', 'ativacao', 'Situação do setor alterada', [
            'active' => $sector->active,
            'processos_em_aberto' => $openCount,
        ], $sector);

        $response = back()->with('status', $sector->active
            ? 'Setor reativado.'
            : 'Setor inativado.');

        if (! $sector->active && $openCount > 0) {
            $response->with('warning', "Setor inativado com {$openCount} processo(s) em aberto. Redistribua-os pela distribuição — o histórico foi preservado.");
        }

        return $response;
    }

    /**
     * Sincroniza o conjunto EXATO de analistas do setor (intenção do gestor =
     * conjunto marcado) em transação, com auditoria explícita antes/depois — o
     * vínculo N:N não entra no diff de model. Sincronizar aqui NÃO remove o
     * analista de outros setores (RN-005: um analista cobre vários setores).
     */
    public function syncAnalysts(Request $request, Sector $sector): RedirectResponse
    {
        $validated = $request->validate([
            'analyst_ids' => ['array'],
            'analyst_ids.*' => [Rule::exists('users', 'id')],
        ]);

        $analystIds = $validated['analyst_ids'] ?? [];

        DB::transaction(function () use ($sector, $analystIds) {
            // Qualifica users.id: o relacionamento N:N junta users e sector_user
            // (ambas têm id), então a coluna precisa do prefixo da tabela.
            $before = $sector->analysts()->orderBy('users.id')->pluck('users.id')->all();

            $sector->analysts()->sync($analystIds);

            $after = $sector->analysts()->orderBy('users.id')->pluck('users.id')->all();

            $this->audit->log('setores', 'vincular-analistas', 'Analistas do setor atualizados', [
                'sector_id' => $sector->id,
                'antes' => $before,
                'depois' => $after,
            ], $sector);
        });

        return back()->with('status', 'Analistas vinculados atualizados.');
    }
}

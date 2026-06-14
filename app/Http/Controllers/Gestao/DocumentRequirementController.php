<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\StoreDocumentRequirementRequest;
use App\Http\Requests\Gestao\UpdateDocumentRequirementRequest;
use App\Models\Cnae;
use App\Models\DocumentRequirement;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD dos requisitos documentais (modelo "Requisito" do SIGVISA — HU-067) e do
 * vínculo N:N com CNAEs: a obrigatoriedade documental por CNAE vira DADO
 * administrável (HU-014), consumido pelo resolver da validação documental
 * (08-08). Atrás da permissão manter-requisitos-documentais (gate na rota); o
 * requisito é auditado via HasAuditoria e o vínculo com antes/depois explícito
 * (RN-002). A tabela por-CNAE nasce vazia — carga oficial pendente SEDUR.
 */
class DocumentRequirementController extends Controller
{
    /**
     * Colunas ordenáveis aceitas via request — whitelist técnica de proteção;
     * o padrão de página é parâmetro (HU-014).
     */
    private const SORTABLE_COLUMNS = ['code', 'name'];

    private const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    public function __construct(private AuditService $audit) {}

    /**
     * Listagem server-driven (Fase 2.4): busca por código/nome, filtro de
     * situação, ordenação e itens por página parametrizados. Cada requisito
     * carrega os CNAEs vinculados (chips na tela de manutenção).
     */
    public function index(Request $request): Response
    {
        $sort = $request->string('sort')->toString();
        $sort = in_array($sort, self::SORTABLE_COLUMNS, true) ? $sort : 'code';

        $direction = $request->string('direction')->toString();
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'asc';

        $perPage = (int) $request->input('per_page');
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true)
            ? $perPage
            : (int) Settings::get('ui.requisitos_documentais.per_page', 15);

        $active = $request->string('active')->toString();

        $requirements = DocumentRequirement::query()
            ->with('cnaes')
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request) {
                $term = (string) $request->string('search')->trim();

                // whereLike sem case: LIKE do PostgreSQL é case-sensitive.
                $query->where(function ($inner) use ($term) {
                    $inner->whereLike('code', "%{$term}%", caseSensitive: false)
                        ->orWhereLike('name', "%{$term}%", caseSensitive: false);
                });
            })
            ->when(in_array($active, ['0', '1'], true), fn ($query) => $query->where('active', $active === '1'))
            ->orderBy($sort, $direction)
            ->orderBy('code')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (DocumentRequirement $requirement) => [
                'id' => $requirement->id,
                'code' => $requirement->code,
                'name' => $requirement->name,
                'description' => $requirement->description,
                'required' => $requirement->required,
                'active' => $requirement->active,
                'validation_instructions' => $requirement->validation_instructions,
                'cnaes' => $requirement->cnaes->map(fn (Cnae $cnae) => [
                    'id' => $cnae->id,
                    'formatted_code' => $cnae->formatted_code,
                    'description' => $cnae->description,
                ])->all(),
            ]);

        return Inertia::render('gestao/requisitos-documentais/index', [
            'requirements' => $requirements,
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

    public function store(StoreDocumentRequirementRequest $request): RedirectResponse
    {
        DocumentRequirement::create([...$request->validated(), 'active' => true]);

        return back()->with('status', 'Requisito documental cadastrado com sucesso.');
    }

    /**
     * Atualiza os campos editáveis; o código permanece imutável (não está nas
     * regras do FormRequest) e a auditoria do updated vem do HasAuditoria.
     */
    public function update(UpdateDocumentRequirementRequest $request, DocumentRequirement $requirement): RedirectResponse
    {
        $requirement->update($request->validated());

        return back()->with('status', 'Requisito documental atualizado com sucesso.');
    }

    /**
     * Alterna a situação preservando o registro e o histórico (RN-002): nunca
     * exclui. O updated é auditado pelo HasAuditoria.
     */
    public function toggle(DocumentRequirement $requirement): RedirectResponse
    {
        $requirement->update(['active' => ! $requirement->active]);

        return back()->with('status', $requirement->active
            ? 'Requisito documental reativado.'
            : 'Requisito documental desativado.');
    }

    /**
     * Sincroniza o conjunto EXATO de CNAEs do requisito (intenção do admin =
     * conjunto marcado, padrão syncSecondaries [03-06]) em transação, com
     * auditoria explícita antes/depois — relações não entram no diff do
     * HasAuditoria. Só CNAEs ativos podem ser vinculados (seleção manual).
     */
    public function syncCnaes(Request $request, DocumentRequirement $requirement): RedirectResponse
    {
        $validated = $request->validate([
            'cnae_ids' => ['array'],
            'cnae_ids.*' => [Rule::exists('cnaes', 'id')->where('active', true)],
        ]);

        $cnaeIds = $validated['cnae_ids'] ?? [];

        DB::transaction(function () use ($requirement, $cnaeIds) {
            $before = $requirement->cnaes()->orderBy('code')->pluck('code')->all();

            $requirement->cnaes()->sync($cnaeIds);

            $after = Cnae::query()->whereIn('id', $cnaeIds)->orderBy('code')->pluck('code')->all();

            $this->audit->log('solicitacoes', 'requisito-cnaes', 'CNAEs do requisito documental atualizados', [
                'requisito_id' => $requirement->id,
                'requisito_code' => $requirement->code,
                'antes' => $before,
                'depois' => $after,
            ], $requirement);
        });

        return back()->with('status', 'CNAEs vinculados atualizados.');
    }
}

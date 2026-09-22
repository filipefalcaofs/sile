<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\StandardTextRequest;
use App\Http\Resources\StandardTextResource;
use App\Models\StandardText;
use App\Support\Audit\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manutenção da biblioteca de textos-padrão do parecer (HU-085) — trechos pré-
 * aprovados que o analista insere ao redigir a fundamentação. Dado versionado
 * (não código): editar o CONTEÚDO incrementa a versão (RN-005, rastreabilidade);
 * editar só metadados (categoria/situação) preserva a versão. Inativar não
 * exclui (preserva histórico). Atrás de manter-parametros — reuso da permissão
 * de admin de configuração (sem 6ª permissão; a leitura da lista ativa pelo
 * parecer fica sob analisar-processos em 10-09; pendência SEDUR: a coordenação
 * pode exigir permissão própria). Listagem server-driven espelhando o
 * ViabilityServiceTypeController; auditoria explícita (RN-002). Telas: 10-17.
 */
class StandardTextController extends Controller
{
    /**
     * Colunas ordenáveis aceitas via request — whitelist técnica de proteção.
     */
    private const SORTABLE_COLUMNS = ['category', 'version'];

    private const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    private const DEFAULT_PER_PAGE = 15;

    public function __construct(private AuditService $audit) {}

    public function index(Request $request): Response
    {
        $sort = $request->string('sort')->toString();
        $sort = in_array($sort, self::SORTABLE_COLUMNS, true) ? $sort : 'category';

        $direction = $request->string('direction')->toString();
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'asc';

        $perPage = (int) $request->input('per_page');
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::DEFAULT_PER_PAGE;

        $active = $request->string('active')->toString();
        $category = (string) $request->string('category')->trim();

        $standardTexts = StandardText::query()
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request) {
                $term = (string) $request->string('search')->trim();

                // whereLike sem case: LIKE do PostgreSQL é case-sensitive.
                $query->whereLike('content', "%{$term}%", caseSensitive: false);
            })
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->when(in_array($active, ['0', '1'], true), fn ($query) => $query->where('active', $active === '1'))
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (StandardText $text) => (new StandardTextResource($text))->resolve());

        return Inertia::render('gestao/textos-padrao/index', [
            'standardTexts' => $standardTexts,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'category' => $category,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
                'active' => in_array($active, ['0', '1'], true) ? $active : '',
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    public function store(StandardTextRequest $request): RedirectResponse
    {
        $text = StandardText::create([...$request->validated(), 'version' => 1]);

        $this->audit->log('textos-padrao', 'criar', 'Texto-padrão cadastrado', [
            'category' => $text->category,
            'active' => $text->active,
            'version' => $text->version,
        ], $text);

        return back()->with('status', 'Texto-padrão cadastrado com sucesso.');
    }

    /**
     * Atualiza o texto-padrão. Alterar o CONTEÚDO incrementa a versão (RN-005);
     * editar só categoria/situação preserva a versão vigente.
     */
    public function update(StandardTextRequest $request, StandardText $standardText): RedirectResponse
    {
        $data = $request->validated();

        $contentChanged = $data['content'] !== $standardText->content;

        if ($contentChanged) {
            $data['version'] = $standardText->version + 1;
        }

        $standardText->update($data);

        $this->audit->log('textos-padrao', 'atualizar', 'Texto-padrão atualizado', [
            'category' => $standardText->category,
            'active' => $standardText->active,
            'version' => $standardText->version,
            'content_changed' => $contentChanged,
        ], $standardText);

        return back()->with('status', 'Texto-padrão atualizado com sucesso.');
    }

    /**
     * Liga/desliga o texto-padrão. NUNCA exclui: um texto inativo sai da
     * biblioteca disponível ao analista mas preserva o histórico (e a versão).
     */
    public function toggleActivation(StandardText $standardText): RedirectResponse
    {
        $standardText->update(['active' => ! $standardText->active]);

        $this->audit->log('textos-padrao', 'ativacao', 'Situação do texto-padrão alterada', [
            'active' => $standardText->active,
            'version' => $standardText->version,
        ], $standardText);

        return back()->with('status', $standardText->active
            ? 'Texto-padrão reativado.'
            : 'Texto-padrão desativado.');
    }
}

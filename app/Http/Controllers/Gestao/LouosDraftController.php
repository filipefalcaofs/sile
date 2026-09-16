<?php

namespace App\Http\Controllers\Gestao;

use App\Exceptions\FourEyesViolationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\ImportLouosCsvRequest;
use App\Http\Requests\Gestao\OpenLouosDraftRequest;
use App\Http\Requests\Gestao\StoreLouosLinhaRequest;
use App\Models\LouosQuadro10Permissao;
use App\Models\LouosQuadro11CondicaoVia;
use App\Models\LouosQuadro7Faixa;
use App\Models\RuleVersion;
use App\Models\User;
use App\Services\Louos\LouosDraftService;
use App\Support\Settings;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Endpoints HTTP do rascunho editável dos Quadros da LOUOS (HU-046).
 * A vigente nunca é alterada diretamente; toda edição ocorre no rascunho
 * coexistente e a publicação exige quatro olhos via LouosDraftService.
 */
class LouosDraftController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    private const QUADRO_LABELS = [
        'quadro7' => 'Quadro 7 — Enquadramento por área',
        'quadro10' => 'Quadro 10 — Permissões por zona',
        'quadro11a' => 'Quadro 11A — Condições por classe de via',
    ];

    private const CSV_HEADERS = [
        'quadro7' => 'cnae,grupo,subgrupo,area_min,area_max,observacao',
        'quadro10' => 'zona,grupo_uso,subgrupo,permissao,condicionante_ref,base_legal',
        'quadro11a' => 'classe_via,grupo_uso,condicoes,base_legal',
    ];

    private const CSV_EXEMPLOS = [
        'quadro7' => '4712-1/00,nR1,nR1-02,0,350,Minimercado',
        'quadro10' => 'ZPR 1,nR1,nR1-01,permitido,,',
        'quadro11a' => 'Arterial I,nR1-01,"Estacionamento nos fundos; acesso único",Art. 92',
    ];

    public function __construct(private LouosDraftService $service) {}

    public function show(Request $request): Response
    {
        $quadro = $request->string('quadro')->toString();

        if (! array_key_exists($quadro, LouosDraftService::QUADRO_DOMAINS)) {
            $quadro = 'quadro7';
        }

        $domain = LouosDraftService::QUADRO_DOMAINS[$quadro];
        $draft = $this->service->rascunhoAberto($domain);

        if ($draft === null) {
            return Inertia::render('gestao/louos/rascunho', [
                'quadro' => $quadro,
                'quadroLabel' => self::QUADRO_LABELS[$quadro],
                'draft' => null,
                'itens' => null,
                'diff' => null,
                'canPublish' => false,
            ]);
        }

        $perPage = (int) $request->input('per_page');
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true)
            ? $perPage
            : (int) Settings::get('ui.cnaes.per_page', 15);

        $search = $request->string('search')->trim()->toString();

        $autor = User::query()->find($draft->created_by);

        return Inertia::render('gestao/louos/rascunho', [
            'quadro' => $quadro,
            'quadroLabel' => self::QUADRO_LABELS[$quadro],
            'draft' => [
                'id' => $draft->id,
                'version' => $draft->version,
                'autor' => [
                    'id' => $draft->created_by,
                    'name' => $autor?->name,
                ],
            ],
            'itens' => $this->listarLinhas($quadro, $draft, $search, $perPage),
            'diff' => $this->service->diff($draft),
            'canPublish' => $request->user()?->id !== $draft->created_by,
        ]);
    }

    public function open(OpenLouosDraftRequest $request): RedirectResponse
    {
        $quadro = $request->validated('quadro');
        $domain = LouosDraftService::QUADRO_DOMAINS[$quadro];

        $this->service->abrirOuRetomar(
            $domain,
            $request->validated('version'),
            (int) $request->user()->id,
        );

        return redirect()->route('gestao.louos.rascunho.show', ['quadro' => $quadro]);
    }

    public function storeLinha(StoreLouosLinhaRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $quadro = $validated['quadro'];
        $domain = LouosDraftService::QUADRO_DOMAINS[$quadro];

        $draft = $this->service->rascunhoAberto($domain);

        if ($draft === null) {
            abort(422, 'Nenhum rascunho aberto para este Quadro.');
        }

        $dados = array_diff_key($validated, ['quadro' => true]);
        $linha = $this->service->inserirLinha($draft, $dados);

        return back()->with('status', "Linha #{$linha->id} inserida no rascunho.");
    }

    public function updateLinha(StoreLouosLinhaRequest $request, int $linha): RedirectResponse
    {
        $validated = $request->validated();
        $quadro = $validated['quadro'];
        $domain = LouosDraftService::QUADRO_DOMAINS[$quadro];

        $draft = $this->service->rascunhoAberto($domain);

        if ($draft === null) {
            abort(422, 'Nenhum rascunho aberto para este Quadro.');
        }

        $dados = array_diff_key($validated, ['quadro' => true]);
        $this->service->alterarLinha($draft, $linha, $dados);

        return back()->with('status', "Linha #{$linha} atualizada no rascunho.");
    }

    public function destroyLinha(Request $request, int $linha): RedirectResponse
    {
        $quadro = (string) $request->input('quadro', '');

        if (! array_key_exists($quadro, LouosDraftService::QUADRO_DOMAINS)) {
            abort(422, 'Quadro inválido ou ausente.');
        }

        $domain = LouosDraftService::QUADRO_DOMAINS[$quadro];
        $draft = $this->service->rascunhoAberto($domain);

        if ($draft === null) {
            abort(422, 'Nenhum rascunho aberto para este Quadro.');
        }

        $this->service->excluirLinha($draft, $linha);

        return back()->with('status', "Linha #{$linha} excluída do rascunho.");
    }

    public function importar(ImportLouosCsvRequest $request): RedirectResponse
    {
        $quadro = $request->validated('quadro');
        $domain = LouosDraftService::QUADRO_DOMAINS[$quadro];

        $draft = $this->service->rascunhoAberto($domain);

        if ($draft === null) {
            abort(422, 'Nenhum rascunho aberto para este Quadro.');
        }

        /** @var UploadedFile $arquivo */
        $arquivo = $request->file('arquivo');
        $nomeOriginal = $arquivo->getClientOriginalName();

        $caminho = $arquivo->store('temp', 'local');
        $pathAbsoluto = Storage::disk('local')->path((string) $caminho);

        try {
            $relatorio = $this->service->importarCsv($draft, $pathAbsoluto);
        } finally {
            Storage::disk('local')->delete((string) $caminho);
        }

        $relatorio['arquivo'] = $nomeOriginal;

        return back()->with('importacao', $relatorio);
    }

    public function publish(Request $request): RedirectResponse
    {
        $quadro = (string) $request->input('quadro', '');

        if (! array_key_exists($quadro, LouosDraftService::QUADRO_DOMAINS)) {
            abort(422, 'Quadro inválido ou ausente.');
        }

        $domain = LouosDraftService::QUADRO_DOMAINS[$quadro];
        $draft = $this->service->rascunhoAberto($domain);

        if ($draft === null) {
            return back()->with('error', 'Nenhum rascunho aberto para publicação.');
        }

        try {
            $publicado = $this->service->publicar($draft, (int) $request->user()->id);
        } catch (FourEyesViolationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('gestao.louos.index')
            ->with('status', "Rascunho {$publicado->version} publicado com sucesso.");
    }

    public function discard(Request $request): RedirectResponse
    {
        $quadro = (string) $request->input('quadro', '');

        if (! array_key_exists($quadro, LouosDraftService::QUADRO_DOMAINS)) {
            abort(422, 'Quadro inválido ou ausente.');
        }

        $domain = LouosDraftService::QUADRO_DOMAINS[$quadro];
        $draft = $this->service->rascunhoAberto($domain);

        if ($draft === null) {
            return redirect()->route('gestao.louos.index')
                ->with('status', 'Nenhum rascunho aberto para descartar.');
        }

        $version = $draft->version;
        $this->service->descartar($draft);

        return redirect()->route('gestao.louos.index')
            ->with('status', "Rascunho {$version} descartado.");
    }

    public function modeloCsv(Request $request): StreamedResponse
    {
        $quadro = $request->string('quadro')->toString();

        if (! array_key_exists($quadro, self::CSV_HEADERS)) {
            abort(404, 'Quadro inválido.');
        }

        $cabecalho = self::CSV_HEADERS[$quadro];
        $exemplo = self::CSV_EXEMPLOS[$quadro];
        $conteudo = $cabecalho."\n".$exemplo."\n";

        return response()->streamDownload(
            function () use ($conteudo) {
                echo $conteudo;
            },
            "modelo-{$quadro}.csv",
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    /**
     * Listagem paginada das linhas do rascunho, com busca. Espelha a lógica
     * de `LouosController::listarQuadro` apontando para o rascunho.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function listarLinhas(string $quadro, RuleVersion $draft, string $search, int $perPage): LengthAwarePaginator
    {
        $versionId = $draft->id;

        return match ($quadro) {
            'quadro7' => LouosQuadro7Faixa::query()
                ->where('rule_version_id', $versionId)
                ->when($search !== '', function ($query) use ($search) {
                    $digits = preg_replace('/\D/', '', $search);

                    $query->where(function ($inner) use ($search, $digits) {
                        if ($digits !== '') {
                            $inner->where('cnae_code', 'like', "{$digits}%");
                        }

                        $inner->orWhereLike('grupo', "%{$search}%", caseSensitive: false);
                    });
                })
                ->orderBy('cnae_code')
                ->orderBy('area_min')
                ->paginate($perPage)
                ->withQueryString()
                ->through(fn (LouosQuadro7Faixa $faixa) => [
                    'id' => $faixa->id,
                    'cnae_code' => $faixa->cnae_code,
                    'formatted_code' => (string) preg_replace('/^(\d{4})(\d)(\d{2})$/', '$1-$2/$3', $faixa->cnae_code),
                    'grupo' => $faixa->grupo,
                    'subgrupo' => $faixa->subgrupo,
                    'area_min' => (float) $faixa->area_min,
                    'area_max' => $faixa->area_max === null ? null : (float) $faixa->area_max,
                    'observacao' => $faixa->observacao,
                ]),
            'quadro10' => LouosQuadro10Permissao::query()
                ->where('rule_version_id', $versionId)
                ->when($search !== '', fn ($query) => $query->where(function ($inner) use ($search) {
                    $inner->whereLike('zona', "%{$search}%", caseSensitive: false)
                        ->orWhereLike('grupo_uso', "%{$search}%", caseSensitive: false);
                }))
                ->orderBy('zona')
                ->orderBy('grupo_uso')
                ->paginate($perPage)
                ->withQueryString()
                ->through(fn (LouosQuadro10Permissao $permissao) => [
                    'id' => $permissao->id,
                    'zona' => $permissao->zona,
                    'grupo_uso' => $permissao->grupo_uso,
                    'subgrupo' => $permissao->subgrupo,
                    'permissao' => $permissao->permissao->value,
                    'permissao_label' => $permissao->permissao->label(),
                    'condicionante_ref' => $permissao->condicionante_ref,
                    'base_legal' => $permissao->base_legal,
                ]),
            default => LouosQuadro11CondicaoVia::query()
                ->where('rule_version_id', $versionId)
                ->when($search !== '', fn ($query) => $query->where(function ($inner) use ($search) {
                    $inner->whereLike('classe_via', "%{$search}%", caseSensitive: false)
                        ->orWhereLike('grupo_uso', "%{$search}%", caseSensitive: false);
                }))
                ->orderBy('classe_via')
                ->orderBy('grupo_uso')
                ->paginate($perPage)
                ->withQueryString()
                ->through(fn (LouosQuadro11CondicaoVia $condicao) => [
                    'id' => $condicao->id,
                    'classe_via' => $condicao->classe_via,
                    'grupo_uso' => $condicao->grupo_uso,
                    'condicoes' => $condicao->condicoes,
                    'base_legal' => $condicao->base_legal,
                ]),
        };
    }
}

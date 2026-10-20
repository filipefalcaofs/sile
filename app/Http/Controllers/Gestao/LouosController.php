<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\RuleDomain;
use App\Enums\RuleVersionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\PublishLouosVersionRequest;
use App\Models\DecisionText;
use App\Models\LouosQuadro10Permissao;
use App\Models\LouosQuadro11CondicaoVia;
use App\Models\RuleVersion;
use App\Models\TerritorialRule;
use App\Models\TratamentoCnaeBinding;
use App\Models\TratamentoCnluItem;
use App\Models\TratamentoCondicionante;
use App\Models\TratamentoEnquadramento;
use App\Models\TratamentoNota;
use App\Models\TratamentoPendencia;
use App\Models\TratamentoPergunta;
use App\Models\TratamentoRegra;
use App\Models\TratamentoRegraRamo;
use App\Services\Louos\LouosMaintenanceService;
use App\Services\Rules\RuleVersionService;
use App\Services\Territorial\TerritorialRuleDraftService;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Hub "Motor de regras" do console SEDUR (HU-015..017 / HU-044 + pacote
 * normativo 2026-08-28): uma tela, três abas — Quadros LOUOS (10 e 11A),
 * Enquadramento (planilha de tratamento vigente) e Regras territoriais
 * (RT-01 a RT-05). A consulta lista a versão vigente de cada domínio; a
 * publicação gera uma NOVA versão por quatro olhos, preservando a anterior —
 * nunca edição destrutiva. Gate cross-guard via permission: nas rotas; cada
 * aba re-checa a permissão do seu domínio (consultar-louos para quadros e
 * territoriais, consultar-cnaes para enquadramento).
 */
class LouosController extends Controller
{
    /**
     * Tamanhos de página aceitos (whitelist técnica). O padrão reusa
     * ui.cnaes.per_page (a listagem do console compartilha a preferência, sem
     * inflar o catálogo de parâmetros) — precedente do RiscoController.
     */
    private const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    /**
     * Abas do hub e a permissão exigida por cada uma.
     *
     * @var array<string, string>
     */
    private const ABAS = [
        'quadros' => 'consultar-louos',
        'enquadramento' => 'consultar-cnaes',
        'territoriais' => 'consultar-louos',
    ];

    /**
     * Param `quadro` → domínio de regra versionada do Quadro.
     *
     * @var array<string, RuleDomain>
     */
    private const QUADRO_DOMAINS = [
        'quadro10' => RuleDomain::LouosQuadro10,
        'quadro11a' => RuleDomain::LouosQuadro11a,
    ];

    public function __construct(
        private LouosMaintenanceService $maintenance,
        private RuleVersionService $ruleVersions,
        private AuditService $audit,
        private TerritorialRuleDraftService $territorialDrafts,
    ) {}

    public function index(Request $request): Response
    {
        $aba = $request->string('aba')->toString();

        if (! array_key_exists($aba, self::ABAS)) {
            $aba = 'quadros';
        }

        // Cada aba re-checa a permissão do seu domínio — a rota aceita as duas
        // (consultar-louos|consultar-cnaes) porque o hub é compartilhado.
        abort_unless($request->user()->can(self::ABAS[$aba]), 403);

        $quadro = $request->string('quadro')->toString();

        if (! array_key_exists($quadro, self::QUADRO_DOMAINS)) {
            $quadro = 'quadro10';
        }

        $domain = self::QUADRO_DOMAINS[$quadro];

        $perPage = (int) $request->input('per_page');
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true)
            ? $perPage
            : (int) Settings::get('ui.cnaes.per_page', 15);

        $search = $request->string('search')->trim()->toString();

        $vigente = RuleVersion::vigente($domain)->first();

        $itens = $aba === 'quadros'
            ? $this->listarQuadro($quadro, $vigente, $search, $perPage)
            : null;

        $this->audit->log(
            logName: 'louos',
            event: 'consulta-quadros',
            description: 'Consulta do hub Motor de regras no console',
            properties: [
                'aba' => $aba,
                'quadro' => $quadro,
                'versao' => $vigente?->version,
                'busca' => $search !== '' ? $search : null,
            ],
            result: 'sucesso',
            rulesVersion: $vigente?->version,
        );

        return Inertia::render('gestao/louos/index', [
            'aba' => $aba,
            'quadros' => $this->resumoQuadros(),
            'quadroSelecionado' => $quadro,
            'itens' => $itens,
            'versoes' => $this->historicoVersoes($quadro, $domain),
            'filtros' => [
                'quadro' => $quadro,
                'search' => $search,
                'per_page' => $perPage,
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'urlRascunho' => route('gestao.louos.rascunho.show', ['quadro' => $quadro], false),
            'urlManual' => route('gestao.louos.manual', ['quadro' => $quadro], false),
            'enquadramento' => $aba === 'enquadramento' ? $this->dadosEnquadramento($search, $perPage) : null,
            'territoriais' => $aba === 'territoriais' ? $this->dadosTerritoriais() : null,
        ]);
    }

    /**
     * Dados da aba Enquadramento: a planilha de tratamento vigente (versão,
     * contagens e perguntas) — migrada da tela "Planilha de regras", cuja rota
     * antiga redireciona para cá.
     *
     * @return array{versao: string|null, contagens: array<string, int>, perguntas: array<int, mixed>, pendencias: array<int, mixed>}
     */
    private function dadosEnquadramento(string $search, int $perPage): array
    {
        $vigente = RuleVersion::vigente(RuleDomain::RiscoTratamento)->first();

        $perguntas = $vigente === null
            ? []
            : TratamentoPergunta::query()
                ->where('rule_version_id', $vigente->getKey())
                ->orderBy('numero')
                ->get(['numero', 'texto', 'referencia'])
                ->values()
                ->all();

        $rascunho = RuleVersion::query()
            ->where('domain', RuleDomain::RiscoTratamento->value)
            ->where('status', RuleVersionStatus::Rascunho->value)
            ->latest('id')
            ->first();

        $pendencias = $rascunho === null
            ? []
            : TratamentoPendencia::query()
                ->where('rule_version_id', $rascunho->getKey())
                ->orderBy('regra')
                ->get(['regra', 'motivo'])
                ->values()
                ->all();

        $id = $vigente?->getKey();

        $aguardandoSedur = $id === null
            ? []
            : TratamentoPendencia::query()
                ->where('rule_version_id', $id)
                ->orderBy('regra')
                ->get(['regra', 'motivo'])
                ->values()
                ->all();

        $regras = $id === null
            ? []
            : TratamentoRegra::query()->where('rule_version_id', $id)->orderBy('numero')->get(['numero', 'tratamento', 'referencia'])->values()->all();

        $ramos = $id === null
            ? []
            : TratamentoRegraRamo::query()->where('rule_version_id', $id)->orderBy('regra')->orderBy('id')->get(['regra', 'pergunta', 'resposta', 'faixa', 'tipo_dirige', 'codigo_louos', 'fluxo', 'bloqueia'])->values()->all();

        $condicionantes = $id === null
            ? []
            : TratamentoCondicionante::query()->where('rule_version_id', $id)->orderBy('numero')->get(['numero', 'texto', 'observacao'])->values()->all();

        $cnlu = $id === null
            ? []
            : TratamentoCnluItem::query()->where('rule_version_id', $id)->orderBy('codigo')->get(['codigo', 'texto'])->values()->all();

        $notas = $id === null
            ? []
            : TratamentoNota::query()->where('rule_version_id', $id)->orderBy('chave')->get(['chave', 'texto'])->values()->all();

        $enquadramentos = TratamentoEnquadramento::query()
            ->where('rule_version_id', $id ?? 0)
            ->when($search !== '', fn ($query) => $query->where(function ($inner) use ($search) {
                $inner->whereLike('cnae', "%{$search}%", caseSensitive: false)
                    ->orWhereLike('denominacao', "%{$search}%", caseSensitive: false)
                    ->orWhereLike('codigo_louos', "%{$search}%", caseSensitive: false)
                    ->orWhereLike('denominacao_louos', "%{$search}%", caseSensitive: false)
                    ->orWhereLike('subcategoria', "%{$search}%", caseSensitive: false)
                    ->orWhereLike('enquadramento2', "%{$search}%", caseSensitive: false)
                    ->orWhereLike('enquadramento3', "%{$search}%", caseSensitive: false)
                    ->orWhereLike('risco', "%{$search}%", caseSensitive: false);
            }))
            ->orderBy('cnae')
            ->orderBy('codigo_louos')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (TratamentoEnquadramento $linha) => [
                'cnae' => $linha->cnae,
                'denominacao' => $linha->denominacao,
                'codigo_louos' => $linha->codigo_louos,
                'denominacao_louos' => $linha->denominacao_louos,
                'subcategoria' => $linha->subcategoria,
                'grupo' => $linha->grupo,
                'risco' => $linha->risco,
                'ate_m2' => $linha->ate_m2,
                'enquadramento2' => $linha->enquadramento2,
                'ate_m2_2' => $linha->ate_m2_2,
                'enquadramento3' => $linha->enquadramento3,
                'acima_m2' => $linha->acima_m2,
                'classificacao' => $linha->classificacao,
                'codigo_tll' => $linha->codigo_tll,
                'regra' => $linha->regra,
            ]);

        $enquadramentos->setCollection($enquadramentos->getCollection()->values());

        return [
            'versao' => $vigente?->version,
            'contagens' => [
                'cnaes' => $id === null ? 0 : TratamentoEnquadramento::query()->where('rule_version_id', $id)->distinct()->count('cnae'),
                'enquadramentos' => $id === null ? 0 : TratamentoEnquadramento::query()->where('rule_version_id', $id)->count(),
                'perguntas' => count($perguntas),
                'regras' => count($regras),
                'ramos' => count($ramos),
                'condicionantes' => count($condicionantes),
                'bindings' => $id === null ? 0 : TratamentoCnaeBinding::query()->where('rule_version_id', $id)->count(),
            ],
            'perguntas' => $perguntas,
            'regras' => $regras,
            'ramos' => $ramos,
            'condicionantes' => $condicionantes,
            'cnlu' => $cnlu,
            'notas' => $notas,
            'pendencias' => $aguardandoSedur,
            'enquadramentos' => $enquadramentos,
            'rascunho' => $rascunho === null ? null : [
                'id' => $rascunho->getKey(),
                'versao' => $rascunho->version,
                'arquivo' => $rascunho->source,
                'autor_id' => $rascunho->created_by,
                'pendencias' => $pendencias,
            ],
        ];
    }

    /**
     * Dados da aba Regras territoriais (RT-01 a RT-05): a versão VIGENTE é a
     * referência; com rascunho aberto, a listagem mostra o rascunho (é ele que
     * está sendo editado) com o aviso de quem é o autor — a publicação é
     * quatro olhos (outro mantenedor).
     *
     * @return array<string, mixed>
     */
    private function dadosTerritoriais(): array
    {
        $vigente = RuleVersion::vigente(RuleDomain::Territorial)->first();
        $rascunho = $this->territorialDrafts->rascunhoAtual();
        $exibida = $rascunho ?? $vigente;

        $regras = $exibida === null
            ? []
            : TerritorialRule::query()
                ->where('rule_version_id', $exibida->getKey())
                ->with(['cnaes', 'addresses'])
                ->orderBy('id')
                ->get()
                ->map(fn (TerritorialRule $regra): array => [
                    'id' => $regra->id,
                    'codigo' => $regra->codigo,
                    'titulo' => $regra->titulo,
                    'tipo' => $regra->tipo->value,
                    'tipo_label' => $regra->tipo->label(),
                    'efeito' => $regra->efeito->value,
                    'efeito_label' => $regra->efeito->label(),
                    'cod_log' => $regra->cod_log,
                    'logradouro_nome' => $regra->logradouro_nome,
                    'camada' => $regra->camada,
                    'camada_filtro' => $regra->camada_filtro,
                    'zona_alvo' => $regra->zona_alvo,
                    'motivo_analise' => $regra->motivo_analise,
                    'base_legal' => $regra->base_legal,
                    'decision_text_id' => $regra->decision_text_id,
                    'ativo' => $regra->ativo,
                    'cnaes' => $regra->cnaes->pluck('cnae')->sort()->values()->all(),
                    'addresses' => $regra->addresses->map(fn ($endereco): array => [
                        'rotulo' => $endereco->rotulo,
                        'cod_log' => $endereco->cod_log,
                        'logradouro_nome' => $endereco->logradouro_nome,
                        'numero_porta' => $endereco->numero_porta,
                        'bairro' => $endereco->bairro,
                    ])->values()->all(),
                ])
                ->all();

        return [
            'versao_vigente' => $vigente?->version,
            'versao_exibida' => $exibida?->version,
            'rascunho' => $rascunho === null ? null : [
                'id' => $rascunho->id,
                'version' => $rascunho->version,
                'autor' => $rascunho->author?->name,
                'autor_id' => $rascunho->created_by,
            ],
            'regras' => $regras,
            'versoes' => RuleVersion::query()
                ->where('domain', RuleDomain::Territorial->value)
                ->orderByRaw('case when status = ? then 0 when status = ? then 1 else 2 end', [
                    RuleVersionStatus::Vigente->value,
                    RuleVersionStatus::Rascunho->value,
                ])
                ->orderByDesc('id')
                ->get()
                ->map(fn (RuleVersion $versao): array => [
                    'id' => $versao->id,
                    'version' => $versao->version,
                    'status' => $versao->status->value,
                    'status_label' => $versao->status->label(),
                    'valid_from' => $versao->valid_from?->toDateString(),
                    'valid_to' => $versao->valid_to?->toDateString(),
                    'total' => TerritorialRule::query()->where('rule_version_id', $versao->getKey())->count(),
                    'vigente' => $versao->status === RuleVersionStatus::Vigente,
                    'rascunho' => $versao->status === RuleVersionStatus::Rascunho,
                    'autor_id' => $versao->created_by,
                ])
                ->values()
                ->all(),
            'textos' => DecisionText::query()
                ->orderBy('key')
                ->get(['id', 'key', 'description'])
                ->all(),
        ];
    }

    /**
     * Publicar um Quadro gera uma NOVA versão (HU-044): a regra dos quatro olhos
     * exige autor distinto do publicador (usuário autenticado). Autor igual ao
     * publicador em domínio sensível é bloqueado e comunicado (flash.error) —
     * degradação controlada, nunca silenciosa.
     */
    public function publish(PublishLouosVersionRequest $request): RedirectResponse
    {
        $publisherId = (int) $request->user()->id;
        $authorId = (int) $request->validated('author_id');

        if ($authorId === $publisherId) {
            return back()->with(
                'error',
                'A publicação por quatro olhos exige que o autor da nova versão seja diferente de quem publica.',
            );
        }

        $domain = self::QUADRO_DOMAINS[$request->validated('quadro')];

        $versao = $this->maintenance->publishNewVersion(
            $domain,
            (string) $request->validated('version'),
            $request->validated('alteracoes', []),
            $authorId,
            $publisherId,
        );

        return back()->with('status', "Nova versão {$versao->version} do {$domain->label()} publicada.");
    }

    /**
     * Ativa uma versão já publicada do Quadro selecionado, fechando a vigente
     * sem apagá-la. Rascunho não pode ser ativado (precisa da publicação).
     */
    public function activate(Request $request, RuleVersion $versao): RedirectResponse
    {
        $quadro = (string) $request->input('quadro', '');

        if (! array_key_exists($quadro, self::QUADRO_DOMAINS)) {
            abort(422, 'Quadro inválido ou ausente.');
        }

        $domain = self::QUADRO_DOMAINS[$quadro];

        if ($versao->domain !== $domain) {
            abort(422, 'A versão não pertence ao Quadro selecionado.');
        }

        try {
            $ativada = $this->ruleVersions->activate($versao);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('gestao.louos.index', ['quadro' => $quadro])
            ->with('status', "Versão {$ativada->version} ativada e em uso.");
    }

    /**
     * Histórico das versões publicadas do Quadro (vigente + substituídas).
     *
     * @return list<array{id: int, version: string, status: string, status_label: string, valid_from: string|null, valid_to: string|null, source: string|null, total: int, vigente: bool}>
     */
    private function historicoVersoes(string $quadro, RuleDomain $domain): array
    {
        return RuleVersion::query()
            ->where('domain', $domain->value)
            ->where('status', '!=', RuleVersionStatus::Rascunho->value)
            ->orderByRaw('case when status = ? then 0 else 1 end', [RuleVersionStatus::Vigente->value])
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->get()
            ->map(fn (RuleVersion $versao): array => [
                'id' => $versao->id,
                'version' => $versao->version,
                'status' => $versao->status->value,
                'status_label' => $versao->status->label(),
                'valid_from' => $versao->valid_from?->toDateString(),
                'valid_to' => $versao->valid_to?->toDateString(),
                'source' => $versao->source,
                'total' => $this->contarLinhas($quadro, $versao),
                'vigente' => $versao->status === RuleVersionStatus::Vigente,
            ])
            ->values()
            ->all();
    }

    /**
     * Resumo das versões vigentes dos Quadros 10 e 11A (visão geral da consulta).
     *
     * @return list<array{quadro: string, label: string, version: string|null, valid_from: string|null, total: int}>
     */
    private function resumoQuadros(): array
    {
        $resumo = [];

        foreach (self::QUADRO_DOMAINS as $quadro => $domain) {
            $vigente = RuleVersion::vigente($domain)->first();

            $resumo[] = [
                'quadro' => $quadro,
                'label' => $domain->label(),
                'version' => $vigente?->version,
                'valid_from' => $vigente?->valid_from?->toDateString(),
                'total' => $this->contarLinhas($quadro, $vigente),
            ];
        }

        return $resumo;
    }

    /**
     * Total de linhas da tabela tipada do Quadro na versão vigente.
     */
    private function contarLinhas(string $quadro, ?RuleVersion $vigente): int
    {
        if ($vigente === null) {
            return 0;
        }

        return match ($quadro) {
            'quadro10' => LouosQuadro10Permissao::query()->where('rule_version_id', $vigente->id)->count(),
            default => LouosQuadro11CondicaoVia::query()->where('rule_version_id', $vigente->id)->count(),
        };
    }

    /**
     * Listagem paginada do Quadro selecionado na versão vigente, com busca. As
     * linhas são mapeadas para o contrato consumido pela tela (05-07).
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function listarQuadro(string $quadro, ?RuleVersion $vigente, string $search, int $perPage): LengthAwarePaginator
    {
        $versionId = $vigente?->id ?? 0;

        return match ($quadro) {
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

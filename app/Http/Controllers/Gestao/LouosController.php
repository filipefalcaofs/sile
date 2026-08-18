<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\RuleDomain;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\PublishLouosVersionRequest;
use App\Models\LouosQuadro10Permissao;
use App\Models\LouosQuadro11CondicaoVia;
use App\Models\LouosQuadro7Faixa;
use App\Models\RuleVersion;
use App\Services\Louos\LouosMaintenanceService;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Consulta e manutenção dos Quadros da LOUOS no console SEDUR (HU-015..018 /
 * HU-046). A consulta lista a versão vigente de cada Quadro (resumo dos 4 +
 * listagem paginada do selecionado, com busca/auditoria); a publicação gera uma
 * NOVA versão por quatro olhos (LouosMaintenanceService), preservando a anterior
 * — nunca edição destrutiva. Espelha o RiscoController (server-driven). Gate
 * cross-guard via permission: nas rotas.
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
     * Param `quadro` → domínio de regra versionada do Quadro.
     *
     * @var array<string, RuleDomain>
     */
    private const QUADRO_DOMAINS = [
        'quadro7' => RuleDomain::LouosQuadro7,
        'quadro10' => RuleDomain::LouosQuadro10,
        'quadro11' => RuleDomain::LouosQuadro11,
        'quadro11a' => RuleDomain::LouosQuadro11a,
    ];

    public function __construct(
        private LouosMaintenanceService $maintenance,
        private AuditService $audit,
    ) {}

    public function index(Request $request): Response
    {
        $quadro = $request->string('quadro')->toString();

        if (! array_key_exists($quadro, self::QUADRO_DOMAINS)) {
            $quadro = 'quadro7';
        }

        $domain = self::QUADRO_DOMAINS[$quadro];

        $perPage = (int) $request->input('per_page');
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true)
            ? $perPage
            : (int) Settings::get('ui.cnaes.per_page', 15);

        $search = $request->string('search')->trim()->toString();

        $vigente = RuleVersion::vigente($domain)->first();

        $itens = $this->listarQuadro($quadro, $vigente, $search, $perPage);

        $this->audit->log(
            logName: 'louos',
            event: 'consulta-quadros',
            description: 'Consulta dos Quadros vigentes da LOUOS no console',
            properties: [
                'quadro' => $quadro,
                'versao' => $vigente?->version,
                'busca' => $search !== '' ? $search : null,
            ],
            result: 'sucesso',
            rulesVersion: $vigente?->version,
        );

        return Inertia::render('gestao/louos/index', [
            'quadros' => $this->resumoQuadros(),
            'quadroSelecionado' => $quadro,
            'itens' => $itens,
            'filtros' => [
                'quadro' => $quadro,
                'search' => $search,
                'per_page' => $perPage,
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    /**
     * Publicar um Quadro gera uma NOVA versão (HU-046): a regra dos quatro olhos
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
     * Resumo das versões vigentes dos 4 Quadros (visão geral da consulta).
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
            'quadro7' => LouosQuadro7Faixa::query()->where('rule_version_id', $vigente->id)->count(),
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
                    'formatted_code' => $this->formatCnae($faixa->cnae_code),
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

    /**
     * Código no formato oficial DDDD-D/SS (espelha Cnae::formatted_code para as
     * faixas, cujo cnae_code é guardado em dígitos).
     */
    private function formatCnae(string $code): string
    {
        return (string) preg_replace('/^(\d{4})(\d)(\d{2})$/', '$1-$2/$3', $code);
    }
}

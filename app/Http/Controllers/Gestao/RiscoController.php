<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\RiscoMunicipal;
use App\Enums\RuleDomain;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\PublishRiscoVersionRequest;
use App\Models\RiskClassification;
use App\Models\RuleVersion;
use App\Services\Risco\RiscoMaintenanceService;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Consulta e manutenção da tabela de risco no console SEDUR (HU-052/HU-053/
 * HU-020). A consulta lista a classificação MUNICIPAL vigente por CNAE
 * (busca/filtro/auditoria); a atualização publica uma NOVA versão por quatro
 * olhos (RiscoMaintenanceService), preservando a anterior — nunca edição
 * destrutiva. Espelha o CnaeController (server-driven) e o TerritoryController
 * (auditoria). Gate cross-guard via permission: nas rotas.
 */
class RiscoController extends Controller
{
    /**
     * Níveis válidos para o filtro e tamanhos de página aceitos — whitelists
     * técnicas. O padrão de página reusa ui.cnaes.per_page (sem inflar o
     * catálogo de parâmetros: a listagem do console compartilha a preferência).
     */
    private const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    public function __construct(
        private RiscoMaintenanceService $maintenance,
        private AuditService $audit,
    ) {}

    public function index(Request $request): Response
    {
        $municipal = RuleVersion::vigente(RuleDomain::RiscoMunicipal)->first();
        $sanitaria = RuleVersion::vigente(RuleDomain::RiscoSanitario)->first();

        $perPage = (int) $request->input('per_page');
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true)
            ? $perPage
            : (int) Settings::get('ui.cnaes.per_page', 15);

        $nivel = $request->string('nivel')->toString();
        $niveis = array_map(fn (RiscoMunicipal $n) => $n->value, RiscoMunicipal::cases());

        $classificacoes = RiskClassification::query()
            ->where('risk_classifications.rule_version_id', $municipal?->id ?? 0)
            ->leftJoin('cnaes', 'cnaes.code', '=', 'risk_classifications.cnae_code')
            ->select('risk_classifications.*', 'cnaes.description as cnae_description')
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request) {
                $term = (string) $request->string('search')->trim();
                $digits = preg_replace('/\D/', '', $term);

                $query->where(function ($inner) use ($term, $digits) {
                    if ($digits !== '') {
                        $inner->where('risk_classifications.cnae_code', 'like', "{$digits}%");
                    }

                    // whereLike sem case: o LIKE do PostgreSQL é case-sensitive.
                    $inner->orWhereLike('cnaes.description', "%{$term}%", caseSensitive: false);
                });
            })
            ->when(in_array($nivel, $niveis, true), fn ($query) => $query->where('risco_municipal', $nivel))
            ->orderBy('risk_classifications.cnae_code')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (RiskClassification $classificacao) => [
                'id' => $classificacao->id,
                'cnae_code' => $classificacao->cnae_code,
                'formatted_code' => $this->formatCnae($classificacao->cnae_code),
                'cnae_description' => $classificacao->cnae_description,
                'risco_municipal' => $classificacao->risco_municipal->value,
                'risco_municipal_label' => $classificacao->risco_municipal->label(),
                'condicionantes' => $classificacao->condicionantes,
                'observacao' => $classificacao->observacao,
            ]);

        $this->audit->log(
            logName: 'risco',
            event: 'consulta-tabela',
            description: 'Consulta da tabela de risco municipal vigente',
            properties: [
                'versao_municipal' => $municipal?->version,
                'busca' => $request->string('search')->toString() ?: null,
                'nivel' => in_array($nivel, $niveis, true) ? $nivel : null,
            ],
            result: 'sucesso',
            rulesVersion: $municipal?->version,
        );

        return Inertia::render('gestao/risco/index', [
            'classificacoes' => $classificacoes,
            'versaoMunicipal' => $municipal === null ? null : [
                'version' => $municipal->version,
                'valid_from' => $municipal->valid_from?->toDateString(),
            ],
            'versaoSanitaria' => $sanitaria === null ? null : [
                'version' => $sanitaria->version,
                'valid_from' => $sanitaria->valid_from?->toDateString(),
            ],
            'resumoNiveis' => $this->resumoNiveis($municipal),
            'filtros' => [
                'search' => $request->string('search')->toString(),
                'nivel' => in_array($nivel, $niveis, true) ? $nivel : '',
                'per_page' => $perPage,
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    /**
     * Atualizar a classificação publica uma NOVA versão (HU-053/HU-020): a
     * regra dos quatro olhos exige autor distinto do publicador (usuário
     * autenticado). Mesmo autor e publicador em domínio sensível é bloqueado e
     * comunicado (flash.error) — degradação controlada, nunca silenciosa.
     */
    public function publish(PublishRiscoVersionRequest $request): RedirectResponse
    {
        $publisherId = (int) $request->user()->id;
        $authorId = (int) $request->validated('author_id');

        if ($authorId === $publisherId) {
            return back()->with(
                'error',
                'A publicação por quatro olhos exige que o autor da nova versão seja diferente de quem publica.',
            );
        }

        $versao = $this->maintenance->publishNewVersion(
            RuleDomain::RiscoMunicipal,
            (string) $request->validated('version'),
            $request->validated('alteracoes', []),
            $authorId,
            $publisherId,
        );

        return back()->with('status', "Nova versão {$versao->version} da tabela de risco publicada.");
    }

    /**
     * Distribuição por nível da versão municipal vigente (resumo da consulta).
     *
     * @return array{baixo_a: int, baixo_b: int, alto: int}
     */
    private function resumoNiveis(?RuleVersion $municipal): array
    {
        $contagens = $municipal === null
            ? collect()
            : RiskClassification::query()
                ->where('rule_version_id', $municipal->id)
                ->selectRaw('risco_municipal, count(*) as total')
                ->groupBy('risco_municipal')
                ->pluck('total', 'risco_municipal');

        return [
            'baixo_a' => (int) ($contagens[RiscoMunicipal::BaixoA->value] ?? 0),
            'baixo_b' => (int) ($contagens[RiscoMunicipal::BaixoB->value] ?? 0),
            'alto' => (int) ($contagens[RiscoMunicipal::Alto->value] ?? 0),
        ];
    }

    /**
     * Código no formato oficial DDDD-D/SS (espelha Cnae::formatted_code para
     * as linhas vindas do join, que não hidratam o accessor do model Cnae).
     */
    private function formatCnae(string $code): string
    {
        return (string) preg_replace('/^(\d{4})(\d)(\d{2})$/', '$1-$2/$3', $code);
    }
}

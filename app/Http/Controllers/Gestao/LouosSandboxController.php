<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\RuleDomain;
use App\Enums\RuleVersionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\SimulateLouosRequest;
use App\Models\RuleVersion;
use App\Services\Louos\LouosSandboxSimulationService;
use App\Services\Rules\RuleVersionService;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sandbox de simulação de parametrização da LOUOS no console SEDUR (HU-143). O
 * gestor seleciona um rascunho de Quadro, simula o impacto contra cenários reais
 * reexecutando o MOTOR REAL (LouosSandboxSimulationService) — sem efeito colateral
 * (RN-001) — e só então publica o rascunho como nova versão por quatro olhos
 * (RuleVersionService, autor ≠ publicador). Gate cross-guard via permission:
 * manter-louos nas rotas (simular/publicar é manutenção). Espelha o LouosController.
 */
class LouosSandboxController extends Controller
{
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
        private LouosSandboxSimulationService $sandbox,
        private RuleVersionService $versions,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('gestao/louos/sandbox', $this->baseProps());
    }

    /**
     * Simula o impacto do rascunho contra a amostra (SEM publicar) e re-renderiza
     * a página com o relatório. A rota de simulação compartilha o caminho da
     * consulta, logo um refresh recai no index (sem o relatório efêmero).
     */
    public function simulate(SimulateLouosRequest $request): Response
    {
        $relatorio = $this->sandbox->simulate(
            $request->dominio(),
            (string) $request->validated('versao_rascunho'),
            $request->validated('amostra') !== null ? (int) $request->validated('amostra') : null,
        );

        return Inertia::render('gestao/louos/sandbox', [
            ...$this->baseProps(),
            'simulacao' => $relatorio,
        ]);
    }

    /**
     * Publica o rascunho como nova versão vigente por quatro olhos (RN-005): o
     * publicador (usuário autenticado) deve ser distinto do autor do rascunho. O
     * bloqueio é interceptado antes e comunicado (flash.error), como no
     * LouosController; o RuleVersionService é a defesa de domínio.
     */
    public function publish(SimulateLouosRequest $request): RedirectResponse
    {
        $domain = $request->dominio();
        $version = (string) $request->validated('versao_rascunho');

        $draft = RuleVersion::query()
            ->where('domain', $domain->value)
            ->where('version', $version)
            ->where('status', RuleVersionStatus::Rascunho->value)
            ->firstOrFail();

        $publisherId = (int) $request->user()->id;

        if ($draft->created_by !== null && $draft->created_by === $publisherId) {
            return back()->with(
                'error',
                'A publicação por quatro olhos exige um publicador diferente do autor do rascunho.',
            );
        }

        $versao = $this->versions->publish($draft, $publisherId);

        return back()->with(
            'status',
            "Rascunho {$versao->version} do {$domain->label()} publicado como nova versão vigente.",
        );
    }

    /**
     * Props comuns à página do sandbox: os rascunhos disponíveis por Quadro e a
     * amostra padrão parametrizável (HU-014).
     *
     * @return array<string, mixed>
     */
    private function baseProps(): array
    {
        return [
            'rascunhos' => $this->rascunhos(),
            'amostraPadrao' => (int) Settings::get('louos.sandbox.amostra_padrao', 50),
        ];
    }

    /**
     * Rascunhos vigentes de Quadros da LOUOS (status rascunho), por domínio — a
     * base candidata do sandbox, que coexiste com a versão vigente.
     *
     * @return list<array<string, mixed>>
     */
    private function rascunhos(): array
    {
        $quadroPorDominio = [];

        foreach (self::QUADRO_DOMAINS as $quadro => $domain) {
            $quadroPorDominio[$domain->value] = $quadro;
        }

        return RuleVersion::query()
            ->whereIn('domain', array_keys($quadroPorDominio))
            ->where('status', RuleVersionStatus::Rascunho->value)
            ->orderBy('domain')
            ->orderBy('version')
            ->get()
            ->map(fn (RuleVersion $version): array => [
                'quadro' => $quadroPorDominio[$version->domain->value],
                'dominio' => $version->domain->value,
                'label' => $version->domain->label(),
                'version' => $version->version,
                'author_id' => $version->created_by,
                'created_at' => $version->created_at?->toDateString(),
            ])
            ->values()
            ->all();
    }
}

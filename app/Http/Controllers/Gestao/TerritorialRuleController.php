<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\RuleDomain;
use App\Enums\RuleVersionStatus;
use App\Exceptions\FourEyesViolationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\TerritorialRuleEditRequest;
use App\Models\RuleVersion;
use App\Services\Rules\RuleVersionService;
use App\Services\Territorial\TerritorialRuleDraftService;
use DomainException;
use Illuminate\Http\RedirectResponse;

/**
 * Manutenção versionada das regras territoriais (RT-01 a RT-05) no hub Motor
 * de regras. Espelha o fluxo dos quadros LOUOS: a edição grava no RASCUNHO
 * do domínio (autor = usuário logado), a publicação é quatro olhos (outro
 * mantenedor) e a vigente nunca é editada destrutivamente. Gate cross-guard
 * via permission:manter-louos nas rotas.
 */
class TerritorialRuleController extends Controller
{
    public function __construct(
        private TerritorialRuleDraftService $drafts,
        private RuleVersionService $ruleVersions,
    ) {}

    /**
     * Aplica a alteração de uma regra no rascunho do domínio (abrindo-o como
     * cópia da vigente quando não há rascunho aberto).
     */
    public function storeDraft(TerritorialRuleEditRequest $request): RedirectResponse
    {
        $draft = $this->drafts->aplicarAlteracao(
            (string) $request->validated('codigo'),
            $request->safe()->except(['codigo', 'version']),
            (string) $request->validated('version'),
            (int) $request->user()->id,
        );

        return redirect()
            ->route('gestao.louos.index', ['aba' => 'territoriais'])
            ->with(
                'status',
                "Regra gravada no rascunho {$draft->version}. A publicação exige outro mantenedor (quatro olhos).",
            );
    }

    /**
     * Publica o rascunho do domínio territorial — quatro olhos: o publicador
     * (usuário logado) deve ser distinto do autor do rascunho.
     */
    public function publish(RuleVersion $versao): RedirectResponse
    {
        $erro = $this->validarVersaoTerritorial($versao, rascunho: true);

        if ($erro !== null) {
            return back()->with('error', $erro);
        }

        try {
            $publicada = $this->ruleVersions->publish($versao, (int) request()->user()->id);
        } catch (FourEyesViolationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('gestao.louos.index', ['aba' => 'territoriais'])
            ->with('status', "Versão {$publicada->version} das regras territoriais publicada e em uso.");
    }

    /**
     * Reativa uma versão já publicada (a vigente é fechada, nunca apagada).
     */
    public function activate(RuleVersion $versao): RedirectResponse
    {
        $erro = $this->validarVersaoTerritorial($versao, rascunho: false);

        if ($erro !== null) {
            return back()->with('error', $erro);
        }

        try {
            $ativada = $this->ruleVersions->activate($versao);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('gestao.louos.index', ['aba' => 'territoriais'])
            ->with('status', "Versão {$ativada->version} das regras territoriais ativada e em uso.");
    }

    /**
     * Descarta o rascunho aberto — a vigente permanece intacta.
     */
    public function destroyDraft(RuleVersion $versao): RedirectResponse
    {
        $erro = $this->validarVersaoTerritorial($versao, rascunho: true);

        if ($erro !== null) {
            return back()->with('error', $erro);
        }

        $this->drafts->descartarRascunho($versao);

        return redirect()
            ->route('gestao.louos.index', ['aba' => 'territoriais'])
            ->with('status', "Rascunho {$versao->version} descartado. A versão vigente não foi alterada.");
    }

    /**
     * A versão alvo precisa ser do domínio territorial e estar no estado
     * esperado pela operação (rascunho para publicar/descartar; publicada
     * para reativar).
     */
    private function validarVersaoTerritorial(RuleVersion $versao, bool $rascunho): ?string
    {
        if ($versao->domain !== RuleDomain::Territorial) {
            return 'A versão não pertence ao domínio das regras territoriais.';
        }

        $ehRascunho = $versao->status === RuleVersionStatus::Rascunho;

        if ($rascunho && ! $ehRascunho) {
            return 'A operação exige uma versão em rascunho.';
        }

        if (! $rascunho && $ehRascunho) {
            return 'Rascunho não pode ser ativado. Publique-o antes.';
        }

        return null;
    }
}

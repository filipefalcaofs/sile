<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\RuleDomain;
use App\Enums\RuleVersionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\ImportarPlanilhaTratamentoRequest;
use App\Models\RuleVersion;
use App\Services\Rules\RuleVersionService;
use App\Services\Tratamento\PlanilhaTratamentoImporter;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class TratamentoPlanilhaController extends Controller
{
    public function __construct(
        private PlanilhaTratamentoImporter $importer,
        private RuleVersionService $versions,
    ) {}

    public function importar(ImportarPlanilhaTratamentoRequest $request): RedirectResponse
    {
        $arquivo = $request->file('arquivo');

        try {
            $resultado = $this->importer->importar(
                $arquivo->getRealPath(),
                (int) $request->user()->id,
                $arquivo->getClientOriginalName(),
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($resultado['duplicada']) {
            $estado = $resultado['versao']->status === RuleVersionStatus::Vigente
                ? 'já está vigente'
                : 'já foi importado';

            return back()->with('error', "Este arquivo {$estado} como {$resultado['versao']->version}. Nenhuma versão nova foi criada.");
        }

        return redirect()
            ->route('gestao.louos.index', ['aba' => 'enquadramento'])
            ->with('status', "Rascunho {$resultado['versao']->version} aberto. Publique para colocar a planilha em uso. {$resultado['pendencias']} regras ficaram sem ramo explícito.");
    }

    public function publicar(RuleVersion $versao): RedirectResponse
    {
        if ($versao->domain !== RuleDomain::RiscoTratamento || $versao->status !== RuleVersionStatus::Rascunho) {
            return back()->with('error', 'Só um rascunho da planilha de tratamento pode ser publicado.');
        }

        $publicada = $this->versions->publish($versao, (int) request()->user()->id, fourEyes: false);

        return redirect()
            ->route('gestao.louos.index', ['aba' => 'enquadramento'])
            ->with('status', "Versão {$publicada->version} da planilha publicada e em uso.");
    }
}

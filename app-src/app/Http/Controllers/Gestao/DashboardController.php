<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Services\Relatorios\PainelOperacaoService;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Home da gestão (HU-122): KPIs operacionais gated por consultar-relatorios.
     * A tela inicial de quem opera o dia a dia é a caixa de trabalho, não o
     * painel (relatório de usabilidade SEDUR 19/09/2026, item 03): analista cai
     * na Caixa de entrada; apoio (tramitação) cai na Caixa do setor.
     */
    public function __invoke(Request $request, PainelOperacaoService $painel): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user->can('consultar-relatorios')) {
            if ($user->can('analisar-processos')) {
                return redirect('/gestao/processos/fila');
            }

            if ($user->can('distribuir-processos')) {
                return redirect('/gestao/caixa-setor');
            }
        }

        return Inertia::render('gestao/dashboard', [
            'kpis' => [
                'operacao' => $user->can('consultar-relatorios')
                    ? $painel->montar($this->janelaDias($request), $user->can('relatorios.produtividade.nominal'))
                    : null,
            ],
        ]);
    }

    private function janelaDias(Request $request): int
    {
        $pedida = $request->integer('janela');

        if (in_array($pedida, PainelOperacaoService::JANELAS, true)) {
            return $pedida;
        }

        return (int) Settings::get('relatorios.dashboard.janela_dias', 30);
    }
}

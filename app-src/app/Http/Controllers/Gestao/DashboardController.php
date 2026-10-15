<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Models\AccessRequest;
use App\Models\User;
use App\Services\Relatorios\PainelOperacaoService;
use App\Support\PerfisSolicitaveis;
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

        // Perfil de analista abre sempre na Caixa de entrada, mesmo com
        // consultar-relatorios concedido pelo administrador.
        if ($user->can('analisar-processos') && $user->hasAnyRole(['Analista Terceirizado', 'Analista Servidor'])) {
            return redirect('/gestao/processos/fila');
        }

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
            'acesso' => $this->acessoPendente($user),
        ]);
    }

    /**
     * Servidor que entrou pelo AD e ainda não tem papel: perfis que pode pedir
     * e o estado do último pedido (primeiro acesso).
     *
     * @return array{perfis: list<string>, solicitacao: array{status: string, perfil: string, justificativa: string, motivo: ?string}|null}|null
     */
    private function acessoPendente(User $user): ?array
    {
        if ($user->roles()->exists()) {
            return null;
        }

        $ultima = AccessRequest::query()
            ->where('user_id', $user->id)
            ->with('role:id,name')
            ->latest('id')
            ->first();

        return [
            'perfis' => PerfisSolicitaveis::roles()->pluck('name')->all(),
            'solicitacao' => $ultima === null ? null : [
                'status' => $ultima->status->value,
                'perfil' => $ultima->role->name,
                'justificativa' => $ultima->justificativa,
                'motivo' => $ultima->decision_note,
            ],
        ];
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

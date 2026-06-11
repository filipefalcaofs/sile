<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\Cnae;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    /**
     * Painel de gestão com KPIs reais (Fase 2.4): contagens sobre os
     * dados existentes, cada bloco condicionado à permissão do módulo.
     * Janela do indicador de acessos é parâmetro administrável.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $janelaDias = (int) Settings::get('ui.dashboard.acessos_janela_dias', 7);

        return Inertia::render('gestao/dashboard', [
            'kpis' => [
                'cnaes' => $user->can('consultar-cnaes') ? [
                    'ativos' => Cnae::query()->where('active', true)->count(),
                    'total' => Cnae::query()->count(),
                ] : null,
                'usuarios' => $user->can('manter-usuarios') ? [
                    'ativos' => User::query()->whereNull('inactivated_at')->count(),
                    'total' => User::query()->count(),
                ] : null,
                'perfis' => $user->can('manter-perfis') ? [
                    'total' => Role::query()->count(),
                    'permissoes' => Permission::query()->count(),
                ] : null,
                'acessos' => $user->can('manter-usuarios') ? [
                    'logins' => AccessLog::query()
                        ->where('event', 'login')
                        ->where('created_at', '>=', now()->subDays($janelaDias))
                        ->count(),
                    'janela_dias' => $janelaDias,
                ] : null,
            ],
        ]);
    }
}

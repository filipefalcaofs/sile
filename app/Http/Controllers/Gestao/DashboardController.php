<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\Cnae;
use App\Models\User;
use App\Services\Relatorios\ExpressoQuedaService;
use App\Services\Relatorios\IndicadoresViabilidadeService;
use App\Services\Relatorios\ReportFilters;
use App\Services\Relatorios\TempoAnaliseService;
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
    public function __invoke(
        Request $request,
        IndicadoresViabilidadeService $indicadores,
        TempoAnaliseService $tempos,
        ExpressoQuedaService $quedas,
    ): Response {
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
                'relatorios' => $user->can('consultar-relatorios')
                    ? $this->relatoriosKpis($indicadores, $tempos, $quedas)
                    : null,
            ],
        ]);
    }

    /**
     * KPIs operacionais do EP15 (HU-122) sobre a janela corrente: cada número vem
     * de um serviço route-free (15-03/05/07) sobre o dado REAL do período. SEM
     * série histórica persistida NÃO há `delta`/comparativo "+X%" (anti-fachada
     * CA-03): a degradação é honesta — taxa null quando não há base, jamais
     * fabricada. A janela é uma constante técnica administrável (sem deploy).
     *
     * @return array<string, mixed>
     */
    private function relatoriosKpis(
        IndicadoresViabilidadeService $indicadores,
        TempoAnaliseService $tempos,
        ExpressoQuedaService $quedas,
    ): array {
        $janelaDias = (int) Settings::get('relatorios.dashboard.janela_dias', 30);
        $filtros = ReportFilters::fromArray([
            'data_de' => now()->subDays($janelaDias)->format('Y-m-d'),
            'data_ate' => now()->format('Y-m-d'),
        ]);

        $serieVolume = $indicadores->porPeriodo($filtros);
        $deferimento = $indicadores->taxaDeferimento($filtros);
        $indeferimento = $indicadores->taxaIndeferimento($filtros);
        $expressa = $quedas->taxaRespostaExpressa($filtros);

        $analise = collect($tempos->tempoPorEtapa($filtros)['etapas'])->firstWhere('etapa', 'analise');

        return [
            'volume' => array_sum(array_column($serieVolume, 'total')),
            'taxa_deferimento' => $deferimento['taxa'],
            'taxa_indeferimento' => $indeferimento['taxa'],
            'tempo_analise_minutos' => $analise['media_minutos'] ?? null,
            'taxa_expressa' => $expressa['taxa'],
            'meta_expressa' => $expressa['meta'],
            'janela_dias' => $janelaDias,
            // Séries reais para os gráficos do painel (montadas no front a partir
            // destes arrays; sem fabricação de pontos).
            'serie_volume' => $serieVolume,
            'por_risco' => $indicadores->porRisco($filtros),
        ];
    }
}

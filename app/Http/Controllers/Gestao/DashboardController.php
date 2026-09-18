<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Services\Relatorios\ExpressoQuedaService;
use App\Services\Relatorios\IndicadoresViabilidadeService;
use App\Services\Relatorios\ReportFilters;
use App\Services\Relatorios\SlaVencimentosService;
use App\Support\Settings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Home da gestão (HU-122): KPIs operacionais gated por consultar-relatorios.
     * Sem a permissão, a home só entrega atalhos de módulo no front.
     */
    public function __invoke(
        Request $request,
        IndicadoresViabilidadeService $indicadores,
        ExpressoQuedaService $quedas,
        SlaVencimentosService $sla,
    ): Response {
        $user = $request->user();

        return Inertia::render('gestao/dashboard', [
            'kpis' => [
                'operacao' => $user->can('consultar-relatorios')
                    ? $this->operacao($indicadores, $quedas, $sla)
                    : null,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function operacao(
        IndicadoresViabilidadeService $indicadores,
        ExpressoQuedaService $quedas,
        SlaVencimentosService $sla,
    ): array {
        $janelaDias = (int) Settings::get('relatorios.dashboard.janela_dias', 30);
        $dataDe = now()->subDays($janelaDias)->format('Y-m-d');
        $dataAte = now()->format('Y-m-d');
        $filtros = ReportFilters::fromArray([
            'data_de' => $dataDe,
            'data_ate' => $dataAte,
        ]);
        $estoqueFiltros = ReportFilters::fromArray([]);
        $expressa = $quedas->taxaRespostaExpressa($filtros);
        $decisoes = $indicadores->decisoesPorFlow($filtros);

        return [
            'janela_dias' => $janelaDias,
            'data_de' => $dataDe,
            'data_ate' => $dataAte,
            'protocolos' => $indicadores->volumeProtocolos($filtros),
            'decisoes' => $decisoes,
            'estoque_total' => $sla->estoqueTotal(),
            'atrasados' => $sla->resumo($estoqueFiltros)['vencidos'],
            'taxa_expressa' => $expressa['taxa'],
            'meta_expressa' => $expressa['meta'],
            'serie_fluxo' => $indicadores->serieFluxo($filtros),
            'estoque_por_status' => $sla->estoquePorStatus(),
        ];
    }
}

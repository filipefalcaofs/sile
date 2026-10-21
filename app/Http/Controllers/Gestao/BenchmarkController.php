<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Services\Ai\DicasTempoService;
use App\Services\Benchmark\BenchmarkRankingService;
use App\Services\Relatorios\DistanciaBenchmarkService;
use App\Services\Relatorios\ReportFilters;
use App\Support\Audit\AuditService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Benchmark nacional de tempo de viabilidade.
 *
 * Mostra como Salvador é medida pela régua federal e onde isso a coloca entre
 * as capitais e no país. É a camada de REFERÊNCIA EXTERNA: não se mistura com
 * a telemetria do VIABILIZA, que mede outra coisa, em outro relógio.
 *
 * Sem competência com as 27 UFs ingeridas, a tela informa a lacuna — um
 * ranking parcial apresentado como nacional seria pior que nenhum.
 */
class BenchmarkController extends Controller
{
    public function __construct(
        private readonly BenchmarkRankingService $ranking,
        private readonly DistanciaBenchmarkService $distancia,
        private readonly DicasTempoService $dicas,
        private readonly AuditService $audit,
    ) {}

    public function __invoke(Request $request): InertiaResponse
    {
        $competencia = $this->ranking->ultimaCompetenciaCompleta();

        $this->audit->log(
            logName: 'relatorios',
            event: 'consulta-benchmark',
            description: 'Consulta do benchmark nacional de viabilidade',
            properties: ['competencia' => $competencia],
        );

        if ($competencia === null) {
            return Inertia::render('gestao/relatorios/benchmark', [
                'competencia' => null,
                'posicao' => [
                    'posicao' => null,
                    'total' => 0,
                    'media' => null,
                    'ufs_ausentes' => $this->ranking->ufsAusentes(
                        (int) now()->subMonthNoOverflow()->year,
                        (int) now()->subMonthNoOverflow()->month,
                    ),
                    'cobertura_completa' => false,
                ],
                'capitais' => [],
                'serie' => $this->ranking->serieSalvador(),
                'evolucao' => $this->distancia->evolucaoMensal(now()),
                'dicas' => $this->dicas->painel($this->periodoDasDicas()),
            ]);
        }

        return Inertia::render('gestao/relatorios/benchmark', [
            'competencia' => $competencia,
            'posicao' => $this->ranking->posicaoNacional($competencia['ano'], $competencia['mes']),
            'capitais' => $this->ranking->rankingCapitais($competencia['ano'], $competencia['mes']),
            'serie' => $this->ranking->serieSalvador(),
            'evolucao' => $this->distancia->evolucaoMensal(now()),
            'dicas' => $this->dicas->painel($this->periodoDasDicas()),
        ]);
    }

    /**
     * As dicas do Benchmark olham o mesmo recorte da barra do VIABILIZA: do dia
     * 1º do mês corrente até hoje.
     */
    private function periodoDasDicas(): ReportFilters
    {
        return ReportFilters::fromArray([
            'data_de' => now()->startOfMonth()->toDateString(),
            'data_ate' => now()->toDateString(),
        ]);
    }
}

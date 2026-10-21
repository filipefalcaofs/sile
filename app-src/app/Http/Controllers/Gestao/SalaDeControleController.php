<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\RelatorioFiltersRequest;
use App\Services\Ai\DicasTempoService;
use App\Services\Relatorios\SalaDeControleService;
use App\Support\Audit\AuditService;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Visão executiva do desempenho da viabilidade em Salvador.
 *
 * Reúne, num carregamento só, o tempo atual, o volume e a automação, a
 * distância para a referência federal, a evolução histórica e os gargalos.
 * Não calcula nada: consome os indicadores que já existem.
 */
class SalaDeControleController extends Controller
{
    public function __construct(
        private readonly SalaDeControleService $sala,
        private readonly DicasTempoService $dicas,
        private readonly AuditService $audit,
    ) {}

    public function __invoke(RelatorioFiltersRequest $request): InertiaResponse
    {
        $filtros = $request->toReportFilters();

        $this->audit->log(
            logName: 'relatorios',
            event: 'consulta-sala-de-controle',
            description: 'Consulta da Sala de Controle',
            properties: $filtros->aplicados(),
        );

        return Inertia::render('gestao/sala-de-controle', [
            ...$this->sala->montar($filtros),
            'filtros' => $filtros->aplicados(),
            'dicas' => $this->dicas->painel($filtros),
        ]);
    }
}

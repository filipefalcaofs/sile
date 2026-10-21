<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\RelatorioFiltersRequest;
use App\Services\Ai\DicasTempoService;
use App\Support\Audit\AuditService;
use Illuminate\Http\RedirectResponse;

/**
 * Gera, sob demanda, as dicas de IA para reduzir o tempo de viabilidade do
 * período. A geração é assíncrona (fila): a tela volta com o aviso e passa a
 * mostrar as dicas quando o job termina.
 */
class DicasTempoController extends Controller
{
    public function __construct(
        private readonly DicasTempoService $dicas,
        private readonly AuditService $audit,
    ) {}

    public function __invoke(RelatorioFiltersRequest $request): RedirectResponse
    {
        $filtros = $request->toReportFilters();

        $this->audit->log(
            logName: 'relatorios',
            event: 'gera-dicas-tempo',
            description: 'Pedido de dicas de IA para reduzir o tempo de viabilidade',
            properties: $filtros->aplicados(),
        );

        if (! $this->dicas->gerar($filtros, $request->user()?->id)) {
            return back()->with('warning', $this->dicas->motivoDeNaoGerar($filtros));
        }

        return back()->with('status', 'Gerando as dicas. Elas aparecem nesta tela em instantes.');
    }
}

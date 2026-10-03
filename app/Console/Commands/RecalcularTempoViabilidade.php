<?php

namespace App\Console\Commands;

use App\Models\ViabilityRequest;
use App\Services\Relatorios\TempoViabilidadeRegistrar;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Reconstrói o tempo de viabilidade dos processos já decididos.
 *
 * Necessário porque a coluna passou a existir depois de o sistema já ter
 * decidido processos, e porque mudanças no calendário de feriados alteram
 * retroativamente a apuração de horas úteis.
 */
class RecalcularTempoViabilidade extends Command
{
    protected $signature = 'viabilidade:recalcular-horas-uteis
                            {--desde= : Recalcula só o que foi decidido a partir da data (YYYY-MM-DD)}';

    protected $description = 'Recalcula o tempo de viabilidade em horas úteis dos processos decididos';

    public function handle(TempoViabilidadeRegistrar $registrar): int
    {
        $desde = $this->option('desde');

        $processos = ViabilityRequest::query()
            ->whereHas('decision', function ($query) use ($desde): void {
                if (is_string($desde) && $desde !== '') {
                    $query->where('decided_at', '>=', Carbon::parse($desde)->startOfDay());
                }
            })
            ->with(['decision', 'pendencies']);

        $apurados = 0;
        $total = 0;

        $processos->chunkById(500, function ($lote) use ($registrar, &$apurados, &$total): void {
            foreach ($lote as $processo) {
                $total++;

                if ($registrar->registrarProcesso($processo) !== null) {
                    $apurados++;
                }
            }
        });

        $this->info("Processos percorridos: {$total}. Com tempo apurado: {$apurados}.");

        return self::SUCCESS;
    }
}

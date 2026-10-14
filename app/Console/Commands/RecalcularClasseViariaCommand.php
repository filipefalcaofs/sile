<?php

namespace App\Console\Commands;

use App\Models\ViabilityRequest;
use App\Services\Geo\TerritorioProcessoService;
use Illuminate\Console\Command;

/**
 * Backfill da classe viária: regrava a etapa e a via do inventário de camadas
 * pelo CODLOG de todos os processos que o têm. Não reavalia o veredito — o
 * enquadramento já usa o CODLOG. Idempotente.
 */
class RecalcularClasseViariaCommand extends Command
{
    protected $signature = 'processos:recalcular-classe-viaria
        {--processo= : Recalcula só este processo}';

    protected $description = 'Regrava a etapa e a via do inventário de camadas pelo CODLOG do processo';

    public function handle(TerritorioProcessoService $territorio): int
    {
        $query = ViabilityRequest::query()
            ->whereNotNull('cod_log')
            ->where('cod_log', '!=', '');

        if ($this->option('processo') !== null) {
            $query->whereKey((int) $this->option('processo'));
        }

        $total = 0;

        $query->chunkById(100, function ($processos) use ($territorio, &$total): void {
            foreach ($processos as $processo) {
                $territorio->registrarClasseViaria($processo);
                $total++;
            }
        });

        $this->info("Processos recalculados: {$total}");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Enums\EtapaMotor;
use App\Enums\SituacaoEtapa;
use App\Models\ViabilityRequest;
use App\Services\Geo\TerritorioProcessoService;
use Illuminate\Console\Command;

/**
 * Backfill da etapa "Classe viária": regrava a etapa pelo CODLOG dos processos
 * que a tinham registrado pela via mais próxima do ponto. Não reavalia o
 * veredito — o enquadramento já usa o CODLOG. Idempotente.
 */
class RecalcularClasseViariaCommand extends Command
{
    protected $signature = 'processos:recalcular-classe-viaria
        {--processo= : Recalcula só este processo}
        {--todos : Inclui também os processos cuja etapa já está concluída}';

    protected $description = 'Regrava a etapa Classe viária pelo CODLOG do processo';

    public function handle(TerritorioProcessoService $territorio): int
    {
        $query = ViabilityRequest::query()
            ->whereNotNull('cod_log')
            ->where('cod_log', '!=', '');

        if ($this->option('processo') !== null) {
            $query->whereKey((int) $this->option('processo'));
        } elseif (! $this->option('todos')) {
            $query->whereHas('etapas', fn ($etapas) => $etapas
                ->where('etapa', EtapaMotor::Via->value)
                ->where('situacao', '!=', SituacaoEtapa::Ok->value));
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

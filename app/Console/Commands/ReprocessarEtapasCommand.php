<?php

namespace App\Console\Commands;

use App\Enums\SituacaoEtapa;
use App\Models\ViabilityRequest;
use App\Services\Motor\ReprocessarEtapasService;
use Illuminate\Console\Command;

/**
 * Reprocessa os processos com etapa do motor indisponível cuja próxima tentativa
 * venceu. Idempotente e segura em multi-instância (agendada com onOneServer).
 */
class ReprocessarEtapasCommand extends Command
{
    protected $signature = 'processos:reprocessar-etapas
        {--processo= : Reprocessa só este processo, ignorando a agenda}
        {--limite= : Máximo de processos nesta passada}';

    protected $description = 'Reprocessa as etapas do motor que falharam por serviço indisponível';

    public function handle(ReprocessarEtapasService $servico): int
    {
        $query = ViabilityRequest::query()->whereHas('etapas', function ($etapas): void {
            $etapas->where('situacao', SituacaoEtapa::Indisponivel->value);
        });

        if ($this->option('processo') !== null) {
            $query->whereKey((int) $this->option('processo'));
        } else {
            $query->whereHas('etapas', function ($etapas): void {
                $etapas->where('situacao', SituacaoEtapa::Indisponivel->value)
                    ->whereNull('esgotada_em')
                    ->where('proxima_tentativa_em', '<=', now());
            });
        }

        $query->orderBy('id');

        if ($this->option('limite') !== null && (int) $this->option('limite') > 0) {
            $query->limit((int) $this->option('limite'));
        }

        $processos = $query->get();

        if ($processos->isEmpty()) {
            $this->info('Nenhum processo com etapa indisponível a reprocessar.');

            return self::SUCCESS;
        }

        foreach ($processos as $processo) {
            $resultado = $servico->reprocessar($processo);
            $this->line("#{$processo->id}: {$resultado->mensagem}");
        }

        return self::SUCCESS;
    }
}

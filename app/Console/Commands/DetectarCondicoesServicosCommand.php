<?php

namespace App\Console\Commands;

use App\Models\ViabilityRequestEvento;
use App\Services\Servicos\CondicaoServicoDetector;
use Illuminate\Console\Command;

/**
 * Preenche a condição (Inclusão/Exclusão, Revisão de Endereço/Alteração de
 * Espaço) dos eventos gravados antes de o De x Para ter linhas condicionais.
 */
class DetectarCondicoesServicosCommand extends Command
{
    protected $signature = 'servicos:detectar-condicoes';

    protected $description = 'Detecta a condição do De x Para nos eventos da JUCEB já gravados';

    public function handle(CondicaoServicoDetector $detector): int
    {
        $preenchidos = 0;

        ViabilityRequestEvento::query()
            ->whereNull('condicao')
            ->with('viabilityRequest')
            ->chunkById(500, function ($eventos) use ($detector, &$preenchidos): void {
                foreach ($eventos as $evento) {
                    if ($evento->viabilityRequest === null) {
                        continue;
                    }

                    $condicao = $detector->detectar($evento->viabilityRequest, $evento->nome);

                    if ($condicao !== null) {
                        $evento->update(['condicao' => $condicao->value]);
                        $preenchidos++;
                    }
                }
            });

        $this->info("Eventos com condição preenchida: {$preenchidos}.");

        return self::SUCCESS;
    }
}

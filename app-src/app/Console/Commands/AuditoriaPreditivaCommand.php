<?php

namespace App\Console\Commands;

use App\Services\Ia\PredictiveAuditService;
use Illuminate\Console\Command;

/**
 * Módulo 3 — Auditoria Preditiva de Processos Expressos. Dispara a varredura
 * periódica (scheduler) sobre os deferimentos automáticos. No-op honesto quando
 * o toggle está desligado (governança DPO/LGPD art. 20); nunca pune.
 */
class AuditoriaPreditivaCommand extends Command
{
    protected $signature = 'ia:auditoria-preditiva';

    protected $description = 'Varredura de auditoria preditiva dos deferimentos do fluxo expresso (gera alerta + malha fina, nunca pune); no-op honesto enquanto desligada';

    public function handle(PredictiveAuditService $service): int
    {
        $resumo = $service->executar();

        if (! $resumo['executado']) {
            $this->info('Auditoria preditiva desligada (features.ia_auditoria_preditiva=0).');

            return self::SUCCESS;
        }

        $this->info("Auditoria preditiva: {$resumo['criadas']} anomalia(s) criada(s), {$resumo['reaproveitadas']} reaproveitada(s), {$resumo['encaminhadas']} encaminhada(s) à malha fina.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Listeners;

use App\Models\AccessLog;
use App\Support\Audit\AuditService;
use Illuminate\Database\Events\ModelsPruned;

/**
 * Audita a poda de access_logs (RN-002 / SC#1). MassPrunable não dispara model
 * events de delete, mas o Laravel emite ModelsPruned (uma vez por chunk) com o
 * modelo e a contagem removida — é esse evento que registra a rotina.
 */
class AuditModelsPruned
{
    public function __construct(private readonly AuditService $audit) {}

    public function handle(ModelsPruned $event): void
    {
        // Só auditar a poda de access_logs — não poluir se outros Prunable surgirem.
        if ($event->model !== AccessLog::class) {
            return;
        }

        $this->audit->log(
            logName: 'retencao',
            event: 'pruning-access-logs',
            description: 'Limpeza automática do histórico de acessos (retenção LGPD)',
            properties: ['modelo' => $event->model, 'removidos' => $event->count],
            rulesVersion: 'retencao-access-logs-v1',
        );
    }
}

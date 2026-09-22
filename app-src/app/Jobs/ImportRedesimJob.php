<?php

namespace App\Jobs;

use App\Services\RedesimImportService;
use App\Support\Audit\AuditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Envelopa o RedesimImportService (existente) num job de fila com retry,
 * timeout e backoff. A lógica de domínio e a auditoria de sucesso já vivem
 * no serviço; aqui adicionamos resiliência e visibilidade de falha — um job
 * que esgota as tentativas vai para failed_jobs e audita a falha (RN-002),
 * nunca silenciosa.
 */
class ImportRedesimJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var array<int, int> */
    public array $backoff = [30, 60, 120];

    public function __construct(public readonly string $jsonPath) {}

    public function handle(RedesimImportService $service): void
    {
        $service->import($this->jsonPath);
    }

    public function failed(?Throwable $exception): void
    {
        app(AuditService::class)->log(
            logName: 'empresas',
            event: 'importacao-redesim',
            description: 'Falha no processamento assíncrono da importação REDESIM',
            properties: ['erro' => $exception?->getMessage()],
            result: 'falha',
            rulesVersion: 'redesim-import-v1',
        );
    }
}

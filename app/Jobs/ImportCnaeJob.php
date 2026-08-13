<?php

namespace App\Jobs;

use App\Services\CnaeImportService;
use App\Support\Audit\AuditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Envelopa o CnaeImportService (existente) num job de fila com retry, timeout
 * e backoff, auditando o relatório da reimportação oficial CNAE-Subclasses 2.3
 * (o serviço não audita — quem audita o relatório é o seeder/comando/job).
 * Um job esgotado vai para failed_jobs e audita a falha (RN-002).
 */
class ImportCnaeJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var array<int, int> */
    public array $backoff = [30, 60, 120];

    public function __construct(public readonly string $csvPath) {}

    public function handle(CnaeImportService $service, AuditService $audit): void
    {
        $report = $service->import($this->csvPath);

        $audit->log(
            logName: 'cnaes',
            event: 'importacao-oficial',
            description: 'Reimportação assíncrona da estrutura oficial CNAE-Subclasses 2.3',
            properties: $report,
            rulesVersion: 'cnae-subclasses-2.3',
        );
    }

    public function failed(?Throwable $exception): void
    {
        app(AuditService::class)->log(
            logName: 'cnaes',
            event: 'importacao-oficial',
            description: 'Falha na reimportação assíncrona de CNAEs',
            properties: ['erro' => $exception?->getMessage()],
            result: 'falha',
            rulesVersion: 'cnae-subclasses-2.3',
        );
    }
}

<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ImportCnaeJob;
use App\Models\Cnae;
use App\Services\CnaeImportService;
use App\Support\Audit\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ImportCnaeJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_importa_e_audita_o_relatorio(): void
    {
        (new ImportCnaeJob(database_path('data/cnaes-subclasses-2-3.csv')))
            ->handle(app(CnaeImportService::class), app(AuditService::class));

        $this->assertSame(1331, Cnae::query()->count());
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'cnaes',
            'event' => 'importacao-oficial',
            'rules_version' => 'cnae-subclasses-2.3',
        ]);
    }

    public function test_job_define_retry_timeout_e_backoff(): void
    {
        $job = new ImportCnaeJob('x.csv');

        $this->assertSame(3, $job->tries);
        $this->assertSame(60, $job->timeout);
        $this->assertSame([30, 60, 120], $job->backoff);
    }

    public function test_comando_sync_importa_audita_e_imprime_relatorio(): void
    {
        $this->artisan('cnae:importar', ['arquivo' => database_path('data/cnaes-subclasses-2-3.csv')])
            ->expectsOutputToContain('Importados: 1331')
            ->assertExitCode(0);

        $this->assertSame(1331, Cnae::query()->count());
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'cnaes',
            'event' => 'importacao-oficial',
        ]);
    }

    public function test_comando_com_queue_enfileira_o_job(): void
    {
        Queue::fake();

        $this->artisan('cnae:importar', [
            'arquivo' => database_path('data/cnaes-subclasses-2-3.csv'),
            '--queue' => true,
        ])
            ->expectsOutputToContain('enfileirada')
            ->assertExitCode(0);

        Queue::assertPushed(ImportCnaeJob::class);
    }

    public function test_comando_com_arquivo_inexistente_falha(): void
    {
        $this->artisan('cnae:importar', ['arquivo' => 'storage/nao-existe.csv'])
            ->expectsOutputToContain('Arquivo não encontrado')
            ->assertExitCode(1);
    }
}

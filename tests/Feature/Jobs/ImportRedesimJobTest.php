<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ImportRedesimJob;
use App\Models\Cnae;
use App\Services\RedesimImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ImportRedesimJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_executa_a_importacao_real(): void
    {
        Cnae::factory()->create(['code' => '6422100']);
        Cnae::factory()->create(['code' => '6499999']);

        (new ImportRedesimJob(base_path('tests/Fixtures/redesim/payload-valido.json')))
            ->handle(app(RedesimImportService::class));

        $this->assertDatabaseHas('companies', ['cnpj' => '00000000000191']);
    }

    public function test_job_define_retry_timeout_e_backoff(): void
    {
        $job = new ImportRedesimJob('x.json');

        $this->assertSame(3, $job->tries);
        $this->assertSame(60, $job->timeout);
        $this->assertSame([30, 60, 120], $job->backoff);
    }

    public function test_comando_com_queue_enfileira_o_job(): void
    {
        Queue::fake();

        $this->artisan('redesim:importar', [
            'arquivo' => base_path('tests/Fixtures/redesim/payload-valido.json'),
            '--queue' => true,
        ])
            ->expectsOutputToContain('enfileirada')
            ->assertExitCode(0);

        Queue::assertPushed(ImportRedesimJob::class);
    }

    public function test_job_que_falha_fica_visivel_em_failed_jobs_e_e_auditado(): void
    {
        config(['queue.default' => 'database']);

        // O serviço lança RuntimeException para arquivo ilegível: a falha é real.
        ImportRedesimJob::dispatch(base_path('nao-existe.json'));

        // $tries = 3 do job tem precedência sobre o worker; é preciso esgotar
        // as 3 tentativas, avançando o relógio além do backoff entre elas para
        // o job voltar a ficar disponível na fila.
        $this->artisan('queue:work', ['--once' => true]); // tentativa 1 → release (delay 30s)
        $this->travel(31)->seconds();
        $this->artisan('queue:work', ['--once' => true]); // tentativa 2 → release (delay 60s)
        $this->travel(61)->seconds();
        $this->artisan('queue:work', ['--once' => true]); // tentativa 3 → attempts >= tries → failed_jobs

        $this->assertSame(1, DB::table('failed_jobs')->count());
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'empresas',
            'event' => 'importacao-redesim',
            'result' => 'falha',
        ]);
    }
}

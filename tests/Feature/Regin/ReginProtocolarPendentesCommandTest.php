<?php

namespace Tests\Feature\Regin;

use App\Jobs\ProtocolarRecebimentoReginJob;
use App\Models\ReginRecebimento;
use App\Models\ViabilityRequest;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReginProtocolarPendentesCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_enfileira_a_protocolacao_so_dos_recebimentos_sem_processo(): void
    {
        Queue::fake();

        $pendente = ReginRecebimento::factory()->create(['protocolo' => 'BAP0001']);
        ReginRecebimento::factory()->create([
            'protocolo' => 'BAP0002',
            'viability_request_id' => ViabilityRequest::factory()->create()->id,
        ]);

        $this->artisan('regin:protocolar-pendentes')
            ->expectsOutputToContain('1 recebimento(s) sem processo')
            ->assertSuccessful();

        Queue::assertPushed(ProtocolarRecebimentoReginJob::class, 1);
        Queue::assertPushed(
            ProtocolarRecebimentoReginJob::class,
            fn (ProtocolarRecebimentoReginJob $job): bool => $job->reginRecebimentoId === $pendente->id,
        );
    }

    public function test_dry_run_lista_os_protocolos_sem_enfileirar(): void
    {
        Queue::fake();

        ReginRecebimento::factory()->create(['protocolo' => 'BAP0001']);

        $this->artisan('regin:protocolar-pendentes', ['--dry-run' => true])
            ->expectsOutputToContain('BAP0001')
            ->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_protocolo_informado_limita_os_recebimentos_processados(): void
    {
        Queue::fake();

        $escolhido = ReginRecebimento::factory()->create(['protocolo' => 'BAP0001']);
        ReginRecebimento::factory()->create(['protocolo' => 'BAP0002']);

        $this->artisan('regin:protocolar-pendentes', ['--protocolo' => ['BAP0001']])->assertSuccessful();

        Queue::assertPushed(ProtocolarRecebimentoReginJob::class, 1);
        Queue::assertPushed(
            ProtocolarRecebimentoReginJob::class,
            fn (ProtocolarRecebimentoReginJob $job): bool => $job->reginRecebimentoId === $escolhido->id,
        );
    }

    public function test_sem_pendentes_informa_e_nao_enfileira(): void
    {
        Queue::fake();

        $this->artisan('regin:protocolar-pendentes')
            ->expectsOutputToContain('Nenhum recebimento sem processo')
            ->assertSuccessful();

        Queue::assertNothingPushed();
    }
}

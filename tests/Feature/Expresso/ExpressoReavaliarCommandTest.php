<?php

namespace Tests\Feature\Expresso;

use App\Jobs\DecidirFluxoExpressoJob;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * Rede de SEGURANÇA do fluxo expresso (HU-134 RN-004 / robustez): o comando
 * expresso:reavaliar varre as solicitações PROTOCOLADAS que ficaram SEM decisão
 * (órfãs — o gatilho do protocolo falhou/se perdeu) e redespacha o
 * DecidirFluxoExpressoJob (o mesmo caminho real do gatilho; não decide inline).
 *
 * Não é o gatilho principal — é reprocesso idempotente: agendado com
 * withoutOverlapping/onOneServer, é no-op honesto quando não há órfãs. Como o
 * serviço de decisão já é idempotente (lock + re-check), redespachar é seguro.
 */
class ExpressoReavaliarCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reenfileira_apenas_orfas_protocoladas_sem_decisao(): void
    {
        // 2 protocoladas SEM decisão (órfãs) + 1 protocolada COM decisão: o
        // comando redespacha exatamente as 2 órfãs e NÃO toca a já decidida.
        Bus::fake([DecidirFluxoExpressoJob::class]);

        $orfas = ViabilityRequest::factory()
            ->protocoled()
            ->count(2)
            ->sequence(
                ['protocol_number' => 'VIA-2026-000101'],
                ['protocol_number' => 'VIA-2026-000102'],
            )
            ->create();

        $jaDecidida = ViabilityRequest::factory()->protocoled()->create(['protocol_number' => 'VIA-2026-000103']);
        ViabilityDecision::factory()->create(['viability_request_id' => $jaDecidida->id]);

        $this->artisan('expresso:reavaliar')
            ->expectsOutputToContain('2')
            ->assertExitCode(0);

        Bus::assertDispatchedTimes(DecidirFluxoExpressoJob::class, 2);

        foreach ($orfas as $orfa) {
            Bus::assertDispatched(
                DecidirFluxoExpressoJob::class,
                fn (DecidirFluxoExpressoJob $job): bool => $job->viabilityRequestId === $orfa->id,
            );
        }

        Bus::assertNotDispatched(
            DecidirFluxoExpressoJob::class,
            fn (DecidirFluxoExpressoJob $job): bool => $job->viabilityRequestId === $jaDecidida->id,
        );
    }

    public function test_no_op_honesto_quando_nao_ha_orfas(): void
    {
        // Sem órfãs (só uma já decidida) → nenhum job, mensagem de no-op e exit 0.
        // Degradação honesta: não finge trabalho onde não há.
        Bus::fake([DecidirFluxoExpressoJob::class]);

        ViabilityDecision::factory()->create();

        $this->artisan('expresso:reavaliar')
            ->expectsOutputToContain('Nenhuma solicitação pendente de decisão')
            ->assertExitCode(0);

        Bus::assertNotDispatched(DecidirFluxoExpressoJob::class);
    }

    public function test_respeita_o_limite_informado(): void
    {
        // --limit protege contra lotes grandes: com 3 órfãs e --limit=2, só 2
        // são reenfileiradas nesta passada (a próxima execução pega o resto).
        Bus::fake([DecidirFluxoExpressoJob::class]);

        ViabilityRequest::factory()
            ->protocoled()
            ->count(3)
            ->sequence(
                ['protocol_number' => 'VIA-2026-000201'],
                ['protocol_number' => 'VIA-2026-000202'],
                ['protocol_number' => 'VIA-2026-000203'],
            )
            ->create();

        $this->artisan('expresso:reavaliar', ['--limit' => 2])
            ->assertExitCode(0);

        Bus::assertDispatchedTimes(DecidirFluxoExpressoJob::class, 2);
    }
}

<?php

namespace Tests\Feature\Expresso;

use App\Enums\ViabilityRequestStatus;
use App\Models\Activity;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Rotina DORMENTE expresso:indeferir-sem-bap (HU-134): varre aguardando_bap com
 * prazo vencido e indefere "sem atuação". Em produção é NO-OP — nada entra em
 * aguardando_bap até o Regin alimentar bap_due_at (Fase 13). O motor é real e
 * testado AGORA com seed; sem fachada (zero indeferimento sem BAP de verdade).
 *
 * RN-003: o indeferimento comunica ao Regin (bloqueado até a Fase 13) e NÃO vai
 * à SEFAZ (ignorado) — reusa os listeners reais da Wave 4.
 */
class ExpressoIndeferirSemBapCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_op_dormente_quando_nada_esta_aguardando_bap(): void
    {
        // Estado REAL de produção: nada vincula BAP, então nada está em
        // aguardando_bap. Mesmo com uma protocolada ativa, o comando não indefere
        // ninguém — prova de dormência (não de fachada): ZERO decisões.
        ViabilityRequest::factory()->protocoled()->create();

        $this->artisan('expresso:indeferir-sem-bap')
            ->expectsOutputToContain('Nenhum processo aguardando BAP vencido')
            ->assertExitCode(0);

        $this->assertDatabaseCount('viability_decisions', 0);
    }

    public function test_indefere_somente_as_aguardando_bap_vencidas(): void
    {
        // 1 vencida (bap_due_at = now-72h, default) + 1 dentro do prazo
        // (bap_due_at = now+24h): o comando indefere SÓ a vencida.
        $vencida = ViabilityRequest::factory()->awaitingBap()->create();
        $dentroPrazo = ViabilityRequest::factory()->awaitingBap(Carbon::now()->addHours(24))->create();

        $this->artisan('expresso:indeferir-sem-bap')
            ->expectsOutputToContain('1 solicitação')
            ->assertExitCode(0);

        $this->assertDatabaseCount('viability_decisions', 1);
        $this->assertSame(ViabilityRequestStatus::Indeferida, $vencida->fresh()->status);
        $this->assertSame(ViabilityRequestStatus::AguardandoBap, $dentroPrazo->fresh()->status);

        $decision = ViabilityDecision::query()->where('viability_request_id', $vencida->id)->first();
        $this->assertNotNull($decision);
        $this->assertSame('indeferido sem atuação', $decision->reason);
    }

    public function test_indeferimento_comunica_regin_e_ignora_sefaz(): void
    {
        // Sem Event::fake: os listeners reais da Wave 4 rodam no ResultadoEmitido.
        // HU-134 RN-003: comunica ao Regin (bloqueado, Fase 13) e a SEFAZ ignora
        // o indeferimento — toda saída deixa trilha, sem simular envio.
        ViabilityRequest::factory()->awaitingBap()->create();

        $this->artisan('expresso:indeferir-sem-bap')->assertExitCode(0);

        $this->assertSame(1, $this->auditorias('regin-parecer', 'bloqueado'));
        $this->assertSame(1, $this->auditorias('sefaz-viabilidade', 'ignorado'));
    }

    private function auditorias(string $event, string $result): int
    {
        return Activity::query()
            ->where('log_name', 'integracoes')
            ->where('event', $event)
            ->where('result', $result)
            ->count();
    }
}

<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisRecordStatus;
use App\Enums\DecisionOutcome;
use App\Enums\ViabilityRequestStatus;
use App\Events\ResultadoEmitido;
use App\Models\AnalysisRecord;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Endpoint de decisão técnica (HU-086/087/088/089): o controller FINO conclui o
 * processo a partir da ficha FINALIZADA, orquestrando o AnaliseTecnicaDecisionService
 * (10-10) — toda a regra (RN-004) e a auditoria já vivem no serviço. Gated por
 * analisar-processos (403 auditado no ponto único — CA-04); ficha em rascunho é
 * recusada com 422 (não decide). A decisão dispara ResultadoEmitido (Regin/SEFAZ
 * bloqueados → Fase 13).
 */
class ProcessoDecisaoEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function analista(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    private function semAnalisarProcessos(): User
    {
        $user = User::factory()->withAcceptedLgpdTerm()->create();
        $user->givePermissionTo('acessar-gestao');

        return $user;
    }

    /**
     * Ficha (revisão 1) de um processo em análise com o per_cnae escolhido pelo
     * analista. $override permite simular a ficha em rascunho.
     *
     * @param  list<array<string, mixed>>  $perCnae
     * @param  array<string, mixed>  $override
     */
    private function processoComFicha(array $perCnae, array $override = []): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->protocoled()->create();
        $request->forceFill(['status' => ViabilityRequestStatus::EmAnalise])->save();

        AnalysisRecord::factory()->finalizada()->create(array_merge([
            'viability_request_id' => $request->id,
            'revision' => 1,
            'per_cnae' => $perCnae,
        ], $override));

        return $request->fresh();
    }

    public function test_sem_permissao_analisar_processos_recebe_403_auditado(): void
    {
        $processo = $this->processoComFicha([
            ['cnae' => '4712100', 'status_sugerido' => 'deferida', 'status_escolhido' => 'deferida'],
        ]);

        $this->actingAs($this->semAnalisarProcessos(), 'gestao')
            ->post("/gestao/processos/{$processo->id}/decidir")
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);

        $this->assertDatabaseCount('viability_decisions', 0);
    }

    public function test_decide_defere_quando_a_ficha_finalizada_tem_todas_deferidas(): void
    {
        // HU-086 RN-004: todas as CNAEs deferidas na ficha → DEFERE. Cria a
        // ViabilityDecision (flow analise_tecnica, decided_by analista) com número
        // TVL, encerra (em_analise→deferida) e dispara ResultadoEmitido.
        Event::fake([ResultadoEmitido::class]);

        $processo = $this->processoComFicha([
            ['cnae' => '4712100', 'status_sugerido' => 'deferida', 'status_escolhido' => 'deferida'],
            ['cnae' => '5611201', 'status_sugerido' => 'deferida', 'status_escolhido' => 'deferida'],
        ]);
        $analista = $this->analista();

        $this->actingAs($analista, 'gestao')
            ->post("/gestao/processos/{$processo->id}/decidir")
            ->assertRedirect();

        $this->assertDatabaseCount('viability_decisions', 1);

        $decision = $processo->fresh()->decision;
        $this->assertSame('analise_tecnica', $decision->flow);
        $this->assertSame(DecisionOutcome::Deferida, $decision->outcome);
        $this->assertSame($analista->id, $decision->decided_by_user_id);
        $this->assertNotNull($decision->tvl_product_number);
        $this->assertSame(ViabilityRequestStatus::Deferida, $processo->fresh()->status);

        Event::assertDispatched(ResultadoEmitido::class);
    }

    public function test_decide_indefere_quando_a_ficha_tem_cnae_indeferida(): void
    {
        // HU-087 RN-004: basta uma CNAE indeferida → INDEFERE o processo, sem TVL.
        Event::fake([ResultadoEmitido::class]);

        $processo = $this->processoComFicha([
            ['cnae' => '4712100', 'status_sugerido' => 'deferida', 'status_escolhido' => 'deferida'],
            ['cnae' => '5611201', 'status_sugerido' => 'deferida', 'status_escolhido' => 'indeferida'],
        ]);

        $this->actingAs($this->analista(), 'gestao')
            ->post("/gestao/processos/{$processo->id}/decidir")
            ->assertRedirect();

        $decision = $processo->fresh()->decision;
        $this->assertSame(DecisionOutcome::Indeferida, $decision->outcome);
        $this->assertNull($decision->tvl_product_number);
        $this->assertSame(ViabilityRequestStatus::Indeferida, $processo->fresh()->status);

        Event::assertDispatched(ResultadoEmitido::class);
    }

    public function test_recusa_decidir_quando_a_ficha_esta_em_rascunho_com_422(): void
    {
        // HU-086 CA-03: ficha em rascunho não decide — 422 (DomainException do
        // serviço traduzida pelo controller); nada gravado, status preservado.
        Event::fake([ResultadoEmitido::class]);

        $processo = $this->processoComFicha(
            [['cnae' => '4712100', 'status_sugerido' => 'deferida', 'status_escolhido' => 'deferida']],
            ['status' => AnalysisRecordStatus::Rascunho, 'finalized_at' => null],
        );

        $this->actingAs($this->analista(), 'gestao')
            ->post("/gestao/processos/{$processo->id}/decidir")
            ->assertStatus(422);

        $this->assertDatabaseCount('viability_decisions', 0);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $processo->fresh()->status);
        Event::assertNotDispatched(ResultadoEmitido::class);
    }
}

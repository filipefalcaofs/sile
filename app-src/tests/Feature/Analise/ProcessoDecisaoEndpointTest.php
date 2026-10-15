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
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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
    use LazilyRefreshDatabase;

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

    public function test_recusa_decidir_quando_a_ficha_esta_em_rascunho_com_erro_inline(): void
    {
        // HU-086 CA-03 + relatório SEDUR 21/09 (item 07): ficha em rascunho não
        // decide — o erro volta INLINE (error bag da sessão), nunca a tela "Oops
        // / 422"; nada gravado, status preservado.
        Event::fake([ResultadoEmitido::class]);

        $processo = $this->processoComFicha(
            [['cnae' => '4712100', 'status_sugerido' => 'deferida', 'status_escolhido' => 'deferida']],
            ['status' => AnalysisRecordStatus::Rascunho, 'finalized_at' => null],
        );

        $this->actingAs($this->analista(), 'gestao')
            ->post("/gestao/processos/{$processo->id}/decidir")
            ->assertSessionHasErrors(['decisao']);

        $this->assertDatabaseCount('viability_decisions', 0);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $processo->fresh()->status);
        Event::assertNotDispatched(ResultadoEmitido::class);
    }

    public function test_concluir_processo_a_partir_do_rascunho_defere_e_encerra(): void
    {
        Event::fake([ResultadoEmitido::class]);

        $processo = $this->processoComFicha(
            [['cnae' => '4712100', 'status_sugerido' => 'deferida', 'status_escolhido' => 'deferida']],
            [
                'status' => AnalysisRecordStatus::Rascunho,
                'finalized_at' => null,
                'parecer' => 'Rascunho do motor — revisar antes de finalizar.',
            ],
        );

        $this->actingAs($this->analista(), 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/ficha/concluir-processo")
            ->assertOk()
            ->assertJsonPath('outcome', 'deferida')
            ->assertJsonPath('processo_status', 'deferida');

        $this->assertSame(AnalysisRecordStatus::Finalizada, $processo->fresh()->currentAnalysisRecord->status);
        $this->assertSame(ViabilityRequestStatus::Deferida, $processo->fresh()->status);
        $this->assertNotNull($processo->fresh()->decision?->tvl_product_number);
        Event::assertDispatched(ResultadoEmitido::class);
    }

    public function test_concluir_processo_indefere_quando_a_ficha_indefere(): void
    {
        Event::fake([ResultadoEmitido::class]);

        $processo = $this->processoComFicha(
            [['cnae' => '4712100', 'status_sugerido' => 'indeferida', 'status_escolhido' => 'indeferida']],
            ['status' => AnalysisRecordStatus::Rascunho, 'finalized_at' => null],
        );

        $this->actingAs($this->analista(), 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/ficha/concluir-processo")
            ->assertOk()
            ->assertJsonPath('outcome', 'indeferida');

        $this->assertSame(ViabilityRequestStatus::Indeferida, $processo->fresh()->status);
        $this->assertNull($processo->fresh()->decision?->tvl_product_number);
    }

    public function test_concluir_processo_recusa_cnae_sem_escolha_do_analista(): void
    {
        // Relatório SEDUR 21/09 (item 07): o 422 vira erro de validação ACIONÁVEL
        // — o corpo lista quais CNAEs ficaram sem decisão, nunca a tela "Oops".
        Event::fake([ResultadoEmitido::class]);

        $processo = $this->processoComFicha(
            [['cnae' => '4712100', 'cnae_formatado' => '4712-1/00', 'status_sugerido' => null, 'status_escolhido' => null]],
            ['status' => AnalysisRecordStatus::Rascunho, 'finalized_at' => null],
        );

        $response = $this->actingAs($this->analista(), 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/ficha/concluir-processo")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ficha']);

        $this->assertStringContainsString('4712-1/00', (string) $response->json('errors.ficha.0'));

        $this->assertDatabaseCount('viability_decisions', 0);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $processo->fresh()->status);
        Event::assertNotDispatched(ResultadoEmitido::class);
    }

    public function test_decidir_com_cnae_sem_escolha_volta_com_erro_inline_e_nao_pagina_de_erro(): void
    {
        // Relatório SEDUR 21/09 (item 07): decidir com atividade sem escolha não
        // pode devolver a tela "Oops / 422" — o analista recebe o erro na própria
        // tela (error bag da sessão) com a atividade pendente identificada.
        Event::fake([ResultadoEmitido::class]);

        $processo = $this->processoComFicha([
            ['cnae' => '8211300', 'cnae_formatado' => '8211-3/00', 'status_sugerido' => null, 'status_escolhido' => null],
        ]);

        $response = $this->actingAs($this->analista(), 'gestao')
            ->post("/gestao/processos/{$processo->id}/decidir");

        $response->assertSessionHasErrors(['decisao']);
        $this->assertStringContainsString(
            '8211-3/00',
            (string) session('errors')->getBag('default')->first('decisao'),
        );

        $this->assertDatabaseCount('viability_decisions', 0);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $processo->fresh()->status);
        Event::assertNotDispatched(ResultadoEmitido::class);
    }
}

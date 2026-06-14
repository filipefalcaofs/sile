<?php

namespace Tests\Feature\Expresso;

use App\Enums\DecisionOutcome;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Registro IMUTÁVEL da decisão expressa (HU-076/078): 1:1 com a solicitação,
 * casts jsonb→array para per_cnae/rules_versions/fundamentacao (RN-005/009),
 * número TVL único preenchido SÓ no deferimento (RN-007) e decided_by_user_id
 * null = decisão do sistema. A concorrência do TVL é provada no @group postgis.
 */
class ViabilityDecisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_deferida_grava_tvl_e_casts_array(): void
    {
        $decision = ViabilityDecision::factory()->create();

        $this->assertSame(DecisionOutcome::Deferida, $decision->outcome);
        $this->assertSame('expresso', $decision->flow);
        $this->assertSame('permitido', $decision->consolidated_result);
        $this->assertNotNull($decision->tvl_product_number);
        $this->assertStringStartsWith('TVL-', $decision->tvl_product_number);
        $this->assertIsArray($decision->per_cnae);
        $this->assertIsArray($decision->rules_versions);
        $this->assertIsArray($decision->fundamentacao);
        $this->assertInstanceOf(Carbon::class, $decision->decided_at);
        $this->assertNull($decision->decided_by_user_id);
        $this->assertTrue($decision->isDeferida());
    }

    public function test_state_indeferida_nao_tem_tvl(): void
    {
        $decision = ViabilityDecision::factory()->indeferida()->create();

        $this->assertSame(DecisionOutcome::Indeferida, $decision->outcome);
        $this->assertSame('nao_permitido', $decision->consolidated_result);
        $this->assertNull($decision->tvl_product_number);
        $this->assertFalse($decision->isDeferida());
    }

    public function test_state_sem_atuacao_bap_indefere_com_motivo(): void
    {
        $decision = ViabilityDecision::factory()->semAtuacaoBap()->create();

        $this->assertSame(DecisionOutcome::Indeferida, $decision->outcome);
        $this->assertNull($decision->tvl_product_number);
        $this->assertSame('indeferido sem atuação', $decision->reason);
    }

    public function test_decisao_e_um_para_um_com_a_solicitacao(): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create();
        ViabilityDecision::factory()->create([
            'viability_request_id' => $request->id,
            'tvl_product_number' => 'TVL-2026-000001',
        ]);

        // unique(viability_request_id): uma segunda decisão para o mesmo
        // processo é rejeitada pelo banco (registro append-only, 1:1).
        $this->expectException(QueryException::class);

        ViabilityDecision::factory()->create([
            'viability_request_id' => $request->id,
            'tvl_product_number' => 'TVL-2026-000002',
        ]);
    }

    public function test_relacoes_viability_request_e_decided_by(): void
    {
        $actor = User::factory()->create();
        $request = ViabilityRequest::factory()->protocoled()->create();
        $decision = ViabilityDecision::factory()->create([
            'viability_request_id' => $request->id,
            'decided_by_user_id' => $actor->id,
        ]);

        $this->assertTrue($request->fresh()->decision->is($decision));
        $this->assertTrue($decision->viabilityRequest->is($request));
        $this->assertTrue($decision->decidedBy->is($actor));
    }

    public function test_viability_request_casta_relogio_bap_para_datetime(): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create();
        $request->forceFill(['bap_due_at' => now(), 'bap_linked_at' => now()])->save();

        $fresh = $request->fresh();

        $this->assertInstanceOf(Carbon::class, $fresh->bap_due_at);
        $this->assertInstanceOf(Carbon::class, $fresh->bap_linked_at);
    }
}

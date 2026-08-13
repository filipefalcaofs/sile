<?php

namespace Tests\Feature\Expresso;

use App\Enums\DecisionOutcome;
use App\Http\Resources\ViabilityDecisionResource;
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

    public function test_factory_reflete_o_contrato_real_do_fluxo_expresso(): void
    {
        // O FluxoExpressoService grava fundamentacao como LISTA (array_values),
        // per_cnae com shape completo e rules_versions ANINHADO por domínio. A
        // factory DEVE refletir esse contrato real — senão mascara bugs de
        // consumo (ex.: a tela de detalhe itera fundamentacao) e viola
        // entrega-funcional (testes exercitam a lógica real).
        $decision = ViabilityDecision::factory()->create();

        $this->assertTrue(
            array_is_list($decision->fundamentacao),
            'fundamentacao deve ser uma LISTA de referências (contrato do FluxoExpressoService)'
        );
        $this->assertContainsOnly('string', $decision->fundamentacao);

        $this->assertTrue(array_is_list($decision->per_cnae), 'per_cnae deve ser uma lista');
        foreach (['cnae', 'cnae_formatado', 'is_primary', 'tendencia', 'tendencia_label', 'fluxo', 'fundamentacao'] as $chave) {
            $this->assertArrayHasKey($chave, $decision->per_cnae[0], "per_cnae deve conter '{$chave}'");
        }
        $this->assertTrue(array_is_list($decision->per_cnae[0]['fundamentacao']));

        // rules_versions é aninhado por domínio (territorio/louos/risco).
        $this->assertIsArray($decision->rules_versions['louos'] ?? null);
        $this->assertIsArray($decision->rules_versions['risco'] ?? null);
    }

    public function test_resource_entrega_fundamentacao_como_lista_mesmo_com_shape_legado(): void
    {
        // Defesa anti-quebra de SSR (HU-076 retaguarda): mesmo que um registro
        // tenha fundamentacao em shape associativo (legado/edge), o Resource
        // entrega uma LISTA (array_values) — a tela de detalhe itera sem derrubar
        // a página inteira.
        $request = ViabilityRequest::factory()->protocoled()->create();
        $decision = ViabilityDecision::factory()->create([
            'viability_request_id' => $request->id,
            'fundamentacao' => ['louos' => 'Lei nº 9.148/2016', 'risco' => 'Decreto nº 32.636/2020'],
        ]);

        $payload = ViabilityDecisionResource::make($decision->fresh())->resolve();

        $this->assertTrue(array_is_list($payload['fundamentacao']));
        $this->assertSame(['Lei nº 9.148/2016', 'Decreto nº 32.636/2020'], $payload['fundamentacao']);
        $this->assertTrue(array_is_list($payload['per_cnae']));
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

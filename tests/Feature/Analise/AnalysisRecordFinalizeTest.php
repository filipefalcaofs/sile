<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisRecordStatus;
use App\Models\AnalysisRecord;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Finalização da ficha (HU-135 RN-003) e divergências (HU-140 RN-002): ao
 * finalizar, a revisão fica IMUTÁVEL (status finalizada + finalized_at) e cada
 * campo onde o valor FINAL diverge do SUGERIDO pelo motor materializa uma
 * analysis_divergences (cnae, field, suggested_value, final_value, justification)
 * — insumo do relatório HU-145. Reeditar/recalcular depois cria uma NOVA revisão
 * (rascunho) copiada da finalizada; a decisão (deferir/indeferir) NÃO acontece
 * aqui (é 10-10). Toda a ação é gated por analisar-processos e auditada (RN-002).
 */
class AnalysisRecordFinalizeTest extends TestCase
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

    /**
     * Ficha rascunho (rev. 1) com um CNAE sugerido pelo motor e a escolha do
     * analista possivelmente divergente.
     *
     * @param  array<string, mixed>  $perCnaeOverride
     */
    private function fichaRascunho(array $perCnaeOverride = []): AnalysisRecord
    {
        $request = ViabilityRequest::factory()->protocoled()->create();

        $perCnae = array_merge([
            'cnae' => '4712100',
            'cnae_formatado' => '4712-1/00',
            'is_primary' => true,
            'status_sugerido' => 'deferida',
            'status_escolhido' => 'deferida',
        ], $perCnaeOverride);

        return AnalysisRecord::factory()->create([
            'viability_request_id' => $request->id,
            'revision' => 1,
            'status' => AnalysisRecordStatus::Rascunho,
            'per_cnae' => [$perCnae],
            'parecer' => 'Parecer em elaboração.',
        ]);
    }

    public function test_finalizar_torna_a_revisao_imutavel_e_marca_finalized_at(): void
    {
        $ficha = $this->fichaRascunho();
        $analista = $this->analista();

        $this->actingAs($analista, 'gestao')
            ->postJson("/gestao/processos/{$ficha->viability_request_id}/ficha/finalizar")
            ->assertOk();

        $ficha->refresh();
        $this->assertSame(AnalysisRecordStatus::Finalizada, $ficha->status);
        $this->assertNotNull($ficha->finalized_at);
        $this->assertSame($analista->id, $ficha->analyst_user_id);

        // RN-003: autosave numa revisão finalizada é recusado (imutável).
        $this->actingAs($analista, 'gestao')
            ->patchJson("/gestao/processos/{$ficha->viability_request_id}/ficha", [
                'parecer' => 'Tentativa de editar após finalizar.',
            ])
            ->assertStatus(422);

        $this->assertSame('Parecer em elaboração.', $ficha->fresh()->parecer);
    }

    public function test_finalizar_grava_divergencia_quando_o_final_diverge_do_sugerido(): void
    {
        $ficha = $this->fichaRascunho([
            'status_sugerido' => 'deferida',
            'status_escolhido' => 'indeferida',
            'justificativa' => 'Conflito de uso não previsto pelo motor.',
        ]);

        $this->actingAs($this->analista(), 'gestao')
            ->postJson("/gestao/processos/{$ficha->viability_request_id}/ficha/finalizar")
            ->assertOk();

        $this->assertDatabaseHas('analysis_divergences', [
            'analysis_record_id' => $ficha->id,
            'cnae' => '4712100',
            'field' => 'status',
            'suggested_value' => 'deferida',
            'final_value' => 'indeferida',
            'justification' => 'Conflito de uso não previsto pelo motor.',
        ]);
    }

    public function test_finalizar_nao_gera_divergencia_quando_concorda_com_o_motor(): void
    {
        $ficha = $this->fichaRascunho([
            'status_sugerido' => 'deferida',
            'status_escolhido' => 'deferida',
        ]);

        $this->actingAs($this->analista(), 'gestao')
            ->postJson("/gestao/processos/{$ficha->viability_request_id}/ficha/finalizar")
            ->assertOk();

        $this->assertDatabaseCount('analysis_divergences', 0);
    }

    public function test_nova_revisao_cria_revision_2_rascunho_copiando_a_finalizada(): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create();

        $finalizada = AnalysisRecord::factory()->finalizada()->create([
            'viability_request_id' => $request->id,
            'revision' => 1,
            'parecer' => 'Parecer técnico finalizado.',
            'per_cnae' => [
                ['cnae' => '4712100', 'status_sugerido' => 'deferida', 'status_escolhido' => 'deferida'],
            ],
        ]);

        $this->actingAs($this->analista(), 'gestao')
            ->postJson("/gestao/processos/{$request->id}/ficha/nova-revisao")
            ->assertOk();

        $atual = $request->fresh()->currentAnalysisRecord;
        $this->assertSame(2, $atual->revision);
        $this->assertSame(AnalysisRecordStatus::Rascunho, $atual->status);
        $this->assertNull($atual->finalized_at);
        // Copia a base da finalizada para o ajuste.
        $this->assertSame('Parecer técnico finalizado.', $atual->parecer);
        $this->assertSame('4712100', $atual->per_cnae[0]['cnae']);
        // A revisão finalizada permanece intacta (append-only).
        $this->assertSame(AnalysisRecordStatus::Finalizada, $finalizada->fresh()->status);
    }

    public function test_finalizar_e_auditado(): void
    {
        $ficha = $this->fichaRascunho();

        $this->actingAs($this->analista(), 'gestao')
            ->postJson("/gestao/processos/{$ficha->viability_request_id}/ficha/finalizar")
            ->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'analise',
            'event' => 'ficha-finalizar',
            'result' => 'sucesso',
        ]);
    }
}

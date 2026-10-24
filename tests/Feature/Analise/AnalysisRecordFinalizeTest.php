<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisRecordStatus;
use App\Models\AnalysisRecord;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Finalização da ficha (HU-126 RN-003) e divergências (HU-130 RN-002): ao
 * finalizar, a revisão fica IMUTÁVEL (status finalizada + finalized_at) e cada
 * campo onde o valor FINAL diverge do SUGERIDO pelo motor materializa uma
 * analysis_divergences (cnae, field, suggested_value, final_value, justification)
 * — insumo do relatório HU-135. Reeditar/recalcular depois cria uma NOVA revisão
 * (rascunho) copiada da finalizada; a decisão (deferir/indeferir) NÃO acontece
 * aqui (é 10-10). Toda a ação é gated por analisar-processos e auditada (RN-002).
 */
class AnalysisRecordFinalizeTest extends TestCase
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
        // HU.183: a nova revisão copia a ficha do PRÓPRIO analista.
        $analista = $this->analista();

        $finalizada = AnalysisRecord::factory()->finalizada()->create([
            'viability_request_id' => $request->id,
            'revision' => 1,
            'analyst_user_id' => $analista->id,
            'parecer' => 'Parecer técnico finalizado.',
            'analysis_reasons' => ['Motivo original'],
            'address_confirmed' => true,
            'per_cnae' => [
                ['cnae' => '4712100', 'status_sugerido' => 'deferida', 'status_escolhido' => 'deferida'],
            ],
        ]);

        $this->actingAs($analista, 'gestao')
            ->postJson("/gestao/processos/{$request->id}/ficha/nova-revisao")
            ->assertOk();

        $atual = $request->fresh()->currentAnalysisRecord;
        $this->assertSame(2, $atual->revision);
        $this->assertSame(AnalysisRecordStatus::Rascunho, $atual->status);
        $this->assertNull($atual->finalized_at);
        // Copia a base da finalizada para o ajuste.
        $this->assertSame('Parecer técnico finalizado.', $atual->parecer);
        $this->assertSame(['Motivo original'], $atual->analysis_reasons);
        $this->assertTrue($atual->address_confirmed);
        $this->assertSame('4712100', $atual->per_cnae[0]['cnae']);
        // A revisão finalizada permanece intacta (append-only).
        $this->assertSame(AnalysisRecordStatus::Finalizada, $finalizada->fresh()->status);
    }

    public function test_nova_revisao_traz_todos_os_campos_da_ficha_anterior_inclusive_sede_condicionantes_e_vagas(): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create();
        $analista = $this->analista();
        AnalysisRecord::factory()->finalizada()->create([
            'viability_request_id' => $request->id,
            'revision' => 1,
            'analyst_user_id' => $analista->id,
            'is_virtual_office_hq' => true,
            'conditions' => ['Instalar sinalização.'],
            'parking' => ['vagas' => ['requerido' => 2]],
        ]);

        $this->actingAs($analista, 'gestao')
            ->postJson("/gestao/processos/{$request->id}/ficha/nova-revisao")
            ->assertOk();

        $atual = $request->fresh()->currentAnalysisRecord;
        $this->assertTrue($atual->is_virtual_office_hq);
        $this->assertSame(['Instalar sinalização.'], $atual->conditions);
        $this->assertSame(['vagas' => ['requerido' => 2]], $atual->parking);
    }

    public function test_ficha_abre_na_revisao_mais_recente_depois_de_criar_outra(): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create();
        $analista = $this->analista();
        AnalysisRecord::factory()->finalizada()->create(['viability_request_id' => $request->id, 'revision' => 1, 'analyst_user_id' => $analista->id, 'parecer' => 'Primeira.']);

        $this->actingAs($analista, 'gestao')->postJson("/gestao/processos/{$request->id}/ficha/nova-revisao")->assertOk();

        $this->actingAs($analista, 'gestao')
            ->get("/gestao/processos/{$request->id}/ficha")
            ->assertInertia(fn (Assert $page) => $page
                ->where('ficha.revision', 2)
                ->where('ficha.editavel', true)
                ->where('ficha.parecer', 'Primeira.'));
    }

    public function test_outra_ficha_pode_ser_aberta_depois_de_cada_finalizacao_sem_impedimento(): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create();
        AnalysisRecord::factory()->finalizada()->create(['viability_request_id' => $request->id, 'revision' => 1]);
        $analista = $this->analista();

        $this->actingAs($analista, 'gestao')->postJson("/gestao/processos/{$request->id}/ficha/nova-revisao")->assertOk();
        $this->actingAs($analista, 'gestao')->postJson("/gestao/processos/{$request->id}/ficha/finalizar")->assertOk();
        $this->actingAs($analista, 'gestao')->postJson("/gestao/processos/{$request->id}/ficha/nova-revisao")->assertOk();

        $this->assertSame(3, $request->fresh()->currentAnalysisRecord->revision);
        $this->assertSame(3, AnalysisRecord::query()->where('viability_request_id', $request->id)->count());
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

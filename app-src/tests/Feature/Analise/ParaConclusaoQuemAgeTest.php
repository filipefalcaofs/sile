<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisRecordStatus;
use App\Enums\AnalysisStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisRecord;
use App\Models\Sector;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Caso BAP2601888728: a analista mandou para conclusão, outra pessoa abriu o
 * processo sem recebê-lo na distribuição, editou a ficha e devolveu para Em
 * análise. O processo ficava na caixa da analista e a ficha dela era
 * sobrescrita. Agora a ficha da analista é guardada ao ir para conclusão e quem
 * age no processo passa a ser o responsável.
 */
class ParaConclusaoQuemAgeTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function servidor(Sector $setor): User
    {
        $user = User::factory()->analistaServidor()->withAcceptedLgpdTerm()->create();
        $user->sectors()->attach($setor);

        return $user;
    }

    private function processoEmAnalise(Sector $setor, User $analista): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->create();
        $processo->forceFill([
            'sector_id' => $setor->id,
            'status' => ViabilityRequestStatus::EmAnalise,
            'analysis_status' => AnalysisStatus::EmAnalise,
            'assigned_user_id' => $analista->id,
            'assigned_at' => now()->subDay(),
            'analysis_due_at' => now()->addDays(2),
        ])->save();

        AnalysisRecord::factory()->create([
            'viability_request_id' => $processo->id,
            'analyst_user_id' => $analista->id,
            'parecer' => 'Parecer da analista.',
        ]);

        return $processo;
    }

    public function test_envio_para_conclusao_guarda_a_ficha_da_analista_e_abre_revisao_nova(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->servidor($setor);
        $processo = $this->processoEmAnalise($setor, $analista);

        // Encaminhar à chefia exige a ficha finalizada (SEDUR, 26/10/2026).
        $this->actingAs($analista, 'gestao')->postJson("/gestao/processos/{$processo->id}/ficha/finalizar")->assertOk();

        $this->actingAs($analista, 'gestao')
            ->post("/gestao/processos/{$processo->id}/status-analise", ['status' => 'para_conclusao'])
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('error');

        $revisoes = $processo->analysisRecords()->orderBy('revision')->get();
        $this->assertCount(2, $revisoes);
        $this->assertSame(AnalysisRecordStatus::Finalizada, $revisoes[0]->status);
        $this->assertSame($analista->id, $revisoes[0]->analyst_user_id);
        $this->assertSame(AnalysisRecordStatus::Rascunho, $revisoes[1]->status);
        $this->assertSame('Parecer da analista.', $revisoes[1]->parecer);
    }

    public function test_quem_conclui_edita_a_revisao_nova_sem_apagar_a_da_analista(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->servidor($setor);
        $chefe = $this->servidor($setor);
        $processo = $this->processoEmAnalise($setor, $analista);

        $this->actingAs($analista, 'gestao')->postJson("/gestao/processos/{$processo->id}/ficha/finalizar")->assertOk();
        $this->actingAs($analista, 'gestao')
            ->post("/gestao/processos/{$processo->id}/status-analise", ['status' => 'para_conclusao'])
            ->assertSessionMissing('error');

        $this->actingAs($chefe, 'gestao')
            ->patchJson("/gestao/processos/{$processo->id}/ficha", ['parecer' => 'Sugiro vistoria.'])
            ->assertOk();

        $revisoes = $processo->analysisRecords()->orderBy('revision')->get();
        $this->assertSame('Parecer da analista.', $revisoes[0]->parecer);
        $this->assertSame($analista->id, $revisoes[0]->analyst_user_id);
        $this->assertSame('Sugiro vistoria.', $revisoes[1]->parecer);
        $this->assertSame($chefe->id, $revisoes[1]->analyst_user_id);
    }

    public function test_ficha_ja_finalizada_ao_ir_para_conclusao_tambem_ganha_revisao_nova(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->servidor($setor);
        $processo = $this->processoEmAnalise($setor, $analista);
        $processo->analysisRecords()->update(['status' => AnalysisRecordStatus::Finalizada, 'finalized_at' => now()]);

        $this->actingAs($analista, 'gestao')
            ->post("/gestao/processos/{$processo->id}/status-analise", ['status' => 'para_conclusao'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $processo->analysisRecords()->count());
        $this->assertSame(AnalysisRecordStatus::Rascunho, $processo->analysisRecords()->orderByDesc('revision')->first()->status);
    }

    public function test_quem_devolve_o_para_conclusao_para_em_analise_passa_a_ser_o_responsavel(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->servidor($setor);
        $chefe = $this->servidor($setor);
        $processo = $this->processoEmAnalise($setor, $analista);
        $processo->forceFill(['analysis_status' => AnalysisStatus::ParaConclusao])->save();

        $this->actingAs($chefe, 'gestao')
            ->post("/gestao/processos/{$processo->id}/status-analise", ['status' => 'em_analise'])
            ->assertSessionHasNoErrors();

        $processo->refresh();
        $this->assertSame(AnalysisStatus::EmAnalise, $processo->analysis_status);
        $this->assertSame($chefe->id, $processo->assigned_user_id);
        $this->assertDatabaseHas('activity_log', [
            'event' => 'assumir',
            'subject_type' => $processo->getMorphClass(),
            'subject_id' => $processo->id,
        ]);
    }

    public function test_responsavel_que_age_no_proprio_processo_nao_registra_assumir(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->servidor($setor);
        $processo = $this->processoEmAnalise($setor, $analista);
        $processo->forceFill(['analysis_status' => AnalysisStatus::ParaConclusao])->save();

        $this->actingAs($analista, 'gestao')
            ->post("/gestao/processos/{$processo->id}/status-analise", ['status' => 'em_analise'])
            ->assertSessionHasNoErrors();

        $this->assertSame($analista->id, $processo->fresh()->assigned_user_id);
        $this->assertDatabaseMissing('activity_log', ['event' => 'assumir', 'subject_id' => $processo->id]);
    }

    /**
     * Lisa, 23/10: com o processo em Para conclusão, a chefia precisa conseguir
     * fazer convite e mandar para a vistoria.
     */
    public function test_chefia_em_para_conclusao_abre_convite(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->servidor($setor);
        $chefe = $this->servidor($setor);
        $processo = $this->processoEmAnalise($setor, $analista);
        $processo->forceFill(['analysis_status' => AnalysisStatus::ParaConclusao])->save();

        $this->actingAs($chefe, 'gestao')
            ->post("/gestao/processos/{$processo->id}/pendencias", ['descricao' => 'Enviar o contrato de locação.'])
            ->assertSessionHasNoErrors();

        $processo->refresh();
        $this->assertSame(ViabilityRequestStatus::EmPendencia, $processo->status);
        $this->assertSame(AnalysisStatus::EmConvite, $processo->analysis_status);
        $this->assertSame($chefe->id, $processo->assigned_user_id);
    }

    public function test_chefia_em_para_conclusao_encaminha_a_vistoria(): void
    {
        $setor = Sector::factory()->create();
        $setorVistoria = Sector::factory()->create();
        $analista = $this->servidor($setor);
        $chefe = $this->servidor($setor);
        $processo = $this->processoEmAnalise($setor, $analista);
        $processo->forceFill(['analysis_status' => AnalysisStatus::ParaConclusao])->save();

        $this->actingAs($chefe, 'gestao')
            ->post("/gestao/processos/{$processo->id}/vistoria/encaminhar", [
                'setor_vistoria_id' => $setorVistoria->id,
                'motivo' => 'Conferir a área utilizada.',
            ])
            ->assertSessionHasNoErrors();

        $processo->refresh();
        $this->assertSame(AnalysisStatus::Vistoriar, $processo->analysis_status);
        $this->assertSame($setorVistoria->id, $processo->sector_id);
    }

    /**
     * Item 8 (Lisa, 22/10): só quem conclui tira o processo de Para conclusão;
     * o analista terceirizado não devolve para Em análise.
     */
    public function test_terceirizado_nao_devolve_o_para_conclusao_para_em_analise(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->servidor($setor);
        $terceirizado = User::factory()->analista()->withAcceptedLgpdTerm()->create();
        $terceirizado->sectors()->attach($setor);
        $processo = $this->processoEmAnalise($setor, $analista);
        $processo->forceFill(['analysis_status' => AnalysisStatus::ParaConclusao])->save();

        $this->actingAs($terceirizado, 'gestao')
            ->post("/gestao/processos/{$processo->id}/status-analise", ['status' => 'em_analise', 'force' => true])
            ->assertSessionHas('error');

        $this->assertSame(AnalysisStatus::ParaConclusao, $processo->refresh()->analysis_status);
    }
}

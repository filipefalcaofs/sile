<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisStage;
use App\Enums\AnalysisStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\Sector;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Quem tramita vê para onde o processo foi: a mensagem de sucesso diz o
 * destino e a situação da análise passa a nomear quem vai concluir.
 */
class TramitacaoConfirmacaoTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function analista(Sector $setor): User
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();
        $analista->sectors()->attach($setor);

        return $analista;
    }

    private function analistaServidor(Sector $setor): User
    {
        $analista = User::factory()->analistaServidor()->withAcceptedLgpdTerm()->create();
        $analista->sectors()->attach($setor);

        return $analista;
    }

    private function processoEmAnalise(Sector $setor, User $analista): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->create();
        $processo->forceFill([
            'sector_id' => $setor->id,
            'status' => ViabilityRequestStatus::EmAnalise,
            'analysis_status' => AnalysisStatus::EmAnalise,
            'analysis_stage' => AnalysisStage::Analise,
            'assigned_user_id' => $analista->id,
            'analysis_due_at' => now()->addDays(2),
        ])->save();

        return $processo;
    }

    public function test_envio_para_conclusao_com_destinatario_e_recusado(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->analista($setor);
        $chefia = User::factory()->gestor()->withAcceptedLgpdTerm()->create(['name' => 'Maria Chefia']);
        $processo = $this->processoEmAnalise($setor, $analista);

        $this->actingAs($analista, 'gestao')
            ->post("/gestao/processos/{$processo->id}/status-analise", [
                'status' => 'para_conclusao',
                'encaminhado_para_user_id' => $chefia->id,
            ])
            ->assertSessionHasErrors('encaminhado_para_user_id')
            ->assertSessionMissing('status');

        $this->assertSame('Em análise', $processo->refresh()->rotuloStatusTramitacao());
    }

    public function test_envio_para_conclusao_sem_destinatario_diz_onde_o_processo_ficou(): void
    {
        $setor = Sector::factory()->create(['name' => 'Subcoordenadoria de Atividade']);
        $analista = $this->analista($setor);
        $processo = $this->processoEmAnalise($setor, $analista);

        $this->actingAs($analista, 'gestao')
            ->post("/gestao/processos/{$processo->id}/status-analise", ['status' => 'para_conclusao'])
            ->assertSessionHas('status', 'Processo enviado para conclusão. Ele está na aba Para conclusão da Caixa do setor Subcoordenadoria de Atividade, aguardando distribuição.');

        $this->assertSame('Para conclusão', $processo->refresh()->rotuloStatusTramitacao());
    }

    public function test_encaminhamento_confirma_usuario_e_setor_de_destino(): void
    {
        $setor = Sector::factory()->create();
        $destinoSetor = Sector::factory()->create(['name' => 'Setor de Vistoria']);
        $analista = $this->analistaServidor($setor);
        $destino = User::factory()->analista()->withAcceptedLgpdTerm()->create(['name' => 'João Destino']);
        $destino->sectors()->attach($destinoSetor);
        $processo = $this->processoEmAnalise($setor, $analista);

        $this->actingAs($analista, 'gestao')
            ->post("/gestao/processos/{$processo->id}/status-analise", [
                'status' => 'encaminhado',
                'sector_id' => $destinoSetor->id,
                'user_id' => $destino->id,
            ])
            ->assertSessionHas('status', 'Processo encaminhado para João Destino (Setor de Vistoria). Ele já está na Caixa de entrada dessa pessoa.');
    }

    public function test_demais_mudancas_mantem_a_mensagem_da_situacao(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->analista($setor);
        $processo = $this->processoEmAnalise($setor, $analista);
        $processo->forceFill(['analysis_status' => AnalysisStatus::Analisar])->save();

        $this->actingAs($analista, 'gestao')
            ->post("/gestao/processos/{$processo->id}/status-analise", ['status' => 'em_analise'])
            ->assertSessionHas('status', 'Situação da análise atualizada para Em análise.');
    }
}

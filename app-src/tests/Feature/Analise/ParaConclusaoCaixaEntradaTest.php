<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisStage;
use App\Enums\AnalysisStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\Sector;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Analise\ProcessoQueryService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Quem vai concluir recebe o processo na Caixa de entrada: o destinatário
 * escolhido no envio para conclusão, ou quem o apoio distribuiu (ou quem
 * assumiu) na aba Para conclusão. O analista que enviou deixa de vê-lo.
 */
class ParaConclusaoCaixaEntradaTest extends TestCase
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

    private function processo(Sector $setor, ?User $responsavel, AnalysisStatus $status): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->create();
        $processo->forceFill([
            'sector_id' => $setor->id,
            'status' => ViabilityRequestStatus::EmAnalise,
            'analysis_status' => $status,
            'analysis_stage' => AnalysisStage::Analise,
            'assigned_user_id' => $responsavel?->id,
            'analysis_due_at' => now()->addDays(2)->startOfSecond(),
        ])->save();

        return $processo;
    }

    /**
     * @return list<int>
     */
    private function caixaDeEntrada(User $user): array
    {
        return app(ProcessoQueryService::class)->fila($user)->pluck('viability_requests.id')->all();
    }

    public function test_destinatario_escolhido_recebe_o_processo_na_caixa_de_entrada(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->analista($setor);
        $chefia = User::factory()->gestor()->withAcceptedLgpdTerm()->create();
        $processo = $this->processo($setor, $analista, AnalysisStatus::EmAnalise);

        $this->actingAs($analista, 'gestao')
            ->post("/gestao/processos/{$processo->id}/status-analise", [
                'status' => 'para_conclusao',
                'encaminhado_para_user_id' => $chefia->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('error');

        $this->assertContains($processo->id, $this->caixaDeEntrada($chefia));
        $this->assertNotContains($processo->id, $this->caixaDeEntrada($analista));
    }

    public function test_encaminhado_para_outro_usuario_sai_da_caixa_de_quem_encaminhou(): void
    {
        $setor = Sector::factory()->create();
        $outroSetor = Sector::factory()->create();
        $analista = $this->analista($setor);
        $destino = $this->analista($outroSetor);
        $processo = $this->processo($setor, $analista, AnalysisStatus::EmAnalise);

        $this->actingAs($analista, 'gestao')
            ->post("/gestao/processos/{$processo->id}/status-analise", [
                'status' => 'encaminhado',
                'sector_id' => $outroSetor->id,
                'user_id' => $destino->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('error');

        $this->assertContains($processo->id, $this->caixaDeEntrada($destino));
        $this->assertNotContains($processo->id, $this->caixaDeEntrada($analista));
    }

    public function test_sem_destinatario_o_processo_nao_fica_na_caixa_de_ninguem(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->analista($setor);
        $processo = $this->processo($setor, $analista, AnalysisStatus::EmAnalise);

        $this->actingAs($analista, 'gestao')
            ->post("/gestao/processos/{$processo->id}/status-analise", ['status' => 'para_conclusao'])
            ->assertSessionMissing('error');

        $this->assertNotContains($processo->id, $this->caixaDeEntrada($analista));
    }

    public function test_quem_o_apoio_distribui_na_aba_para_conclusao_recebe_na_caixa_de_entrada(): void
    {
        $setor = Sector::factory()->create();
        $apoio = User::factory()->apoio()->withAcceptedLgpdTerm()->create();
        $apoio->sectors()->attach($setor);
        $quemAnalisou = $this->analista($setor);
        $destino = $this->analista($setor);
        $processo = $this->processo($setor, $quemAnalisou, AnalysisStatus::ParaConclusao);

        $this->actingAs($apoio, 'gestao')
            ->post('/gestao/caixa-setor/distribuir', [
                'request_ids' => [$processo->id],
                'analista_id' => $destino->id,
            ])
            ->assertSessionMissing('error');

        $this->assertContains($processo->id, $this->caixaDeEntrada($destino));
        $this->assertNotContains($processo->id, $this->caixaDeEntrada($quemAnalisou));
        $this->assertSame(AnalysisStatus::ParaConclusao, $processo->refresh()->analysis_status);
    }

    public function test_quem_assume_na_aba_para_conclusao_recebe_na_caixa_de_entrada(): void
    {
        $setor = Sector::factory()->create();
        $quemAnalisou = $this->analista($setor);
        $analista = $this->analista($setor);
        $processo = $this->processo($setor, $quemAnalisou, AnalysisStatus::ParaConclusao);

        $this->actingAs($analista, 'gestao')
            ->post("/gestao/caixa-setor/{$processo->id}/assumir")
            ->assertSessionMissing('error');

        $this->assertContains($processo->id, $this->caixaDeEntrada($analista));
        $this->assertNotContains($processo->id, $this->caixaDeEntrada($quemAnalisou));
    }
}

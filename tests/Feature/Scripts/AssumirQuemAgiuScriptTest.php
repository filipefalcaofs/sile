<?php

namespace Tests\Feature\Scripts;

use App\Enums\AnalysisPendencyStatus;
use App\Enums\AnalysisStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisPendency;
use App\Models\User;
use App\Models\ViabilityRequest;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Varredura dos processos que ficaram com o responsável errado antes da
 * v0.116.0: convite aberto ou Para conclusão devolvido por quem não era o
 * responsável. Simula por padrão e só grava com --aplicar.
 */
class AssumirQuemAgiuScriptTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function rodar(bool $aplicar): string
    {
        $app = app();

        ob_start();
        include base_path('scripts/assumir-quem-agiu.php');

        return (string) ob_get_clean();
    }

    private function processo(AnalysisStatus $eixo, User $responsavel): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->create(['external_reference' => 'BAP'.random_int(1000000000, 9999999999)]);
        $processo->forceFill([
            'status' => ViabilityRequestStatus::EmAnalise,
            'analysis_status' => $eixo,
            'assigned_user_id' => $responsavel->id,
            'assigned_at' => now()->subDays(2),
        ])->save();

        return $processo->fresh();
    }

    private function transicao(ViabilityRequest $processo, AnalysisStatus $de, AnalysisStatus $para, User $ator): void
    {
        $processo->analysisStatusTransitions()->create([
            'from_status' => $de,
            'to_status' => $para,
            'actor_user_id' => $ator->id,
        ]);
    }

    public function test_convite_aberto_por_outra_pessoa_passa_para_quem_abriu(): void
    {
        $suzana = User::factory()->create();
        $tina = User::factory()->create();
        $processo = $this->processo(AnalysisStatus::EmConvite, $suzana);
        AnalysisPendency::factory()->create(['viability_request_id' => $processo->id, 'requested_by_user_id' => $tina->id]);

        $this->rodar(true);

        $this->assertSame($tina->id, $processo->fresh()->assigned_user_id);
        $this->assertDatabaseHas('activity_log', ['event' => 'assumir', 'subject_id' => $processo->id]);
    }

    public function test_para_conclusao_devolvido_por_outra_pessoa_passa_para_quem_devolveu(): void
    {
        $suzana = User::factory()->create();
        $tina = User::factory()->create();
        $processo = $this->processo(AnalysisStatus::EmAnalise, $suzana);
        $this->transicao($processo, AnalysisStatus::EmAnalise, AnalysisStatus::ParaConclusao, $suzana);
        $this->transicao($processo, AnalysisStatus::ParaConclusao, AnalysisStatus::EmAnalise, $tina);

        $saida = $this->rodar(true);

        $this->assertSame($tina->id, $processo->fresh()->assigned_user_id);
        $this->assertStringContainsString($processo->external_reference, $saida);
    }

    public function test_simulacao_lista_sem_gravar(): void
    {
        $suzana = User::factory()->create();
        $tina = User::factory()->create();
        $processo = $this->processo(AnalysisStatus::EmConvite, $suzana);
        AnalysisPendency::factory()->create(['viability_request_id' => $processo->id, 'requested_by_user_id' => $tina->id]);

        $saida = $this->rodar(false);

        $this->assertStringContainsString($processo->external_reference, $saida);
        $this->assertSame($suzana->id, $processo->fresh()->assigned_user_id);
        $this->assertDatabaseMissing('activity_log', ['event' => 'assumir']);
    }

    public function test_redistribuicao_depois_da_devolucao_vale_e_o_processo_nao_e_mexido(): void
    {
        $suzana = User::factory()->create();
        $tina = User::factory()->create();
        $processo = $this->processo(AnalysisStatus::EmAnalise, $suzana);
        $this->transicao($processo, AnalysisStatus::ParaConclusao, AnalysisStatus::EmAnalise, $tina);
        $processo->forceFill(['assigned_at' => now()->addMinute()])->save();

        $this->rodar(true);

        $this->assertSame($suzana->id, $processo->fresh()->assigned_user_id);
    }

    public function test_convite_ja_respondido_tambem_passa_para_quem_abriu(): void
    {
        $suzana = User::factory()->create();
        $tina = User::factory()->create();
        $processo = $this->processo(AnalysisStatus::ConviteRespondido, $suzana);
        AnalysisPendency::factory()->create([
            'viability_request_id' => $processo->id,
            'requested_by_user_id' => $tina->id,
            'status' => AnalysisPendencyStatus::Respondida,
        ]);

        $this->rodar(true);

        $this->assertSame($tina->id, $processo->fresh()->assigned_user_id);
    }

    public function test_ignora_quem_ja_esta_certo_redistribuido_depois_e_analise_concluida(): void
    {
        $suzana = User::factory()->create();
        $tina = User::factory()->create();

        $certo = $this->processo(AnalysisStatus::EmConvite, $tina);
        AnalysisPendency::factory()->create(['viability_request_id' => $certo->id, 'requested_by_user_id' => $tina->id]);

        $redistribuido = $this->processo(AnalysisStatus::EmAnalise, $suzana);
        AnalysisPendency::factory()->create([
            'viability_request_id' => $redistribuido->id,
            'requested_by_user_id' => $tina->id,
            'status' => AnalysisPendencyStatus::Respondida,
        ]);
        $redistribuido->forceFill(['assigned_at' => now()->addMinute()])->save();

        $concluido = $this->processo(AnalysisStatus::AnaliseConcluida, $suzana);
        $this->transicao($concluido, AnalysisStatus::ParaConclusao, AnalysisStatus::EmAnalise, $tina);

        $this->rodar(true);

        $this->assertSame($tina->id, $certo->fresh()->assigned_user_id);
        $this->assertSame($suzana->id, $redistribuido->fresh()->assigned_user_id);
        $this->assertSame($suzana->id, $concluido->fresh()->assigned_user_id);
        $this->assertDatabaseMissing('activity_log', ['event' => 'assumir']);
    }
}

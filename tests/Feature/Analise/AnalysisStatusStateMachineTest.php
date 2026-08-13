<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisStatus;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Analise\AnalysisStatusStateMachine;
use App\Services\Analise\InvalidAnalysisStatusTransitionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalysisStatusStateMachineTest extends TestCase
{
    use RefreshDatabase;

    private function machine(): AnalysisStatusStateMachine
    {
        return app(AnalysisStatusStateMachine::class);
    }

    public function test_transicao_valida_grava_status_timeline_e_auditoria(): void
    {
        $request = ViabilityRequest::factory()->create();
        $request->forceFill(['analysis_status' => AnalysisStatus::EmAnalise])->save();
        $actor = User::factory()->create();

        $this->machine()->transition($request, AnalysisStatus::AnaliseConcluida, $actor, 'concluída');

        $request->refresh();
        $this->assertSame(AnalysisStatus::AnaliseConcluida, $request->analysis_status);
        $this->assertSame(AnalysisStatus::EmAnalise, $request->analysisStatusTransitions->last()->from_status);
        $this->assertSame(AnalysisStatus::AnaliseConcluida, $request->analysisStatusTransitions->last()->to_status);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'analise', 'event' => 'status-analise']);
    }

    public function test_transicao_invalida_lanca_excecao(): void
    {
        $request = ViabilityRequest::factory()->create();
        $request->forceFill(['analysis_status' => AnalysisStatus::ParaDistribuir])->save();

        $this->expectException(InvalidAnalysisStatusTransitionException::class);
        $this->machine()->transition($request, AnalysisStatus::Vistoriado, null, null);
    }

    public function test_force_ignora_o_grafo_para_override_do_gestor(): void
    {
        $request = ViabilityRequest::factory()->create();
        $request->forceFill(['analysis_status' => AnalysisStatus::ParaDistribuir])->save();
        $actor = User::factory()->create();

        $this->machine()->transition($request, AnalysisStatus::Vistoriado, $actor, 'override', force: true);

        $this->assertSame(AnalysisStatus::Vistoriado, $request->refresh()->analysis_status);
    }
}

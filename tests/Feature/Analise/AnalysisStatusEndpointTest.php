<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisStatus;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Endpoint de transição manual do status de análise (eixo operacional
 * AnalysisStatus): o controller FINO delega à AnalysisStatusStateMachine, que
 * já valida o grafo de transições e grava a timeline/auditoria. Gated por
 * analisar-processos. Transição inválida sem `force` é recusada e comunicada
 * via flash (session `error`) — nada é gravado, status preservado.
 */
class AnalysisStatusEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_analista_seta_transicao_valida(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $analista = User::factory()->analista()->create();

        $request = ViabilityRequest::factory()->create();
        $request->forceFill(['analysis_status' => AnalysisStatus::Analisar])->save();

        $this->actingAs($analista, 'gestao')
            ->post("/gestao/processos/{$request->id}/status-analise", ['status' => 'em_analise'])
            ->assertRedirect();

        $this->assertSame(AnalysisStatus::EmAnalise, $request->refresh()->analysis_status);
    }

    public function test_transicao_invalida_sem_override_falha(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $analista = User::factory()->analista()->create();

        $request = ViabilityRequest::factory()->create();
        $request->forceFill(['analysis_status' => AnalysisStatus::Analisar])->save();

        $this->actingAs($analista, 'gestao')
            ->post("/gestao/processos/{$request->id}/status-analise", ['status' => 'vistoriado'])
            ->assertSessionHas('error');

        $this->assertSame(AnalysisStatus::Analisar, $request->refresh()->analysis_status);
    }
}

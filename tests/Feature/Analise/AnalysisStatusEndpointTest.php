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

    /**
     * Estado dirigido por evento (convite_expirado) é aresta válida no grafo
     * completo da state machine, mas NÃO integra o subconjunto manual
     * (EmConvite->proximas() só oferece convite_cancelado) — o analista não
     * pode setá-lo via POST direto, só o sistema (Fases 2/3).
     */
    public function test_estado_dirigido_por_evento_recusado_manualmente(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $analista = User::factory()->analista()->create();

        $request = ViabilityRequest::factory()->create();
        $request->forceFill(['analysis_status' => AnalysisStatus::EmConvite])->save();

        $this->actingAs($analista, 'gestao')
            ->post("/gestao/processos/{$request->id}/status-analise", ['status' => 'convite_expirado'])
            ->assertSessionHas('error');

        $this->assertSame(AnalysisStatus::EmConvite, $request->refresh()->analysis_status);
    }

    /**
     * O override do gestor (`force`) ignora o subconjunto manual — permite
     * qualquer transição válida no grafo completo, mesmo fora de proximas().
     */
    public function test_gestor_com_force_ignora_subconjunto_manual(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $gestor = User::factory()->create();
        $gestor->assignRole('gestor');

        $request = ViabilityRequest::factory()->create();
        $request->forceFill(['analysis_status' => AnalysisStatus::Analisar])->save();

        $this->actingAs($gestor, 'gestao')
            ->post("/gestao/processos/{$request->id}/status-analise", [
                'status' => 'vistoriado',
                'force' => true,
            ])
            ->assertRedirect();

        $this->assertSame(AnalysisStatus::Vistoriado, $request->refresh()->analysis_status);
    }

    /**
     * `force` só vale para gestor — um analista pedindo force é ignorado e a
     * transição fora do subconjunto manual continua recusada.
     */
    public function test_force_de_nao_gestor_e_ignorado(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $analista = User::factory()->analista()->create();

        $request = ViabilityRequest::factory()->create();
        $request->forceFill(['analysis_status' => AnalysisStatus::Analisar])->save();

        $this->actingAs($analista, 'gestao')
            ->post("/gestao/processos/{$request->id}/status-analise", [
                'status' => 'vistoriado',
                'force' => true,
            ])
            ->assertSessionHas('error');

        $this->assertSame(AnalysisStatus::Analisar, $request->refresh()->analysis_status);
    }
}

<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisStatus;
use App\Models\Sector;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
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
    use LazilyRefreshDatabase;

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

    public function test_encaminhar_exige_setor_e_usuario_do_setor(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $analista = User::factory()->analista()->create();
        $destino = User::factory()->analista()->create();
        $setor = Sector::factory()->create(['name' => 'Análise Locacional', 'active' => true]);
        $destino->sectors()->attach($setor->id);

        $request = ViabilityRequest::factory()->create();
        $request->forceFill(['analysis_status' => AnalysisStatus::ParaDistribuir])->save();

        $this->actingAs($analista, 'gestao')
            ->post("/gestao/processos/{$request->id}/status-analise", ['status' => 'encaminhado'])
            ->assertSessionHasErrors(['sector_id', 'user_id']);

        $this->assertSame(AnalysisStatus::ParaDistribuir, $request->refresh()->analysis_status);

        $this->actingAs($analista, 'gestao')
            ->post("/gestao/processos/{$request->id}/status-analise", [
                'status' => 'encaminhado',
                'sector_id' => $setor->id,
                'user_id' => $destino->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $request->refresh();
        $this->assertSame(AnalysisStatus::Encaminhado, $request->analysis_status);
        $this->assertSame($setor->id, $request->sector_id);
        $this->assertSame($destino->id, $request->assigned_user_id);
    }

    public function test_processo_em_analise_pode_ser_encaminhado_a_outro_setor(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $analista = User::factory()->analista()->create();
        $destino = User::factory()->analista()->create();
        $setor = Sector::factory()->create(['name' => 'Gabinete', 'active' => true]);
        $destino->sectors()->attach($setor->id);

        foreach ([AnalysisStatus::Analisar, AnalysisStatus::EmAnalise] as $origem) {
            $request = ViabilityRequest::factory()->create();
            $request->forceFill(['analysis_status' => $origem])->save();

            $this->actingAs($analista, 'gestao')
                ->post("/gestao/processos/{$request->id}/status-analise", [
                    'status' => 'encaminhado',
                    'sector_id' => $setor->id,
                    'user_id' => $destino->id,
                ])
                ->assertSessionHasNoErrors()
                ->assertSessionMissing('error');

            $request->refresh();
            $this->assertSame(AnalysisStatus::Encaminhado, $request->analysis_status, $origem->value);
            $this->assertSame('Encaminhado para Gabinete', $request->rotuloStatusTramitacao());
        }
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
    public function test_quem_tem_permissao_de_forcar_ignora_subconjunto_manual(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $papel = Role::create(['name' => 'perfil-livre', 'guard_name' => 'web']);
        $papel->givePermissionTo(['acessar-gestao', 'analisar-processos', 'forcar-transicao-analise']);
        $usuario = User::factory()->create();
        $usuario->assignRole('perfil-livre');

        $request = ViabilityRequest::factory()->create();
        $request->forceFill(['analysis_status' => AnalysisStatus::Analisar])->save();

        $this->actingAs($usuario, 'gestao')
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
    public function test_force_sem_a_permissao_e_ignorado(): void
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

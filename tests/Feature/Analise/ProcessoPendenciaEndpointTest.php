<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisPendencyStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Endpoint de abertura de pendência pelo analista (HU-083): o controller FINO
 * orquestra o PendenciaService::abrir (10-11) — a transição em_analise→
 * em_pendencia, a auditoria e o e-mail ao requerente já vivem no serviço. Gated
 * por analisar-processos (403 auditado — CA-04); descrição é obrigatória (422 de
 * validação) e abrir fora de em_analise é recusado com 422.
 */
class ProcessoPendenciaEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();
    }

    private function analista(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    private function semAnalisarProcessos(): User
    {
        $user = User::factory()->withAcceptedLgpdTerm()->create();
        $user->givePermissionTo('acessar-gestao');

        return $user;
    }

    private function processo(ViabilityRequestStatus $status = ViabilityRequestStatus::EmAnalise): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->protocoled()->create();
        $request->forceFill(['status' => $status])->save();

        return $request->fresh();
    }

    public function test_sem_permissao_analisar_processos_recebe_403_auditado(): void
    {
        $processo = $this->processo();

        $this->actingAs($this->semAnalisarProcessos(), 'gestao')
            ->post("/gestao/processos/{$processo->id}/pendencias", ['descricao' => 'Falta o contrato social.'])
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);

        $this->assertDatabaseCount('analysis_pendencies', 0);
    }

    public function test_abre_pendencia_em_processo_em_analise(): void
    {
        $processo = $this->processo();

        $this->actingAs($this->analista(), 'gestao')
            ->post("/gestao/processos/{$processo->id}/pendencias", ['descricao' => 'Anexar o contrato social atualizado.'])
            ->assertRedirect();

        $this->assertSame(ViabilityRequestStatus::EmPendencia, $processo->fresh()->status);

        $this->assertDatabaseHas('analysis_pendencies', [
            'viability_request_id' => $processo->id,
            'description' => 'Anexar o contrato social atualizado.',
            'status' => AnalysisPendencyStatus::Aberta->value,
        ]);
    }

    public function test_descricao_e_obrigatoria(): void
    {
        $processo = $this->processo();

        $this->actingAs($this->analista(), 'gestao')
            ->post("/gestao/processos/{$processo->id}/pendencias", ['descricao' => ''])
            ->assertSessionHasErrors('descricao');

        $this->assertDatabaseCount('analysis_pendencies', 0);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $processo->fresh()->status);
    }

    public function test_abrir_fora_de_em_analise_e_recusado_com_422(): void
    {
        // CA-03: só abre pendência sobre processo em análise; deferida → 422.
        $processo = $this->processo(ViabilityRequestStatus::Deferida);

        $this->actingAs($this->analista(), 'gestao')
            ->post("/gestao/processos/{$processo->id}/pendencias", ['descricao' => 'Complementar a documentação.'])
            ->assertStatus(422);

        $this->assertDatabaseCount('analysis_pendencies', 0);
        $this->assertSame(ViabilityRequestStatus::Deferida, $processo->fresh()->status);
    }
}

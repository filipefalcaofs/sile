<?php

namespace Tests\Feature\Ia;

use App\Enums\AbuseAlertStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\PredictiveAnomaly;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Camada HTTP do painel de Auditoria Preditiva (Módulo 3): revisão das anomalias,
 * gated por gerenciar-alertas-abuso, consulta auditada (RN-002). ANTI-FACHADA:
 * confirmar/descartar muda SÓ o status da anomalia — NUNCA pune o processo. 403
 * sem permissão é auditado no ponto único (CA-04).
 */
class AuditoriaPreditivaControllerTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function auditor(): User
    {
        $user = User::factory()->withAcceptedLgpdTerm()->create();
        $user->givePermissionTo(['acessar-gestao', 'gerenciar-alertas-abuso']);

        return $user;
    }

    private function semPermissao(): User
    {
        $user = User::factory()->withAcceptedLgpdTerm()->create();
        $user->givePermissionTo('acessar-gestao');

        return $user;
    }

    private function processoDeferido(): ViabilityRequest
    {
        $protocolo = 'VIA-'.now()->year.'-'.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT);

        return ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::Deferida,
            'protocol_number' => $protocolo,
            'protocoled_at' => now(),
        ]);
    }

    public function test_sem_permissao_recebe_403_auditado(): void
    {
        $this->actingAs($this->semPermissao(), 'gestao')
            ->get('/gestao/auditoria-preditiva')
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);
    }

    public function test_index_renderiza_anomalias_e_efetividade_auditado(): void
    {
        PredictiveAnomaly::factory()->confirmada()->create(['viability_request_id' => $this->processoDeferido()->id]);
        PredictiveAnomaly::factory()->create(['viability_request_id' => $this->processoDeferido()->id]);

        $response = $this->actingAs($this->auditor(), 'gestao')
            ->get('/gestao/auditoria-preditiva')
            ->assertOk();

        $page = $response->viewData('page');
        $this->assertSame('gestao/auditoria-preditiva/index', $page['component']);

        $props = $page['props'];
        $this->assertCount(2, $props['anomalias']['data']);
        $this->assertSame(2, $props['efetividade']['gerados']);
        $this->assertSame(1, $props['efetividade']['confirmados']);
        $this->assertSame(50.0, $props['efetividade']['taxa']);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'ia',
            'event' => 'consulta-auditoria-preditiva',
            'result' => 'sucesso',
        ]);
    }

    public function test_confirmar_muda_status_da_anomalia_sem_punir_o_processo(): void
    {
        $processo = $this->processoDeferido();
        $anomalia = PredictiveAnomaly::factory()->create([
            'viability_request_id' => $processo->id,
            'status' => AbuseAlertStatus::Aberto,
        ]);

        $auditor = $this->auditor();

        $this->actingAs($auditor, 'gestao')
            ->post("/gestao/auditoria-preditiva/{$anomalia->id}/confirmar", ['justification' => 'Reincidência confirmada manualmente.'])
            ->assertRedirect();

        $anomalia->refresh();
        $this->assertSame(AbuseAlertStatus::Confirmado, $anomalia->status);
        $this->assertSame($auditor->id, $anomalia->resolved_by_user_id);

        // ANTI-FACHADA: o processo NÃO é punido — status permanece deferido.
        $this->assertSame(ViabilityRequestStatus::Deferida, $processo->fresh()->status);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'ia',
            'event' => 'confirmar-anomalia-preditiva',
            'result' => 'sucesso',
        ]);
    }

    public function test_confirmar_exige_justificativa(): void
    {
        $anomalia = PredictiveAnomaly::factory()->create(['viability_request_id' => $this->processoDeferido()->id]);

        $this->actingAs($this->auditor(), 'gestao')
            ->post("/gestao/auditoria-preditiva/{$anomalia->id}/confirmar", ['justification' => '   '])
            ->assertSessionHasErrors('justification');

        $this->assertSame(AbuseAlertStatus::Aberto, $anomalia->fresh()->status);
    }

    public function test_descartar_marca_como_descartada(): void
    {
        $anomalia = PredictiveAnomaly::factory()->create([
            'viability_request_id' => $this->processoDeferido()->id,
            'status' => AbuseAlertStatus::Aberto,
        ]);

        $this->actingAs($this->auditor(), 'gestao')
            ->post("/gestao/auditoria-preditiva/{$anomalia->id}/descartar", ['justification' => 'Falso positivo.'])
            ->assertRedirect();

        $this->assertSame(AbuseAlertStatus::Descartado, $anomalia->fresh()->status);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'ia',
            'event' => 'descartar-anomalia-preditiva',
            'result' => 'sucesso',
        ]);
    }
}

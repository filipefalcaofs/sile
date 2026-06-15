<?php

namespace Tests\Feature\Analise;

use App\Enums\ViabilityRequestStatus;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Endpoint de encaminhamento à malha fina (HU-136): o controller FINO orquestra
 * o MalhaFinaService::encaminharLote (10-12) — single é um lote de um. O
 * encaminhamento é ORTOGONAL ao status (RN-001): funciona em qualquer situação,
 * inclusive deferida, ligando in_fine_mesh + gravando fine_mesh_referrals SEM
 * transicionar o status. Gated por encaminhar-malha-fina (403 auditado — CA-04);
 * o motivo é obrigatório (RN-002).
 */
class MalhaFinaEndpointTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function analista(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    private function semEncaminharMalhaFina(): User
    {
        $user = User::factory()->withAcceptedLgpdTerm()->create();
        $user->givePermissionTo('acessar-gestao');

        return $user;
    }

    private function processo(ViabilityRequestStatus $status = ViabilityRequestStatus::EmAnalise): ViabilityRequest
    {
        $protocolo = 'VIA-'.now()->year.'-'.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT);

        $request = ViabilityRequest::factory()->create([
            'status' => $status,
            'protocol_number' => $protocolo,
            'protocoled_at' => now(),
        ]);

        return $request->fresh();
    }

    public function test_sem_permissao_encaminhar_malha_fina_recebe_403_auditado(): void
    {
        $processo = $this->processo();

        $this->actingAs($this->semEncaminharMalhaFina(), 'gestao')
            ->post('/gestao/processos/malha-fina', [
                'request_id' => $processo->id,
                'motivo' => 'Reavaliar o enquadramento.',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);

        $this->assertDatabaseCount('fine_mesh_referrals', 0);
    }

    public function test_encaminha_single_em_analise(): void
    {
        $processo = $this->processo();

        $this->actingAs($this->analista(), 'gestao')
            ->post('/gestao/processos/malha-fina', [
                'request_id' => $processo->id,
                'motivo' => 'Divergência na metragem informada.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('fine_mesh_referrals', [
            'viability_request_id' => $processo->id,
            'reason' => 'Divergência na metragem informada.',
            'resolved_at' => null,
        ]);

        $this->assertTrue((bool) $processo->fresh()->in_fine_mesh);
    }

    public function test_encaminha_em_lote_incluindo_processo_deferido(): void
    {
        // RN-001/RN-004: o lote aplica o mesmo motivo a vários processos e atinge
        // até o DEFERIDO (corrige o bug legado), sem mexer no status.
        $emAnalise = $this->processo();
        $emPendencia = $this->processo(ViabilityRequestStatus::EmPendencia);
        $deferido = $this->processo(ViabilityRequestStatus::Deferida);

        $this->actingAs($this->analista(), 'gestao')
            ->post('/gestao/processos/malha-fina', [
                'request_ids' => [$emAnalise->id, $emPendencia->id, $deferido->id],
                'motivo' => 'Auditoria por amostragem.',
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('fine_mesh_referrals', 3);

        foreach ([$emAnalise, $emPendencia, $deferido] as $processo) {
            $this->assertTrue((bool) $processo->fresh()->in_fine_mesh);
        }

        // RN-001: o deferido continua deferido — a malha fina não muda o desfecho.
        $this->assertSame(ViabilityRequestStatus::Deferida, $deferido->fresh()->status);
    }

    public function test_motivo_e_obrigatorio(): void
    {
        $processo = $this->processo();

        $this->actingAs($this->analista(), 'gestao')
            ->post('/gestao/processos/malha-fina', [
                'request_id' => $processo->id,
                'motivo' => '',
            ])
            ->assertSessionHasErrors('motivo');

        $this->assertDatabaseCount('fine_mesh_referrals', 0);
        $this->assertFalse((bool) $processo->fresh()->in_fine_mesh);
    }
}

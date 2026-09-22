<?php

namespace Tests\Feature\Analise;

use App\Enums\ViabilityRequestStatus;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Analise\MalhaFinaService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Conclusão pela Caixa de Malha Fina: POST .../concluir baixa todos os
 * encaminhamentos abertos do processo (resolverAbertos), com observação
 * OPCIONAL, SEM mudar o status (ortogonal — RN-001). Gated por
 * analisar-malha-fina: quem só encaminha (encaminhar-malha-fina — analista)
 * NÃO conclui (403 auditado no ponto único).
 */
class MalhaFinaConcluirTest extends TestCase
{
    use LazilyRefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function revisor(): User
    {
        return User::factory()->gestor()->withAcceptedLgpdTerm()->create();
    }

    private function processoEmMalhaFina(User $ator): ViabilityRequest
    {
        $protocolo = 'VIA-'.now()->year.'-'.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT);

        $processo = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::EmAnalise,
            'protocol_number' => $protocolo,
            'protocoled_at' => now(),
        ]);

        app(MalhaFinaService::class)->encaminhar($processo, $ator, 'Revisão de enquadramento.');

        return $processo->fresh();
    }

    public function test_conclui_a_malha_fina_com_observacao_opcional(): void
    {
        $revisor = $this->revisor();
        $processo = $this->processoEmMalhaFina($revisor);

        $this->actingAs($revisor, 'gestao')
            ->post("/gestao/malha-fina/{$processo->id}/concluir", [
                'observacao' => 'Enquadramento confirmado.',
            ])
            ->assertRedirect();

        $processo->refresh();
        $this->assertFalse((bool) $processo->in_fine_mesh);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $processo->status);

        $this->assertDatabaseHas('fine_mesh_referrals', [
            'viability_request_id' => $processo->id,
            'resolved_by_user_id' => $revisor->id,
            'resolution_note' => 'Enquadramento confirmado.',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'analise',
            'event' => 'malha-fina-resolver',
        ]);
    }

    public function test_conclui_sem_observacao(): void
    {
        $revisor = $this->revisor();
        $processo = $this->processoEmMalhaFina($revisor);

        $this->actingAs($revisor, 'gestao')
            ->post("/gestao/malha-fina/{$processo->id}/concluir", [])
            ->assertRedirect();

        $this->assertFalse((bool) $processo->fresh()->in_fine_mesh);

        $this->assertDatabaseHas('fine_mesh_referrals', [
            'viability_request_id' => $processo->id,
            'resolved_by_user_id' => $revisor->id,
            'resolution_note' => null,
        ]);
    }

    public function test_concluir_processo_fora_da_malha_fina_avisa_sem_falhar(): void
    {
        $revisor = $this->revisor();
        $protocolo = 'VIA-'.now()->year.'-'.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT);
        $processo = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::EmAnalise,
            'protocol_number' => $protocolo,
            'protocoled_at' => now(),
        ]);

        $this->actingAs($revisor, 'gestao')
            ->post("/gestao/malha-fina/{$processo->id}/concluir", [])
            ->assertRedirect()
            ->assertSessionHas('warning');
    }

    public function test_analista_sem_analisar_malha_fina_recebe_403_auditado(): void
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();
        $processo = $this->processoEmMalhaFina($analista);

        $this->actingAs($analista, 'gestao')
            ->post("/gestao/malha-fina/{$processo->id}/concluir", [])
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);

        $this->assertTrue((bool) $processo->fresh()->in_fine_mesh);
    }
}

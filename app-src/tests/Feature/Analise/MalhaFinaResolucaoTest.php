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
 * Baixa da malha fina (Caixa de Malha Fina): resolver() grava quem baixou
 * (resolved_by_user_id) e a observação OPCIONAL (resolution_note), audita por
 * encaminhamento e mantém o invariante da flag in_fine_mesh — SEM tocar no
 * status do processo (ortogonal, RN-001). resolverAbertos() baixa todos os
 * encaminhamentos abertos do processo (a caixa lista processos, não
 * encaminhamentos).
 */
class MalhaFinaResolucaoTest extends TestCase
{
    use LazilyRefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function ator(): User
    {
        return User::factory()->create();
    }

    private function processo(ViabilityRequestStatus $status = ViabilityRequestStatus::EmAnalise): ViabilityRequest
    {
        $protocolo = 'VIA-'.now()->year.'-'.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT);

        return ViabilityRequest::factory()->create([
            'status' => $status,
            'protocol_number' => $protocolo,
            'protocoled_at' => now(),
        ])->fresh();
    }

    public function test_resolver_grava_quem_baixou_e_a_observacao_e_audita(): void
    {
        $ator = $this->ator();
        $processo = $this->processo();
        $referral = app(MalhaFinaService::class)->encaminhar($processo, $ator, 'Divergência de metragem.');

        app(MalhaFinaService::class)->resolver($referral, $ator, 'Conferido em campo: metragem correta.');

        $referral->refresh();
        $this->assertNotNull($referral->resolved_at);
        $this->assertSame($ator->id, $referral->resolved_by_user_id);
        $this->assertSame('Conferido em campo: metragem correta.', $referral->resolution_note);
        $this->assertSame($ator->id, $referral->resolvedBy?->id);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'analise',
            'event' => 'malha-fina-resolver',
        ]);

        $this->assertFalse((bool) $processo->fresh()->in_fine_mesh);
    }

    public function test_resolver_sem_observacao_grava_nota_nula(): void
    {
        $ator = $this->ator();
        $processo = $this->processo();
        $referral = app(MalhaFinaService::class)->encaminhar($processo, $ator, 'Revisão de rotina.');

        app(MalhaFinaService::class)->resolver($referral, $ator);

        $referral->refresh();
        $this->assertNotNull($referral->resolved_at);
        $this->assertSame($ator->id, $referral->resolved_by_user_id);
        $this->assertNull($referral->resolution_note);
    }

    public function test_resolver_abertos_baixa_todos_e_desliga_a_flag_sem_mudar_o_status(): void
    {
        $ator = $this->ator();
        $processo = $this->processo(ViabilityRequestStatus::Deferida);
        $service = app(MalhaFinaService::class);
        $service->encaminhar($processo, $ator, 'Primeiro motivo.');
        $service->encaminhar($processo, $ator, 'Segundo motivo.');

        $baixados = $service->resolverAbertos($processo->fresh(), $ator, 'Baixa em conjunto.');

        $this->assertSame(2, $baixados);
        $this->assertSame(0, $processo->fresh()->fineMeshReferrals()->whereNull('resolved_at')->count());
        $this->assertFalse((bool) $processo->fresh()->in_fine_mesh);
        $this->assertSame(ViabilityRequestStatus::Deferida, $processo->fresh()->status);

        $this->assertDatabaseHas('fine_mesh_referrals', [
            'viability_request_id' => $processo->id,
            'resolution_note' => 'Baixa em conjunto.',
            'resolved_by_user_id' => $ator->id,
        ]);
    }

    public function test_resolver_abertos_sem_encaminhamento_aberto_retorna_zero(): void
    {
        $ator = $this->ator();
        $processo = $this->processo();

        $baixados = app(MalhaFinaService::class)->resolverAbertos($processo, $ator);

        $this->assertSame(0, $baixados);
        $this->assertFalse((bool) $processo->fresh()->in_fine_mesh);
    }
}

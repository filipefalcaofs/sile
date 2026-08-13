<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Enums\CommunicationType;
use App\Models\Communication;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Models\VirtualOfficeInscriptionLock;
use App\Services\EscritorioVirtual\DesvincularInscricaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Tests\TestCase;

/**
 * Desvinculação da inscrição da sede (RN-EV-06): desativa o lock e notifica cada
 * abrigado (sem cassação). O vínculo do abrigado é derivado do lock ativo.
 */
class DesvincularInscricaoTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function abrigado(string $inscricao): ViabilityRequest
    {
        $requester = User::factory()->create();
        $abrigado = ViabilityRequest::factory()->protocoled()->create([
            'property_registration' => $inscricao,
            'requester_user_id' => $requester->id,
            'protocol_number' => 'VIA-2026-'.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT),
        ]);
        ViabilityDecision::factory()->create([
            'viability_request_id' => $abrigado->id,
            'is_virtual_office_tenant' => true,
            'tvl_product_number' => 'TVL-2026-'.str_pad((string) $this->seq, 6, '0', STR_PAD_LEFT),
        ]);

        return $abrigado;
    }

    public function test_desvincular_desativa_lock_e_notifica_abrigados(): void
    {
        NotificationFacade::fake();

        $sede = ViabilityRequest::factory()->create(['property_registration' => '123.456.789']);
        $lock = VirtualOfficeInscriptionLock::create([
            'property_registration' => '123.456.789',
            'sede_viability_request_id' => $sede->id,
            'active' => true,
            'locked_at' => now(),
        ]);
        $this->abrigado('123.456.789');
        $this->abrigado('123.456.789');

        $resultado = app(DesvincularInscricaoService::class)
            ->desvincular($lock, 'Sede mudou de endereço.', User::factory()->create());

        $this->assertSame(2, $resultado['abrigados_notificados']);

        $lock->refresh();
        $this->assertFalse($lock->active);
        $this->assertNotNull($lock->released_at);
        $this->assertFalse(VirtualOfficeInscriptionLock::ativoPara('123.456.789'));

        // Os 2 abrigados foram notificados (ledger multicanal do dispatcher).
        $this->assertSame(2, Communication::query()
            ->where('type', CommunicationType::Resultado->value)
            ->distinct()->count('viability_request_id'));

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'escritorio-virtual',
            'event' => 'ev-desvinculacao',
        ]);
    }

    public function test_desvincular_sem_abrigados_apenas_libera_o_lock(): void
    {
        NotificationFacade::fake();

        $sede = ViabilityRequest::factory()->create(['property_registration' => '999']);
        $lock = VirtualOfficeInscriptionLock::create([
            'property_registration' => '999',
            'sede_viability_request_id' => $sede->id,
            'active' => true,
            'locked_at' => now(),
        ]);

        $resultado = app(DesvincularInscricaoService::class)->desvincular($lock, 'sem abrigados');

        $this->assertSame(0, $resultado['abrigados_notificados']);
        $this->assertFalse($lock->refresh()->active);
    }
}

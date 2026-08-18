<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Models\VirtualOfficeInscriptionLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Escritório virtual — resolver da sede ativa (RN-EV-03): a partir da
 * inscrição imobiliária do abrigado, `sedeAtiva()` deve devolver o lock ATIVO
 * com a sede (ViabilityRequest) e a decisão da sede (com o TVL) já carregadas,
 * para os consumidores M2 alcançarem o TVL sem N+1.
 */
class SedeAtivaResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_sede_ativa_resolve_lock_com_sede_e_tvl(): void
    {
        $sede = ViabilityRequest::factory()->protocoled()->create([
            'property_registration' => '123',
        ]);
        $decision = ViabilityDecision::factory()->create([
            'viability_request_id' => $sede->id,
        ]);

        VirtualOfficeInscriptionLock::create([
            'property_registration' => '123',
            'sede_viability_request_id' => $sede->id,
            'active' => true,
            'locked_at' => now(),
        ]);

        $lock = VirtualOfficeInscriptionLock::sedeAtiva('123');

        $this->assertNotNull($lock);
        $this->assertSame($sede->id, $lock->sede->id);
        $this->assertSame($decision->tvl_product_number, $lock->sede->decision->tvl_product_number);
        $this->assertNotNull($lock->sede->decision->tvl_product_number);
    }

    public function test_sede_ativa_nula_para_inscricao_sem_lock(): void
    {
        $this->assertNull(VirtualOfficeInscriptionLock::sedeAtiva('999'));
    }

    public function test_sede_ativa_nula_quando_lock_inativo(): void
    {
        $sede = ViabilityRequest::factory()->protocoled()->create([
            'property_registration' => '123',
        ]);
        ViabilityDecision::factory()->create([
            'viability_request_id' => $sede->id,
        ]);

        VirtualOfficeInscriptionLock::create([
            'property_registration' => '123',
            'sede_viability_request_id' => $sede->id,
            'active' => false,
            'locked_at' => now(),
            'released_at' => now(),
        ]);

        $this->assertNull(VirtualOfficeInscriptionLock::sedeAtiva('123'));
    }
}

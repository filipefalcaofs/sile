<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Models\ViabilityRequest;
use App\Models\VirtualOfficeInscriptionLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InscriptionLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_scope_ativo_por_inscricao(): void
    {
        $sede = ViabilityRequest::factory()->create();
        VirtualOfficeInscriptionLock::create([
            'property_registration' => '123.456.789',
            'sede_viability_request_id' => $sede->id,
            'active' => true,
            'locked_at' => now(),
        ]);

        $this->assertTrue(VirtualOfficeInscriptionLock::ativoPara('123.456.789'));
        $this->assertFalse(VirtualOfficeInscriptionLock::ativoPara('000.000.000'));
    }
}

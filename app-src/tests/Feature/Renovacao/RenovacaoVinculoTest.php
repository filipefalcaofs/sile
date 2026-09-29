<?php

namespace Tests\Feature\Renovacao;

use App\Models\ViabilityRequest;
use App\Models\ViabilityServiceType;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RenovacaoVinculoTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_renovacao_aponta_para_a_solicitacao_de_origem(): void
    {
        $origem = ViabilityRequest::factory()->create();
        $tipo = ViabilityServiceType::factory()->create(['code' => 'renovacao-tvl']);

        $renovacao = ViabilityRequest::factory()->create([
            'service_type_id' => $tipo->id,
            'renews_viability_request_id' => $origem->id,
        ]);

        $this->assertTrue($renovacao->isRenewal());
        $this->assertSame($origem->id, $renovacao->renews->id);
        $this->assertFalse($origem->isRenewal());
    }
}

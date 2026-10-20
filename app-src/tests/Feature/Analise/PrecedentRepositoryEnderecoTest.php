<?php

namespace Tests\Feature\Analise;

use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Analise\PostgisPrecedentRepository;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Precedentes do imóvel pelo endereço (caminho sem polígono, que roda sem
 * PostGIS): trazem o BAP de cada processo decidido e não listam o próprio
 * processo da ficha.
 */
class PrecedentRepositoryEnderecoTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function decidido(?string $bap): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->create([
            'address_street' => 'Rua do Jaracatiá',
            'address_number' => '106',
            'external_reference' => $bap,
        ]);
        ViabilityDecision::factory()->create(['viability_request_id' => $processo->id, 'tvl_product_number' => 'TVL-'.$processo->id]);

        return $processo;
    }

    public function test_traz_o_bap_e_exclui_o_proprio_processo(): void
    {
        $atual = $this->decidido('BAP-ATUAL');
        $anterior = $this->decidido('BAP2601853153');
        $semBap = $this->decidido(null);

        $linhas = app(PostgisPrecedentRepository::class)
            ->propertyPrecedents([], 10, 'Rua do Jaracatiá', '106', $atual->id);

        $porId = collect($linhas)->keyBy('viability_request_id');

        $this->assertFalse($porId->has($atual->id));
        $this->assertSame('BAP2601853153', $porId[$anterior->id]['bap']);
        $this->assertNull($porId[$semBap->id]['bap']);
    }
}

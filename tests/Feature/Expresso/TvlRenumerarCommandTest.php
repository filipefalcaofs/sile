<?php

namespace Tests\Feature\Expresso;

use App\Models\TvlSequence;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Expresso\TvlNumberGenerator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TvlRenumerarCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function decisao(string $tvl, string $decididaEm, ?string $sede = null): ViabilityDecision
    {
        return ViabilityDecision::factory()->create([
            'viability_request_id' => ViabilityRequest::factory()->protocoled()->create(['protocol_number' => 'VIA-2026-'.fake()->unique()->numerify('######')]),
            'tvl_product_number' => $tvl,
            'decided_at' => $decididaEm,
            'virtual_office_hq_tvl_number' => $sede,
        ]);
    }

    public function test_simulacao_nao_grava(): void
    {
        $this->decisao('TVL-2026-000001', '2026-01-10');

        $this->artisan('tvl:renumerar')->assertSuccessful();

        $this->assertSame('TVL-2026-000001', ViabilityDecision::query()->sole()->tvl_product_number);
        $this->assertSame(0, TvlSequence::query()->count());
    }

    public function test_renumera_na_ordem_da_decisao_e_acompanha_o_vinculo_da_sede(): void
    {
        Storage::fake('local');
        $b = $this->decisao('TVL-2027-000001', '2027-02-01');
        $a = $this->decisao('TVL-2026-000007', '2026-03-01');
        $inquilina = $this->decisao('TVL-2026-000008', '2026-04-01', sede: 'TVL-2026-000007');

        $this->artisan('tvl:renumerar', ['--executar' => true])->assertSuccessful();

        $this->assertSame('TVL-3000000', $a->fresh()->tvl_product_number);
        $this->assertSame('TVL-3000001', $inquilina->fresh()->tvl_product_number);
        $this->assertSame('TVL-3000002', $b->fresh()->tvl_product_number);
        $this->assertSame('TVL-3000000', $inquilina->fresh()->virtual_office_hq_tvl_number);
        $this->assertSame(3000000, $a->fresh()->numeroSefaz());
        $this->assertCount(1, Storage::disk('local')->files('tvl-renumeracao'));
    }

    public function test_proximo_tvl_continua_do_contador_e_a_rodada_e_idempotente(): void
    {
        Storage::fake('local');
        $this->decisao('TVL-2026-000001', '2026-01-10');

        $this->artisan('tvl:renumerar', ['--executar' => true])->assertSuccessful();
        $this->artisan('tvl:renumerar', ['--executar' => true])->assertSuccessful();

        $this->assertSame('TVL-3000000', ViabilityDecision::query()->sole()->tvl_product_number);
        $this->assertSame('TVL-3000001', (new TvlNumberGenerator)->generate());
    }
}

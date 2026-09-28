<?php

namespace Tests\Feature\Renovacao;

use App\Enums\TvlKind;
use App\Models\ViabilityDecision;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TvlKindDecisionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_decisao_grava_tipo_e_validade_da_renovacao(): void
    {
        $decision = ViabilityDecision::factory()->create([
            'tvl_kind' => TvlKind::Provisoria,
            'tvl_valid_until' => '2027-09-28',
        ]);

        $this->assertSame(TvlKind::Provisoria, $decision->fresh()->tvl_kind);
        $this->assertSame('2027-09-28', $decision->fresh()->tvl_valid_until->toDateString());
    }
}

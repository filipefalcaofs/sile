<?php

namespace Tests\Feature\Relatorios;

use App\Models\ViabilityRequest;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TempoViabilidadeSchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_persiste_o_tempo_de_viabilidade_em_horas_uteis_como_float(): void
    {
        $request = ViabilityRequest::factory()->create();

        $request->forceFill(['viabilidade_horas_uteis' => 12.5])->save();

        $this->assertSame(12.5, $request->fresh()->viabilidade_horas_uteis);
    }

    public function test_tempo_de_viabilidade_nasce_nulo(): void
    {
        $this->assertNull(ViabilityRequest::factory()->create()->viabilidade_horas_uteis);
    }

    public function test_tempo_de_viabilidade_nao_e_preenchivel_em_massa(): void
    {
        $this->assertNotContains('viabilidade_horas_uteis', (new ViabilityRequest)->getFillable());
    }
}

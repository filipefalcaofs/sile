<?php

namespace Tests\Feature\Relatorios;

use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * O tempo de viabilidade é materializado quando a decisão é registrada —
 * qualquer que seja o caminho que a registrou (expresso, análise técnica,
 * indeferimento por prazo) — e pode ser reconstruído pelo comando de recálculo
 * para os processos decididos antes da coluna existir.
 */
class TempoViabilidadeGravacaoTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function processoProtocolado(string $protocoladoEm = '2026-03-02 00:00:00'): ViabilityRequest
    {
        return ViabilityRequest::factory()->protocoled()->create([
            'protocoled_at' => Carbon::parse($protocoladoEm),
        ]);
    }

    public function test_registrar_a_decisao_materializa_o_tempo_de_viabilidade(): void
    {
        $request = $this->processoProtocolado();

        ViabilityDecision::factory()->for($request, 'viabilityRequest')->create([
            'decided_at' => Carbon::parse('2026-03-04 00:00:00'),
        ]);

        $this->assertEqualsWithDelta(48.0, $request->fresh()->viabilidade_horas_uteis, 0.01);
    }

    public function test_decisao_em_processo_sem_protocolo_nao_materializa_tempo(): void
    {
        $request = ViabilityRequest::factory()->draft()->create(['protocoled_at' => null]);

        ViabilityDecision::factory()->for($request, 'viabilityRequest')->create([
            'decided_at' => Carbon::parse('2026-03-04 00:00:00'),
        ]);

        $this->assertNull($request->fresh()->viabilidade_horas_uteis);
    }

    public function test_comando_preenche_processos_decididos_sem_tempo_apurado(): void
    {
        $request = $this->processoProtocolado();

        ViabilityDecision::factory()->for($request, 'viabilityRequest')->create([
            'decided_at' => Carbon::parse('2026-03-04 00:00:00'),
        ]);

        $request->forceFill(['viabilidade_horas_uteis' => null])->save();

        $this->artisan('viabilidade:recalcular-horas-uteis')->assertSuccessful();

        $this->assertEqualsWithDelta(48.0, $request->fresh()->viabilidade_horas_uteis, 0.01);
    }

    public function test_comando_e_idempotente(): void
    {
        $request = $this->processoProtocolado();

        ViabilityDecision::factory()->for($request, 'viabilityRequest')->create([
            'decided_at' => Carbon::parse('2026-03-04 00:00:00'),
        ]);

        $this->artisan('viabilidade:recalcular-horas-uteis')->assertSuccessful();
        $primeiro = $request->fresh()->viabilidade_horas_uteis;

        $this->artisan('viabilidade:recalcular-horas-uteis')->assertSuccessful();

        $this->assertSame($primeiro, $request->fresh()->viabilidade_horas_uteis);
    }

    public function test_comando_ignora_processos_sem_decisao(): void
    {
        $request = $this->processoProtocolado();

        $this->artisan('viabilidade:recalcular-horas-uteis')->assertSuccessful();

        $this->assertNull($request->fresh()->viabilidade_horas_uteis);
    }
}

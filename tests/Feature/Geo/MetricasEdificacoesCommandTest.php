<?php

namespace Tests\Feature\Geo;

use App\Enums\PoligonoFonte;
use App\Enums\StatusResolucaoPoligono;
use App\Models\LotPolygonResolution;
use App\Models\ViabilityRequest;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class MetricasEdificacoesCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function processoCom(?PoligonoFonte $fonte): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->create();
        $request->forceFill([
            'address_polygon_geojson' => $fonte === null ? null : ['type' => 'Polygon', 'coordinates' => [[[-38.5, -12.98], [-38.499, -12.98], [-38.499, -12.979], [-38.5, -12.98]]]],
            'address_polygon_source' => $fonte,
        ])->save();

        return $request;
    }

    /**
     * @return array<string, mixed>
     */
    private function metricas(): array
    {
        Artisan::call('geo:edificacoes:metricas', ['--json' => true]);

        return json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_cobertura_por_fonte(): void
    {
        $this->processoCom(PoligonoFonte::Overture);
        $this->processoCom(PoligonoFonte::Overture);
        $this->processoCom(PoligonoFonte::Chave);
        $this->processoCom(null);

        $metricas = $this->metricas();

        $this->assertSame(4, $metricas['processos']);
        $this->assertSame(50.0, $metricas['cobertura']['overture']);
        $this->assertSame(25.0, $metricas['cobertura']['chave']);
        $this->assertSame(25.0, $metricas['cobertura']['sem_poligono']);
    }

    public function test_latencia_p95(): void
    {
        $request = $this->processoCom(null);

        foreach (range(10, 100, 10) as $latencia) {
            LotPolygonResolution::factory()->create([
                'viability_request_id' => $request->id,
                'source' => PoligonoFonte::Overture,
                'status' => StatusResolucaoPoligono::NaoEncontrado,
                'latency_ms' => $latencia,
            ]);
        }

        $metricas = $this->metricas();

        $this->assertSame(50, $metricas['latencia_ms']['overture']['p50']);
        $this->assertSame(100, $metricas['latencia_ms']['overture']['p95']);
    }

    public function test_falhas_por_motivo(): void
    {
        $request = $this->processoCom(null);
        LotPolygonResolution::factory()->count(2)->create(['viability_request_id' => $request->id, 'source' => PoligonoFonte::Ia, 'status' => StatusResolucaoPoligono::NaoResolvido, 'reason' => 'imagem_indisponivel']);
        LotPolygonResolution::factory()->create(['viability_request_id' => $request->id, 'source' => PoligonoFonte::Ia, 'status' => StatusResolucaoPoligono::Indisponivel, 'reason' => 'servico_fora']);
        LotPolygonResolution::factory()->create(['viability_request_id' => $request->id, 'source' => PoligonoFonte::Ia, 'status' => StatusResolucaoPoligono::Encontrado, 'latency_ms' => 4000]);

        $metricas = $this->metricas();

        $this->assertSame(2, $metricas['falhas']['ia']['imagem_indisponivel']);
        $this->assertSame(1, $metricas['falhas']['ia']['servico_fora']);
        $this->assertSame(4, $metricas['ia']['tentativas']);
        $this->assertSame(1, $metricas['ia']['encontrados']);
        $this->assertStringContainsString('sem medida automática', $metricas['precisao']);
    }

    public function test_saida_em_texto(): void
    {
        $this->processoCom(PoligonoFonte::Overture);

        $this->artisan('geo:edificacoes:metricas')
            ->expectsOutputToContain('Cobertura')
            ->assertSuccessful();
    }
}

<?php

namespace Tests\Feature\Geo;

use App\Enums\LotePoligonoFonte;
use App\Models\ViabilityRequest;
use App\Services\Geo\PoligonoLoteHit;
use App\Services\Geo\PoligonoLoteService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * As três fontes de geometria do lote convivem no processo: endereçamento da
 * SEDUR, SEMAP (GeoSalvador/SEFAZ) e o polígono declarado no REGIN. Cada uma
 * fica guardada; a que vale é decidida por precedência, nunca sobrescrita.
 */
class PoligonoLoteFontesTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function poligono(float $deslocamento = 0.0): array
    {
        return [
            'type' => 'Polygon',
            'coordinates' => [[
                [-38.516331 + $deslocamento, -12.977852],
                [-38.516291 + $deslocamento, -12.977746],
                [-38.516453 + $deslocamento, -12.977688],
                [-38.516492 + $deslocamento, -12.977795],
                [-38.516331 + $deslocamento, -12.977852],
            ]],
        ];
    }

    public function test_enderecamento_prevalece_sobre_semap_e_regin(): void
    {
        $processo = ViabilityRequest::factory()->draft()->create();

        app(PoligonoLoteService::class)->registrar(
            $processo,
            PoligonoLoteHit::encontrado(LotePoligonoFonte::Regin, $this->poligono(0.002)),
        );
        app(PoligonoLoteService::class)->registrar(
            $processo,
            PoligonoLoteHit::encontrado(LotePoligonoFonte::Semap, $this->poligono(0.001)),
        );
        app(PoligonoLoteService::class)->registrar(
            $processo,
            PoligonoLoteHit::encontrado(LotePoligonoFonte::Enderecamento, $this->poligono()),
        );

        $processo->refresh();

        $this->assertSame(LotePoligonoFonte::Enderecamento, $processo->lote_poligono_fonte);
        $this->assertSame($this->poligono(), $processo->poligonoDoLote());
        $this->assertEqualsCanonicalizing(
            ['enderecamento', 'semap', 'regin'],
            array_keys($processo->lote_poligonos),
        );
        $this->assertNotNull($processo->lote_poligonos['semap']['consultado_em']);
    }

    public function test_sem_enderecamento_vale_o_semap(): void
    {
        $processo = ViabilityRequest::factory()->draft()->create();

        app(PoligonoLoteService::class)->registrar(
            $processo,
            PoligonoLoteHit::encontrado(LotePoligonoFonte::Regin, $this->poligono(0.002)),
        );
        app(PoligonoLoteService::class)->registrar(
            $processo,
            PoligonoLoteHit::encontrado(LotePoligonoFonte::Semap, $this->poligono(0.001)),
        );

        $processo->refresh();

        $this->assertSame(LotePoligonoFonte::Semap, $processo->lote_poligono_fonte);
        $this->assertSame($this->poligono(0.001), $processo->poligonoDoLote());
    }

    public function test_so_o_regin_vale_quando_nao_ha_fonte_oficial(): void
    {
        $processo = ViabilityRequest::factory()->draft()->create();

        app(PoligonoLoteService::class)->registrar(
            $processo,
            PoligonoLoteHit::encontrado(LotePoligonoFonte::Regin, $this->poligono(0.002)),
        );

        $processo->refresh();

        $this->assertSame(LotePoligonoFonte::Regin, $processo->lote_poligono_fonte);
        $this->assertSame($this->poligono(0.002), $processo->poligonoDoLote());
    }

    public function test_fonte_sem_geometria_fica_registrada_sem_virar_vigente(): void
    {
        $processo = ViabilityRequest::factory()->draft()->create();

        app(PoligonoLoteService::class)->registrar(
            $processo,
            PoligonoLoteHit::encontrado(LotePoligonoFonte::Regin, $this->poligono()),
        );
        app(PoligonoLoteService::class)->registrar(
            $processo,
            PoligonoLoteHit::indisponivel(LotePoligonoFonte::Semap, 'ArcGIS fora do ar'),
        );

        $processo->refresh();

        $this->assertSame(LotePoligonoFonte::Regin, $processo->lote_poligono_fonte);
        $this->assertSame('indisponivel', $processo->lote_poligonos['semap']['status']);
        $this->assertSame('ArcGIS fora do ar', $processo->lote_poligonos['semap']['motivo']);
        $this->assertNull($processo->lote_poligonos['semap']['geojson']);
    }

    public function test_processo_sem_nenhuma_fonte_nao_tem_poligono_do_lote(): void
    {
        $processo = ViabilityRequest::factory()->draft()->create([
            'property_polygon_geojson' => null,
        ]);

        $this->assertNull($processo->poligonoDoLote());
        $this->assertNull($processo->lote_poligono_fonte);
    }
}

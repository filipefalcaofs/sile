<?php

namespace Tests\Unit\Geo;

use App\Services\Geo\Edificacoes\AceiteMascaraIa;
use App\Services\Geo\Edificacoes\MascaraIa;
use App\Services\Geo\Edificacoes\RecorteOrtofoto;
use Tests\TestCase;

class AceiteMascaraIaTest extends TestCase
{
    private const LAT = -12.98;

    private const LNG = -38.46;

    private function recorte(float $ladoM = 65.0, int $pixels = 800): RecorteOrtofoto
    {
        $meioLat = $ladoM / 2 / 110574;
        $meioLng = $ladoM / 2 / (111320 * cos(deg2rad(self::LAT)));

        return new RecorteOrtofoto('/tmp/x.png', self::LNG - $meioLng, self::LAT - $meioLat, self::LNG + $meioLng, self::LAT + $meioLat, $pixels, $pixels, 'imageserver-2024', '2024');
    }

    /**
     * Retângulo em pixels, com largura e altura em metros, centrado no pixel informado.
     */
    private function mascara(RecorteOrtofoto $recorte, float $larguraM, float $alturaM, float $cx = 400, float $cy = 400): MascaraIa
    {
        $pxPorM = $recorte->largura / (($recorte->leste - $recorte->oeste) * 111320 * cos(deg2rad(self::LAT)));
        $dx = $larguraM * $pxPorM / 2;
        $dy = $alturaM * $pxPorM / 2;

        return new MascaraIa('ok', [[$cx - $dx, $cy - $dy], [$cx + $dx, $cy - $dy], [$cx + $dx, $cy + $dy], [$cx - $dx, $cy + $dy]], 0.93, 'sam2.1-hiera-small@1');
    }

    private function avaliar(MascaraIa $mascara, RecorteOrtofoto $recorte): array
    {
        return app(AceiteMascaraIa::class)->avaliar($mascara, $recorte, self::LAT, self::LNG);
    }

    public function test_aceita_quadrado_de_100m2_que_contem_o_ponto(): void
    {
        $recorte = $this->recorte();

        $resultado = $this->avaliar($this->mascara($recorte, 10, 10), $recorte);

        $this->assertTrue($resultado['aceita']);
        $this->assertNull($resultado['motivo']);
        $this->assertEqualsWithDelta(100.0, $resultado['area_m2'], 3.0);
        $this->assertSame('MultiPolygon', $resultado['geojson']['type']);
        $anel = $resultado['geojson']['coordinates'][0][0];
        $this->assertSame($anel[0], $anel[count($anel) - 1]);
        $this->assertEqualsWithDelta(self::LNG - 5 / (111320 * cos(deg2rad(self::LAT))), $anel[0][0], 0.00002);
        $this->assertEqualsWithDelta(self::LAT + 5 / 110574, $anel[0][1], 0.00002);
    }

    public function test_recusa_sem_o_ponto(): void
    {
        $recorte = $this->recorte();

        $resultado = $this->avaliar($this->mascara($recorte, 10, 10, 600, 600), $recorte);

        $this->assertFalse($resultado['aceita']);
        $this->assertSame('nao_contem_o_ponto', $resultado['motivo']);
        $this->assertNull($resultado['geojson']);
    }

    public function test_recusa_area_19m2(): void
    {
        $recorte = $this->recorte();

        $this->assertSame('area_fora_dos_limites', $this->avaliar($this->mascara($recorte, sqrt(19), sqrt(19)), $recorte)['motivo']);
    }

    public function test_recusa_area_5001m2(): void
    {
        $recorte = $this->recorte(200);

        $this->assertSame('area_fora_dos_limites', $this->avaliar($this->mascara($recorte, 100.01, 50.01), $recorte)['motivo']);
    }

    public function test_recusa_tira_fina_compacidade_0_1(): void
    {
        $recorte = $this->recorte();

        $this->assertSame('compacidade_baixa', $this->avaliar($this->mascara($recorte, 40, 2), $recorte)['motivo']);
    }

    public function test_recusa_encostada_na_borda(): void
    {
        $recorte = $this->recorte();
        $mascara = new MascaraIa('ok', [[1, 300], [500, 300], [500, 500], [1, 500]], null, 'sam2');

        $this->assertSame('encosta_na_borda', $this->avaliar($mascara, $recorte)['motivo']);
    }

    public function test_sem_objeto(): void
    {
        $recorte = $this->recorte();

        $this->assertSame('sem_objeto', $this->avaliar(new MascaraIa('sem_objeto', [], null, 'sam2'), $recorte)['motivo']);
    }
}

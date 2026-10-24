<?php

namespace Tests\Unit\Geo;

use App\Services\Geo\DistanciaGeografica;
use PHPUnit\Framework\TestCase;

class DistanciaGeograficaTest extends TestCase
{
    public function test_ponto_sobre_a_linha_tem_distancia_zero(): void
    {
        $linha = [[-38.50, -12.97], [-38.49, -12.97]];

        $distancia = DistanciaGeografica::ateLinhas(-12.97, -38.495, [$linha]);

        $this->assertEqualsWithDelta(0.0, $distancia, 0.5);
    }

    public function test_distancia_ate_segmento_usa_a_projecao_e_nao_so_os_vertices(): void
    {
        // Segmento leste-oeste de ~2,2 km; o ponto está ~100 m ao norte do meio.
        $linha = [[-38.51, -12.97], [-38.49, -12.97]];
        $deslocamento = 100 / 111_320;

        $distancia = DistanciaGeografica::ateLinhas(-12.97 + $deslocamento, -38.50, [$linha]);

        $this->assertEqualsWithDelta(100.0, $distancia, 5.0);
    }

    public function test_escolhe_a_linha_mais_proxima(): void
    {
        $longe = [[-38.51, -12.90], [-38.49, -12.90]];
        $perto = [[-38.51, -12.9701], [-38.49, -12.9701]];

        $distancia = DistanciaGeografica::ateLinhas(-12.97, -38.50, [$longe, $perto]);

        $this->assertEqualsWithDelta(11.1, $distancia, 1.0);
    }

    public function test_sem_linhas_devolve_null(): void
    {
        $this->assertNull(DistanciaGeografica::ateLinhas(-12.97, -38.50, []));
    }

    public function test_linha_com_um_vertice_e_ignorada(): void
    {
        $this->assertNull(DistanciaGeografica::ateLinhas(-12.97, -38.50, [[[-38.50, -12.97]]]));
    }

    public function test_entre_pontos_conhece_um_grau_de_latitude(): void
    {
        $distancia = DistanciaGeografica::entrePontos(-13.0, -38.5, -12.0, -38.5);

        $this->assertEqualsWithDelta(111_195.0, $distancia, 1_200.0);
    }

    public function test_centroide_do_poligono_quadrado(): void
    {
        $poligono = [
            'type' => 'Polygon',
            'coordinates' => [[[-38.50, -12.96], [-38.48, -12.96], [-38.48, -12.98], [-38.50, -12.98], [-38.50, -12.96]]],
        ];

        $centroide = DistanciaGeografica::centroideDoPoligono($poligono);

        $this->assertEqualsWithDelta(-12.97, $centroide['lat'], 0.0001);
        $this->assertEqualsWithDelta(-38.49, $centroide['lng'], 0.0001);
    }

    public function test_centroide_de_multipoligono_usa_o_primeiro_poligono(): void
    {
        $multi = [
            'type' => 'MultiPolygon',
            'coordinates' => [[[[-38.50, -12.96], [-38.48, -12.96], [-38.48, -12.98], [-38.50, -12.98], [-38.50, -12.96]]]],
        ];

        $this->assertEqualsWithDelta(-12.97, DistanciaGeografica::centroideDoPoligono($multi)['lat'], 0.0001);
    }

    public function test_centroide_sem_geometria_devolve_null(): void
    {
        $this->assertNull(DistanciaGeografica::centroideDoPoligono([]));
    }
}

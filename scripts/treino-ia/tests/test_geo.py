import random

import pytest
from shapely.geometry import Point, shape

from treino_ia import geo
from tests import sinteticos


def test_caixa_recorte_igual_ao_ortofotoclient():
    assert geo.caixa_recorte(-12.996143, -38.465385, 60) == pytest.approx(
        (-38.4656615778369, -12.996414311519887, -38.4651084221631, -12.995871688480113), abs=1e-9)


def test_pixel_e_coordenada_sao_inversos():
    caixa = geo.caixa_recorte(-12.996143, -38.465385, 60)

    assert geo.para_pixel(caixa, -12.996143, -38.465385) == (400, 400)
    lng, lat = geo.para_coord(caixa, 400, 400)
    assert (lat, lng) == pytest.approx((-12.996143, -38.465385), abs=1e-7)


def test_ponto_interno_fica_dentro_longe_da_divisa_e_varia():
    poligono = sinteticos.retangulo(0, 0, 30, 20)
    forma = shape(poligono)
    rng = random.Random(7)

    pontos = [geo.ponto_interno(poligono, rng) for _ in range(20)]

    for lat, lng in pontos:
        p = Point(lng, lat)
        assert forma.contains(p)
        assert geo.distancia_m(forma.exterior, p) >= 1.0
    assert len({round(lat, 7) for lat, _ in pontos}) > 5


def test_lado_minimo_escolhe_o_menor_recorte_que_contem_o_lote():
    pequeno = sinteticos.retangulo(0, 0, 20, 20)
    medio = sinteticos.retangulo(0, 0, 80, 40)
    grande = sinteticos.retangulo(0, 0, 300, 40)

    def centro(p):
        c = shape(p).centroid
        return c.y, c.x

    assert geo.lado_minimo(pequeno, *centro(pequeno)) == 60
    assert geo.lado_minimo(medio, *centro(medio)) == 120
    assert geo.lado_minimo(grande, *centro(grande)) is None


def test_area_m2_do_retangulo():
    assert geo.area_m2(sinteticos.retangulo(0, 0, 30, 20)) == pytest.approx(600, rel=0.01)


def test_lados_de_treino_incluem_os_recortes_menores_que_o_lote():
    assert geo.lados_de_treino({"lado_m": 240}) == [60, 120, 240]
    assert geo.lados_de_treino({"lado_m": 120}) == [60, 120]
    assert geo.lados_de_treino({"lado_m": 60}) == [60]

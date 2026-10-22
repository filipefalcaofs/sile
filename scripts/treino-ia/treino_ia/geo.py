"""Conta de recorte e de metros, no mesmo cálculo do OrtofotoClient."""

import math
import random

from shapely.geometry import Point, box, shape
from shapely.ops import transform

M_LAT = 110574.0
LADOS_RECORTE = (60, 120, 240)
MARGEM = 0.9


def m_lng(lat: float) -> float:
    return 111320.0 * math.cos(math.radians(lat))


def caixa_recorte(lat: float, lng: float, lado_m: float) -> tuple[float, float, float, float]:
    meio_lat = lado_m / 2 / M_LAT
    meio_lng = lado_m / 2 / m_lng(lat)
    return (lng - meio_lng, lat - meio_lat, lng + meio_lng, lat + meio_lat)


def para_pixel(caixa, lat: float, lng: float, px: int = 800) -> tuple[int, int]:
    oeste, sul, leste, norte = caixa
    return (round((lng - oeste) / (leste - oeste) * px), round((norte - lat) / (norte - sul) * px))


def para_coord(caixa, x: float, y: float, px: int = 800) -> tuple[float, float]:
    oeste, sul, leste, norte = caixa
    return (oeste + x / px * (leste - oeste), norte - y / px * (norte - sul))


def em_metros(geometria, lat_ref: float):
    escala = m_lng(lat_ref)
    return transform(lambda x, y, z=None: (x * escala, y * M_LAT), geometria)


def area_m2(poligono: dict) -> float:
    forma = shape(poligono)
    return em_metros(forma, forma.centroid.y).area


def distancia_m(geometria, ponto) -> float:
    lat = ponto.y
    return em_metros(geometria, lat).distance(em_metros(ponto, lat))


def ponto_interno(poligono: dict, rng: random.Random) -> tuple[float, float]:
    forma = shape(poligono)
    lat_ref = forma.centroid.y
    metros = em_metros(forma, lat_ref)
    interior = metros.buffer(-1.0)
    alvo = interior if not interior.is_empty else metros
    minx, miny, maxx, maxy = alvo.bounds
    escolhido = None
    for _ in range(1000):
        candidato = Point(rng.uniform(minx, maxx), rng.uniform(miny, maxy))
        if alvo.contains(candidato):
            escolhido = candidato
            break
    if escolhido is None:
        escolhido = alvo.representative_point()
    return (escolhido.y / M_LAT, escolhido.x / m_lng(lat_ref))


def lados_de_treino(lote: dict) -> list[int]:
    """O recorte que contém o lote e os menores, em que o lote encosta na borda."""
    return [lado for lado in LADOS_RECORTE if lado <= lote["lado_m"]]


def lado_minimo(poligono: dict, lat: float, lng: float) -> int | None:
    forma = shape(poligono)
    for lado in LADOS_RECORTE:
        if box(*caixa_recorte(lat, lng, lado * MARGEM)).contains(forma):
            return lado
    return None

"""Indicadores entre o contorno da IA e o lote do CODLOG, em metros."""

import math

from shapely.geometry import Point, Polygon, shape
from shapely.validation import make_valid

from treino_ia import geo

MARGEM_BORDA_PX = 2
AREA_MIN_M2 = 20
AREA_MAX_M2 = 5000
COMPACIDADE_MIN = 0.2


def _poligonal(geometria):
    valida = make_valid(geometria)
    if valida.geom_type in ("Polygon", "MultiPolygon"):
        return valida
    partes = [g for g in getattr(valida, "geoms", []) if g.geom_type in ("Polygon", "MultiPolygon")]
    from shapely.ops import unary_union

    return unary_union(partes)


def _divisa_media(origem, destino) -> float:
    pontos = list(origem.exterior.coords) if origem.geom_type == "Polygon" else [c for g in origem.geoms for c in g.exterior.coords]
    borda = destino.boundary
    return sum(borda.distance(Point(p)) for p in pontos) / len(pontos)


def comparar(ia: dict | None, referencia: dict) -> dict:
    lat_ref = shape(referencia).centroid.y
    ref = _poligonal(geo.em_metros(shape(referencia), lat_ref))
    area_ref = ref.area
    if ia is None:
        return {
            "area_ref_m2": round(area_ref, 1), "area_ia_m2": 0.0, "dif_area_m2": round(-area_ref, 1), "dif_area_pct": -100.0,
            "intersecao_m2": 0.0, "iou": 0, "pct_ia_dentro_ref": 0.0, "pct_ref_coberto": 0.0, "hausdorff_m": None,
            "divisa_media_ia_ref_m": None, "divisa_media_ref_ia_m": None, "aceite_atual": False, "motivo_recusa": "sem_objeto",
        }
    contorno = _poligonal(geo.em_metros(shape(ia), lat_ref))
    if contorno.is_empty or contorno.area <= 0:
        return comparar(None, referencia)
    intersecao = contorno.intersection(ref).area
    uniao = contorno.union(ref).area
    return {
        "area_ref_m2": round(area_ref, 1),
        "area_ia_m2": round(contorno.area, 1),
        "dif_area_m2": round(contorno.area - area_ref, 1),
        "dif_area_pct": round((contorno.area - area_ref) / area_ref * 100, 1),
        "intersecao_m2": round(intersecao, 1),
        "iou": round(intersecao / uniao, 4) if uniao else 0,
        "pct_ia_dentro_ref": round(intersecao / contorno.area * 100, 1) if contorno.area else 0.0,
        "pct_ref_coberto": round(intersecao / area_ref * 100, 1),
        "hausdorff_m": round(contorno.hausdorff_distance(ref), 1),
        "divisa_media_ia_ref_m": round(_divisa_media(contorno, ref), 1),
        "divisa_media_ref_ia_m": round(_divisa_media(ref, contorno), 1),
        "aceite_atual": None,
        "motivo_recusa": None,
    }


def aceite_atual(anel_px: list, caixa, lat: float, lng: float, px: int = 800) -> tuple[bool, str | None]:
    if len(anel_px) < 3:
        return False, "sem_objeto"
    for x, y in anel_px:
        if x <= MARGEM_BORDA_PX or y <= MARGEM_BORDA_PX or x >= px - 1 - MARGEM_BORDA_PX or y >= px - 1 - MARGEM_BORDA_PX:
            return False, "encosta_na_borda"
    coords = [geo.para_coord(caixa, x, y, px) for x, y in anel_px]
    if not Polygon(coords).contains(Point(lng, lat)):
        return False, "nao_contem_o_ponto"
    metros = geo.em_metros(Polygon(coords), lat)
    if not AREA_MIN_M2 <= metros.area <= AREA_MAX_M2:
        return False, "area_fora_dos_limites"
    if metros.length <= 0 or 4 * math.pi * metros.area / metros.length ** 2 < COMPACIDADE_MIN:
        return False, "compacidade_baixa"
    return True, None

"""Variantes de inferência do SAM 2 avaliadas na galeria.

A: serviço atual (60 m, 1 ponto, maior score). B: recorte crescente, maior
máscara que não encosta na borda. C: B escolhendo pela área do Cadastro, só
quando a inscrição não é de unidade. D: B com pontos negativos no eixo do
logradouro. E: recorte crescente com o decodificador treinado. F: E com a
regra escolhida na avaliação. Nenhuma variante lê o polígono do lote.
"""

import importlib.util
import math
import os
from functools import lru_cache
from pathlib import Path

import numpy as np
from shapely.geometry import Polygon

from treino_ia import geo, recursos

LADOS = geo.LADOS_RECORTE
PX = 800
PASSO_NEGATIVOS_M = 15
MARGEM_BORDA_PX = 2


@lru_cache(maxsize=1)
def servico():
    """O app.py do footprint-ia, importado pelo caminho, sem cópia."""
    caminho = Path(os.environ.get("TREINO_IA_APP_PY", Path(__file__).resolve().parents[3] / "docker" / "footprint-ia" / "app.py"))
    spec = importlib.util.spec_from_file_location("footprint_ia_app", caminho)
    modulo = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(modulo)
    return modulo


class Segmentador:
    def __init__(self, pesos_decodificador: Path | None = None):
        import torch
        from sam2.sam2_image_predictor import SAM2ImagePredictor

        self.preditor = SAM2ImagePredictor.from_pretrained("facebook/sam2.1-hiera-small", device=recursos.dispositivo())
        if pesos_decodificador is not None:
            estado = torch.load(pesos_decodificador, map_location="cpu")
            self.preditor.model.sam_mask_decoder.load_state_dict(estado.get("decodificador", estado))
        self.preditor.model.eval()

    def codificar(self, img) -> dict:
        import torch

        with torch.no_grad():
            self.preditor.set_image(img)
        return {
            "image_embed": self.preditor._features["image_embed"].detach().clone(),
            "high_res_feats": [f.detach().clone() for f in self.preditor._features["high_res_feats"]],
        }

    def mascaras(self, img, pontos, rotulos, caixa_px=None):
        import torch

        with torch.no_grad():
            self.preditor.set_image(img)
            extra = {} if caixa_px is None else {"box": np.array(caixa_px, dtype=np.float32)}
            ms, scores, _ = self.preditor.predict(
                point_coords=np.array(pontos, dtype=np.float32),
                point_labels=np.array(rotulos, dtype=np.int32),
                multimask_output=True, **extra,
            )
        return [(m > 0, float(s)) for m, s in zip(ms, scores)]


def _candidatos(seg, lote, contexto, lado, negativos):
    lat, lng = lote["ponto"]
    img, caixa = contexto["carregar_recorte"](lote["id"], lado)
    x, y = geo.para_pixel(caixa, lat, lng, PX)
    pontos, rotulos = [[x, y]], [1]
    if negativos:
        extras = _pontos_no_eixo(contexto.get("eixo") or [], caixa, lat)
        pontos += extras
        rotulos += [0] * len(extras)
    saida = []
    for mascara, score in seg.mascaras(img, pontos, rotulos):
        anel = servico().contorno_do_ponto(mascara.astype(np.uint8), x, y)
        if len(anel) < 3:
            continue
        coords = [geo.para_coord(caixa, px, py, PX) for px, py in anel]
        saida.append({
            "lado_m": lado, "score": round(score, 4), "anel_px": anel,
            "area_m2": round(geo.em_metros(Polygon(coords), lat).area, 1),
            "encosta": any(px <= MARGEM_BORDA_PX or py <= MARGEM_BORDA_PX or px >= PX - 1 - MARGEM_BORDA_PX or py >= PX - 1 - MARGEM_BORDA_PX for px, py in anel),
            "poligono": {"type": "Polygon", "coordinates": [coords + coords[:1]]},
        })
    return saida


def _pontos_no_eixo(linhas, caixa, lat) -> list[list[int]]:
    pontos = []
    for linha in linhas:
        for (lng1, lat1), (lng2, lat2) in zip(linha, linha[1:]):
            comprimento = math.hypot((lng2 - lng1) * geo.m_lng(lat), (lat2 - lat1) * geo.M_LAT)
            passos = max(1, int(comprimento // PASSO_NEGATIVOS_M))
            for i in range(passos + 1):
                t = i / passos
                x, y = geo.para_pixel(caixa, lat1 + (lat2 - lat1) * t, lng1 + (lng2 - lng1) * t, PX)
                if 5 <= x < PX - 5 and 5 <= y < PX - 5:
                    pontos.append([x, y])
    return pontos


def _crescente(seg, lote, contexto, escolher, negativos=False):
    for lado in LADOS:
        todos = _candidatos(seg, lote, contexto, lado, negativos)
        livres = [c for c in todos if not c["encosta"]]
        if livres and (len(livres) == len(todos) or lado == LADOS[-1]):
            return escolher(livres)
    return None


def _maior(cands):
    return max(cands, key=lambda c: c["area_m2"])


def _melhor_score(cands):
    return max(cands, key=lambda c: c["score"])


def _perto_da_area(area):
    return lambda cands: min(cands, key=lambda c: abs(math.log(max(c["area_m2"], 1e-6) / area)))


def rodar(nome: str, lote: dict, seg, contexto: dict) -> dict | None:
    if nome == "A":
        cands = _candidatos(seg, lote, contexto, LADOS[0], negativos=False)
        escolhido = _melhor_score(cands) if cands else None
    elif nome == "B":
        escolhido = _crescente(seg, lote, contexto, _maior)
    elif nome == "C":
        if contexto.get("unidade") is not False or not contexto.get("area_cadastro"):
            return None
        escolhido = _crescente(seg, lote, contexto, _perto_da_area(float(contexto["area_cadastro"])))
    elif nome == "D":
        escolhido = _crescente(seg, lote, contexto, _maior, negativos=True)
    elif nome == "E":
        escolhido = _crescente(seg, lote, contexto, _melhor_score)
    elif nome == "F":
        regra = contexto.get("regra_f")
        if regra == "C" and contexto.get("unidade") is False and contexto.get("area_cadastro"):
            escolhido = _crescente(seg, lote, contexto, _perto_da_area(float(contexto["area_cadastro"])))
        else:
            escolhido = _crescente(seg, lote, contexto, _maior, negativos=regra == "D")
    else:
        raise ValueError(f"Variante desconhecida: {nome}")
    if escolhido is None:
        return None
    return {"variante": nome, **{k: escolhido[k] for k in ("lado_m", "area_m2", "score", "poligono", "anel_px")}}

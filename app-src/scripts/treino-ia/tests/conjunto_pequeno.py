"""Dois lotes sintéticos com recorte gravado, para os testes de cache e treino."""

import numpy as np
from PIL import Image

from treino_ia import arquivos, config, geo
from tests import sinteticos


def preparar(tmp_path, monkeypatch, n=2):
    monkeypatch.setenv("TREINO_IA_RAIZ", str(tmp_path))
    c = config.caminhos()
    rng = np.random.default_rng(0)
    lotes = []
    for i in range(n):
        poligono = sinteticos.retangulo(i * 200, 0, 25, 20)
        lat = sinteticos.LAT0 + 10 / sinteticos.M_LAT
        lng = sinteticos.LNG0 + (i * 200 + 12) / sinteticos.M_LNG
        lote = {"id": f"9-{i}", "ponto": [lat, lng], "lado_m": 60, "poligono": poligono, "bairro": "B"}
        caixa = geo.caixa_recorte(lat, lng, 60)
        c.recortes.mkdir(parents=True, exist_ok=True)
        Image.fromarray(rng.integers(0, 255, (800, 800, 3), dtype=np.uint8)).save(c.recortes / f"{lote['id']}_60.png")
        arquivos.gravar_json(c.recortes / f"{lote['id']}_60.json", {"caixa": list(caixa), "lado_m": 60, "pixel_do_ponto": list(geo.para_pixel(caixa, lat, lng))})
        lotes.append(lote)
    return {"semente": 1, "treino": lotes, "teste": [], "bairros_teste": [], "descartados": {}}

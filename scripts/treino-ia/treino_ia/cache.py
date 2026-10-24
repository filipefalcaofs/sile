"""Saídas do codificador do SAM 2 por recorte, em float16, para treinar só o decodificador."""

import io
import time

import numpy as np
import torch
from PIL import Image

from treino_ia import arquivos, config, geo, recursos, situacao


def caminho(lote_id: str, lado: int):
    return config.caminhos().cache / f"{lote_id}_{lado}.pt"


def gerar(manifesto: dict, seg, limite: int | None = None) -> dict:
    c = config.caminhos()
    lotes = manifesto.get("treino", [])
    if limite is not None:
        lotes = lotes[:limite]
    pedidos = [(lote, lado) for lote in lotes for lado in geo.lados_de_treino(lote)]
    contagem = {"gerados": 0, "ja_existiam": 0, "sem_recorte": 0}
    inicio = time.monotonic()
    for i, (lote, lado) in enumerate(pedidos, 1):
        destino = caminho(lote["id"], lado)
        png = c.recortes / f"{lote['id']}_{lado}.png"
        if destino.exists() and (not png.exists() or destino.stat().st_mtime >= png.stat().st_mtime):
            contagem["ja_existiam"] += 1
        elif not png.exists():
            contagem["sem_recorte"] += 1
        else:
            img = np.array(Image.open(png).convert("RGB"))
            feats = seg.codificar(img)
            buffer = io.BytesIO()
            torch.save({
                "image_embed": feats["image_embed"].to("cpu", torch.float16),
                "high_res_feats": [f.to("cpu", torch.float16) for f in feats["high_res_feats"]],
            }, buffer)
            arquivos.gravar_atomico(destino, buffer.getvalue())
            contagem["gerados"] += 1
            recursos.conferir_memoria()
        if i % 5 == 0 or i == len(pedidos):
            gerados = contagem["gerados"]
            situacao.registrar(c, "cache", feitos=i, total=len(pedidos),
                               tempo_medio_s=(time.monotonic() - inicio) / gerados if gerados else None)
    return contagem

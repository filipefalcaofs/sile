"""Recortes da ortofoto 2024 em volta do ponto e a máscara do lote no recorte.

A ortofoto 2024 tem buracos: nesses pontos o serviço devolve uma imagem de cor
única. Esses recortes ficam gravados (para não serem baixados de novo), são
contados como em branco, os outros tamanhos do mesmo lote não são baixados e o
lote sai do conjunto em filtrar_com_imagem.
"""

import time

import numpy as np
from PIL import Image, ImageDraw
from shapely.geometry import shape

from treino_ia import arquivos, config, geo, recursos, situacao, wfs

URL_ORTOFOTO = "https://geo.salvador.ba.gov.br/imageserver/rest/services/Ortofotos/2024/ImageServer/exportImage"
PIXELS = 800
FRACAO_COR_UNICA = 0.5


def _parametros(caixa) -> dict:
    return {
        "bbox": ",".join(str(v) for v in caixa), "bboxSR": 4326, "imageSR": 4326,
        "size": f"{PIXELS},{PIXELS}", "format": "png", "f": "image",
    }


def baixar_um(lote_id: str, lat: float, lng: float, lado_m: int, sessao) -> str:
    c = config.caminhos()
    png = c.recortes / f"{lote_id}_{lado_m}.png"
    if png.exists():
        return "ja_existia"
    caixa = geo.caixa_recorte(lat, lng, lado_m)
    resposta = sessao.get(URL_ORTOFOTO, _parametros(caixa), timeout=60)
    tipo = str(resposta.headers.get("Content-Type", "")).lower()
    if resposta.status_code != 200 or not tipo.startswith("image/") or not resposta.content:
        return "falha"
    arquivos.gravar_json(c.recortes / f"{lote_id}_{lado_m}.json", {
        "caixa": list(caixa), "lado_m": lado_m, "pixel_do_ponto": list(geo.para_pixel(caixa, lat, lng, PIXELS)),
    })
    arquivos.gravar_atomico(png, resposta.content)
    return "baixado"


def baixar_todos(manifesto: dict, sessao=None, limite: int | None = None) -> dict:
    sessao = sessao or wfs.SessaoHttp()
    c = config.caminhos()
    lotes = [(l, geo.lados_de_treino(l)) for l in manifesto.get("treino", [])]
    lotes += [(l, list(geo.LADOS_RECORTE)) for l in manifesto.get("teste", [])]
    if limite is not None:
        lotes = lotes[:limite]
    pedidos = [(lote, lado) for lote, lados in lotes for lado in lados]
    contagem = {"baixados": 0, "ja_existiam": 0, "falhas": 0, "em_branco": 0}
    inicio = time.monotonic()
    ultimo_erro = None
    sem_imagem = set()
    for i, (lote, lado) in enumerate(pedidos, 1):
        if lote["id"] in sem_imagem:
            continue
        lat, lng = lote["ponto"]
        resultado = baixar_um(lote["id"], lat, lng, lado, sessao)
        chave = {"baixado": "baixados", "ja_existia": "ja_existiam", "falha": "falhas"}[resultado]
        contagem[chave] += 1
        if resultado != "falha" and em_branco(c.recortes / f"{lote['id']}_{lado}.png"):
            sem_imagem.add(lote["id"])
            contagem["em_branco"] += resultado == "baixado"
        if resultado == "falha":
            ultimo_erro = f"recorte de {lote['id']} ({lado} m) falhou"
        if i % 20 == 0 or i == len(pedidos):
            recursos.conferir_memoria()
            feitos = contagem["baixados"]
            situacao.registrar(c, "recortes", feitos=i, total=len(pedidos),
                               tempo_medio_s=(time.monotonic() - inicio) / feitos if feitos else None,
                               ultimo_erro=ultimo_erro)
    return contagem


def em_branco(png) -> bool:
    """Metade ou mais dos pixels com a mesma cor: o recorte caiu num buraco da ortofoto."""
    pixels = np.asarray(Image.open(png).convert("RGB")).reshape(-1, 3).astype(np.int32)
    cores = (pixels[:, 0] << 16) | (pixels[:, 1] << 8) | pixels[:, 2]
    return np.bincount(cores).max() >= FRACAO_COR_UNICA * len(cores)


def filtrar_com_imagem(manifesto: dict, n_treino: int, n_teste: int) -> dict:
    """Tira os lotes com recorte em branco ou ausente e corta nas quantidades pedidas, mantendo a ordem do sorteio."""
    c = config.caminhos()

    def tem_imagem(lote, lados):
        pngs = [c.recortes / f"{lote['id']}_{lado}.png" for lado in lados]
        return all(p.exists() and not em_branco(p) for p in pngs)

    treino = [l for l in manifesto.get("treino", []) if tem_imagem(l, geo.lados_de_treino(l))]
    teste = [l for l in manifesto.get("teste", []) if tem_imagem(l, geo.LADOS_RECORTE)]
    descartados = {
        **manifesto.get("descartados", {}),
        "sem_imagem_treino": len(manifesto.get("treino", [])) - len(treino),
        "sem_imagem_teste": len(manifesto.get("teste", [])) - len(teste),
    }
    teste = teste[:n_teste]
    return {
        **manifesto,
        "treino": treino[:n_treino],
        "teste": teste,
        "bairros_teste": sorted({l["bairro"] for l in teste}),
        "bairros_sorteados_teste": manifesto.get("bairros_teste", []),
        "descartados": descartados,
    }


def mascara_lote(poligono: dict, caixa, px: int = PIXELS) -> np.ndarray:
    imagem = Image.new("L", (px, px), 0)
    desenho = ImageDraw.Draw(imagem)
    forma = shape(poligono)
    partes = list(forma.geoms) if hasattr(forma, "geoms") else [forma]
    for parte in partes:
        desenho.polygon([geo.para_pixel(caixa, y, x, px) for x, y in parte.exterior.coords], fill=1)
        for buraco in parte.interiors:
            desenho.polygon([geo.para_pixel(caixa, y, x, px) for x, y in buraco.coords], fill=0)
    return np.array(imagem, dtype=bool)

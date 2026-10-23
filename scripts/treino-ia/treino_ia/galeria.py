"""Galeria estática dos resultados de uma avaliação, lida pela rota da gestão."""

import html as html_texto
import json
from importlib import resources as pacote
from pathlib import Path

import numpy as np
from PIL import Image, ImageDraw
from shapely.geometry import shape

from treino_ia import arquivos, config, geo

CORES = {"A": "#E24B4A", "B": "#EF9F27", "C": "#7F77DD", "D": "#1D9E75", "E": "#D4537E", "F": "#2C2C2A", "J": "#00A3E0"}
REF = "#185FA5"
TAMANHO_IMAGEM = 480


def _estatisticas(valores: list) -> dict:
    v = [x for x in valores if x is not None]
    if not v:
        return {"n": 0, "mediana": None, "q1": None, "q3": None}
    q1, mediana, q3 = np.percentile(v, [25, 50, 75])
    return {"n": len(v), "mediana": round(float(mediana), 4), "q1": round(float(q1), 4), "q3": round(float(q3), 4)}


def resumo(linhas: list[dict]) -> dict:
    ordem = sorted({l["variante"] for l in linhas})
    saida = {"lotes": len({l["lote_id"] for l in linhas}), "ordem_variantes": ordem, "variantes": {}}
    for v in ordem:
        das = [l for l in linhas if l["variante"] == v]
        saida["variantes"][v] = {
            "iou": _estatisticas([l["iou"] for l in das]),
            "iou_sem_cadastro_diferente": _estatisticas([l["iou"] for l in das if not l["cadastro_diferente"]]),
            "divisa_media_ia_ref_m": _estatisticas([l.get("divisa_media_ia_ref_m") for l in das]),
            "dif_area_pct": _estatisticas([l.get("dif_area_pct") for l in das]),
        }
    return saida


def _imagem(lote_id: str, linhas: list[dict], destino: Path) -> None:
    c = config.caminhos()
    lado = max([l["lado_m"] for l in linhas if l.get("lado_m")] or [60])
    png, meta = c.recortes / f"{lote_id}_{lado}.png", c.recortes / f"{lote_id}_{lado}.json"
    lat, lng = linhas[0]["ponto"]
    if png.exists() and meta.exists():
        base = Image.open(png).convert("RGB")
        caixa = arquivos.ler_json(meta)["caixa"]
    else:
        base = Image.new("RGB", (800, 800), (200, 200, 200))
        caixa = geo.caixa_recorte(lat, lng, lado)
    desenho = ImageDraw.Draw(base)

    def contorno(poligono, cor, largura):
        if not poligono:
            return
        forma = shape(poligono)
        for parte in getattr(forma, "geoms", [forma]):
            desenho.line([geo.para_pixel(caixa, y, x) for x, y in parte.exterior.coords], fill=cor, width=largura)

    contorno(linhas[0]["poligono_ref"], REF, 6)
    for linha in linhas:
        contorno(linha.get("poligono_ia"), CORES.get(linha["variante"], "#000000"), 3)
    x, y = geo.para_pixel(caixa, lat, lng)
    desenho.ellipse([x - 8, y - 8, x + 8, y + 8], fill="#FFD400", outline="#000000", width=2)
    base.resize((TAMANHO_IMAGEM, TAMANHO_IMAGEM)).save(destino)


def _json_seguro(obj) -> str:
    return json.dumps(obj, ensure_ascii=False).replace("</", "<\\/")


def _fmt(valor, casas):
    return "—" if valor is None else f"{valor:.{casas}f}"


def _cartao(cartao: dict, ordem: list[str]) -> str:
    linhas = []
    for v in ordem:
        r = cartao["variantes"].get(v) or {}
        situacao = "aceito" if r.get("aceite_atual") else (r.get("motivo_recusa") or "recusado")
        linhas.append(f"<tr><td>{v}</td><td>IoU {_fmt(r.get('iou'), 3)}</td><td>divisa {_fmt(r.get('divisa_media_ia_ref_m'), 1)} m</td>"
                      f"<td>área {_fmt(r.get('dif_area_pct'), 1)}%</td><td>{html_texto.escape(situacao)}</td></tr>")
    marca = '<span class="marca">Cadastro diferente do CODLOG</span>' if cartao["cadastro_diferente"] else ""
    lote = html_texto.escape(str(cartao["lote_id"]))
    return (f'<div class="card" data-lote="{lote}" data-faixa="{html_texto.escape(cartao["faixa_area"])}">'
            f'<img src="img/{lote}.png" alt="Lote {lote}"><div><b>{lote}</b> · {html_texto.escape(cartao.get("bairro") or "")} · '
            f'{html_texto.escape(cartao["faixa_area"])} {marca}</div><table>{"".join(linhas)}</table></div>')


def gerar(execucao: str) -> Path:
    c = config.caminhos()
    linhas = arquivos.ler_json(c.avaliacoes / execucao / "resultados.json", padrao=[])
    pasta = c.galerias / execucao
    (pasta / "img").mkdir(parents=True, exist_ok=True)
    por_lote: dict[str, list] = {}
    for linha in linhas:
        por_lote.setdefault(linha["lote_id"], []).append(linha)
    cartoes = []
    for lote_id, das in por_lote.items():
        _imagem(lote_id, das, pasta / "img" / f"{lote_id}.png")
        cartoes.append({
            "lote_id": lote_id, "bairro": das[0].get("bairro"), "faixa_area": das[0]["faixa_area"],
            "cadastro_diferente": das[0]["cadastro_diferente"],
            "variantes": {l["variante"]: {k: l.get(k) for k in ("iou", "divisa_media_ia_ref_m", "dif_area_pct", "aceite_atual", "motivo_recusa")} for l in das},
        })
    sumario = resumo(linhas)
    arquivos.gravar_json(pasta / "resumo.json", {**sumario, "execucao": execucao})
    legenda = "".join(f'{v}<span style="background:{CORES.get(v, "#000")}"></span>' for v in sumario["ordem_variantes"])
    modelo = (pacote.files("treino_ia") / "modelos" / "galeria.html").read_text(encoding="utf-8")
    html = (modelo.replace("__EXECUCAO__", html_texto.escape(execucao)).replace("__LEGENDA__", legenda)
            .replace("__CARTOES__", "".join(_cartao(c, sumario["ordem_variantes"]) for c in cartoes))
            .replace("__DADOS__", _json_seguro(cartoes)).replace("__RESUMO__", _json_seguro(sumario)))
    destino = pasta / "index.html"
    arquivos.gravar_atomico(destino, html.encode("utf-8"))
    return destino

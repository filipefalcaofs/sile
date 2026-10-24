"""Verificação de ponta a ponta do serviço footprint-ia com os pesos treinados.

Sobe o serviço (docker/footprint-ia/app.py) no próprio processo, chama /segmentar
com os recortes reais e aplica a mesma regra de recorte crescente do
GerarPoligonoIaJob: em cada lado (60, 120, 240 m), fica o candidato de maior
score entre os que não encostam na borda se nenhum encosta, ou no último lado.
O anel em pixels vira coordenada como em RecorteOrtofoto::coordenada e é medido
contra o lote do CODLOG, que não entra na chamada.

Uso (no ambiente do serviço, com shapely):
  FOOTPRINT_IA_PESOS=<pesos.pt> python ponta_a_ponta.py validacao|teste [--saida arquivo.json]
"""

import argparse
import io
import json
import os
import resource
import statistics
import sys
import time
from pathlib import Path

from PIL import Image
from shapely.geometry import Polygon, shape

AQUI = Path(__file__).resolve().parent
sys.path.insert(0, str(AQUI))
sys.path.insert(0, str(AQUI.parents[1] / "docker" / "footprint-ia"))

from treino_ia import arquivos, config, geo, metricas  # noqa: E402

LADOS = (60, 120, 240)


def _cliente():
    from fastapi.testclient import TestClient

    import app as servico

    return TestClient(servico.app), servico


def _segmentar(cliente, png: Path, x: int, y: int) -> tuple[dict, float]:
    inicio = time.perf_counter()
    resposta = cliente.post(
        "/segmentar",
        files={"imagem": ("recorte.png", png.read_bytes(), "image/png")},
        data={"x": str(x), "y": str(y), "largura": "800", "altura": "800"},
    )
    resposta.raise_for_status()
    return resposta.json(), time.perf_counter() - inicio


def _crescente(cliente, lote: dict) -> dict:
    c = config.caminhos()
    lat, lng = lote["ponto"]
    tempos, ultimo = [], None
    for indice, lado in enumerate(LADOS):
        meta = arquivos.ler_json(c.recortes / f"{lote['id']}_{lado}.json")
        caixa = meta["caixa"]
        x, y = geo.para_pixel(caixa, lat, lng)
        corpo, tempo = _segmentar(cliente, c.recortes / f"{lote['id']}_{lado}.png", x, y)
        tempos.append(tempo)
        candidatos = corpo.get("candidatos", [])
        livres = [k for k in candidatos if not k["encosta"]]
        ultimo = {"lado_m": lado, "caixa": caixa, "anel": None, "score": None}
        if livres and (len(livres) == len(candidatos) or indice == len(LADOS) - 1):
            melhor = max(livres, key=lambda k: k["score"])
            ultimo.update(anel=melhor["anel"], score=melhor["score"])
            break
    return {**ultimo, "tempos_s": tempos, "modelo": corpo.get("modelo")}


def _poligono(anel_px: list, caixa) -> dict | None:
    if not anel_px or len(anel_px) < 3:
        return None
    coords = [list(geo.para_coord(caixa, px, py)) for px, py in anel_px]
    return {"type": "Polygon", "coordinates": [coords + coords[:1]]}


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("conjunto", choices=("validacao", "teste"))
    parser.add_argument("--saida")
    parser.add_argument("--limite", type=int)
    args = parser.parse_args()

    cliente, servico = _cliente()
    lotes = arquivos.ler_json(config.caminhos().dados / "manifesto.json")[args.conjunto][: args.limite]
    linhas = []
    for i, lote in enumerate(lotes, 1):
        r = _crescente(cliente, lote)
        poligono = _poligono(r["anel"], r["caixa"])
        m = metricas.comparar(poligono, lote["poligono"])
        forma = shape(poligono) if poligono else None
        linhas.append({
            "lote_id": lote["id"], "bairro": lote["bairro"], "lado_m": r["lado_m"], "score": r["score"],
            "tempos_s": r["tempos_s"], "vertices": len(r["anel"] or []),
            "valido": bool(forma is not None and forma.is_valid), "vazio": forma is None or forma.is_empty,
            "dice": round(2 * m["intersecao_m2"] / (m["area_ref_m2"] + m["area_ia_m2"]), 4) if m["area_ia_m2"] else 0.0,
            "poligono_ia": poligono, **{k: v for k, v in m.items() if k != "aceite_atual"},
        })
        if i % 20 == 0:
            print(f"{i}/{len(lotes)}", flush=True)

    ious = [l["iou"] or 0 for l in linhas]
    tempos = [sum(l["tempos_s"]) for l in linhas]
    chamadas = [t for l in linhas for t in l["tempos_s"]]
    com_poligono = [l for l in linhas if not l["vazio"]]
    rss_mb = resource.getrusage(resource.RUSAGE_SELF).ru_maxrss / (1024 * 1024 if sys.platform == "darwin" else 1024)
    resumo = {
        "conjunto": args.conjunto, "lotes": len(linhas), "modelo": servico.nome_do_modelo(),
        "iou_mediano": round(statistics.median(ious), 4), "iou_q1": round(statistics.quantiles(ious, n=4)[0], 4),
        "iou_q3": round(statistics.quantiles(ious, n=4)[2], 4),
        "iou_05": sum(i >= 0.5 for i in ious), "iou_07": sum(i >= 0.7 for i in ious),
        "dice_mediano": round(statistics.median(l["dice"] for l in linhas), 4),
        "divisa_mediana_m": round(statistics.median(d), 2) if (d := [l["divisa_media_ia_ref_m"] for l in com_poligono if l["divisa_media_ia_ref_m"] is not None]) else None,
        "sem_poligono": len(linhas) - len(com_poligono),
        "invalidos": sum(not l["valido"] for l in com_poligono),
        "vertices_mediana": statistics.median(l["vertices"] for l in com_poligono) if com_poligono else 0,
        "lados": {lado: sum(l["lado_m"] == lado for l in linhas) for lado in LADOS},
        "tempo_por_chamada_s": round(statistics.median(chamadas), 2), "tempo_por_lote_mediano_s": round(statistics.median(tempos), 2),
        "tempo_por_lote_max_s": round(max(tempos), 2), "memoria_pico_mb": round(rss_mb),
        "dispositivo": os.environ.get("FOOTPRINT_IA_DEVICE", "cpu"),
    }
    print(json.dumps(resumo, ensure_ascii=False, indent=1))
    if args.saida:
        Path(args.saida).write_text(json.dumps({"resumo": resumo, "lotes": linhas}, ensure_ascii=False))


if __name__ == "__main__":
    main()

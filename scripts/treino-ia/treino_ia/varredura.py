"""Escolha da época pela validação: avalia a variante E com os pesos de cada época.

O teste não entra aqui; ele fica para o resultado final do modelo escolhido.
"""

import statistics

from treino_ia import arquivos, avaliacao, config


def _resumo(caminho) -> dict:
    linhas = [l for l in arquivos.ler_json(caminho) if l["variante"] == "E"]
    ious = [l["iou"] or 0 for l in linhas]
    return {
        "lotes": len(ious),
        "iou_mediano": round(statistics.median(ious), 4) if ious else 0,
        "iou_05": sum(i >= 0.5 for i in ious),
        "iou_07": sum(i >= 0.7 for i in ious),
    }


def varrer(semente: int, passo: int = 2) -> dict:
    c = config.caminhos()
    pesos = sorted((c.pesos / "epocas" / f"s{semente}").glob("epoca_*.pt"))
    if not pesos:
        raise SystemExit(f"Sem pesos por época em pesos/epocas/s{semente}.")
    escolhidos = [p for p in pesos if int(p.stem.split("_")[1]) % passo == 0]
    if pesos[-1] not in escolhidos:
        escolhidos.append(pesos[-1])
    epocas = []
    for p in escolhidos:
        epoca = int(p.stem.split("_")[1])
        destino = avaliacao.avaliar("validacao", ["E"], execucao=f"val-s{semente}-e{epoca:02d}", pesos=str(p))
        epocas.append({"epoca": epoca, "pesos": str(p), **_resumo(destino)})
    melhor = max(epocas, key=lambda e: (e["iou_mediano"], e["iou_05"]))
    resumo = {"semente": semente, "passo": passo, "epocas": epocas, "melhor": melhor}
    arquivos.gravar_json(c.avaliacoes / f"varredura-s{semente}.json", resumo)
    return resumo

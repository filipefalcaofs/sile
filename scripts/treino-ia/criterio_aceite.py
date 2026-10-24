"""Simula o AceiteMascaraIa do Viabiliza sobre a saída de ponta_a_ponta.py, para cada score mínimo.

As regras são as do aceite (ponto contido, área entre 20 e 20.000 m², compacidade
≥ 0,2; o contorno que encosta na borda já sai sem polígono do recorte crescente).
Para cada score mínimo, mostra quantos lotes seriam aceitos, a qualidade dos
aceitos contra o CODLOG e quantos contornos bons seriam perdidos.

Uso: python criterio_aceite.py <saida_de_ponta_a_ponta.json>
"""

import json
import statistics
import sys

LIMITES = (None, 0.5, 0.6, 0.7, 0.75, 0.8, 0.85, 0.9, 0.95)
BOM, RUIM = 0.5, 0.3


def aceito_pelas_regras(lote: dict) -> bool:
    return (not lote["vazio"] and lote["contem_ponto"] and 20 <= lote["area_ia_m2"] <= 20000
            and lote["compacidade"] >= 0.2)


def _postos(valores: list[float]) -> list[float]:
    ordem = sorted(range(len(valores)), key=lambda i: valores[i])
    postos = [0.0] * len(valores)
    for posto, i in enumerate(ordem):
        postos[i] = float(posto)
    return postos


def spearman(x: list[float], y: list[float]) -> float:
    px, py = _postos(x), _postos(y)
    mx, my = statistics.mean(px), statistics.mean(py)
    cov = sum((a - mx) * (b - my) for a, b in zip(px, py))
    return cov / (sum((a - mx) ** 2 for a in px) * sum((b - my) ** 2 for b in py)) ** 0.5


def tabela(lotes: list[dict]) -> list[dict]:
    bons = sum((l["iou"] or 0) >= BOM for l in lotes)
    linhas = []
    for limite in LIMITES:
        aceitos = [l for l in lotes if aceito_pelas_regras(l) and (limite is None or (l["score"] or 0) >= limite)]
        ious = [l["iou"] or 0 for l in aceitos]
        bons_aceitos = sum(i >= BOM for i in ious)
        linhas.append({
            "score_min": limite, "aceitos": len(aceitos), "cobertura": round(len(aceitos) / len(lotes), 3),
            "iou_mediano_aceitos": round(statistics.median(ious), 3) if ious else None,
            "precisao_iou_05": round(bons_aceitos / len(aceitos), 3) if aceitos else None,
            "ruins_aceitos": sum(i < RUIM for i in ious), "bons_perdidos": bons - bons_aceitos,
        })
    return linhas


def main() -> None:
    lotes = json.load(open(sys.argv[1]))["lotes"]
    com_score = [l for l in lotes if l["score"] is not None]
    print(f"{len(lotes)} lotes; correlação de Spearman entre score e IoU: "
          f"{spearman([l['score'] for l in com_score], [l['iou'] or 0 for l in com_score]):.2f}")
    print("score_min | aceitos | cobertura | IoU mediano | precisão (IoU ≥ 0,5) | ruins (IoU < 0,3) | bons perdidos")
    for t in tabela(lotes):
        print(f"{str(t['score_min']):>9} | {t['aceitos']:7d} | {t['cobertura']:9.0%} | {t['iou_mediano_aceitos'] or 0:11.3f} | "
              f"{t['precisao_iou_05'] or 0:20.0%} | {t['ruins_aceitos']:17d} | {t['bons_perdidos']:13d}")


if __name__ == "__main__":
    main()

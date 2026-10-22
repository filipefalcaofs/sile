"""Conjunto de lotes do CODLOG para treino e teste, separado por bairro."""

import random
from collections import defaultdict

from shapely.geometry import Point, shape

from treino_ia import arquivos, config, geo, wfs

AREA_MIN_M2 = 40
AREA_MAX_M2 = 20000
CAMADA_LOTES = "enderecamento:VM_ENDERECAMENTO"
CAMADA_BAIRROS = "bairro_oficial:VM_BAIRRO_OFICIAL"


def _chave(feicao: dict) -> tuple:
    p = feicao.get("properties") or {}
    return (p.get("CODLOG"), p.get("NUMERACAO_METRICA"))


def montar_de(lotes: list[dict], bairros: list[dict], semente: int, n_treino: int = 600, n_teste: int = 60) -> dict:
    rng = random.Random(semente)
    descartados = {"invalido": 0, "area": 0, "chave_ambigua": 0, "maior_que_recorte": 0, "sem_bairro": 0}
    formas_bairro = [((b.get("properties") or {}).get("NOME_BAIRRO"), shape(b["geometry"])) for b in bairros if b.get("geometry")]

    por_chave = defaultdict(list)
    for feicao in lotes:
        geometria = feicao.get("geometry")
        forma = shape(geometria) if geometria else None
        if forma is None or forma.is_empty or not forma.is_valid:
            descartados["invalido"] += 1
            continue
        if not AREA_MIN_M2 <= geo.area_m2(geometria) <= AREA_MAX_M2:
            descartados["area"] += 1
            continue
        por_chave[_chave(feicao)].append(feicao)

    candidatos = []
    for chave in sorted(por_chave, key=lambda c: (str(c[0]), str(c[1]))):
        feicoes = por_chave[chave]
        if len({shape(f["geometry"]).wkb for f in feicoes}) > 1:
            descartados["chave_ambigua"] += len(feicoes)
            continue
        geometria = feicoes[0]["geometry"]
        lat, lng = geo.ponto_interno(geometria, rng)
        bairro = next((nome for nome, forma in formas_bairro if forma.contains(Point(lng, lat))), None)
        if bairro is None:
            descartados["sem_bairro"] += 1
            continue
        lado = geo.lado_minimo(geometria, lat, lng)
        if lado is None:
            descartados["maior_que_recorte"] += 1
            continue
        candidatos.append({
            "id": f"{chave[0]}-{chave[1]}", "codlog": chave[0], "numero": chave[1], "bairro": bairro,
            "ponto": [lat, lng], "lado_m": lado, "poligono": geometria,
        })

    nomes = sorted({c["bairro"] for c in candidatos})
    rng.shuffle(nomes)
    bairros_teste, no_teste = [], []
    for nome in nomes:
        if len(no_teste) >= n_teste:
            break
        bairros_teste.append(nome)
        no_teste.extend(c for c in candidatos if c["bairro"] == nome)
    resto = [c for c in candidatos if c["bairro"] not in bairros_teste]

    return {
        "semente": semente,
        "bairros_teste": sorted(bairros_teste),
        "treino": rng.sample(resto, min(n_treino, len(resto))),
        "teste": rng.sample(no_teste, min(n_teste, len(no_teste))),
        "descartados": descartados,
    }


def montar(semente: int, n_treino: int = 600, n_teste: int = 60) -> dict:
    c = config.caminhos()
    wfs.baixar(CAMADA_LOTES, c.dados / "wfs" / "lotes", ordenar_por="CODLOG,NUMERACAO_METRICA")
    wfs.baixar(CAMADA_BAIRROS, c.dados / "wfs" / "bairros", ordenar_por="ID")
    manifesto = montar_de(wfs.ler(c.dados / "wfs" / "lotes"), wfs.ler(c.dados / "wfs" / "bairros"), semente, n_treino, n_teste)
    arquivos.gravar_json(c.dados / "manifesto.json", manifesto)
    return manifesto

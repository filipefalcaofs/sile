"""Conjunto de lotes do CODLOG para treino e teste, separado por bairro."""

import random
from collections import defaultdict

from shapely.geometry import Point, shape

from treino_ia import arquivos, config, geo, recursos, wfs

AREA_MIN_M2 = 40
AREA_MAX_M2 = 20000
CAMADA_LOTES = "enderecamento:VM_ENDERECAMENTO"
CAMADA_BAIRROS = "bairro_oficial:VM_BAIRRO_OFICIAL"


def _chave(feicao: dict) -> tuple:
    p = feicao.get("properties") or {}
    return (p.get("CODLOG"), p.get("NUMERACAO_METRICA"))


def montar_de(lotes: list[dict], bairros: list[dict], semente: int, n_treino: int = 600, n_teste: int = 60,
              teste_por_bairro: int | None = None) -> dict:
    """teste_por_bairro limita os lotes de teste de cada bairro, para o teste cobrir mais bairros."""
    rng = random.Random(semente)
    descartados = {"invalido": 0, "area": 0, "chave_ambigua": 0, "maior_que_recorte": 0, "sem_bairro": 0}
    formas_bairro = [((b.get("properties") or {}).get("NOME_BAIRRO"), shape(b["geometry"])) for b in bairros if b.get("geometry")]

    por_chave = defaultdict(list)
    recursos.conferir_memoria()
    for indice, feicao in enumerate(lotes):
        if indice % 2000 == 0:
            recursos.conferir_memoria()
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
        do_bairro = [c for c in candidatos if c["bairro"] == nome]
        if teste_por_bairro is not None and len(do_bairro) > teste_por_bairro:
            do_bairro = rng.sample(do_bairro, teste_por_bairro)
        no_teste.extend(do_bairro)
    resto = [c for c in candidatos if c["bairro"] not in bairros_teste]

    return {
        "semente": semente,
        "bairros_teste": sorted(bairros_teste),
        "treino": rng.sample(resto, min(n_treino, len(resto))),
        "teste": rng.sample(no_teste, min(n_teste, len(no_teste))),
        "descartados": descartados,
    }


def separar_validacao(manifesto: dict, fracao: float, semente: int) -> dict:
    """Tira do treino bairros inteiros para a validação, até a fração pedida dos lotes.

    Parte sempre do treino somado à validação anterior, então refazer com a mesma
    semente devolve a mesma separação.
    """
    todos = manifesto.get("treino", []) + manifesto.get("validacao", [])
    nomes = sorted({l["bairro"] for l in todos})
    random.Random(semente).shuffle(nomes)
    escolhidos, quantidade = [], 0
    for nome in nomes:
        if quantidade >= fracao * len(todos):
            break
        escolhidos.append(nome)
        quantidade += sum(1 for l in todos if l["bairro"] == nome)
    return {
        **manifesto,
        "treino": [l for l in todos if l["bairro"] not in escolhidos],
        "validacao": [l for l in todos if l["bairro"] in escolhidos],
        "bairros_validacao": sorted(escolhidos),
        "semente_validacao": semente,
    }


def montar(semente: int, n_treino: int = 600, n_teste: int = 60, teste_por_bairro: int | None = None) -> dict:
    c = config.caminhos()
    wfs.baixar(CAMADA_LOTES, c.dados / "wfs" / "lotes", ordenar_por="CODLOG,NUMERACAO_METRICA")
    wfs.baixar(CAMADA_BAIRROS, c.dados / "wfs" / "bairros", ordenar_por="ID")
    manifesto = montar_de(wfs.ler(c.dados / "wfs" / "lotes"), wfs.ler(c.dados / "wfs" / "bairros"), semente, n_treino, n_teste, teste_por_bairro)
    arquivos.gravar_json(c.dados / "manifesto.json", manifesto)
    return manifesto

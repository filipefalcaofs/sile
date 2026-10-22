"""Roda as variantes nos lotes de teste ou do teste cego e mede contra o CODLOG.

O modelo recebe só o lote sem o polígono; o polígono de referência entra só
em metricas.comparar. Cada lote é gravado à parte, então a avaliação retoma.
"""

from datetime import date

import numpy as np
from PIL import Image
from shapely.geometry import Point, shape

from treino_ia import arquivos, config, geo, metricas, recortes, recursos, situacao, variantes, wfs

CAMADA_LOGRADOUROS = "logradouros:VM_LOGRADOURO"
CAMPOS_DE_REFERENCIA = ("poligono",)
CAMPOS_DO_MODELO = ("id", "ponto", "codlog", "numero")


class RecorteIndisponivel(RuntimeError):
    pass


def faixa_area(area_m2: float) -> str:
    if area_m2 < 200:
        return "<200"
    if area_m2 < 1000:
        return "200–1.000"
    if area_m2 < 5000:
        return "1.000–5.000"
    return ">5.000"


def eixos_do_logradouro(codlog) -> list:
    c = config.caminhos()
    destino = c.dados / "eixos" / str(codlog)
    wfs.baixar(CAMADA_LOGRADOUROS, destino, pagina=500, filtro=f"CODLOG={int(codlog)}", ordenar_por="CODLOG")
    linhas = []
    for feicao in wfs.ler(destino):
        g = feicao.get("geometry") or {}
        if g.get("type") == "LineString":
            linhas.append(g["coordinates"])
        elif g.get("type") == "MultiLineString":
            linhas.extend(g["coordinates"])
    return linhas


def _lotes(conjunto: str) -> list[tuple[dict, dict, dict]]:
    c = config.caminhos()
    saida = []
    if conjunto == "teste":
        for lote in arquivos.ler_json(c.dados / "manifesto.json")["teste"]:
            entrada = {k: v for k, v in lote.items() if k not in CAMPOS_DE_REFERENCIA}
            saida.append((entrada, lote["poligono"], {"cadastro_diferente": False, "unidade": None, "area_cadastro": None}))
    else:
        referencias = arquivos.ler_json(c.cego / "referencia.json")
        for entrada in arquivos.ler_json(c.cego / "entrada.json"):
            ref = referencias.get(str(entrada["id"]))
            if not ref:
                continue
            lat, lng = entrada["ponto"]
            saida.append((entrada, ref, {
                "cadastro_diferente": not shape(ref).contains(Point(lng, lat)),
                "unidade": entrada.get("unidade"), "area_cadastro": entrada.get("area_cadastro"),
            }))
    return saida


def _carregador(entrada: dict, sessao):
    def carregar(lote_id, lado):
        c = config.caminhos()
        lat, lng = entrada["ponto"]
        recortes.baixar_um(lote_id, lat, lng, lado, sessao)
        png, meta = c.recortes / f"{lote_id}_{lado}.png", c.recortes / f"{lote_id}_{lado}.json"
        if not png.exists() or not meta.exists():
            raise RecorteIndisponivel(f"recorte {lote_id}_{lado} indisponível")
        return np.array(Image.open(png).convert("RGB")), tuple(arquivos.ler_json(meta)["caixa"])

    return carregar


def _pesos_treinados():
    pesos = sorted(config.caminhos().pesos.glob("decodificador_*.pt"))
    return pesos[-1] if pesos else None


def avaliar(conjunto: str, nomes: list[str], execucao: str | None = None, seg_base=None, seg_treinado=None,
            sessao=None, eixos=None, regra_f: str | None = None):
    c = config.caminhos()
    execucao = execucao or f"{date.today():%Y%m%d}-{conjunto}"
    pasta = c.avaliacoes / execucao
    sessao = sessao or wfs.SessaoHttp()
    eixos = eixos or eixos_do_logradouro
    if seg_base is None and any(n in "ABCD" for n in nomes):
        seg_base = variantes.Segmentador()
    if seg_treinado is None and any(n in "EF" for n in nomes):
        if _pesos_treinados() is None:
            raise SystemExit("Variantes E/F pedidas sem pesos treinados em pesos/decodificador_*.pt. Rode treinar antes ou tire E/F.")
        seg_treinado = variantes.Segmentador(_pesos_treinados())

    lotes = _lotes(conjunto)
    for i, (entrada, referencia, extra) in enumerate(lotes, 1):
        arquivo = pasta / "lotes" / f"{entrada['id']}.json"
        feitas = arquivos.ler_json(arquivo, padrao=[]) or []
        faltam = [n for n in nomes if n not in {l["variante"] for l in feitas}]
        if not faltam:
            continue
        lat, lng = entrada["ponto"]
        modelo_entrada = {k: entrada[k] for k in CAMPOS_DO_MODELO if k in entrada}
        contexto = {"carregar_recorte": _carregador(entrada, sessao), "eixo": eixos(entrada.get("codlog")) if entrada.get("codlog") else [],
                    "unidade": extra["unidade"], "area_cadastro": extra["area_cadastro"], "regra_f": regra_f}
        novas, erro = [], None
        for nome in faltam:
            seg = seg_treinado if nome in "EF" else seg_base
            try:
                r = variantes.rodar(nome, modelo_entrada, seg, contexto)
            except RecorteIndisponivel as falha:
                erro = str(falha)
                break
            m = metricas.comparar(r["poligono"] if r else None, referencia)
            if r:
                caixa = geo.caixa_recorte(lat, lng, r["lado_m"])
                m["aceite_atual"], m["motivo_recusa"] = metricas.aceite_atual(r["anel_px"], caixa, lat, lng)
            novas.append({
                "lote_id": entrada["id"], "conjunto": conjunto, "variante": nome, "bairro": entrada.get("bairro"),
                "faixa_area": faixa_area(m["area_ref_m2"]), "cadastro_diferente": extra["cadastro_diferente"],
                "unidade": extra["unidade"], "lado_m": r["lado_m"] if r else None, "score": r["score"] if r else None,
                "poligono_ia": r["poligono"] if r else None, "poligono_ref": referencia, "ponto": entrada["ponto"], **m,
            })
        if erro is None:
            arquivos.gravar_json(arquivo, feitas + novas)
        recursos.conferir_memoria()
        situacao.registrar(c, f"avaliar {conjunto}", feitos=i, total=len(lotes), ultimo_erro=erro)

    todas = []
    for entrada, _, _ in lotes:
        todas.extend(arquivos.ler_json(pasta / "lotes" / f"{entrada['id']}.json", padrao=[]))
    destino = pasta / "resultados.json"
    arquivos.gravar_json(destino, todas)
    return destino

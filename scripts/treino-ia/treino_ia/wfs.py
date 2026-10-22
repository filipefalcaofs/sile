"""Download paginado e retomável das camadas do GeoServer da SEDUR."""

import json
import time
import urllib.parse
import urllib.request
from pathlib import Path

from treino_ia import arquivos

URL = "https://geoserver.sedur.salvador.ba.gov.br/geoserver/wfs"
TENTATIVAS = 3


class WfsIndisponivel(RuntimeError):
    pass


class _Resposta:
    def __init__(self, status_code: int, content: bytes, headers: dict | None = None):
        self.status_code = status_code
        self.content = content
        self.headers = headers or {}


class SessaoHttp:
    def get(self, url, params, timeout):
        try:
            with urllib.request.urlopen(url + "?" + urllib.parse.urlencode(params), timeout=timeout) as r:
                return _Resposta(r.status, r.read(), dict(r.headers))
        except Exception as erro:
            return _Resposta(getattr(erro, "code", 0) or 0, str(erro).encode())


def _parametros(type_name: str, inicio: int, pagina: int, filtro: str | None, ordenar_por: str | None) -> dict:
    parametros = {
        "service": "WFS", "version": "1.1.0", "request": "GetFeature", "typeName": type_name,
        "outputFormat": "application/json", "srsName": "EPSG:4326",
        "startIndex": inicio, "maxFeatures": pagina,
    }
    if ordenar_por:
        parametros["sortBy"] = ordenar_por
    if filtro:
        parametros["CQL_FILTER"] = filtro
    return parametros


def baixar(type_name: str, destino: Path, pagina: int = 2000, sessao=None, filtro: str | None = None, limite_paginas: int | None = None, ordenar_por: str | None = None) -> int:
    sessao = sessao or SessaoHttp()
    destino.mkdir(parents=True, exist_ok=True)
    feitas = sorted(destino.glob("pagina-*.json"))
    inicio = len(feitas) * pagina
    if feitas and arquivos.ler_json(destino / "completo.json", padrao=False):
        return len(ler(destino))
    baixadas = 0
    while limite_paginas is None or baixadas < limite_paginas:
        corpo = None
        for tentativa in range(TENTATIVAS):
            resposta = sessao.get(URL, _parametros(type_name, inicio, pagina, filtro, ordenar_por), timeout=120)
            if resposta.status_code == 200:
                try:
                    corpo = json.loads(resposta.content.decode("latin-1"))
                    break
                except ValueError:
                    pass
            time.sleep(5 * (tentativa + 1))
        if corpo is None:
            raise WfsIndisponivel(f"{type_name}: página a partir de {inicio} falhou {TENTATIVAS} vezes.")
        feicoes = corpo.get("features", [])
        arquivos.gravar_json(destino / f"pagina-{inicio // pagina:05d}.json", feicoes)
        baixadas += 1
        if len(feicoes) < pagina:
            arquivos.gravar_json(destino / "completo.json", True)
            break
        inicio += pagina
    return len(ler(destino))


def ler(destino: Path) -> list[dict]:
    feicoes = []
    for arquivo in sorted(destino.glob("pagina-*.json")):
        feicoes.extend(arquivos.ler_json(arquivo, padrao=[]))
    return feicoes

import json

import pytest

from treino_ia import wfs


class Resposta:
    def __init__(self, status, corpo):
        self.status_code = status
        self.content = json.dumps(corpo).encode("latin-1") if isinstance(corpo, dict) else corpo


class SessaoFalsa:
    def __init__(self, paginas, falhar_uma_vez_em=None):
        self.paginas = paginas
        self.chamadas = []
        self.falhar_uma_vez_em = falhar_uma_vez_em

    def get(self, url, params, timeout):
        inicio = params["startIndex"]
        self.chamadas.append(inicio)
        if inicio == self.falhar_uma_vez_em:
            self.falhar_uma_vez_em = None
            return Resposta(500, b"erro")
        pagina = self.paginas.get(inicio, [])
        return Resposta(200, {"type": "FeatureCollection", "features": pagina})


def _feicao(i):
    return {"type": "Feature", "properties": {"ID": i}, "geometry": None}


def test_baixa_todas_as_paginas_e_tenta_de_novo(tmp_path, monkeypatch):
    monkeypatch.setattr(wfs.time, "sleep", lambda _: None)
    sessao = SessaoFalsa({0: [_feicao(1), _feicao(2)], 2: [_feicao(3)]}, falhar_uma_vez_em=2)

    total = wfs.baixar("camada:X", tmp_path / "x", pagina=2, sessao=sessao)

    assert total == 3
    assert [f["properties"]["ID"] for f in wfs.ler(tmp_path / "x")] == [1, 2, 3]


def test_retoma_da_pagina_gravada(tmp_path, monkeypatch):
    monkeypatch.setattr(wfs.time, "sleep", lambda _: None)
    paginas = {0: [_feicao(1), _feicao(2)], 2: [_feicao(3), _feicao(4)], 4: []}
    wfs.baixar("camada:X", tmp_path / "x", pagina=2, sessao=SessaoFalsa({0: paginas[0]}), limite_paginas=1)

    sessao = SessaoFalsa(paginas)
    wfs.baixar("camada:X", tmp_path / "x", pagina=2, sessao=sessao)

    assert 0 not in sessao.chamadas
    assert len(wfs.ler(tmp_path / "x")) == 4


def test_desiste_depois_de_3_falhas(tmp_path, monkeypatch):
    monkeypatch.setattr(wfs.time, "sleep", lambda _: None)

    class SempreFalha(SessaoFalsa):
        def get(self, url, params, timeout):
            return Resposta(500, b"erro")

    with pytest.raises(wfs.WfsIndisponivel):
        wfs.baixar("camada:X", tmp_path / "x", pagina=2, sessao=SempreFalha({}))


def test_ordena_pelo_atributo_informado_e_nunca_pela_geometria(tmp_path):
    vistos = []

    class Registra(SessaoFalsa):
        def get(self, url, params, timeout):
            vistos.append(params.get("sortBy"))
            return super().get(url, params, timeout)

    wfs.baixar("camada:X", tmp_path / "x", pagina=2, sessao=Registra({0: [_feicao(1)]}), ordenar_por="ID")

    assert vistos == ["ID"]

import io

import pytest
from PIL import Image

from treino_ia import config, geo, recortes
from tests import sinteticos


class Resposta:
    def __init__(self, status_code, content, tipo):
        self.status_code = status_code
        self.content = content
        self.headers = {"Content-Type": tipo}


def _png():
    saida = io.BytesIO()
    Image.new("RGB", (800, 800), (120, 120, 120)).save(saida, format="PNG")
    return saida.getvalue()


class SessaoContadora:
    def __init__(self, tipo="image/png", conteudo=None):
        self.chamadas = 0
        self.tipo = tipo
        self.conteudo = conteudo

    def get(self, url, params, timeout):
        self.chamadas += 1
        return Resposta(200, self.conteudo if self.conteudo is not None else _png(), self.tipo)


def _manifesto():
    lotes = []
    for i in range(2):
        poligono = sinteticos.retangulo(i * 100, 0, 20, 20)
        c = geo.em_metros  # garante import
        lat, lng = -12.98 + 10 / 110574, -38.47 + (i * 100 + 10) / geo.m_lng(-12.98)
        lotes.append({"id": f"1-{i}", "ponto": [lat, lng], "lado_m": 60, "poligono": poligono})
    return {"treino": lotes[:1], "teste": lotes[1:]}


def test_nao_baixa_de_novo_o_que_ja_existe(tmp_path, monkeypatch):
    monkeypatch.setenv("TREINO_IA_RAIZ", str(tmp_path))
    recortes.baixar_todos(_manifesto(), sessao=SessaoContadora())

    sessao = SessaoContadora()
    resultado = recortes.baixar_todos(_manifesto(), sessao=sessao)

    assert sessao.chamadas == 0
    assert resultado["ja_existiam"] == 2
    assert (config.caminhos().recortes / "1-0_60.png").exists()
    assert (config.caminhos().recortes / "1-0_60.json").exists()


def test_resposta_que_nao_e_imagem_conta_como_falha_e_nao_grava(tmp_path, monkeypatch):
    monkeypatch.setenv("TREINO_IA_RAIZ", str(tmp_path))

    resultado = recortes.baixar_todos(_manifesto(), sessao=SessaoContadora(tipo="text/html", conteudo=b"<html>bloqueado</html>"))

    assert resultado["falhas"] == 2
    assert not list(config.caminhos().recortes.glob("*.png"))


def test_limite_processa_so_os_primeiros(tmp_path, monkeypatch):
    monkeypatch.setenv("TREINO_IA_RAIZ", str(tmp_path))

    resultado = recortes.baixar_todos(_manifesto(), sessao=SessaoContadora(), limite=1)

    assert resultado["baixados"] == 1


def test_mascara_do_lote_cobre_a_area_esperada():
    poligono = sinteticos.retangulo(0, 0, 20, 20)
    lat, lng = -12.98 + 10 / 110574, -38.47 + 10 / geo.m_lng(-12.98)
    caixa = geo.caixa_recorte(lat, lng, 60)

    mascara = recortes.mascara_lote(poligono, caixa)

    esperado = (800 * 20 / 60) ** 2
    assert mascara.dtype == bool
    assert mascara.sum() == pytest.approx(esperado, rel=0.03)
    assert mascara[400, 400]


def test_lote_de_treino_ganha_os_recortes_menores_e_o_de_teste_nao(tmp_path, monkeypatch):
    monkeypatch.setenv("TREINO_IA_RAIZ", str(tmp_path))
    m = _manifesto()
    m["treino"][0]["lado_m"] = 120
    m["teste"][0]["lado_m"] = 120

    recortes.baixar_todos(m, sessao=SessaoContadora())

    nomes = sorted(p.name for p in config.caminhos().recortes.glob("*.png"))
    assert nomes == ["1-0_120.png", "1-0_60.png", "1-1_120.png"]

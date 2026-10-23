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


def _png(vazio=False):
    import numpy as np

    saida = io.BytesIO()
    if vazio:
        Image.new("RGB", (800, 800), (253, 253, 253)).save(saida, format="PNG")
    else:
        Image.fromarray(np.random.default_rng(0).integers(0, 255, (800, 800, 3), dtype=np.uint8)).save(saida, format="PNG")
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
    assert resultado["ja_existiam"] == 4
    assert (config.caminhos().recortes / "1-0_60.png").exists()
    assert (config.caminhos().recortes / "1-0_60.json").exists()


def test_resposta_que_nao_e_imagem_conta_como_falha_e_nao_grava(tmp_path, monkeypatch):
    monkeypatch.setenv("TREINO_IA_RAIZ", str(tmp_path))

    resultado = recortes.baixar_todos(_manifesto(), sessao=SessaoContadora(tipo="text/html", conteudo=b"<html>bloqueado</html>"))

    assert resultado["falhas"] == 4
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


def test_lote_de_treino_ganha_os_recortes_menores_e_o_de_teste_todos_os_da_avaliacao(tmp_path, monkeypatch):
    monkeypatch.setenv("TREINO_IA_RAIZ", str(tmp_path))
    m = _manifesto()
    m["treino"][0]["lado_m"] = 120
    m["teste"][0]["lado_m"] = 120

    recortes.baixar_todos(m, sessao=SessaoContadora())

    nomes = sorted(p.name for p in config.caminhos().recortes.glob("*.png"))
    assert nomes == ["1-0_120.png", "1-0_60.png", "1-1_120.png", "1-1_240.png", "1-1_60.png"]


def _salvar(caminho, matriz):
    import numpy as np

    Image.fromarray(np.asarray(matriz, dtype="uint8")).save(caminho)


def test_em_branco_reconhece_recorte_sem_imagem(tmp_path):
    import numpy as np

    rng = np.random.default_rng(0)
    foto = rng.integers(0, 255, (100, 100, 3))
    meio_vazio = foto.copy()
    meio_vazio[:60] = 253
    _salvar(tmp_path / "vazio.png", np.full((100, 100, 3), 253))
    _salvar(tmp_path / "preto.png", np.zeros((100, 100, 3)))
    _salvar(tmp_path / "meio.png", meio_vazio)
    _salvar(tmp_path / "foto.png", foto)

    assert recortes.em_branco(tmp_path / "vazio.png")
    assert recortes.em_branco(tmp_path / "preto.png")
    assert recortes.em_branco(tmp_path / "meio.png")
    assert not recortes.em_branco(tmp_path / "foto.png")


def test_baixar_todos_conta_os_recortes_em_branco(tmp_path, monkeypatch):
    monkeypatch.setenv("TREINO_IA_RAIZ", str(tmp_path))

    resultado = recortes.baixar_todos(_manifesto(), sessao=SessaoContadora(conteudo=_png(vazio=True)))

    assert resultado["em_branco"] == resultado["baixados"] == 2


def _com_recortes(tmp_path, monkeypatch, vazios):
    import numpy as np

    monkeypatch.setenv("TREINO_IA_RAIZ", str(tmp_path))
    r = config.caminhos().recortes
    r.mkdir(parents=True, exist_ok=True)
    rng = np.random.default_rng(1)
    lotes = {"treino": [], "teste": []}
    for conjunto, ids in (("treino", ["t1", "t2", "t3"]), ("teste", ["s1", "s2"])):
        for i in ids:
            lado = 120 if i == "t2" else 60
            lotes[conjunto].append({"id": i, "lado_m": lado, "bairro": "B"})
            lados = geo.lados_de_treino({"lado_m": lado}) if conjunto == "treino" else geo.LADOS_RECORTE
            for l in lados:
                if (i, l) == ("t3", 60) and "t3" in vazios:
                    continue
                vazio = (i, l) in vazios
                _salvar(r / f"{i}_{l}.png", np.full((50, 50, 3), 253) if vazio else rng.integers(0, 255, (50, 50, 3)))
    return {**lotes, "semente": 1, "bairros_teste": ["B"], "descartados": {}}


def test_filtrar_tira_lote_com_recorte_em_branco_ou_ausente_e_corta_na_quantidade(tmp_path, monkeypatch):
    m = _com_recortes(tmp_path, monkeypatch, vazios={("t2", 60), ("s1", 240), "t3"})

    filtrado = recortes.filtrar_com_imagem(m, n_treino=5, n_teste=1)

    assert [l["id"] for l in filtrado["treino"]] == ["t1"]
    assert [l["id"] for l in filtrado["teste"]] == ["s2"]
    assert filtrado["descartados"]["sem_imagem_treino"] == 2
    assert filtrado["descartados"]["sem_imagem_teste"] == 1


def test_filtrar_corta_no_numero_pedido_mantendo_a_ordem(tmp_path, monkeypatch):
    m = _com_recortes(tmp_path, monkeypatch, vazios=set())

    filtrado = recortes.filtrar_com_imagem(m, n_treino=2, n_teste=1)

    assert [l["id"] for l in filtrado["treino"]] == ["t1", "t2"]
    assert [l["id"] for l in filtrado["teste"]] == ["s1"]


def test_cli_filtrar_guarda_o_manifesto_bruto_e_grava_o_filtrado(tmp_path, monkeypatch):
    from treino_ia import __main__, arquivos

    m = _com_recortes(tmp_path, monkeypatch, vazios={("t2", 60)})
    c = config.caminhos()
    arquivos.gravar_json(c.dados / "manifesto.json", m)

    assert __main__.main(["filtrar", "--treino", "5", "--teste", "5"]) == 0

    assert len(arquivos.ler_json(c.dados / "manifesto-bruto.json")["treino"]) == 3
    filtrado = arquivos.ler_json(c.dados / "manifesto.json")
    assert [l["id"] for l in filtrado["treino"]] == ["t1", "t3"]
    assert filtrado["bairros_sorteados_teste"] == ["B"]

    assert __main__.main(["filtrar", "--treino", "1", "--teste", "5"]) == 0
    assert [l["id"] for l in arquivos.ler_json(c.dados / "manifesto.json")["treino"]] == ["t1"]


def test_lote_com_primeiro_recorte_em_branco_nao_baixa_os_outros_lados(tmp_path, monkeypatch):
    monkeypatch.setenv("TREINO_IA_RAIZ", str(tmp_path))
    m = _manifesto()
    m["treino"][0]["lado_m"] = 240
    sessao = SessaoContadora(conteudo=_png(vazio=True))

    resultado = recortes.baixar_todos(m, sessao=sessao)

    assert sessao.chamadas == 2
    assert resultado["em_branco"] == 2
    assert sorted(p.name for p in config.caminhos().recortes.glob("*.png")) == ["1-0_60.png", "1-1_60.png"]

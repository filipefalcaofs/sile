from treino_ia import dados
from tests import sinteticos


def _montar(lotes, semente=2110, n_treino=20, n_teste=5):
    return dados.montar_de(lotes, sinteticos.bairros(), semente=semente, n_treino=n_treino, n_teste=n_teste)


def test_nenhum_bairro_de_teste_tem_lote_de_treino():
    m = _montar(sinteticos.lotes_em_grade())

    assert m["teste"]
    assert {l["bairro"] for l in m["treino"]}.isdisjoint(m["bairros_teste"])
    assert all(l["bairro"] in m["bairros_teste"] for l in m["teste"])


def test_mesma_semente_mesmo_conjunto():
    a = _montar(sinteticos.lotes_em_grade())
    b = _montar(sinteticos.lotes_em_grade())

    assert [l["id"] for l in a["treino"]] == [l["id"] for l in b["treino"]]
    assert a["semente"] == 2110


def test_quantidades_pedidas_sao_respeitadas():
    m = _montar(sinteticos.lotes_em_grade(), n_treino=12, n_teste=5)

    assert len(m["treino"]) == 12
    assert len(m["teste"]) == 5


def test_descarta_e_conta_lote_maior_que_240_m():
    lotes = sinteticos.lotes_em_grade() + [sinteticos.lote(999, 1, 20, 300, largura_m=300, altura_m=40)]

    m = _montar(lotes)

    assert m["descartados"]["maior_que_recorte"] == 1
    assert all(l["codlog"] != 999 for l in m["treino"] + m["teste"])


def test_descarta_area_fora_de_40_a_20000():
    lotes = sinteticos.lotes_em_grade() + [sinteticos.lote(998, 1, 20, 400, largura_m=5, altura_m=5)]

    assert _montar(lotes)["descartados"]["area"] == 1


def test_descarta_chave_ambigua():
    lotes = sinteticos.lotes_em_grade() + [
        sinteticos.lote(997, 7, 20, 300),
        sinteticos.lote(997, 7, 120, 300),
    ]

    assert _montar(lotes)["descartados"]["chave_ambigua"] == 2


def test_descarta_geometria_invalida():
    invalido = sinteticos.lote(996, 1, 20, 300)
    anel = invalido["geometry"]["coordinates"][0]
    invalido["geometry"]["coordinates"][0] = [anel[0], anel[2], anel[1], anel[3], anel[0]]

    assert _montar(sinteticos.lotes_em_grade() + [invalido])["descartados"]["invalido"] == 1


def test_lote_traz_ponto_lado_e_poligono():
    lote = _montar(sinteticos.lotes_em_grade())["treino"][0]

    assert set(lote) >= {"id", "codlog", "numero", "bairro", "ponto", "lado_m", "poligono"}
    assert lote["lado_m"] in (60, 120, 240)


def test_montar_confere_a_memoria_durante_a_leitura(monkeypatch):
    import pytest

    from treino_ia import recursos

    monkeypatch.setattr(recursos, "memoria_residente_mb", lambda: 9000)

    with pytest.raises(recursos.MemoriaExcedida):
        _montar(sinteticos.lotes_em_grade())

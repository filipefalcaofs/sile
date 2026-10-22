import numpy as np
import pytest

from treino_ia import geo, variantes

LAT, LNG = -12.996143, -38.465385


def _retangulo(x0, y0, x1, y1):
    m = np.zeros((800, 800), dtype=bool)
    m[y0:y1, x0:x1] = True
    return m


class SegFalso:
    """Devolve máscaras conforme o lado do último recorte carregado."""

    def __init__(self, por_lado):
        self.por_lado = por_lado
        self.lado = None
        self.chamadas = []

    def mascaras(self, img, pontos, rotulos, caixa_px=None):
        self.chamadas.append({"lado": self.lado, "pontos": pontos, "rotulos": rotulos})
        return self.por_lado[self.lado]


def _contexto(seg, **extra):
    def carregar(lote_id, lado):
        seg.lado = lado
        return np.zeros((800, 800, 3), dtype=np.uint8), geo.caixa_recorte(LAT, LNG, lado)

    return {"carregar_recorte": carregar, **extra}


LOTE = {"id": "1-1", "ponto": [LAT, LNG], "codlog": 1, "numero": 1}
PEQUENA = (_retangulo(380, 380, 420, 420), 0.9)
GRANDE = (_retangulo(300, 300, 500, 500), 0.5)
ENCOSTA = (_retangulo(0, 300, 800, 500), 0.4)


def test_A_escolhe_a_mascara_de_maior_score():
    seg = SegFalso({60: [GRANDE, PEQUENA]})

    r = variantes.rodar("A", LOTE, seg, _contexto(seg))

    assert r["lado_m"] == 60
    assert r["score"] == 0.9
    assert r["poligono"]["type"] == "Polygon"


def test_B_cresce_o_recorte_ate_a_mascara_nao_encostar_na_borda():
    seg = SegFalso({60: [PEQUENA, ENCOSTA], 120: [ENCOSTA], 240: [PEQUENA, GRANDE]})

    r = variantes.rodar("B", LOTE, seg, _contexto(seg))

    assert r["lado_m"] == 240
    assert r["area_m2"] == pytest.approx((200 * 240 / 800) ** 2, rel=0.05)


def test_B_para_no_primeiro_recorte_sem_mascara_na_borda():
    seg = SegFalso({60: [PEQUENA, GRANDE], 120: [ENCOSTA], 240: [ENCOSTA]})

    assert variantes.rodar("B", LOTE, seg, _contexto(seg))["lado_m"] == 60


def test_C_nao_roda_para_unidade():
    seg = SegFalso({60: [PEQUENA, GRANDE]})

    assert variantes.rodar("C", LOTE, seg, _contexto(seg, unidade=True, area_cadastro=36)) is None


def test_C_escolhe_a_mascara_de_area_mais_proxima_do_cadastro():
    seg = SegFalso({60: [PEQUENA, GRANDE], 120: [PEQUENA], 240: [PEQUENA]})
    area_pequena = (40 * 60 / 800) ** 2

    r = variantes.rodar("C", LOTE, seg, _contexto(seg, unidade=False, area_cadastro=area_pequena))

    assert r["area_m2"] == pytest.approx(area_pequena, rel=0.1)


def test_D_poe_pontos_negativos_no_eixo_do_logradouro():
    seg = SegFalso({60: [PEQUENA, GRANDE], 120: [PEQUENA], 240: [PEQUENA]})
    caixa = geo.caixa_recorte(LAT, LNG, 60)
    eixo = [[[caixa[0] - 0.001, LAT - 0.0001], [caixa[2] + 0.001, LAT - 0.0001]]]

    variantes.rodar("D", LOTE, seg, _contexto(seg, eixo=eixo))

    primeira = seg.chamadas[0]
    negativos = [p for p, r in zip(primeira["pontos"], primeira["rotulos"]) if r == 0]
    _, y_eixo = geo.para_pixel(caixa, LAT - 0.0001, LNG)
    assert len(negativos) >= 2
    assert all(abs(y - y_eixo) <= 2 for _, y in negativos)
    assert primeira["rotulos"][0] == 1


def test_nenhuma_variante_le_o_poligono_do_lote():
    seg = SegFalso({60: [PEQUENA, GRANDE], 120: [PEQUENA], 240: [PEQUENA]})
    lote_sem_poligono = {k: v for k, v in LOTE.items() if k != "poligono"}

    for nome in ("A", "B", "D", "E"):
        variantes.rodar(nome, lote_sem_poligono, seg, _contexto(seg, eixo=[], unidade=False, area_cadastro=100))


def test_sem_mascara_que_contenha_o_ponto_devolve_nada():
    seg = SegFalso({60: [(_retangulo(10, 10, 50, 50), 0.9)], 120: [], 240: []})

    assert variantes.rodar("A", LOTE, seg, _contexto(seg)) is None

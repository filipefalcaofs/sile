import json
from pathlib import Path

import pytest

from treino_ia import geo, metricas

FIXTURES = Path(__file__).parent / "fixtures"


def _ler(nome):
    return json.loads((FIXTURES / nome).read_text())


def test_reproduz_o_teste_cego_do_maxcenter():
    m = metricas.comparar(_ler("ia_maxcenter.json"), _ler("ref_maxcenter.json"))

    assert m["iou"] == pytest.approx(0.0129, abs=0.001)
    assert m["area_ref_m2"] == pytest.approx(7107.6, rel=0.005)
    assert m["area_ia_m2"] == pytest.approx(91.8, rel=0.01)
    assert m["pct_ia_dentro_ref"] == pytest.approx(100.0, abs=0.5)
    assert m["pct_ref_coberto"] == pytest.approx(1.3, abs=0.1)
    assert m["hausdorff_m"] == pytest.approx(78.2, abs=0.5)
    assert m["divisa_media_ia_ref_m"] == pytest.approx(20.8, abs=0.5)


def test_ia_nula_vira_iou_zero():
    m = metricas.comparar(None, _ler("ref_maxcenter.json"))

    assert m["iou"] == 0
    assert m["motivo_recusa"] == "sem_objeto"
    assert m["aceite_atual"] is False


def test_contorno_identico_tem_iou_um_e_erro_zero():
    ref = _ler("ref_maxcenter.json")

    m = metricas.comparar(ref, ref)

    assert m["iou"] == pytest.approx(1.0)
    assert m["hausdorff_m"] == pytest.approx(0.0, abs=1e-6)


def test_aceite_atual_aceita_o_contorno_do_maxcenter():
    caixa = geo.caixa_recorte(-12.996143, -38.465385, 60)

    assert metricas.aceite_atual(_ler("anel_px_maxcenter.json"), caixa, -12.996143, -38.465385) == (True, None)


def test_aceite_recusa_contorno_que_encosta_na_borda():
    caixa = geo.caixa_recorte(-12.996143, -38.465385, 60)
    anel = [[1, 1], [500, 1], [500, 500], [1, 500]]

    assert metricas.aceite_atual(anel, caixa, -12.996143, -38.465385) == (False, "encosta_na_borda")


def test_aceite_recusa_contorno_que_nao_contem_o_ponto():
    caixa = geo.caixa_recorte(-12.996143, -38.465385, 60)
    anel = [[10, 10], [200, 10], [200, 200], [10, 200]]

    assert metricas.aceite_atual(anel, caixa, -12.996143, -38.465385) == (False, "nao_contem_o_ponto")

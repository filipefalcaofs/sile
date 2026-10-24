import criterio_aceite


def _lote(iou, score, contem=True, area=500, compacidade=0.7, vazio=False):
    return {"iou": iou, "score": score, "contem_ponto": contem, "area_ia_m2": area, "compacidade": compacidade, "vazio": vazio}


def test_regras_do_viabiliza():
    assert criterio_aceite.aceito_pelas_regras(_lote(0.8, 0.9))
    assert not criterio_aceite.aceito_pelas_regras(_lote(0.8, 0.9, contem=False))
    assert not criterio_aceite.aceito_pelas_regras(_lote(0.8, 0.9, area=19))
    assert not criterio_aceite.aceito_pelas_regras(_lote(0.8, 0.9, area=20001))
    assert not criterio_aceite.aceito_pelas_regras(_lote(0.8, 0.9, compacidade=0.1))
    assert not criterio_aceite.aceito_pelas_regras(_lote(0, None, vazio=True))


def test_tabela_conta_aceitos_precisao_e_bons_perdidos():
    lotes = [_lote(0.9, 0.95), _lote(0.6, 0.7), _lote(0.2, 0.55), _lote(0.7, 0.5, contem=False)]

    por_limite = {t["score_min"]: t for t in criterio_aceite.tabela(lotes)}

    assert por_limite[None]["aceitos"] == 3
    assert por_limite[None]["ruins_aceitos"] == 1
    assert por_limite[None]["bons_perdidos"] == 1
    assert por_limite[0.6]["aceitos"] == 2 and por_limite[0.6]["precisao_iou_05"] == 1.0
    assert por_limite[0.9]["aceitos"] == 1 and por_limite[0.9]["bons_perdidos"] == 2


def test_spearman():
    assert criterio_aceite.spearman([1, 2, 3], [10, 20, 30]) == 1.0
    assert criterio_aceite.spearman([1, 2, 3], [30, 20, 10]) == -1.0

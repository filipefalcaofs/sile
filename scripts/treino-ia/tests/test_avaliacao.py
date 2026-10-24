import numpy as np
from PIL import Image

from treino_ia import arquivos, avaliacao, config, geo, variantes
from tests import conjunto_pequeno


def _preparar(tmp_path, monkeypatch):
    m = conjunto_pequeno.preparar(tmp_path, monkeypatch)
    manifesto = {**m, "teste": m["treino"], "treino": []}
    arquivos.gravar_json(config.caminhos().dados / "manifesto.json", manifesto)
    return manifesto


def _rodar_falso(registro):
    def rodar(nome, lote, seg, contexto):
        registro.append((nome, set(lote)))
        lat, lng = lote["ponto"]
        img, caixa = contexto["carregar_recorte"](lote["id"], 60)
        x, y = geo.para_pixel(caixa, lat, lng)
        anel = [[x - 50, y - 50], [x + 50, y - 50], [x + 50, y + 50], [x - 50, y + 50]]
        coords = [geo.para_coord(caixa, px, py) for px, py in anel]
        return {"variante": nome, "lado_m": 60, "area_m2": 56.0, "score": 0.9, "anel_px": anel,
                "poligono": {"type": "Polygon", "coordinates": [coords + coords[:1]]}}

    return rodar


def test_avaliacao_entrega_ao_modelo_lote_sem_poligono(tmp_path, monkeypatch):
    _preparar(tmp_path, monkeypatch)
    registro = []
    monkeypatch.setattr(variantes, "rodar", _rodar_falso(registro))

    caminho = avaliacao.avaliar("teste", ["A", "B"], execucao="x", seg_base=object(), eixos=lambda codlog: [])

    assert registro and all("poligono" not in chaves for _, chaves in registro)
    linhas = arquivos.ler_json(caminho)
    assert len(linhas) == 4
    assert {l["variante"] for l in linhas} == {"A", "B"}
    assert all(0 < l["iou"] <= 1 for l in linhas)
    assert all(l["aceite_atual"] in (True, False) for l in linhas)


def test_avaliacao_retoma_por_lote(tmp_path, monkeypatch):
    _preparar(tmp_path, monkeypatch)
    monkeypatch.setattr(variantes, "rodar", _rodar_falso([]))
    avaliacao.avaliar("teste", ["A"], execucao="x", seg_base=object(), eixos=lambda codlog: [])

    registro = []
    monkeypatch.setattr(variantes, "rodar", _rodar_falso(registro))
    avaliacao.avaliar("teste", ["A"], execucao="x", seg_base=object(), eixos=lambda codlog: [])

    assert registro == []


def test_variante_sem_resultado_vira_iou_zero(tmp_path, monkeypatch):
    _preparar(tmp_path, monkeypatch)
    monkeypatch.setattr(variantes, "rodar", lambda *a: None)

    linhas = arquivos.ler_json(avaliacao.avaliar("teste", ["A"], execucao="x", seg_base=object(), eixos=lambda codlog: []))

    assert all(l["iou"] == 0 and l["motivo_recusa"] == "sem_objeto" for l in linhas)


def test_teste_cego_usa_entrada_e_referencia_separadas_e_marca_ponto_fora(tmp_path, monkeypatch):
    m = _preparar(tmp_path, monkeypatch)
    c = config.caminhos()
    lote = m["teste"][0]
    entrada = [{"id": "p1", "ponto": lote["ponto"], "codlog": 1, "numero": 1, "inscricao": "1", "unidade": True, "area_cadastro": 36}]
    distante = conjunto_pequeno.sinteticos.retangulo(5000, 5000, 20, 20)
    arquivos.gravar_json(c.cego / "entrada.json", entrada)
    arquivos.gravar_json(c.cego / "referencia.json", {"p1": distante})
    registro = []
    monkeypatch.setattr(variantes, "rodar", _rodar_falso(registro))
    monkeypatch.setattr(avaliacao.recortes, "baixar_um", lambda *a, **k: "ja_existia")
    (c.recortes / "p1_60.png").write_bytes((c.recortes / f"{lote['id']}_60.png").read_bytes())
    (c.recortes / "p1_60.json").write_bytes((c.recortes / f"{lote['id']}_60.json").read_bytes())

    linhas = arquivos.ler_json(avaliacao.avaliar("cego", ["A"], execucao="c", seg_base=object(), eixos=lambda codlog: []))

    assert linhas[0]["cadastro_diferente"] is True
    assert linhas[0]["unidade"] is True


def test_modelo_recebe_so_id_ponto_codlog_e_numero(tmp_path, monkeypatch):
    _preparar(tmp_path, monkeypatch)
    registro = []
    monkeypatch.setattr(variantes, "rodar", _rodar_falso(registro))

    avaliacao.avaliar("teste", ["A"], execucao="x", seg_base=object(), eixos=lambda codlog: [])

    assert all(chaves <= {"id", "ponto", "codlog", "numero"} for _, chaves in registro)


def test_falha_de_recorte_nao_grava_o_lote_e_registra_o_erro(tmp_path, monkeypatch):
    _preparar(tmp_path, monkeypatch)

    def sem_recorte(nome, lote, seg, contexto):
        contexto["carregar_recorte"](lote["id"], 120)

    monkeypatch.setattr(variantes, "rodar", sem_recorte)
    monkeypatch.setattr(avaliacao.recortes, "baixar_um", lambda *a, **k: "falha")

    linhas = arquivos.ler_json(avaliacao.avaliar("teste", ["A"], execucao="x", seg_base=object(), eixos=lambda codlog: []))

    assert linhas == []
    assert not list((config.caminhos().avaliacoes / "x" / "lotes").glob("*.json"))
    assert "recorte" in (arquivos.ler_json(config.caminhos().estado / "situacao.json")["avaliar teste"]["ultimo_erro"] or "")


def test_retomada_completa_variantes_que_faltam(tmp_path, monkeypatch):
    _preparar(tmp_path, monkeypatch)
    monkeypatch.setattr(variantes, "rodar", _rodar_falso([]))
    avaliacao.avaliar("teste", ["A"], execucao="x", seg_base=object(), eixos=lambda codlog: [])

    registro = []
    monkeypatch.setattr(variantes, "rodar", _rodar_falso(registro))
    linhas = arquivos.ler_json(avaliacao.avaliar("teste", ["A", "B"], execucao="x", seg_base=object(), eixos=lambda codlog: []))

    assert {nome for nome, _ in registro} == {"B"}
    assert sorted(l["variante"] for l in linhas) == ["A", "A", "B", "B"]


def test_pedir_E_sem_pesos_treinados_para_com_mensagem(tmp_path, monkeypatch):
    _preparar(tmp_path, monkeypatch)
    import pytest

    with pytest.raises(SystemExit, match="pesos"):
        avaliacao.avaliar("teste", ["A", "E"], execucao="x", seg_base=object(), eixos=lambda codlog: [])


def _nao_chamar(codlog):
    raise AssertionError("eixo baixado sem a variante D")


def test_eixo_do_logradouro_so_e_baixado_quando_a_variante_D_roda(tmp_path, monkeypatch):
    m = _preparar(tmp_path, monkeypatch)
    m["teste"] = [{**l, "codlog": 123} for l in m["teste"]]
    arquivos.gravar_json(config.caminhos().dados / "manifesto.json", m)
    monkeypatch.setattr(variantes, "rodar", _rodar_falso([]))
    baixados = []

    avaliacao.avaliar("teste", ["A", "B"], execucao="x", seg_base=object(), eixos=_nao_chamar)
    avaliacao.avaliar("teste", ["D"], execucao="x", seg_base=object(), eixos=lambda codlog: baixados.append(codlog) or [])

    assert baixados == [123, 123]


def test_variante_J_recebe_a_juiza_e_o_modelo_treinado_e_grava_a_justificativa(tmp_path, monkeypatch):
    _preparar(tmp_path, monkeypatch)
    contextos = []
    rodar = _rodar_falso([])

    def rodar_com_juiza(nome, lote, seg, contexto):
        contextos.append(contexto)
        return {**rodar(nome, lote, seg, contexto), "juiza": "lote inteiro"}

    monkeypatch.setattr(variantes, "rodar", rodar_com_juiza)
    juiza, treinado = object(), object()

    linhas = arquivos.ler_json(avaliacao.avaliar("teste", ["J"], execucao="x", seg_base=object(), seg_treinado=treinado,
                                                 juiza=juiza, eixos=_nao_chamar))

    assert all(c["juiza"] is juiza and c["seg_treinado"] is treinado for c in contextos)
    assert {l["juiza"] for l in linhas} == {"lote inteiro"}


def test_cli_avaliar_com_juiza_inclui_a_variante_J(monkeypatch, tmp_path):
    from treino_ia import __main__

    monkeypatch.setenv("TREINO_IA_RAIZ", str(tmp_path))
    chamado = {}
    monkeypatch.setattr(avaliacao, "avaliar", lambda alvo, nomes, **k: chamado.update(nomes=nomes) or "ok")

    assert __main__.main(["avaliar", "teste", "--juiza"]) == 0
    assert "J" in chamado["nomes"]
    assert __main__.main(["cego", "--juiza"]) == 0
    assert "J" in chamado["nomes"]


def test_avaliacao_na_validacao_com_pesos_escolhidos(tmp_path, monkeypatch):
    m = _preparar(tmp_path, monkeypatch)
    m["validacao"], m["teste"] = m["teste"], []
    arquivos.gravar_json(config.caminhos().dados / "manifesto.json", m)
    carregados = []

    class SegRegistrado:
        def __init__(self, pesos=None):
            carregados.append(pesos)

    monkeypatch.setattr(variantes, "Segmentador", SegRegistrado)
    monkeypatch.setattr(variantes, "rodar", _rodar_falso([]))

    linhas = arquivos.ler_json(avaliacao.avaliar("validacao", ["E"], execucao="v", pesos="/x/epoca_03.pt", eixos=_nao_chamar))

    assert carregados == ["/x/epoca_03.pt"]
    assert len(linhas) == len(m["validacao"]) and {l["conjunto"] for l in linhas} == {"validacao"}


def test_cli_avaliar_so_as_variantes_pedidas_com_pesos(monkeypatch, tmp_path):
    from treino_ia import __main__

    monkeypatch.setenv("TREINO_IA_RAIZ", str(tmp_path))
    chamado = {}
    monkeypatch.setattr(avaliacao, "avaliar", lambda alvo, nomes, **k: chamado.update(alvo=alvo, nomes=nomes, **k) or "ok")

    assert __main__.main(["avaliar", "validacao", "--variantes", "E", "--pesos", "/x/p.pt"]) == 0
    assert chamado["alvo"] == "validacao" and chamado["nomes"] == ["E"] and chamado["pesos"] == "/x/p.pt"

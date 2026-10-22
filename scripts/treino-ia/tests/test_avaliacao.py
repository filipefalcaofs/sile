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

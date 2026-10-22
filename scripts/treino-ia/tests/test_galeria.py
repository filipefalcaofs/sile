from treino_ia import arquivos, config, galeria
from tests import conjunto_pequeno


def _resultados(tmp_path, monkeypatch):
    m = conjunto_pequeno.preparar(tmp_path, monkeypatch)
    c = config.caminhos()
    linhas = []
    for lote, iou in zip(m["treino"], (0.2, 0.8)):
        for variante, ajuste in (("A", 0.0), ("E", 0.1)):
            linhas.append({
                "lote_id": lote["id"], "conjunto": "teste", "variante": variante, "bairro": "B", "faixa_area": "<200",
                "cadastro_diferente": lote["id"] == "9-0", "lado_m": 60, "iou": iou + ajuste, "dif_area_pct": -10.0,
                "divisa_media_ia_ref_m": 2.0, "hausdorff_m": 5.0, "aceite_atual": True, "motivo_recusa": None,
                "poligono_ia": lote["poligono"], "poligono_ref": lote["poligono"], "ponto": lote["ponto"],
            })
    arquivos.gravar_json(c.avaliacoes / "x" / "resultados.json", linhas)
    return linhas


def test_resumo_traz_mediana_com_e_sem_cadastro_diferente(tmp_path, monkeypatch):
    _resultados(tmp_path, monkeypatch)

    galeria.gerar("x")

    resumo = arquivos.ler_json(config.caminhos().galerias / "x" / "resumo.json")
    assert resumo["variantes"]["A"]["iou"]["mediana"] == 0.5
    assert resumo["variantes"]["A"]["iou_sem_cadastro_diferente"]["mediana"] == 0.8
    assert resumo["lotes"] == 2


def test_galeria_tem_um_card_por_lote_e_uma_imagem_por_lote(tmp_path, monkeypatch):
    _resultados(tmp_path, monkeypatch)

    destino = galeria.gerar("x")

    html = destino.read_text(encoding="utf-8")
    assert html.count('data-lote="') == 2
    assert (destino.parent / "img" / "9-0.png").exists()
    assert (destino.parent / "img" / "9-1.png").exists()
    assert "http://" not in html and "https://" not in html

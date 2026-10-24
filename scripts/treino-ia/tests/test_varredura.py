import torch

from treino_ia import __main__, arquivos, avaliacao, config, varredura


def _pesos(tmp_path, monkeypatch, epocas):
    monkeypatch.setenv("TREINO_IA_RAIZ", str(tmp_path))
    pasta = config.caminhos().pesos / "epocas" / "s5"
    pasta.mkdir(parents=True)
    for e in epocas:
        torch.save({"decodificador": {}}, pasta / f"epoca_{e:02d}.pt")
    return pasta


def _avaliar_falso(ious_por_epoca, chamadas):
    def avaliar(conjunto, nomes, execucao=None, pesos=None, **k):
        chamadas.append((conjunto, nomes, execucao, pesos))
        epoca = int(str(pesos).rsplit("_", 1)[1][:2])
        destino = config.caminhos().avaliacoes / execucao / "resultados.json"
        arquivos.gravar_json(destino, [{"variante": "E", "iou": i, "poligono_ia": {}} for i in ious_por_epoca[epoca]])
        return destino

    return avaliar


def test_varredura_avalia_as_epocas_no_passo_e_escolhe_a_melhor_mediana(tmp_path, monkeypatch):
    _pesos(tmp_path, monkeypatch, range(1, 7))
    chamadas = []
    ious = {2: [0.2, 0.3, 0.4], 4: [0.5, 0.6, 0.7], 6: [0.4, 0.5, 0.9]}
    monkeypatch.setattr(avaliacao, "avaliar", _avaliar_falso(ious, chamadas))

    resumo = varredura.varrer(5, passo=2)

    assert [c[0] for c in chamadas] == ["validacao"] * 3
    assert all(c[1] == ["E"] for c in chamadas)
    assert [r["epoca"] for r in resumo["epocas"]] == [2, 4, 6]
    assert resumo["melhor"]["epoca"] == 4
    assert resumo["melhor"]["iou_mediano"] == 0.6
    assert arquivos.ler_json(config.caminhos().avaliacoes / "varredura-s5.json")["melhor"]["epoca"] == 4


def test_varredura_inclui_a_ultima_epoca_fora_do_passo(tmp_path, monkeypatch):
    _pesos(tmp_path, monkeypatch, range(1, 6))
    chamadas = []
    ious = {e: [0.1 * e] for e in range(1, 6)}
    monkeypatch.setattr(avaliacao, "avaliar", _avaliar_falso(ious, chamadas))

    resumo = varredura.varrer(5, passo=2)

    assert [r["epoca"] for r in resumo["epocas"]] == [2, 4, 5]


def test_cli_varrer(tmp_path, monkeypatch):
    monkeypatch.setenv("TREINO_IA_RAIZ", str(tmp_path))
    chamado = {}
    monkeypatch.setattr(varredura, "varrer", lambda semente, passo: chamado.update(semente=semente, passo=passo) or {"epocas": [], "melhor": None})

    assert __main__.main(["varrer", "--semente", "9", "--passo", "3"]) == 0
    assert chamado == {"semente": 9, "passo": 3}

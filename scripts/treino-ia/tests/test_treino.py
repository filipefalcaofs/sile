import pytest
import torch

from treino_ia import cache, config, treino
from tests import conjunto_pequeno

pytestmark = pytest.mark.sam2


@pytest.fixture(scope="module")
def segmentador():
    pytest.importorskip("sam2")
    from treino_ia import variantes

    return variantes.Segmentador()


@pytest.fixture
def conjunto(tmp_path, monkeypatch, segmentador):
    m = conjunto_pequeno.preparar(tmp_path, monkeypatch)
    cache.gerar(m, segmentador)
    return m


def test_treino_retoma_do_estado_salvo(conjunto, segmentador):
    treino.treinar(conjunto, epocas=2, semente=1, modelo=segmentador.preditor, parar_apos_passos=1)
    assert torch.load(config.caminhos().pesos / "estado.pt")["passo"] == 1

    final = treino.treinar(conjunto, epocas=2, semente=1, modelo=segmentador.preditor)

    assert torch.load(config.caminhos().pesos / "estado.pt")["passo"] == 4
    assert final.name.startswith("decodificador_") and final.name.endswith("_s1.pt")
    assert "decodificador" in torch.load(final)


def test_treino_so_altera_o_decodificador(conjunto, segmentador):
    modelo = segmentador.preditor.model
    codificador_antes = {k: v.clone() for k, v in modelo.image_encoder.state_dict().items()}
    decodificador_antes = {k: v.clone() for k, v in modelo.sam_mask_decoder.state_dict().items()}

    final = treino.treinar(conjunto, epocas=1, semente=1, modelo=segmentador.preditor)

    for k, v in modelo.image_encoder.state_dict().items():
        assert torch.equal(v, codificador_antes[k])
    novo = torch.load(final)["decodificador"]
    assert any(not torch.equal(novo[k], decodificador_antes[k]) for k in novo)


def test_perda_registrada_por_epoca(conjunto, segmentador):
    treino.treinar(conjunto, epocas=2, semente=1, modelo=segmentador.preditor)

    log = (config.caminhos().logs / "treino.log").read_text()
    assert "época 1/2" in log and "época 2/2" in log

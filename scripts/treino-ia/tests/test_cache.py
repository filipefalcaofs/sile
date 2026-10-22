import torch

from treino_ia import cache, config
from tests import conjunto_pequeno


class SegContador:
    def __init__(self):
        self.chamadas = 0

    def codificar(self, img):
        self.chamadas += 1
        return {"image_embed": torch.zeros(1, 256, 4, 4), "high_res_feats": [torch.zeros(1, 32, 8, 8), torch.zeros(1, 64, 6, 6)]}


def test_cache_nao_recalcula_o_que_existe(tmp_path, monkeypatch):
    m = conjunto_pequeno.preparar(tmp_path, monkeypatch)
    cache.gerar(m, SegContador())

    seg = SegContador()
    resultado = cache.gerar(m, seg)

    assert seg.chamadas == 0
    assert resultado == {"gerados": 0, "ja_existiam": 2, "sem_recorte": 0}


def test_cache_grava_em_float16(tmp_path, monkeypatch):
    m = conjunto_pequeno.preparar(tmp_path, monkeypatch)

    cache.gerar(m, SegContador())

    salvo = torch.load(config.caminhos().cache / "9-0_60.pt")
    assert salvo["image_embed"].dtype == torch.float16
    assert len(salvo["high_res_feats"]) == 2


def test_cache_conta_lote_sem_recorte(tmp_path, monkeypatch):
    m = conjunto_pequeno.preparar(tmp_path, monkeypatch)
    (config.caminhos().recortes / "9-1_60.png").unlink()

    assert cache.gerar(m, SegContador())["sem_recorte"] == 1

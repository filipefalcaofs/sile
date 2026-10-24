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


def test_cache_gera_tambem_os_recortes_menores_do_treino(tmp_path, monkeypatch):
    import shutil

    m = conjunto_pequeno.preparar(tmp_path, monkeypatch, n=1)
    r = config.caminhos().recortes
    shutil.copy(r / "9-0_60.png", r / "9-0_120.png")
    m["treino"][0]["lado_m"] = 120

    cache.gerar(m, SegContador())

    assert sorted(p.name for p in config.caminhos().cache.glob("*.pt")) == ["9-0_120.pt", "9-0_60.pt"]


def test_cache_mais_velho_que_o_recorte_e_refeito(tmp_path, monkeypatch):
    import os

    m = conjunto_pequeno.preparar(tmp_path, monkeypatch, n=1)
    cache.gerar(m, SegContador())
    png = config.caminhos().recortes / "9-0_60.png"
    pt = config.caminhos().cache / "9-0_60.pt"
    os.utime(pt, (png.stat().st_mtime - 100, png.stat().st_mtime - 100))

    seg = SegContador()
    resultado = cache.gerar(m, seg)

    assert seg.chamadas == 1 and resultado["gerados"] == 1

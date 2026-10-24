import torch

from treino_ia import __main__, config, media_pesos


def _salvar(pasta, epoca, valor):
    pasta.mkdir(parents=True, exist_ok=True)
    torch.save({"decodificador": {"w": torch.full((2,), float(valor)), "n": torch.tensor(epoca)}, "epoca": epoca},
               pasta / f"epoca_{epoca:02d}.pt")


def test_media_das_epocas_pedidas(tmp_path, monkeypatch):
    monkeypatch.setenv("TREINO_IA_RAIZ", str(tmp_path))
    pasta = config.caminhos().pesos / "epocas" / "s7"
    for e, v in ((20, 1.0), (21, 2.0), (22, 6.0)):
        _salvar(pasta, e, v)

    destino = media_pesos.media(7, 21, 22)

    salvo = torch.load(destino, weights_only=True)
    assert torch.equal(salvo["decodificador"]["w"], torch.full((2,), 4.0))
    assert salvo["epocas_na_media"] == [21, 22]
    assert destino.name == "media_e21-e22.pt"


def test_tensor_inteiro_fica_o_da_ultima_epoca(tmp_path, monkeypatch):
    monkeypatch.setenv("TREINO_IA_RAIZ", str(tmp_path))
    pasta = config.caminhos().pesos / "epocas" / "s7"
    _salvar(pasta, 1, 1.0)
    _salvar(pasta, 2, 3.0)

    salvo = torch.load(media_pesos.media(7, 1, 2), weights_only=True)

    assert salvo["decodificador"]["n"].item() == 2


def test_cli_media(tmp_path, monkeypatch):
    monkeypatch.setenv("TREINO_IA_RAIZ", str(tmp_path))
    chamado = {}
    monkeypatch.setattr(media_pesos, "media", lambda s, i, f: chamado.update(s=s, i=i, f=f) or tmp_path / "x.pt")

    assert __main__.main(["media", "--semente", "7", "--de", "21", "--ate", "30"]) == 0
    assert chamado == {"s": 7, "i": 21, "f": 30}

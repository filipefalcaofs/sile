import os

import pytest

from treino_ia import arquivos, config


def test_gravar_atomico_nao_deixa_arquivo_parcial(tmp_path, monkeypatch):
    destino = tmp_path / "a.json"

    def falha(*_):
        raise OSError("pod trocado")

    monkeypatch.setattr(os, "replace", falha)

    with pytest.raises(OSError):
        arquivos.gravar_atomico(destino, b"{}")

    assert not destino.exists()
    assert list(tmp_path.iterdir()) == []


def test_gravar_e_ler_json(tmp_path):
    destino = tmp_path / "sub" / "x.json"

    arquivos.gravar_json(destino, {"a": 1, "nome": "lote"})

    assert arquivos.ler_json(destino) == {"a": 1, "nome": "lote"}


def test_ler_json_inexistente_devolve_padrao(tmp_path):
    assert arquivos.ler_json(tmp_path / "nao.json", padrao={}) == {}


def test_caminhos_usam_a_raiz_do_ambiente(tmp_path, monkeypatch):
    monkeypatch.setenv("TREINO_IA_RAIZ", str(tmp_path))

    c = config.caminhos()

    assert c.recortes == tmp_path / "recortes"
    assert c.galerias == tmp_path / "galerias"
    assert c.cego == tmp_path / "cego"

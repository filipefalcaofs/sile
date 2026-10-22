import pytest

from treino_ia import __main__, recursos


def test_conferir_memoria_levanta_acima_do_teto(monkeypatch):
    monkeypatch.setattr(recursos, "memoria_residente_mb", lambda: 5000)

    with pytest.raises(recursos.MemoriaExcedida, match="5000"):
        recursos.conferir_memoria(teto_mb=4096)


def test_conferir_memoria_aceita_abaixo_do_teto(monkeypatch):
    monkeypatch.setattr(recursos, "memoria_residente_mb", lambda: 1200)

    recursos.conferir_memoria(teto_mb=4096)


def test_cli_sai_com_codigo_3_quando_memoria_excede(monkeypatch, tmp_path):
    monkeypatch.setenv("TREINO_IA_RAIZ", str(tmp_path))
    monkeypatch.setattr(recursos, "memoria_residente_mb", lambda: 5000)

    assert __main__.main(["situacao"]) == 3


def test_cli_recusa_etapa_desconhecida(monkeypatch, tmp_path):
    monkeypatch.setenv("TREINO_IA_RAIZ", str(tmp_path))

    with pytest.raises(SystemExit):
        __main__.main(["inexistente"])

import io
import os

import pytest

os.environ["FOOTPRINT_IA_STUB"] = "1"

from fastapi.testclient import TestClient  # noqa: E402
from PIL import Image  # noqa: E402

from app import app  # noqa: E402

cliente = TestClient(app)


def png(largura=800, altura=800):
    buffer = io.BytesIO()
    Image.new("RGB", (largura, altura), (120, 120, 120)).save(buffer, format="PNG")
    return buffer.getvalue()


def test_saude():
    resposta = cliente.get("/saude")

    assert resposta.status_code == 200
    assert resposta.json()["status"] == "ok"
    assert resposta.json()["modelo"].startswith("stub")


def test_segmentar_devolve_anel_em_pixel():
    resposta = cliente.post(
        "/segmentar",
        files={"imagem": ("recorte.png", png(), "image/png")},
        data={"x": "400", "y": "400", "largura": "800", "altura": "800"},
    )

    assert resposta.status_code == 200
    corpo = resposta.json()
    assert corpo["status"] == "ok"
    assert len(corpo["anel"]) >= 4
    xs = [p[0] for p in corpo["anel"]]
    ys = [p[1] for p in corpo["anel"]]
    assert min(xs) < 400 < max(xs)
    assert min(ys) < 400 < max(ys)
    assert corpo["modelo"].startswith("stub")


def test_requisicao_sem_imagem_da_422():
    resposta = cliente.post("/segmentar", data={"x": "1", "y": "1", "largura": "800", "altura": "800"})

    assert resposta.status_code == 422


def test_ponto_fora_da_imagem_da_422():
    resposta = cliente.post(
        "/segmentar",
        files={"imagem": ("recorte.png", png(), "image/png")},
        data={"x": "900", "y": "10", "largura": "800", "altura": "800"},
    )

    assert resposta.status_code == 422


def test_segmentar_devolve_os_candidatos_com_score_e_borda():
    resposta = cliente.post(
        "/segmentar",
        files={"imagem": ("recorte.png", png(), "image/png")},
        data={"x": "400", "y": "400", "largura": "800", "altura": "800"},
    )

    candidatos = resposta.json()["candidatos"]
    assert candidatos and all({"anel", "score", "encosta"} <= set(c) for c in candidatos)
    assert candidatos[0]["encosta"] is False
    assert resposta.json()["anel"] == max(candidatos, key=lambda c: c["score"])["anel"]


def test_candidato_que_encosta_na_borda_vem_marcado():
    resposta = cliente.post(
        "/segmentar",
        files={"imagem": ("recorte.png", png(), "image/png")},
        data={"x": "20", "y": "400", "largura": "800", "altura": "800"},
    )

    assert resposta.json()["candidatos"][0]["encosta"] is True


def test_pesos_do_decodificador_sao_carregados_sobre_o_modelo(tmp_path):
    torch = pytest.importorskip("torch")
    import app as servico

    decodificador = torch.nn.Linear(2, 2)
    original = {k: v.clone() for k, v in decodificador.state_dict().items()}
    novos = {k: torch.ones_like(v) for k, v in original.items()}
    torch.save({"decodificador": novos}, tmp_path / "pesos.pt")
    modelo = type("Modelo", (), {"sam_mask_decoder": decodificador})()

    servico.aplicar_pesos(modelo, str(tmp_path / "pesos.pt"))

    assert all(torch.equal(v, torch.ones_like(v)) for v in decodificador.state_dict().values())


def test_nome_do_modelo_identifica_os_pesos(monkeypatch):
    import app as servico

    monkeypatch.setattr(servico, "STUB", False)
    monkeypatch.setattr(servico, "PESOS", "/opt/pesos/decodificador_20261024_s1.pt")

    assert "decodificador_20261024_s1" in servico.nome_do_modelo()

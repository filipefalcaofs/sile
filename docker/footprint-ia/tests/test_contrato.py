import io
import os

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

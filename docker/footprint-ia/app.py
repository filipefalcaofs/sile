"""Serviço de inferência de edificações do Viabiliza.

Recebe um recorte da ortofoto e o ponto do imóvel em pixels e devolve o
contorno que contém o ponto, também em pixels, e os candidatos das três máscaras
do SAM 2, cada um com o score e se encosta na borda. Com FOOTPRINT_IA_PESOS, o
decodificador treinado com os lotes do CODLOG substitui o original e o contorno
passa a ser o do lote. O georreferenciamento, a escolha do tamanho do recorte e o
aceite ficam no Laravel.
"""

import io
import os
from functools import lru_cache

import cv2
import numpy as np
from fastapi import FastAPI, File, Form, HTTPException, UploadFile
from PIL import Image

MODELO = os.environ.get("FOOTPRINT_IA_MODELO", "facebook/sam2.1-hiera-small")
DISPOSITIVO = os.environ.get("FOOTPRINT_IA_DEVICE", "cpu")
STUB = os.environ.get("FOOTPRINT_IA_STUB") == "1"
EPSILON_PX = float(os.environ.get("FOOTPRINT_IA_EPSILON_PX", "1.5"))
PESOS = os.environ.get("FOOTPRINT_IA_PESOS", "")
MARGEM_BORDA_PX = 2

app = FastAPI(title="footprint-ia")


def nome_do_modelo() -> str:
    if STUB:
        return "stub@1"
    pesos = f"+{os.path.splitext(os.path.basename(PESOS))[0]}" if PESOS else ""
    return f"{MODELO.split('/')[-1]}{pesos}@{DISPOSITIVO}"


def aplicar_pesos(modelo, caminho: str) -> None:
    """Carrega os pesos do decodificador treinado sobre o modelo base."""
    import torch

    estado = torch.load(caminho, map_location="cpu", weights_only=True)
    modelo.sam_mask_decoder.load_state_dict(estado.get("decodificador", estado))


@lru_cache(maxsize=1)
def preditor():
    """Carrega o SAM 2 uma vez por processo."""
    from sam2.sam2_image_predictor import SAM2ImagePredictor

    carregado = SAM2ImagePredictor.from_pretrained(MODELO, device=DISPOSITIVO)
    if PESOS:
        aplicar_pesos(carregado.model, PESOS)
    carregado.model.eval()
    return carregado


def mascaras_stub(imagem: np.ndarray, x: int, y: int) -> list[tuple[np.ndarray, float]]:
    """Quadrado de 100 px em volta do ponto, para testes sem o modelo."""
    mascara = np.zeros(imagem.shape[:2], dtype=np.uint8)
    mascara[max(0, y - 50) : y + 50, max(0, x - 50) : x + 50] = 1
    return [(mascara, 1.0)]


def mascaras_sam(imagem: np.ndarray, x: int, y: int) -> list[tuple[np.ndarray, float]]:
    """As três máscaras do SAM 2 com um ponto positivo, com o IoU previsto."""
    import torch

    modelo = preditor()
    with torch.no_grad():
        modelo.set_image(imagem)
        mascaras, scores, _ = modelo.predict(
            point_coords=np.array([[x, y]], dtype=np.float32),
            point_labels=np.array([1], dtype=np.int32),
            multimask_output=True,
        )
    return [((m > 0).astype(np.uint8), float(s)) for m, s in zip(mascaras, scores)]


def encosta_na_borda(anel: list[list[float]], largura: int, altura: int) -> bool:
    return any(
        px <= MARGEM_BORDA_PX or py <= MARGEM_BORDA_PX or px >= largura - 1 - MARGEM_BORDA_PX or py >= altura - 1 - MARGEM_BORDA_PX
        for px, py in anel
    )


def contorno_do_ponto(mascara: np.ndarray, x: int, y: int) -> list[list[float]]:
    """Maior contorno que contém o ponto, simplificado."""
    contornos, _ = cv2.findContours(mascara, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    candidatos = [c for c in contornos if cv2.pointPolygonTest(c, (float(x), float(y)), False) >= 0]

    if not candidatos:
        return []

    maior = max(candidatos, key=cv2.contourArea)
    simplificado = cv2.approxPolyDP(maior, EPSILON_PX, True)
    return [[float(p[0][0]), float(p[0][1])] for p in simplificado]


@app.get("/saude")
def saude() -> dict:
    return {"status": "ok", "modelo": nome_do_modelo()}


@app.post("/segmentar")
async def segmentar(
    imagem: UploadFile = File(...),
    x: int = Form(...),
    y: int = Form(...),
    largura: int = Form(...),
    altura: int = Form(...),
) -> dict:
    if not (0 <= x < largura and 0 <= y < altura):
        raise HTTPException(status_code=422, detail="Ponto fora da imagem.")

    try:
        rgb = np.array(Image.open(io.BytesIO(await imagem.read())).convert("RGB"))
    except Exception as erro:  # imagem corrompida ou formato desconhecido
        raise HTTPException(status_code=422, detail=f"Imagem inválida: {erro}") from erro

    altura_img, largura_img = rgb.shape[:2]
    candidatos = []
    for mascara, score in (mascaras_stub if STUB else mascaras_sam)(rgb, x, y):
        anel = contorno_do_ponto(mascara, x, y)
        if len(anel) >= 3:
            candidatos.append({"anel": anel, "score": round(score, 4), "encosta": encosta_na_borda(anel, largura_img, altura_img)})

    if not candidatos:
        return {"status": "sem_objeto", "anel": [], "score": None, "candidatos": [], "modelo": nome_do_modelo()}

    melhor = max(candidatos, key=lambda c: c["score"])
    return {"status": "ok", "anel": melhor["anel"], "score": melhor["score"], "candidatos": candidatos, "modelo": nome_do_modelo()}

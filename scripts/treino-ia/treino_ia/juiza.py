"""LLM como juíza dos contornos candidatos do SAM 2.

A juíza recebe só o recorte da ortofoto com os candidatos numerados e o ponto
do imóvel, e devolve o número do candidato que representa o lote inteiro, ou 0
quando nenhum serve. O polígono do CODLOG nunca entra no pedido.
"""

import base64
import io
import json
import os

import numpy as np
from PIL import Image, ImageDraw, ImageFont

MODELO = os.environ.get("TREINO_IA_JUIZA_MODELO", "claude-opus-5-5")
ESFORCO = os.environ.get("TREINO_IA_JUIZA_ESFORCO", "high")
CORES = ["#E6194B", "#3CB44B", "#4363D8", "#F58231", "#911EB4", "#42D4F4", "#F032E6", "#9A6324"]
ESQUEMA = {
    "type": "object",
    "properties": {"escolha": {"type": "integer"}, "justificativa": {"type": "string"}},
    "required": ["escolha", "justificativa"],
    "additionalProperties": False,
}
INSTRUCAO = (
    "Esta é uma ortofoto de Salvador: um recorte quadrado de {lado} m, com 800 px de lado. "
    "O ponto amarelo marca o endereço de um imóvel. Os contornos numerados de 1 a {n} são "
    "candidatos ao contorno do lote desse imóvel.\n\n"
    "O lote é o terreno inteiro: edificação, estacionamento, recuos, jardins e áreas livres "
    "dentro dos muros. Não inclui a rua, a calçada nem os terrenos vizinhos.\n\n"
    "Escolha o número do candidato que mais se aproxima do lote inteiro. Responda 0 quando "
    "nenhum candidato representa o lote, por exemplo quando todos pegam só um pedaço do "
    "telhado ou a quadra inteira. Na justificativa, diga em uma frase o que você viu."
)


def desenhar(img: np.ndarray, aneis: list, x: int, y: int) -> bytes:
    """Recorte com os candidatos numerados e o ponto, em PNG."""
    base = Image.fromarray(img).convert("RGB")
    d = ImageDraw.Draw(base)
    fonte = ImageFont.load_default(size=26)
    for k, anel in enumerate(aneis, 1):
        cor = CORES[(k - 1) % len(CORES)]
        pontos = [tuple(p) for p in anel]
        d.line(pontos + pontos[:1], fill=cor, width=4)
        px, py = max(pontos, key=lambda p: (p[1], -p[0]))
        d.text((min(px, base.width - 30), min(py + 2, base.height - 30)), str(k), fill=cor, font=fonte,
               stroke_width=3, stroke_fill=(0, 0, 0))
    d.ellipse([x - 9, y - 9, x + 9, y + 9], fill="#FFD400", outline="#000000", width=3)
    saida = io.BytesIO()
    base.save(saida, format="PNG")
    return saida.getvalue()


class Juiza:
    def __init__(self, cliente=None):
        if cliente is None:
            import anthropic

            cliente = anthropic.Anthropic()
        self.cliente = cliente

    def escolher(self, img: np.ndarray, aneis: list, x: int, y: int, lado_m: int = 120) -> dict:
        if not aneis:
            return {"escolha": None, "justificativa": None, "recusa": False}
        imagem = base64.standard_b64encode(desenhar(img, aneis, x, y)).decode("utf-8")
        resposta = self.cliente.beta.messages.create(
            model=MODELO,
            max_tokens=16000,
            betas=["server-side-fallback-2026-07-01"],
            fallbacks="default",
            output_config={"effort": ESFORCO, "format": {"type": "json_schema", "schema": ESQUEMA}},
            messages=[{
                "role": "user",
                "content": [
                    {"type": "image", "source": {"type": "base64", "media_type": "image/png", "data": imagem}},
                    {"type": "text", "text": INSTRUCAO.format(lado=lado_m, n=len(aneis))},
                ],
            }],
        )
        if resposta.stop_reason == "refusal":
            return {"escolha": None, "justificativa": None, "recusa": True}
        texto = next((b.text for b in resposta.content if b.type == "text"), None)
        if texto is None:
            return {"escolha": None, "justificativa": None, "recusa": False}
        dados = json.loads(texto)
        escolha = dados["escolha"] if 1 <= dados["escolha"] <= len(aneis) else None
        return {"escolha": escolha, "justificativa": dados["justificativa"], "recusa": False}

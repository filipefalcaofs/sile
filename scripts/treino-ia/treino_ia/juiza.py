"""LLM como juíza dos contornos candidatos do SAM 2.

A juíza recebe só o recorte da ortofoto com os candidatos numerados e o ponto
do imóvel, e devolve o número do candidato que representa o lote inteiro, ou 0
quando nenhum serve. O polígono do CODLOG nunca entra no pedido.

O provedor padrão é o mesmo da IA cadastrada no Viabiliza (OpenAI, gpt-4o-mini).
TREINO_IA_JUIZA_PROVEDOR (openai ou anthropic) e TREINO_IA_JUIZA_MODELO trocam a
juíza; a credencial vem de OPENAI_API_KEY ou ANTHROPIC_API_KEY.
"""

import base64
import io
import json
import os

import numpy as np
from PIL import Image, ImageDraw, ImageFont

MODELOS_PADRAO = {"openai": "gpt-4o-mini", "anthropic": "claude-opus-5-5"}
ESFORCO_ANTHROPIC = os.environ.get("TREINO_IA_JUIZA_ESFORCO", "high")
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
NENHUM = {"escolha": None, "justificativa": None, "recusa": False}
RECUSA = {"escolha": None, "justificativa": None, "recusa": True}


def provedor() -> str:
    return os.environ.get("TREINO_IA_JUIZA_PROVEDOR", "openai")


provedor_padrao = provedor


def modelo(nome_provedor: str) -> str:
    return os.environ.get("TREINO_IA_JUIZA_MODELO", MODELOS_PADRAO[nome_provedor])


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
    def __init__(self, cliente=None, provedor: str | None = None):
        self.provedor = provedor or provedor_padrao()
        self.modelo = modelo(self.provedor)
        if cliente is None:
            if self.provedor == "openai":
                import openai

                cliente = openai.OpenAI()
            else:
                import anthropic

                cliente = anthropic.Anthropic()
        self.cliente = cliente

    def escolher(self, img: np.ndarray, aneis: list, x: int, y: int, lado_m: int = 120) -> dict:
        if not aneis:
            return dict(NENHUM)
        imagem = base64.standard_b64encode(desenhar(img, aneis, x, y)).decode("utf-8")
        texto = INSTRUCAO.format(lado=lado_m, n=len(aneis))
        chamar = self._openai if self.provedor == "openai" else self._anthropic
        resposta = chamar(imagem, texto)
        if resposta in (NENHUM, RECUSA):
            return dict(resposta)
        dados = json.loads(resposta)
        escolha = dados["escolha"] if 1 <= dados["escolha"] <= len(aneis) else None
        return {"escolha": escolha, "justificativa": dados["justificativa"], "recusa": False}

    def _openai(self, imagem: str, texto: str):
        resposta = self.cliente.chat.completions.create(
            model=self.modelo,
            max_completion_tokens=16000,
            response_format={"type": "json_schema", "json_schema": {"name": "escolha_do_lote", "schema": ESQUEMA, "strict": True}},
            messages=[{
                "role": "user",
                "content": [
                    {"type": "image_url", "image_url": {"url": f"data:image/png;base64,{imagem}"}},
                    {"type": "text", "text": texto},
                ],
            }],
        )
        mensagem = resposta.choices[0].message
        if mensagem.refusal:
            return RECUSA
        return mensagem.content or NENHUM

    def _anthropic(self, imagem: str, texto: str):
        resposta = self.cliente.beta.messages.create(
            model=self.modelo,
            max_tokens=16000,
            betas=["server-side-fallback-2026-07-01"],
            fallbacks="default",
            output_config={"effort": ESFORCO_ANTHROPIC, "format": {"type": "json_schema", "schema": ESQUEMA}},
            messages=[{
                "role": "user",
                "content": [
                    {"type": "image", "source": {"type": "base64", "media_type": "image/png", "data": imagem}},
                    {"type": "text", "text": texto},
                ],
            }],
        )
        if resposta.stop_reason == "refusal":
            return RECUSA
        return next((b.text for b in resposta.content if b.type == "text"), None) or NENHUM

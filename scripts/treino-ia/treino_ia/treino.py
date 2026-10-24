"""Treino do decodificador de máscaras do SAM 2 com os lotes do CODLOG.

O codificador fica congelado e suas saídas vêm do cache. A cada passo, um lote
num dos seus recortes (o que o contém ou um menor, em que ele encosta na borda,
para o modelo aprender a pedir o recorte maior) e um ponto sorteado dentro dele; a perda é Dice + BCE da melhor das 3 máscaras
contra a máscara do lote, mais o erro do IoU previsto. O estado é salvo a cada
5 minutos e ao fim de cada época, e uma nova chamada retoma de onde parou.
"""

import hashlib
import io
import random
import time
from datetime import date, datetime

import numpy as np
import torch
import torch.nn.functional as F

from treino_ia import arquivos, cache, config, geo, recortes, recursos, situacao

INTERVALO_ESTADO_S = 300
TAXA = 1e-4
PESO_IOU = 0.1


def _salvar(obj, caminho) -> None:
    buffer = io.BytesIO()
    torch.save(obj, buffer)
    arquivos.gravar_atomico(caminho, buffer.getvalue())


def _amostras(manifesto: dict) -> list[dict]:
    c = config.caminhos()
    saida = []
    for lote in manifesto.get("treino", []):
        for lado in geo.lados_de_treino(lote):
            meta = arquivos.ler_json(c.recortes / f"{lote['id']}_{lado}.json")
            if meta is None or not cache.caminho(lote["id"], lado).exists():
                continue
            saida.append({"lote": lote, "lado": lado, "caixa": meta["caixa"]})
    return saida


def _perda(logits, alvo):
    bce = F.binary_cross_entropy_with_logits(logits, alvo.expand_as(logits), reduction="none").mean(dim=(-1, -2))
    prob = torch.sigmoid(logits)
    inter = (prob * alvo).sum(dim=(-1, -2))
    dice = 1 - (2 * inter + 1) / (prob.sum(dim=(-1, -2)) + alvo.sum(dim=(-1, -2)) + 1)
    return bce + dice


def _passo(preditor, amostra, rng: np.random.Generator):
    sam = preditor.model
    dev = preditor.device
    feats = torch.load(cache.caminho(amostra["lote"]["id"], amostra["lado"]), map_location=dev)
    embed = feats["image_embed"].float()
    altas = [f.float() for f in feats["high_res_feats"]]
    mascara = recortes.mascara_lote(amostra["lote"]["poligono"], amostra["caixa"])
    ys, xs = np.nonzero(mascara)
    k = rng.integers(len(xs))
    coords = preditor._transforms.transform_coords(torch.tensor([[[float(xs[k]), float(ys[k])]]], device=dev), normalize=True, orig_hw=mascara.shape)
    rotulos = torch.tensor([[1]], dtype=torch.int, device=dev)
    with torch.no_grad():
        esparso, denso = sam.sam_prompt_encoder(points=(coords, rotulos), boxes=None, masks=None)
    logits, iou_previsto, _, _ = sam.sam_mask_decoder(
        image_embeddings=embed, image_pe=sam.sam_prompt_encoder.get_dense_pe(),
        sparse_prompt_embeddings=esparso, dense_prompt_embeddings=denso,
        multimask_output=True, repeat_image=False, high_res_features=altas,
    )
    alvo = F.interpolate(torch.from_numpy(mascara).float()[None, None].to(dev), size=logits.shape[-2:], mode="bilinear") > 0.5
    alvo = alvo.float()
    perdas = _perda(logits, alvo)[0]
    melhor = int(torch.argmin(perdas))
    with torch.no_grad():
        binarias = (logits[0] > 0).float()
        iou_real = (binarias * alvo[0]).sum(dim=(-1, -2)) / ((binarias + alvo[0]) > 0).float().sum(dim=(-1, -2)).clamp(min=1)
    return perdas[melhor] + PESO_IOU * F.mse_loss(iou_previsto[0], iou_real)


def treinar(manifesto: dict, epocas: int, semente: int, modelo=None, parar_apos_passos: int | None = None,
            taxa: float = TAXA):
    c = config.caminhos()
    if modelo is None:
        from treino_ia import variantes

        modelo = variantes.Segmentador().preditor
    sam = modelo.model
    for parametro in sam.parameters():
        parametro.requires_grad_(False)
    decodificador = sam.sam_mask_decoder
    for parametro in decodificador.parameters():
        parametro.requires_grad_(True)
    otimizador = torch.optim.AdamW(decodificador.parameters(), lr=taxa)

    amostras = _amostras(manifesto)
    assinatura = hashlib.sha1("|".join(sorted(f"{a['lote']['id']}_{a['lado']}" for a in amostras)).encode()).hexdigest()
    identidade = {"semente": semente, "epocas": epocas, "assinatura": assinatura, "amostras": len(amostras), "taxa": taxa}

    caminho_estado = c.pesos / "estado.pt"
    estado = {"epoca": 0, "indice": 0, "passo": 0, "soma_perda": 0.0}
    if caminho_estado.exists():
        salvo = torch.load(caminho_estado, map_location=modelo.device)
        if all(salvo.get(k) == v for k, v in identidade.items()):
            decodificador.load_state_dict(salvo["decodificador"])
            otimizador.load_state_dict(salvo["otimizador"])
            estado = {k: salvo[k] for k in estado}
        else:
            caminho_estado.rename(c.pesos / f"estado-anterior-{datetime.now():%Y%m%d%H%M%S}.pt")

    def salvar_estado():
        _salvar({**estado, **identidade, "decodificador": decodificador.state_dict(), "otimizador": otimizador.state_dict()}, caminho_estado)

    final = c.pesos / f"decodificador_{date.today():%Y%m%d}_s{semente}.pt"
    ultimo_estado = time.monotonic()
    passos_nesta_chamada = 0
    decodificador.train()
    while estado["epoca"] < epocas:
        ordem = list(range(len(amostras)))
        random.Random(semente * 1000 + estado["epoca"]).shuffle(ordem)
        rng = np.random.default_rng(semente * 1000 + estado["epoca"])
        rng.integers(10 ** 6, size=estado["indice"])
        while estado["indice"] < len(ordem):
            perda = _passo(modelo, amostras[ordem[estado["indice"]]], rng)
            otimizador.zero_grad()
            perda.backward()
            otimizador.step()
            estado["indice"] += 1
            estado["passo"] += 1
            estado["soma_perda"] += float(perda.detach())
            passos_nesta_chamada += 1
            if estado["passo"] % 10 == 0:
                recursos.conferir_memoria()
            if parar_apos_passos is not None and passos_nesta_chamada >= parar_apos_passos:
                salvar_estado()
                return caminho_estado
            if time.monotonic() - ultimo_estado > INTERVALO_ESTADO_S:
                salvar_estado()
                ultimo_estado = time.monotonic()
        media = estado["soma_perda"] / max(1, len(ordem))
        c.logs.mkdir(parents=True, exist_ok=True)
        with open(c.logs / "treino.log", "a", encoding="utf-8") as log:
            log.write(f"época {estado['epoca'] + 1}/{epocas}: perda média {media:.4f} ({len(ordem)} recortes)\n")
        estado["epoca"] += 1
        estado["indice"] = 0
        estado["soma_perda"] = 0.0
        situacao.registrar(c, "treinar", feitos=estado["epoca"], total=epocas)
        _salvar({"decodificador": {k: v.cpu() for k, v in decodificador.state_dict().items()}, "semente": semente,
                 "epoca": estado["epoca"], "taxa": taxa}, c.pesos / "epocas" / f"s{semente}" / f"epoca_{estado['epoca']:02d}.pt")
        salvar_estado()
    _salvar({"decodificador": {k: v.cpu() for k, v in decodificador.state_dict().items()}, "semente": semente, "epocas": epocas, "lotes": len(amostras)}, final)
    decodificador.eval()
    return final

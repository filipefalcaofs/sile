"""Média dos pesos do decodificador de várias épocas do mesmo treino.

A qualidade na validação oscila de uma época para outra; a média das últimas
épocas costuma ficar num ponto mais estável. Tensores inteiros (contadores)
ficam com o valor da última época.
"""

import io

import torch

from treino_ia import arquivos, config


def media(semente: int, de: int, ate: int):
    pasta = config.caminhos().pesos / "epocas" / f"s{semente}"
    estados = [torch.load(pasta / f"epoca_{e:02d}.pt", map_location="cpu", weights_only=True)["decodificador"]
               for e in range(de, ate + 1)]
    ultimo = estados[-1]
    resultado = {}
    for chave, valor in ultimo.items():
        if valor.is_floating_point():
            resultado[chave] = torch.stack([e[chave].float() for e in estados]).mean(dim=0).to(valor.dtype)
        else:
            resultado[chave] = valor.clone()
    destino = pasta / f"media_e{de:02d}-e{ate:02d}.pt"
    buffer = io.BytesIO()
    torch.save({"decodificador": resultado, "semente": semente, "epocas_na_media": list(range(de, ate + 1))}, buffer)
    arquivos.gravar_atomico(destino, buffer.getvalue())
    return destino

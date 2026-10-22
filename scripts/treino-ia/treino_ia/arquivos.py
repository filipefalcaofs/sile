"""Gravação atômica: uma troca de pod no meio nunca deixa arquivo pela metade."""

import json
import os
import tempfile
from pathlib import Path


def gravar_atomico(caminho: Path, dados: bytes) -> None:
    caminho.parent.mkdir(parents=True, exist_ok=True)
    descritor, temporario = tempfile.mkstemp(dir=caminho.parent, prefix=f".{caminho.name}.")
    try:
        with os.fdopen(descritor, "wb") as arquivo:
            arquivo.write(dados)
            arquivo.flush()
            os.fsync(arquivo.fileno())
        os.replace(temporario, caminho)
    except BaseException:
        Path(temporario).unlink(missing_ok=True)
        raise


def gravar_json(caminho: Path, obj) -> None:
    gravar_atomico(caminho, json.dumps(obj, ensure_ascii=False, indent=1).encode("utf-8"))


def ler_json(caminho: Path, padrao=None):
    if not caminho.exists():
        return padrao
    return json.loads(caminho.read_text(encoding="utf-8"))

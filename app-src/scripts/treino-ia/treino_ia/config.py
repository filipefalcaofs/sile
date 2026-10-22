"""Pastas da ferramenta, todas dentro do volume persistente do pod."""

import os
from dataclasses import dataclass
from pathlib import Path

RAIZ_PADRAO = "/opt/app-root/src/storage/app/private/treino-ia"


@dataclass(frozen=True)
class Caminhos:
    raiz: Path

    @property
    def dados(self) -> Path:
        return self.raiz / "dados"

    @property
    def recortes(self) -> Path:
        return self.raiz / "recortes"

    @property
    def cache(self) -> Path:
        return self.raiz / "cache"

    @property
    def pesos(self) -> Path:
        return self.raiz / "pesos"

    @property
    def logs(self) -> Path:
        return self.raiz / "logs"

    @property
    def avaliacoes(self) -> Path:
        return self.raiz / "avaliacoes"

    @property
    def galerias(self) -> Path:
        return self.raiz / "galerias"

    @property
    def cego(self) -> Path:
        return self.raiz / "cego"

    @property
    def estado(self) -> Path:
        return self.raiz / "estado"


def caminhos() -> Caminhos:
    return Caminhos(Path(os.environ.get("TREINO_IA_RAIZ", RAIZ_PADRAO)))

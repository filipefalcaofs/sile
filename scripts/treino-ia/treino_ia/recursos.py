"""Limites para o treino não derrubar a homologação: 2 threads e 4 GB."""

import os
import resource
from pathlib import Path


class MemoriaExcedida(RuntimeError):
    pass


def aplicar_limites(threads: int = 2) -> None:
    for variavel in ("OMP_NUM_THREADS", "MKL_NUM_THREADS", "OPENBLAS_NUM_THREADS"):
        os.environ[variavel] = str(threads)
    try:
        import torch

        torch.set_num_threads(threads)
    except ImportError:
        pass


def memoria_residente_mb() -> float:
    status = Path("/proc/self/status")
    if status.exists():
        for linha in status.read_text().splitlines():
            if linha.startswith("VmRSS:"):
                return int(linha.split()[1]) / 1024
    return resource.getrusage(resource.RUSAGE_SELF).ru_maxrss / 1024


def conferir_memoria(teto_mb: int = 4096) -> None:
    usada = memoria_residente_mb()
    if usada > teto_mb:
        raise MemoriaExcedida(f"Memória em uso ({usada:.0f} MB) passou do teto de {teto_mb} MB.")

"""Limites de threads e memória e escolha do dispositivo de cálculo.

No pod valem 2 threads e 4 GB. Na máquina local, TREINO_IA_THREADS,
TREINO_IA_TETO_MB e TREINO_IA_DISPOSITIVO (cpu, mps ou auto) ajustam esses valores.
"""

import os
import resource
import sys
from pathlib import Path

THREADS_POD = 2
TETO_POD_MB = 4096


class MemoriaExcedida(RuntimeError):
    pass


def threads_padrao() -> int:
    return int(os.environ.get("TREINO_IA_THREADS", THREADS_POD))


def teto_padrao_mb() -> int:
    return int(os.environ.get("TREINO_IA_TETO_MB", TETO_POD_MB))


def aplicar_limites(threads: int | None = None) -> None:
    threads = threads or threads_padrao()
    for variavel in ("OMP_NUM_THREADS", "MKL_NUM_THREADS", "OPENBLAS_NUM_THREADS"):
        os.environ[variavel] = str(threads)
    os.environ.setdefault("PYTORCH_ENABLE_MPS_FALLBACK", "1")
    try:
        import torch

        torch.set_num_threads(threads)
    except ImportError:
        pass


def dispositivo() -> str:
    escolhido = os.environ.get("TREINO_IA_DISPOSITIVO", "auto")
    if escolhido != "auto":
        return escolhido
    import torch

    return "mps" if torch.backends.mps.is_available() else "cpu"


def _maxrss_em_mb(maxrss: int, plataforma: str) -> float:
    """O ru_maxrss vem em bytes no macOS e em KB no Linux."""
    return maxrss / (1024 * 1024 if plataforma == "darwin" else 1024)


def memoria_residente_mb() -> float:
    status = Path("/proc/self/status")
    if status.exists():
        for linha in status.read_text().splitlines():
            if linha.startswith("VmRSS:"):
                return int(linha.split()[1]) / 1024
    return _maxrss_em_mb(resource.getrusage(resource.RUSAGE_SELF).ru_maxrss, sys.platform)


def conferir_memoria(teto_mb: int | None = None) -> None:
    teto_mb = teto_mb or teto_padrao_mb()
    usada = memoria_residente_mb()
    if usada > teto_mb:
        raise MemoriaExcedida(f"Memória em uso ({usada:.0f} MB) passou do teto de {teto_mb} MB.")

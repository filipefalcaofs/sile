import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

import pytest


@pytest.fixture(autouse=True)
def _limites_do_pod(monkeypatch):
    """Os testes partem dos limites do pod, mesmo com a máquina local configurada."""
    monkeypatch.delenv("TREINO_IA_TETO_MB", raising=False)
    monkeypatch.delenv("TREINO_IA_THREADS", raising=False)

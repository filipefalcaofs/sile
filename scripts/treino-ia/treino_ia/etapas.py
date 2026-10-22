"""Despacho das etapas da linha de comando."""

from treino_ia import config


def situacao(args) -> None:
    from treino_ia import situacao as modulo

    print(modulo.resumo(config.caminhos()))

"""Despacho das etapas da linha de comando."""

from treino_ia import config


def situacao(args) -> None:
    from treino_ia import situacao as modulo

    print(modulo.resumo(config.caminhos()))


def dados(args) -> None:
    from treino_ia import dados as modulo

    if args.semente is None:
        raise SystemExit("Informe --semente.")
    m = modulo.montar(args.semente, args.treino, args.teste)
    print(f"treino: {len(m['treino'])} | teste: {len(m['teste'])} | bairros de teste: {len(m['bairros_teste'])} | descartados: {m['descartados']}")

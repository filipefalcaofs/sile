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


def _manifesto():
    from treino_ia import arquivos

    m = arquivos.ler_json(config.caminhos().dados / "manifesto.json")
    if m is None:
        raise SystemExit("Rode antes: python -m treino_ia dados --semente N")
    return m


def recortes(args) -> None:
    from treino_ia import recortes as modulo

    print(modulo.baixar_todos(_manifesto(), limite=args.limite))


def cache(args) -> None:
    from treino_ia import cache as modulo, variantes

    print(modulo.gerar(_manifesto(), variantes.Segmentador(), limite=args.limite))


def treinar(args) -> None:
    from treino_ia import treino

    if args.semente is None:
        raise SystemExit("Informe --semente.")
    print(f"pesos: {treino.treinar(_manifesto(), args.epocas, args.semente)}")

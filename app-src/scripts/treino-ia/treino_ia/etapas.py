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


def avaliar(args) -> None:
    from treino_ia import avaliacao

    alvo = args.alvo or "teste"
    nomes = ["A", "B", "C", "D", "E"] if alvo == "cego" else ["A", "B", "D", "E"]
    if args.regra_f:
        nomes.append("F")
    print(avaliacao.avaliar(alvo, nomes, execucao=args.execucao, regra_f=args.regra_f))


def cego(args) -> None:
    from treino_ia import avaliacao

    nomes = ["A", "B", "C", "D", "E"] + (["F"] if args.regra_f else [])
    print(avaliacao.avaliar("cego", nomes, execucao=args.execucao, regra_f=args.regra_f))


def galeria(args) -> None:
    from treino_ia import galeria as modulo

    execucoes = [args.execucao] if args.execucao else sorted(p.name for p in config.caminhos().avaliacoes.iterdir() if (p / "resultados.json").exists())
    for execucao in execucoes:
        print(modulo.gerar(execucao))

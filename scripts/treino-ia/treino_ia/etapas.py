"""Despacho das etapas da linha de comando."""

from treino_ia import config


def situacao(args) -> None:
    from treino_ia import situacao as modulo

    print(modulo.resumo(config.caminhos()))


def dados(args) -> None:
    from treino_ia import dados as modulo

    if args.semente is None:
        raise SystemExit("Informe --semente.")
    m = modulo.montar(args.semente, args.treino, args.teste, teste_por_bairro=args.teste_por_bairro)
    (config.caminhos().dados / "manifesto-bruto.json").unlink(missing_ok=True)
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


def filtrar(args) -> None:
    """Sempre a partir do manifesto do sorteio (manifesto-bruto.json), para poder rodar de novo."""
    from treino_ia import arquivos, recortes as modulo

    c = config.caminhos()
    bruto = arquivos.ler_json(c.dados / "manifesto-bruto.json")
    if bruto is None:
        bruto = _manifesto()
        arquivos.gravar_json(c.dados / "manifesto-bruto.json", bruto)
    m = modulo.filtrar_com_imagem(bruto, args.treino, args.teste)
    arquivos.gravar_json(c.dados / "manifesto.json", m)
    print(f"treino: {len(m['treino'])} | teste: {len(m['teste'])} | bairros de teste: {len(m['bairros_teste'])} | descartados: {m['descartados']}")


def validacao(args) -> None:
    """Separa bairros de validação do treino, baixa os recortes deles e tira os sem imagem."""
    from treino_ia import arquivos, dados as modulo_dados, recortes as modulo_recortes

    if args.semente is None:
        raise SystemExit("Informe --semente.")
    c = config.caminhos()
    m = modulo_dados.separar_validacao(_manifesto(), args.fracao, args.semente)
    print(modulo_recortes.baixar_todos({"validacao": m["validacao"]}))
    m = modulo_recortes.filtrar_com_imagem(m, len(m["treino"]), len(m["teste"]))
    arquivos.gravar_json(c.dados / "manifesto.json", m)
    print(f"treino: {len(m['treino'])} | validação: {len(m['validacao'])} em {len(m['bairros_validacao'])} bairros | teste: {len(m['teste'])}")


def varrer(args) -> None:
    from treino_ia import varredura

    if args.semente is None:
        raise SystemExit("Informe --semente do treino.")
    resumo = varredura.varrer(args.semente, args.passo)
    for e in resumo["epocas"]:
        print(f"época {e['epoca']:2d}: IoU mediano {e['iou_mediano']:.3f} | IoU ≥ 0,5: {e['iou_05']} | ≥ 0,7: {e['iou_07']} | {e['lotes']} lotes")
    if resumo["melhor"]:
        print(f"melhor: época {resumo['melhor']['epoca']} ({resumo['melhor']['pesos']})")


def cache(args) -> None:
    from treino_ia import cache as modulo, variantes

    print(modulo.gerar(_manifesto(), variantes.Segmentador(), limite=args.limite))


def treinar(args) -> None:
    from treino_ia import treino

    if args.semente is None:
        raise SystemExit("Informe --semente.")
    print(f"pesos: {treino.treinar(_manifesto(), args.epocas, args.semente, taxa=args.taxa)}")


def avaliar(args) -> None:
    from treino_ia import avaliacao

    alvo = args.alvo or "teste"
    nomes = ["A", "B", "C", "D", "E"] if alvo == "cego" else ["A", "B", "D", "E"]
    if args.regra_f:
        nomes.append("F")
    if args.juiza:
        nomes.append("J")
    if args.variantes:
        nomes = [n.strip() for n in args.variantes.split(",") if n.strip()]
    print(avaliacao.avaliar(alvo, nomes, execucao=args.execucao, regra_f=args.regra_f, pesos=args.pesos))


def cego(args) -> None:
    from treino_ia import avaliacao

    nomes = ["A", "B", "C", "D", "E"] + (["F"] if args.regra_f else []) + (["J"] if args.juiza else [])
    print(avaliacao.avaliar("cego", nomes, execucao=args.execucao, regra_f=args.regra_f))


def galeria(args) -> None:
    from treino_ia import galeria as modulo

    execucoes = [args.execucao] if args.execucao else sorted(p.name for p in config.caminhos().avaliacoes.iterdir() if (p / "resultados.json").exists())
    for execucao in execucoes:
        print(modulo.gerar(execucao))

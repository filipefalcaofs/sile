"""Linha de comando: python -m treino_ia <etapa> [opções]."""

import argparse
import sys

from treino_ia import recursos

ETAPAS = ("dados", "recortes", "filtrar", "validacao", "cache", "varrer", "treinar", "avaliar", "cego", "galeria", "situacao")


def _argumentos(argv: list[str]) -> argparse.Namespace:
    parser = argparse.ArgumentParser(prog="python -m treino_ia")
    parser.add_argument("etapa", choices=ETAPAS)
    parser.add_argument("alvo", nargs="?", choices=("teste", "validacao", "cego"))
    parser.add_argument("--semente", type=int)
    parser.add_argument("--treino", type=int, default=600)
    parser.add_argument("--teste", type=int, default=60)
    parser.add_argument("--teste-por-bairro", dest="teste_por_bairro", type=int)
    parser.add_argument("--limite", type=int)
    parser.add_argument("--epocas", type=int, default=10)
    parser.add_argument("--taxa", type=float, default=1e-4, help="taxa de aprendizado do treino")
    parser.add_argument("--passo", type=int, default=2, help="de quantas em quantas épocas a varredura avalia")
    parser.add_argument("--fracao", type=float, default=0.1, help="fração dos lotes de treino que vai para a validação")
    parser.add_argument("--variantes", help="variantes a avaliar, separadas por vírgula (ex.: E)")
    parser.add_argument("--pesos", help="arquivo de pesos do decodificador para E/F/J (padrão: o mais recente)")
    parser.add_argument("--execucao")
    parser.add_argument("--regra-f", dest="regra_f", choices=("B", "C", "D"))
    parser.add_argument("--juiza", action="store_true", help="inclui a variante J (LLM escolhe o candidato; usa a API da Anthropic)")
    return parser.parse_args(argv)


def _executar(args: argparse.Namespace) -> None:
    from treino_ia import etapas

    getattr(etapas, args.etapa)(args)


def main(argv: list[str] | None = None) -> int:
    args = _argumentos(sys.argv[1:] if argv is None else argv)
    recursos.aplicar_limites()
    try:
        recursos.conferir_memoria()
        _executar(args)
    except recursos.MemoriaExcedida as erro:
        print(f"PARADO: {erro}", file=sys.stderr)
        return 3
    return 0


if __name__ == "__main__":
    sys.exit(main())

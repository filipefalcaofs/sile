"""Andamento das etapas para o comando situacao."""

from treino_ia import arquivos
from treino_ia.config import Caminhos


def resumo(c: Caminhos) -> str:
    estado = arquivos.ler_json(c.estado / "situacao.json", padrao={}) or {}
    if not estado:
        return "Nenhuma etapa iniciada."
    linhas = []
    for etapa, dados in estado.items():
        linhas.append(
            f"{etapa}: {dados.get('feitos', 0)}/{dados.get('total', '?')}"
            f" | tempo médio {dados.get('tempo_medio_s', '-')} s"
            f" | memória de pico {dados.get('memoria_pico_mb', '-')} MB"
            f" | último erro: {dados.get('ultimo_erro') or '-'}"
        )
    return "\n".join(linhas)


def registrar(c: Caminhos, etapa: str, *, feitos: int, total: int, tempo_medio_s: float | None = None, ultimo_erro: str | None = None) -> None:
    from treino_ia import recursos

    caminho = c.estado / "situacao.json"
    estado = arquivos.ler_json(caminho, padrao={}) or {}
    anterior = estado.get(etapa, {})
    pico = max(float(anterior.get("memoria_pico_mb", 0)), round(recursos.memoria_residente_mb(), 1))
    estado[etapa] = {
        "feitos": feitos,
        "total": total,
        "tempo_medio_s": None if tempo_medio_s is None else round(tempo_medio_s, 2),
        "memoria_pico_mb": pico,
        "ultimo_erro": ultimo_erro or anterior.get("ultimo_erro"),
    }
    arquivos.gravar_json(caminho, estado)

#!/usr/bin/env python3
"""Converte as HUs revisadas pelo código para o Markdown do padrão Sudoeste.

Não inventa regra. Copia status, objetivo, fluxo, regras, validações,
critérios e o que não foi evidenciado.
"""

from __future__ import annotations

import re
import unicodedata
from pathlib import Path

RAIZ = Path(__file__).resolve().parents[1]
ORIGEM = RAIZ / "docs" / "VIABILIZA_HUs_Completas_MD"
DESTINO = RAIZ / "docs" / "requisitos" / "hus"
PROJETO = "Viabiliza — Sistema de Licenciamento Eletrônico"

APROVACAO = """A Sudoeste Informática esclarece que o desenvolvimento das funcionalidades descritas neste documento foi realizado de acordo com os requisitos e especificações fornecidos pelo cliente. Nossa equipe seguiu rigorosamente as instruções recebidas, garantindo que o sistema esteja alinhado com o que foi solicitado.

Para evitar possíveis retrabalhos ou ajustes futuros, sugerimos que o cliente revise atentamente os requisitos apresentados, verificando se eles atendem plenamente às suas necessidades e expectativas, bem como se estão em conformidade com eventuais normas aplicáveis ao seu negócio.

Reafirmamos nosso compromisso de entregar um software de qualidade que agregue valor às operações do cliente, e a validação cuidadosa dos requisitos é um passo importante para garantir o sucesso do projeto.

| Data | Setor | Nome | Assinatura |
|------|-------|------|------------|
|      |       |      |            |
|      |       |      |            |
|      |       |      |            |
"""


def secoes(texto: str) -> dict[str, str]:
    atual = "_inicio"
    buf: dict[str, list[str]] = {atual: []}
    for linha in texto.splitlines():
        if linha.startswith("## "):
            atual = linha[3:].strip()
            buf[atual] = []
        else:
            buf.setdefault(atual, []).append(linha)
    return {k: "\n".join(v).strip() for k, v in buf.items()}


def limpar(texto: str) -> str:
    texto = texto.replace("**", "")
    texto = re.sub(r"`([^`]*)`", r"\1", texto)
    texto = re.sub(r"\s+", " ", texto).strip(" \n,;.")
    return texto


def titulo_de(caminho: Path, inicio: str) -> tuple[int, str]:
    match = re.search(r"HU-(\d+)", caminho.name)
    if not match:
        raise ValueError(caminho.name)
    numero = int(match.group(1))
    primeira = inicio.splitlines()[0]
    titulo = re.sub(r"^#\s*HU-\d+\s*[—–-]\s*", "", primeira).strip()
    return numero, titulo


def como_quero_para(bloco: str) -> tuple[str, str, str] | None:
    texto = re.sub(r"\s+", " ", bloco.replace("**", " ")).strip()
    match = re.search(
        r"Como\s+(.+?)\s+quero\s+(.+?)\s+para\s+(.+)",
        texto,
        flags=re.IGNORECASE,
    )
    if not match:
        return None
    return tuple(parte.strip(" .") for parte in match.groups())  # type: ignore[return-value]


def bullets(bloco: str) -> list[str]:
    itens = []
    for linha in bloco.splitlines():
        linha = linha.strip()
        if linha.startswith("- "):
            item = limpar(linha[2:])
            if item:
                itens.append(item)
    return itens


def paragrafos(bloco: str) -> list[str]:
    partes = []
    for linha in bloco.splitlines():
        linha = linha.strip()
        if not linha or linha.startswith("- ") or linha.startswith("#") or linha.startswith("|"):
            continue
        if linha.startswith("**"):
            continue
        partes.append(limpar(linha))
    return [p for p in partes if p]


def cenarios(bloco: str) -> list[tuple[str, str, str, str]]:
    encontrados: list[tuple[str, str, str, str]] = []
    titulo = ""
    dado = quando = entao = ""

    def fecha() -> None:
        nonlocal titulo, dado, quando, entao
        if dado and quando and entao:
            nome = titulo or f"Comportamento {len(encontrados) + 1}"
            encontrados.append((limpar(nome), limpar(dado), limpar(quando), limpar(entao)))
        titulo = dado = quando = entao = ""

    for linha in bloco.splitlines():
        bruto = linha.strip()
        if not bruto:
            continue
        cabeca = re.match(r"^###\s+(.+)$", bruto)
        if cabeca:
            fecha()
            titulo = cabeca.group(1)
            continue
        dado_m = re.match(r"^\*\*Dado(?:\s+que)?\*\*\s*(.+)$", bruto, re.I)
        if not dado_m:
            dado_m = re.match(r"^\*\*Dado\*\*\s+que\s+(.+)$", bruto, re.I)
        if dado_m:
            if dado:
                fecha()
            dado = dado_m.group(1)
            continue
        quando_m = re.match(r"^\*\*Quando\*\*\s*(.+)$", bruto, re.I)
        if quando_m:
            quando = quando_m.group(1)
            continue
        entao_m = re.match(r"^\*\*Então\*\*\s*(.+)$", bruto, re.I)
        if entao_m:
            entao = entao_m.group(1)
            continue
    fecha()
    return encontrados


def slug(titulo: str) -> str:
    texto = unicodedata.normalize("NFKD", titulo)
    texto = "".join(c for c in texto if not unicodedata.combining(c))
    texto = re.sub(r"[\\/:*?\"<>|]", " ", texto)
    texto = re.sub(r"\s+", " ", texto).strip()
    return texto


def frase(*partes: str) -> str:
    texto = re.sub(r"\s+", " ", " ".join(p for p in partes if p)).strip()
    return texto.rstrip(" ,.;")


def converter(caminho: Path) -> Path:
    texto = caminho.read_text(encoding="utf-8")
    blocos = secoes(texto)
    numero, titulo = titulo_de(caminho, blocos.get("_inicio", texto))
    epica = limpar(blocos.get("Épica", "Viabiliza"))
    objetivo = " ".join(paragrafos(blocos.get("Objetivo", ""))) or titulo
    situacao = " ".join(paragrafos(blocos.get("Situação na implementação", "")))
    historia = como_quero_para(blocos.get("História de Usuário", ""))
    if historia is None:
        historia = ("usuário do Viabiliza", objetivo, "o comportamento do código seja o descrito nesta história")
    ator, quero, beneficio = historia
    regras = bullets(blocos.get("Regras de negócio evidenciadas", ""))
    validacoes = bullets(blocos.get("Validações", "")) + paragrafos(blocos.get("Validações", ""))
    fluxo = paragrafos(blocos.get("Fluxo evidenciado", ""))
    atores = bullets(blocos.get("Atores e permissões", "")) + paragrafos(blocos.get("Atores e permissões", ""))
    nao = bullets(blocos.get("Não evidenciado", "")) + paragrafos(blocos.get("Não evidenciado", ""))
    divergencias = paragrafos(blocos.get("Divergências da redação anterior", ""))
    casos = cenarios(blocos.get("Critérios de aceite", ""))
    if not casos:
        casos = [(
            "Comportamento evidenciado no código",
            situacao or "o código desta história está como descrito na visão geral",
            quero,
            objetivo,
        )]

    visao = [f"Catálogo Viabiliza: HU-{numero:03d}. {situacao}".strip()]
    visao.append(objetivo)
    if regras:
        visao.append("Regras evidenciadas no código: " + " ".join(f"({i}) {r}." for i, r in enumerate(regras, 1)))
    if fluxo:
        visao.append("Fluxo evidenciado: " + " ".join(fluxo))
    if nao:
        visao.append("Não evidenciado no código: " + " ".join(nao) + ".")
    if divergencias:
        visao.append("Divergência em relação à redação anterior: " + " ".join(divergencias))

    pre = atores[:8] or ["Condição descrita no fluxo evidenciado pelo código."]
    if validacoes:
        pre.extend(validacoes[:6])

    codigo = f"HU.{numero:03d}"
    linhas = [
        f"# {codigo} – {titulo.upper()}",
        f"**PROJETO: {PROJETO}**",
        "",
        "---",
        "",
        "## Histórico de Revisão",
        "",
        "| Data | Resumo das Alterações | Versão | Responsável |",
        "|:---:|---|:---:|:---:|",
        "| 16/10/2026 | Revisão pelo código-fonte vigente | 2.0 | Sudoeste Informática |",
        "",
        "---",
        "",
        "## Visão Geral",
        "",
        *visao,
        "",
        "---",
        "",
        "## Sumário",
        "",
        f"- [Épico: {epica}](#épico)",
        f"- [História de Usuário: {titulo}](#história-de-usuário)",
        "- [Pré-requisitos](#pré-requisitos)",
        "- [Dependências](#dependências)",
        "- [Interface de Usuário](#interface-de-usuário)",
        "- [Aprovação do Requisito](#aprovação-do-requisito)",
        "",
        "---",
        "",
        f"## Épico: {epica}",
        "",
        f"**Como** a SEDUR **quero** {frase(titulo.lower())}**, de modo que** {frase(objetivo)}",
        "",
        f"## História de Usuário: {titulo}",
        "",
        f"**Como** {frase(ator)} **quero** {frase(quero)}**, de modo que** {frase(beneficio)}",
        "",
        "## Pré-requisitos",
        "",
        *[f"- {item}" for item in pre],
        "",
        "## Dependências",
        "",
        "**Passadas**",
        "",
        "- Nenhuma além do que o código desta história já exige",
        "",
        "**Futuras**",
        "",
        "- Nenhuma",
        "",
        "---",
        "",
        "## Interface de Usuário",
        "",
        f"### Tela 1 — {titulo}",
        "",
        "**Caminho no menu:** Conforme a permissão e a rota descritas nos pré-requisitos",
        "",
        "*[Espaço para protótipo/screenshot]*",
        "",
        "#### Comportamento Esperado Tela 1",
        "",
    ]
    for indice, (nome, dado, quando, entao) in enumerate(casos, 1):
        linhas.extend([
            f"**Cenário {indice}: {nome}**",
            f"**Dado que** {dado}",
            f"**Quando** {quando}",
            f"**Então** {entao}",
            "",
        ])
    linhas.extend([
        "---",
        "",
        "## Aprovação do Requisito",
        "",
        APROVACAO.rstrip(),
        "",
    ])

    DESTINO.mkdir(parents=True, exist_ok=True)
    destino = DESTINO / f"{codigo} - {slug(titulo)}.md"
    destino.write_text("\n".join(linhas) + "\n", encoding="utf-8")
    return destino


def main() -> None:
    arquivos = sorted(ORIGEM.rglob("HU-*.md"))
    gerados = [converter(caminho) for caminho in arquivos]
    print(f"{len(gerados)} HUs em {DESTINO}")


if __name__ == "__main__":
    main()

#!/usr/bin/env python3
"""Aplica a medição função por função: TD e AR reais, retirada do que não é processo elementar
e prova de cada função gravada na observação da planilha."""

from __future__ import annotations

import json
import re
import shutil
import sys
from collections import Counter
from pathlib import Path

sys.path.insert(0, "/Users/filipefalcao/.cursor/skills/contagem-ponto-funcao/scripts")
from ifpug_calc import calcular_pf, complexidade  # noqa: E402

PASTA = Path(__file__).resolve().parent
ARQUIVO = PASTA / "contagem-viabiliza-completa.json"
VERSAO_ANTERIOR = PASTA / "contagem-viabiliza-completa-v2.1-1585pf.json"
MEDICAO = Path("/tmp/pf-resultado.json")

BLOQUEADOS = {
    "Acesso gov.br - concluir autenticação": (
        "Integração implementada e desligada aguardando a credencial da SEDUR no Login Único (HU-151). "
        "Sem validação no ambiente real do gov.br não há como comprovar ao cliente."
    ),
    "Dados - Comunicação à SEFAZ": (
        "Envio à SEFAZ bloqueado (HU-110): o sistema só registra a pendência. Sem a integração real "
        "não há como comprovar ao cliente."
    ),
}

NOVO_ARQUIVO = {
    "grupo": "Dados de negócio complementares",
    "nome": "Dados - Registro de envio de e-mail",
    "tipo": "ALI",
    "td": 8,
    "ar": 1,
    "campos": ["destinatário", "nome do destinatário", "tipo de mensagem", "situação", "erro", "data na fila", "data de envio", "data de falha"],
    "arquivos": [],
    "prova": (
        "Model EmailLog; migration 2026_06_12_135955_create_email_logs_table. Gravado a cada e-mail enviado pelo "
        "sistema (User::routeNotificationForMail e eventos de envio) e consultado na tela Monitoramento de e-mails "
        "(GET /gestao/emails). Teste: tests/Feature/Emails/EmailLogIndexTest.php::"
        "test_administrador_consulta_log_de_emails_com_contadores."
    ),
    "processo_elementar": (
        "Grupo lógico reconhecido pelo gestor: o registro de cada e-mail enviado, com situação e falha, "
        "consultado e filtrado no monitoramento de e-mails."
    ),
    "dependencia": "",
}


def observacao(medida: dict) -> str:
    tipo = medida["tipo"]
    rotulo = "TR" if tipo in ("ALI", "AIE") else "AR"
    justificativa = re.sub(r"^\s*Por que é processo elementar:\s*", "", medida["processo_elementar"]).strip()
    if tipo in ("ALI", "AIE"):
        partes = [f"Prova: {medida['prova'].strip()}", f"Arquivo lógico: {justificativa}"]
    else:
        partes = [f"Prova: {medida['prova'].strip()}", f"Processo elementar independente: {justificativa}"]
    partes.append(f"TD={medida['td']} ({', '.join(medida.get('campos') or [])}).")
    arquivos = medida.get("arquivos") or []
    partes.append(f"{rotulo}={medida['ar']}" + (f" ({', '.join(arquivos)})." if arquivos else "."))
    if medida.get("decisao") == "reclassificar" and medida.get("motivo"):
        partes.append(f"Classificação: {medida['motivo'].strip()}")
    if medida.get("dependencia"):
        partes.append(f"Para demonstrar: {medida['dependencia'].strip()}.")
    return " ".join(p if p.endswith((".", ")")) else p + "." for p in partes)


def item(medida: dict) -> dict:
    tipo, td, ar = medida["tipo"], medida["td"], medida["ar"]
    capitulo = "6" if tipo in ("ALI", "AIE") else "7"
    return {
        "nome": medida["nome"],
        "tipo": tipo,
        "complexidade": complexidade(tipo, td, ar),
        "td": td,
        "ar": ar,
        "observacoes": observacao(medida),
        "citacao_cpm": f"Parte 2 Cap {capitulo}.",
        "pf_local": calcular_pf(tipo, td, ar),
    }


def main() -> None:
    dados = json.loads(ARQUIVO.read_text(encoding="utf-8"))
    if dados["identificacao"].get("versao") == "3.0":
        raise SystemExit("Medição já aplicada.")
    if not VERSAO_ANTERIOR.exists():
        shutil.copyfile(ARQUIVO, VERSAO_ANTERIOR)

    medidas = json.loads(MEDICAO.read_text(encoding="utf-8"))
    por_posicao = {(m["grupo_id"], m["indice"]): m for m in medidas}
    antes = sum(i["pf_local"] for g in dados["grupos"] for i in g["itens"])

    retirados = []
    grupos = []
    for grupo in dados["grupos"]:
        itens = []
        for indice, atual in enumerate(grupo["itens"]):
            medida = por_posicao[(grupo["id"], indice)]
            if medida["nome"] != atual["nome"]:
                raise SystemExit(f"Desalinhado: {atual['nome']} x {medida['nome']}")
            if medida["nome"] in BLOQUEADOS:
                medida = {**medida, "decisao": "remover", "motivo": BLOQUEADOS[medida["nome"]]}
            if medida["decisao"] == "remover":
                retirados.append({
                    "pacote": grupo["nome"],
                    "nome": atual["nome"],
                    "tipo": atual["tipo"],
                    "pf_retirado": atual["pf_local"],
                    "motivo": medida["motivo"],
                })
                continue
            itens.append(item(medida))
        if grupo["nome"] == NOVO_ARQUIVO["grupo"]:
            itens.append(item(NOVO_ARQUIVO))
        if itens:
            grupos.append({"id": "", "nome": grupo["nome"], "itens": itens})

    for indice, grupo in enumerate(grupos, start=1):
        grupo["id"] = f"F{indice}"

    nomes = [i["nome"] for g in grupos for i in g["itens"]]
    repetidos = [n for n, q in Counter(nomes).items() if q > 1]
    if repetidos:
        raise SystemExit(f"Nomes repetidos: {repetidos}")

    total = sum(i["pf_local"] for g in grupos for i in g["itens"])
    dados["grupos"] = grupos
    dados["itens_retirados"] = retirados
    dados["termo"]["total_pf"] = total
    dados["identificacao"]["versao"] = "3.0"
    dados["identificacao"]["proposito"] = (
        "Medir o tamanho funcional do Viabiliza entregue, como projeto de desenvolvimento, seguindo o Manual de "
        "Práticas de Contagem IFPUG 4.3.1. Cada função foi medida no código-fonte pela visão do usuário, e a "
        "observação traz a prova: rota e tela onde é executada, arquivos lidos e gravados, teste automatizado que "
        "a exercita e a justificativa de processo elementar."
    )

    ARQUIVO.write_text(json.dumps(dados, ensure_ascii=False, indent=2), encoding="utf-8")

    por_tipo = Counter()
    for g in grupos:
        for i in g["itens"]:
            por_tipo[i["tipo"]] += i["pf_local"]
    print(f"Antes: {antes} PF | retirados: {len(retirados)} itens, {sum(r['pf_retirado'] for r in retirados)} PF")
    print(f"Total medido: {total} PF {dict(por_tipo)} | pacotes: {len(grupos)} | funções: {len(nomes)}")


if __name__ == "__main__":
    main()

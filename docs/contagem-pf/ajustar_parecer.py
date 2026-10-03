#!/usr/bin/env python3
"""Atende ao parecer da auditoria APF sobre a v3.0: regra de negócio dos arquivos de domínio,
fronteira das zonas, TR do Setor e premissas dos itens negociáveis em bloco."""

from __future__ import annotations

import json
import shutil
import sys
from pathlib import Path

sys.path.insert(0, "/Users/filipefalcao/.cursor/skills/contagem-ponto-funcao/scripts")
from ifpug_calc import calcular_pf, complexidade  # noqa: E402

PASTA = Path(__file__).resolve().parent
ARQUIVO = PASTA / "contagem-viabiliza-completa.json"
VERSAO_ANTERIOR = PASTA / "contagem-viabiliza-completa-v3.2-1618pf.json"

REGRA = {
    "Dados - Via": (
        "a publicação de uma versão do Quadro 11A da LOUOS recusa classe de via que não esteja ativa neste "
        "cadastro (LouosDraftService), e a classe da via decide a condição de instalação da atividade no imóvel"
    ),
    "Dados - Feriado": (
        "todo prazo em dias úteis (pendência do requerente, SLA da análise, convite) desconta os feriados ativos "
        "deste cadastro (BusinessDeadlineCalculator); incluir ou inativar um feriado muda o vencimento dos processos"
    ),
    "Dados - Tipo de serviço": (
        "toda solicitação é protocolada num tipo de serviço deste cadastro; só os ativos aparecem para novo "
        "protocolo no portal, no atendimento presencial e na contingência, e o tipo identifica o processo no TVL "
        "e nos relatórios por serviço"
    ),
    "Dados - Texto-padrão": (
        "a ficha de análise monta o parecer com os textos ativos da categoria da ficha (StandardTextComposer), "
        "preenchidos com os dados do processo; alterar o conteúdo gera nova versão do texto"
    ),
    "Dados - Texto decisório": (
        "a fundamentação legal que o motor emite na decisão automática e na explicação da decisão sai deste "
        "cadastro (DecisionTextCatalog); alterar o texto muda o que é emitido, sem deploy"
    ),
    "Dados - Zona urbanística": (
        "a publicação de uma versão do Quadro 10 da LOUOS recusa zona que não esteja ativa neste cadastro "
        "(LouosDraftService); o código da zona é a chave da permissão de uso por zona"
    ),
    "Dados - Atividade permitida em escritório virtual": (
        "a lista publicada decide quais CNAEs a sede em escritório virtual pode exercer (SedeAtividadesResolver); "
        "CNAE fora do anexo vigente impede o enquadramento como escritório virtual"
    ),
}

FRONTEIRA_ZONA_LOCAL = (
    "Fronteira com o AIE Zonas urbanísticas do GeoServer da SEDUR: este arquivo é o catálogo de códigos e nomes "
    "das zonas da LOUOS, mantido pelo gestor no Viabiliza; o AIE é a geometria oficial do zoneamento, mantida pela "
    "SEDUR no GeoServer, que diz em que zona o ponto do imóvel está. São dados diferentes, um de cada lado da fronteira."
)
FRONTEIRA_ZONA_AIE = (
    "Fronteira com o ALI Zona urbanística: o AIE traz a geometria do zoneamento (em que zona o imóvel cai), mantida "
    "pela SEDUR; o ALI é o catálogo de códigos e nomes das zonas mantido no Viabiliza. Não há dado contado nos dois lados."
)

PREMISSA = {
    "Notificações - marcar todas como lidas": (
        "processo distinto de marcar uma como lida: não recebe a notificação escolhida e aplica a marcação em massa "
        "às não lidas do usuário (TD e lógica diferentes)"
    ),
    "Relatórios - imprimir trilha por processo": (
        "não é só outro formato da consulta: gera o documento de prestação de contas com emissor e data de emissão, "
        "que a consulta não mostra, e registra a impressão na trilha"
    ),
    "Gestão - sair do sistema": (
        "a saída tem efeito reconhecido pelo usuário e pelo auditor: encerra só a sessão da gestão, preserva a do "
        "portal e grava o Registro de acesso"
    ),
    "Envio para análise - pesquisar processo": (
        "consulta própria do envio para análise: localiza qualquer processo pelo número, em qualquer situação, o "
        "que a fila e a busca rápida não fazem"
    ),
    "Simulação de regras - abrir simulação": (
        "consulta própria: lista os rascunhos de quadro abertos que podem ser simulados, com autor e data, dado que "
        "nenhuma outra tela entrega"
    ),
}


def main() -> None:
    dados = json.loads(ARQUIVO.read_text(encoding="utf-8"))
    if dados["identificacao"].get("versao") != "3.2":
        raise SystemExit("Esperava a versão 3.2.")
    shutil.copyfile(ARQUIVO, VERSAO_ANTERIOR)
    antes = sum(i["pf_local"] for g in dados["grupos"] for i in g["itens"])

    feitos = set()
    for grupo in dados["grupos"]:
        for item in grupo["itens"]:
            nome = item["nome"]
            obs = item["observacoes"].rstrip()
            if nome in REGRA:
                obs += f" Regra de negócio que o tira de dado de código: {REGRA[nome]}."
            if nome == "Dados - Zona urbanística":
                obs += f" {FRONTEIRA_ZONA_LOCAL}"
            if nome == "Dados externos - Zonas urbanísticas do GeoServer da SEDUR":
                obs += f" {FRONTEIRA_ZONA_AIE}"
            if nome in PREMISSA:
                obs += f" Premissa declarada: {PREMISSA[nome]}."
            if nome == "Dados - Setor":
                obs = obs.replace(
                    "RLR: setor e vínculo analista-setor.",
                    "O vínculo analista-setor é tabela de ligação sem atributos próprios: entra como TD (analista "
                    "vinculado), não como TR.",
                ).replace("TR=2 (Setor).", "TR=1 (Setor).")
                item["ar"] = 1
                item["complexidade"] = complexidade(item["tipo"], item["td"], 1)
                item["pf_local"] = calcular_pf(item["tipo"], item["td"], 1)
            if obs != item["observacoes"]:
                item["observacoes"] = obs
                feitos.add(nome)

    esperados = set(REGRA) | set(PREMISSA) | {
        "Dados - Setor", "Dados externos - Zonas urbanísticas do GeoServer da SEDUR",
    }
    if esperados - feitos:
        raise SystemExit(f"Não ajustados: {esperados - feitos}")

    total = sum(i["pf_local"] for g in dados["grupos"] for i in g["itens"])
    dados["termo"]["total_pf"] = total
    dados["identificacao"]["versao"] = "3.3"
    ARQUIVO.write_text(json.dumps(dados, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"Itens ajustados: {len(feitos)} | PF {antes} -> {total}")


if __name__ == "__main__":
    main()

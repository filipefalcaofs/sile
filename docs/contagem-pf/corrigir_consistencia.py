#!/usr/bin/env python3
"""Fecha a consistência da contagem: todo AR citado por uma transação passa a ser um arquivo lógico
da lista, subgrupos são citados pelo arquivo pai e dados que não são arquivo lógico saem do AR."""

from __future__ import annotations

import json
import shutil
import sys
from collections import Counter
from pathlib import Path

sys.path.insert(0, "/Users/filipefalcao/.cursor/skills/contagem-ponto-funcao/scripts")
from ifpug_calc import calcular_pf, complexidade  # noqa: E402

from aplicar_medicao import observacao  # noqa: E402

PASTA = Path(__file__).resolve().parent
ARQUIVO = PASTA / "contagem-viabiliza-completa.json"
VERSAO_ANTERIOR = PASTA / "contagem-viabiliza-completa-v3.0-1571pf.json"
MEDICAO = Path("/tmp/pf-resultado.json")

PAI = {
    "Histórico de tramitação da solicitação": "Solicitação de viabilidade",
    "Histórico de situação da análise": "Solicitação de viabilidade",
    "Queda do fluxo expresso": "Solicitação de viabilidade",
    "Feição geográfica": "Camada geográfica",
    "Camadas geográficas de referência": "Camada geográfica",
    "Divergência entre analista e motor de regras": "Ficha de análise técnica",
    "Envio de e-mail (email_logs, ausente na lista de arquivos lógicos)": "Registro de envio de e-mail",
}

FORA_DO_AR = {
    "Faixa de área por atividade (Quadro 7)",
    "Arquivo de exportação",
    "Classificação de risco sanitário",
    "Condicionante de risco",
    "Comunicação à SEFAZ",
    "Protocolo de simulação do REGIN",
}

TEXTO_SUBGRUPO = {
    "Histórico de tramitação da solicitação": "Solicitação de viabilidade (subgrupo histórico de tramitação)",
    "Histórico de situação da análise": "Solicitação de viabilidade (subgrupo histórico de situação)",
    "Queda do fluxo expresso": "Solicitação de viabilidade (subgrupo queda do fluxo expresso)",
    "Feição geográfica": "Camada geográfica (subgrupo feição)",
    "Divergência entre analista e motor de regras": "Ficha de análise técnica (subgrupo divergência com o motor)",
}

TEXTO_ITEM = {
    "Consulta de viabilidade - consultar por CNAE": [
        ("Aplica risco, Quadro 7 e planilha de enquadramento sem localização",
         "Aplica o risco e a planilha de enquadramento por área, sem localização (a antiga tabela do Quadro 7 "
         "foi retirada em 19/09/2026 e o enquadramento por área está na planilha; o nome do teste é anterior "
         "a essa mudança)"),
    ],
    "CNAEs - alterar": [
        ("realoca Classificação de risco sanitário, Condicionante de risco e Atividade permitida em escritório virtual",
         "atualiza o código em Atividade permitida em escritório virtual e nas tabelas de apoio carregadas por "
         "seeder, que não são arquivo lógico e não entram no AR"),
    ],
    "Escritório virtual - desvincular inscrição": [
        ("registra a comunicação à SEFAZ",
         "registra a pendência de comunicação à SEFAZ (envio bloqueado pela HU-110, fora do AR)"),
    ],
    "Relatórios - baixar exportação": [
        ("Lê Arquivo de exportação",
         "Lê o arquivo gerado pela própria exportação, que é parte desta saída e não arquivo lógico"),
    ],
    "Auditoria - exportar trilha": [
        ("(Arquivo de exportação)", "(arquivo temporário da exportação, não é arquivo lógico)"),
    ],
}

RENOMEAR_GRUPO = {"Consulta de CNPJ na Receita Federal": "Consulta de CNPJ na base de contatos da SEDUR"}
RENOMEAR_ITEM = {
    "Empresas - consultar CNPJ na Receita Federal": "Empresas - consultar CNPJ na base de contatos da SEDUR",
}

ESCOPO = (
    "Escopo da Contagem\n\n"
    "Fronteira: aplicação Viabiliza (Sistema de Licenciamento Eletrônico da SEDUR) completa. Inclui o console de "
    "gestão da SEDUR, o Portal do Cidadão, a consulta prévia pública de viabilidade, a consulta pública de "
    "protocolo, a verificação pública de documentos emitidos, o recebimento de solicitações do REGIN, as rotinas "
    "automáticas de prazos e as integrações percebidas pelo usuário (contatos cadastrais da SEDUR, cadastro "
    "imobiliário da SEFAZ, GeoServer da SEDUR, Active Directory, servidor de e-mail e inteligência artificial).\n\n"
    "Fora da contagem: login gov.br (aguarda credencial, HU-151), envio à SEFAZ (bloqueado, HU-110), migração do "
    "legado, funções técnicas sem efeito para o usuário (teste de conexão, ping) e dados carregados só por seeder.\n\n"
    "Critério de conferência: linhas F1, F2, etc. são grupos funcionais e não pontuam. Cada função contável possui "
    "tipo, TD, AR/TR e, na coluna Observações, a prova (rota, tela, arquivos lidos e gravados, teste automatizado), "
    "a justificativa de processo elementar e os campos e arquivos que formam TD e AR. Todo AR citado é um arquivo "
    "lógico da própria contagem; subgrupos aparecem pelo arquivo pai."
)


def normalizar(nome: str) -> str:
    return nome.replace("Dados externos - ", "").replace("Dados - ", "").strip()


def main() -> None:
    dados = json.loads(ARQUIVO.read_text(encoding="utf-8"))
    if dados["identificacao"].get("versao") != "3.0":
        raise SystemExit("Esperava a versão 3.0.")
    shutil.copyfile(ARQUIVO, VERSAO_ANTERIOR)

    medidas = {m["nome"]: m for m in json.loads(MEDICAO.read_text(encoding="utf-8"))}
    logicos = {normalizar(i["nome"]) for g in dados["grupos"] for i in g["itens"] if i["tipo"] in ("ALI", "AIE")}
    antes = sum(i["pf_local"] for g in dados["grupos"] for i in g["itens"])
    alterados = []

    for grupo in dados["grupos"]:
        for indice, atual in enumerate(grupo["itens"]):
            if atual["tipo"] in ("ALI", "AIE"):
                continue
            medida = dict(medidas[atual["nome"]])
            originais = medida["arquivos"]
            arquivos = []
            for nome in originais:
                if nome in FORA_DO_AR:
                    continue
                nome = PAI.get(nome, nome)
                if normalizar(nome) not in logicos:
                    raise SystemExit(f"{atual['nome']}: AR '{nome}' não é arquivo lógico da contagem.")
                if nome not in arquivos:
                    arquivos.append(nome)
            if arquivos == originais and atual["nome"] not in TEXTO_ITEM:
                continue

            prova = medida["prova"]
            for antigo, novo in TEXTO_SUBGRUPO.items():
                prova = prova.replace(antigo, novo)
            for antigo, novo in TEXTO_ITEM.get(atual["nome"], []):
                if antigo not in prova:
                    raise SystemExit(f"{atual['nome']}: trecho não encontrado: {antigo}")
                prova = prova.replace(antigo, novo)

            medida.update(prova=prova, arquivos=arquivos, ar=len(arquivos))
            tipo, td, ar = medida["tipo"], medida["td"], medida["ar"]
            novo = {
                **atual,
                "complexidade": complexidade(tipo, td, ar),
                "ar": ar,
                "observacoes": observacao(medida),
                "pf_local": calcular_pf(tipo, td, ar),
            }
            alterados.append((atual["nome"], atual["ar"], ar, atual["pf_local"], novo["pf_local"]))
            grupo["itens"][indice] = novo

    for grupo in dados["grupos"]:
        grupo["nome"] = RENOMEAR_GRUPO.get(grupo["nome"], grupo["nome"])
        for atual in grupo["itens"]:
            atual["nome"] = RENOMEAR_ITEM.get(atual["nome"], atual["nome"])

    sobras = [
        (i["nome"], termo)
        for g in dados["grupos"]
        for i in g["itens"]
        for termo in list(PAI) + sorted(FORA_DO_AR) + ["Receita Federal", "SIGVISA", "sanitário"]
        if termo in i["observacoes"] and termo not in ("Comunicação à SEFAZ",)
    ]

    total = sum(i["pf_local"] for g in dados["grupos"] for i in g["itens"])
    dados["termo"]["total_pf"] = total
    dados["identificacao"]["versao"] = "3.1"
    dados["identificacao"]["escopo"] = ESCOPO
    ARQUIVO.write_text(json.dumps(dados, ensure_ascii=False, indent=2), encoding="utf-8")

    for nome, ar_antes, ar_depois, pf_antes, pf_depois in alterados:
        if pf_antes != pf_depois:
            print(f"  {nome}: AR {ar_antes}->{ar_depois}, PF {pf_antes}->{pf_depois}")
    print(f"Transações ajustadas: {len(alterados)} | PF {antes} -> {total}")
    print("Termos remanescentes nas observações:", sobras or "nenhum")


if __name__ == "__main__":
    main()

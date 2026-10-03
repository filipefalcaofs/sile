#!/usr/bin/env python3
"""Conta como funções distintas as abas que mostram ou gravam conjuntos de dados diferentes
(CPM 4.3.1: processos elementares com TD, arquivos ou lógica diferentes são únicos)."""

from __future__ import annotations

import json
import shutil
from collections import Counter
from pathlib import Path

from aplicar_medicao import item

PASTA = Path(__file__).resolve().parent
ARQUIVO = PASTA / "contagem-viabiliza-completa.json"
VERSAO_ANTERIOR = PASTA / "contagem-viabiliza-completa-v3.1-1561pf.json"

TESTE_APIS = "tests/Feature/Gestao/ApiIntegracaoControllerTest.php"
UNICIDADE = (
    "A aba é função distinta das demais abas da tela: tem TD próprios, e a regra de unicidade do CPM 4.3.1 "
    "trata como processos diferentes os que diferem em TD, arquivos ou lógica."
)

ABAS_API = [
    ("REGIN", "regin", ["usar produção", "URL de homologação", "URL de produção", "usuário", "senha", "CNPJ da Prefeitura"],
     "integrations.regin.usuario"),
    ("Inscrição imobiliária", "inscricao", ["consulta habilitada", "usar produção", "URL de homologação", "URL de produção"],
     "integrations.inscricao_imobiliaria.base_url"),
    ("CPF e CNPJ", "sedur_contato", ["consulta de CNPJ habilitada", "usar produção", "URL de homologação", "URL de produção"],
     "integrations.sedur_contato.base_url"),
    ("Autenticação SEDUR", "sedur_auth", ["login AD habilitado", "usar produção", "URL de homologação", "URL de produção"],
     "integrations.sedur_auth.url_homologacao"),
    ("GOV.BR", "govbr", ["login GOV.BR habilitado", "usar produção", "URL de homologação", "URL de produção", "client ID", "client secret", "nível mínimo"],
     "security.govbr.minimum_level"),
    ("Geocodificação", "geocoding", ["geocodificação habilitada", "usar produção", "URL de homologação", "URL de produção"],
     "integrations.geocoding.url_homologacao"),
    ("GeoServer", "geoserver", ["consulta de zona habilitada", "regras territoriais habilitadas", "classificação viária habilitada", "usar produção", "URL de homologação", "URL de produção"],
     "features.geoserver_via_classe"),
]


def medida(nome, tipo, campos, arquivos, prova, justificativa, dependencia=""):
    return {
        "nome": nome,
        "tipo": tipo,
        "td": len(campos),
        "ar": len(arquivos),
        "campos": campos,
        "arquivos": arquivos,
        "prova": prova,
        "processo_elementar": justificativa,
        "dependencia": dependencia,
        "decisao": "manter",
    }


def funcoes_apis() -> list[dict]:
    funcoes = []
    for rotulo, aba, campos, chave in ABAS_API:
        sigilosos = [c for c in campos if c in ("senha", "client ID", "client secret")]
        exibidos = ["integração", "descrição"] + [
            f"{c} (só indica se está cadastrado)" if c in sigilosos else c for c in campos
        ] + ["ajuda do campo", "ação abrir aba"]
        funcoes.append(medida(
            f"APIs - consultar configuração {rotulo}", "CE", exibidos, ["Parâmetro"],
            f"Rota GET /gestao/config-apis?aba={aba} (permissão manter-parametros). Tela: Configuração > APIs, aba "
            f"{rotulo}. O gestor abre a aba e vê os valores em uso"
            + (", com credencial nunca reexibida" if sigilosos else "")
            + f". Lê Parâmetro; não grava. Teste: {TESTE_APIS}::test_administrador_ve_as_abas_sem_credencial_em_claro.",
            "menor unidade com significado para o gestor; completa por si (abre a aba e vê a configuração "
            f"da integração {rotulo}); não grava; lógica própria de apresentação dos campos desta integração. "
            + UNICIDADE,
        ))
        espelha = "" if aba == "regin" else " e espelha a URL ativa conforme o interruptor de produção"
        funcoes.append(medida(
            f"APIs - alterar configuração {rotulo}", "EE", campos + ["mensagem", "ação salvar"],
            ["Parâmetro", "Trilha de auditoria"],
            f"Rota PUT /gestao/config-apis/{aba} (permissão manter-parametros). Tela: Configuração > APIs, aba "
            f"{rotulo}. O gestor altera e salva. Valida os campos da aba, grava os Parâmetro alterados{espelha}"
            + (" (senha em branco mantém a atual)" if sigilosos else "")
            + f" e grava Trilha com valor anterior e novo. Teste: {TESTE_APIS}::"
            f"test_cada_aba_grava_seus_parametros_e_audita (caso \"{rotulo}\", confere {chave} e a trilha).",
            "menor unidade com significado para o gestor; completa por si (salva a aba e a nova configuração "
            f"passa a valer sem deploy); deixa o negócio consistente (valor validado e auditado); lógica própria "
            f"da integração {rotulo}. " + UNICIDADE,
        ))
    return funcoes


def funcoes_motor() -> list[dict]:
    rota = "Rota GET /gestao/louos?aba={aba} ({perm}). Tela: Regras > Motor de regras, aba {rotulo}. {texto}"
    return [
        medida(
            "Motor de regras - consultar quadros da LOUOS", "SE",
            ["aba", "quadro", "busca", "zona", "grupo de uso", "subgrupo", "permissão", "condicionante",
             "base legal", "classe de via", "condições", "versão vigente", "vigência", "total de registros",
             "versão do histórico", "situação da versão", "registros por versão", "ação consultar"],
            ["Versão de regras", "Permissão de uso por zona (Quadro 10)", "Condição de instalação por via (Quadro 11A)"],
            rota.format(aba="quadros", perm="consultar-louos", rotulo="Quadros",
                        texto="O analista escolhe o Quadro 10 ou 11A e vê as linhas vigentes e o histórico de "
                              "versões com total de registros calculado. Lê só os quadros; grava Trilha da consulta. "
                              "Teste: tests/Feature/Louos/LouosConsultaTest.php::test_analista_consulta_quadros_vigentes."),
            "menor unidade com significado para o analista; completa por si (escolhe o quadro e recebe as regras "
            "vigentes); não altera regra; lógica própria (quadro vigente e totais por versão). " + UNICIDADE,
        ),
        medida(
            "Motor de regras - consultar planilha de enquadramento", "SE",
            ["aba", "busca", "versão vigente", "total de CNAEs", "total de enquadramentos", "total de perguntas",
             "total de regras", "total de ramos", "total de condicionantes", "total de vínculos", "pergunta",
             "regra de tratamento", "ramo (pergunta, resposta, faixa, destino, código LOUOS, fluxo, bloqueio)",
             "condicionante", "item CNLU", "nota", "CNAE", "código LOUOS", "subcategoria", "grupo", "risco",
             "faixas de área", "classificação", "código TLL", "pendências do rascunho", "ação consultar"],
            ["Planilha de enquadramento", "Versão de regras"],
            rota.format(aba="enquadramento", perm="consultar-cnaes", rotulo="Planilha de enquadramento",
                        texto="O usuário vê a planilha de tratamento vigente: contagens, perguntas, regras, ramos, "
                              "condicionantes e a lista paginada de enquadramentos por CNAE, mais as pendências do "
                              "rascunho aberto. Não grava regra. Teste: tests/Feature/Tratamento/"
                              "TratamentoRegrasUiTest.php::test_aba_enquadramento_mostra_planilha_vigente."),
            "menor unidade com significado para o gestor de regras; completa por si (abre a aba e vê a planilha em "
            "uso); não altera regra; lógica própria (contagens calculadas da versão vigente e pendências do "
            "rascunho), com permissão própria (consultar-cnaes). " + UNICIDADE,
        ),
        medida(
            "Motor de regras - consultar regras territoriais", "SE",
            ["aba", "versão vigente", "versão exibida", "autor do rascunho", "código", "título", "tipo", "efeito",
             "logradouro", "camada", "filtro da camada", "zona alvo", "motivo de análise", "base legal",
             "texto decisório", "ativa", "CNAEs", "endereços (rótulo, logradouro, número, bairro)",
             "versão do histórico", "situação da versão", "vigência", "regras por versão", "ação consultar"],
            ["Regra territorial", "Versão de regras", "Texto decisório", "Usuário"],
            rota.format(aba="territoriais", perm="consultar-louos", rotulo="Regras territoriais",
                        texto="O usuário vê as regras territoriais da versão vigente ou do rascunho aberto, com "
                              "autor, CNAEs e endereços de cada regra e o histórico de versões com total calculado. "
                              "Não grava regra. Teste: tests/Feature/Louos/TerritorialRuleCrudTest.php::"
                              "test_aba_territoriais_lista_as_regras_da_versao_vigente."),
            "menor unidade com significado para o gestor de regras; completa por si (abre a aba e vê as regras "
            "territoriais em uso ou em edição); não altera regra; lógica própria (escolhe rascunho ou vigente e "
            "totaliza por versão). " + UNICIDADE,
        ),
    ]


def funcoes_processo() -> list[dict]:
    rota = "Rota GET /gestao/processos/{id} (consultar-solicitacoes). Tela: Operação > Processos, detalhe, aba "
    return [
        medida(
            "Processos - consultar informações do processo", "SE",
            ["protocolo", "situação", "situação da análise", "etapa", "empresa", "CNPJ", "serviço",
             "tipo de imóvel", "endereço", "inscrição imobiliária", "setor", "analista", "protocolado em",
             "prazo restante", "categoria", "fluxo", "motivo do encaminhamento", "resultado", "motivo do parecer",
             "fundamentação legal", "nº do TVL", "polígono do imóvel", "ponto do imóvel", "origem da localização",
             "camadas incidentes", "ficha vigente", "próximas situações", "setores para encaminhar",
             "analistas para encaminhar", "ação consultar"],
            ["Solicitação de viabilidade", "Empresa", "Decisão de viabilidade", "Setor", "Usuário",
             "Ficha de análise técnica", "Tipo de serviço"],
            rota + "Informações do processo. O analista abre o processo e vê dados, parecer, mini-mapa, ficha "
                   "vigente e prazo restante calculado. Lê os arquivos listados; grava Trilha de acesso a dado "
                   "pessoal. Teste: tests/Feature/Analise/ProcessoUiSmokeTest.php::"
                   "test_detalhe_renderiza_o_componente_com_identificadores_timeline_e_geometria.",
            "menor unidade com significado para o analista; completa por si (abre o processo e vê o dossiê); "
            "não altera o processo; lógica própria (prazo restante calculado e ações possíveis). " + UNICIDADE,
        ),
        medida(
            "Processos - consultar tramitação", "CE",
            ["protocolo", "situação de origem", "situação de destino", "motivo da transição", "data da transição",
             "responsável", "ação abrir aba"],
            ["Solicitação de viabilidade", "Usuário"],
            rota + "Tramitação. O analista vê a linha do tempo de cada transição do processo, com quem fez e por "
                   "quê. Lê Solicitação de viabilidade (subgrupo histórico de tramitação) e Usuário; não grava. "
                   "Teste: tests/Feature/Analise/ProcessoUiSmokeTest.php::"
                   "test_detalhe_renderiza_o_componente_com_identificadores_timeline_e_geometria.",
            "menor unidade com significado para o analista; completa por si (abre a aba e vê o caminho do "
            "processo); não grava nem calcula; lógica própria de recuperação do histórico. " + UNICIDADE,
        ),
        medida(
            "Processos - consultar explicação da decisão", "SE",
            ["protocolo", "resultado", "passo da explicação", "resultado parcial do passo", "versão da regra",
             "fundamentação legal", "ação abrir aba"],
            ["Decisão de viabilidade", "Solicitação de viabilidade"],
            rota + "Explicabilidade (só aparece quando há decisão). O analista vê, passo a passo, como o motor "
                   "chegou ao resultado: entrada, risco, quadros da LOUOS e regra aplicada, com a versão de cada "
                   "regra. DecisionExplanationService monta a explicação a partir do registro da decisão. Teste: "
                   "tests/Feature/Auditoria/DecisionExplanationTest.php::"
                   "test_show_processo_envia_prop_explicacao_quando_ha_decisao.",
            "menor unidade com significado para o analista e o auditor; completa por si (abre a aba e entende a "
            "decisão); não grava; lógica própria (texto de cada passo derivado do registro da decisão). "
            + UNICIDADE,
        ),
    ]


def main() -> None:
    dados = json.loads(ARQUIVO.read_text(encoding="utf-8"))
    if dados["identificacao"].get("versao") != "3.1":
        raise SystemExit("Esperava a versão 3.1.")
    shutil.copyfile(ARQUIVO, VERSAO_ANTERIOR)
    antes = sum(i["pf_local"] for g in dados["grupos"] for i in g["itens"])

    substituir = {
        "APIs - consultar configuração das integrações": funcoes_apis(),
        "APIs - alterar configuração de integração": [],
        "Motor de regras - consultar quadros": funcoes_motor(),
        "Processos - consultar detalhe": funcoes_processo(),
    }
    retirar = {
        "Integração de inscrição imobiliária - alterar configuração": (
            "Mesma função da aba Inscrição imobiliária da tela de APIs (mesmos parâmetros, mesma lógica). "
            "A tela antiga não está no menu; a função é contada uma vez, pela aba."
        ),
    }

    encontrados = set()
    grupos = []
    for grupo in dados["grupos"]:
        itens = []
        for atual in grupo["itens"]:
            if atual["nome"] in substituir:
                encontrados.add(atual["nome"])
                dados["itens_retirados"].append({
                    "pacote": grupo["nome"], "nome": atual["nome"], "tipo": atual["tipo"],
                    "pf_retirado": atual["pf_local"],
                    "motivo": "Substituída pelas funções de cada aba da tela, contadas uma a uma.",
                })
                itens.extend(item(m) for m in substituir[atual["nome"]])
            elif atual["nome"] in retirar:
                encontrados.add(atual["nome"])
                dados["itens_retirados"].append({
                    "pacote": grupo["nome"], "nome": atual["nome"], "tipo": atual["tipo"],
                    "pf_retirado": atual["pf_local"], "motivo": retirar[atual["nome"]],
                })
            else:
                itens.append(atual)
        if itens:
            grupos.append({**grupo, "itens": itens})

    faltando = (set(substituir) | set(retirar)) - encontrados
    if faltando:
        raise SystemExit(f"Não encontrados: {faltando}")

    for indice, grupo in enumerate(grupos, start=1):
        grupo["id"] = f"F{indice}"

    nomes = [i["nome"] for g in grupos for i in g["itens"]]
    repetidos = [n for n, q in Counter(nomes).items() if q > 1]
    if repetidos:
        raise SystemExit(f"Nomes repetidos: {repetidos}")

    total = sum(i["pf_local"] for g in grupos for i in g["itens"])
    dados["grupos"] = grupos
    dados["termo"]["total_pf"] = total
    dados["identificacao"]["versao"] = "3.2"
    ARQUIVO.write_text(json.dumps(dados, ensure_ascii=False, indent=2), encoding="utf-8")

    for g in grupos:
        for i in g["itens"]:
            if any(i["nome"].startswith(p) for p in ("APIs - ", "Motor de regras - consultar", "Processos - consultar i", "Processos - consultar t", "Processos - consultar e")):
                print(f"  {i['tipo']} TD={i['td']} AR={i['ar']} {i['complexidade']} {i['pf_local']} PF  {i['nome']}")
    print(f"PF {antes} -> {total} | funções: {len(nomes)} | pacotes: {len(grupos)}")


if __name__ == "__main__":
    main()

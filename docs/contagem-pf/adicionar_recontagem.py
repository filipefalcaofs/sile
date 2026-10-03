#!/usr/bin/env python3
"""Inclui na contagem as funções já em produção que ficaram fora dos 1.273 PF."""

from __future__ import annotations

import json
import shutil
import sys
from collections import Counter
from pathlib import Path

sys.path.insert(0, "/Users/filipefalcao/.cursor/skills/contagem-ponto-funcao/scripts")
from ifpug_calc import calcular_pf, complexidade  # noqa: E402

PASTA = Path(__file__).resolve().parent
ARQUIVO = PASTA / "contagem-viabiliza-completa.json"
VERSAO_ANTERIOR = PASTA / "contagem-viabiliza-completa-v2.0-1273pf.json"
MARCA = "Incluído na recontagem de 01/10/2026."
ROTINA = "Processo elementar independente, disparado pelo calendário, com efeito reconhecido pelo usuário."

CAPITULO = {"ALI": "6", "AIE": "6", "EE": "7", "CE": "7", "SE": "7"}

# (pacote, nome, tipo, td, ar, justificativa)
NOVAS = [
    # Vistoria
    ("Vistoria", "Dados - Ficha de vistoria", "ALI", 62, 3,
     "Ficha com dados do imóvel, acessos, entorno, obras, equipamentos, segurança e parecer; subgrupos ficha, anexos e encaminhamento"),
    ("Vistoria", "Vistoria - encaminhar processo para vistoria", "EE", 5, 3,
     "Processo, setor de vistoria e motivo; grava o encaminhamento e muda a etapa da solicitação"),
    ("Vistoria", "Vistoria - consultar ficha", "CE", 60, 4,
     "Ficha, dados do processo, tipos de imóvel e anexos; abrir para preencher e visualizar só leitura têm a mesma lógica e contam uma vez"),
    ("Vistoria", "Vistoria - salvar rascunho da ficha", "EE", 55, 2,
     "Todos os campos da ficha; valida tipo de imóvel ativo e recusa ficha concluída"),
    ("Vistoria", "Vistoria - concluir ficha", "EE", 3, 2,
     "Parecer obrigatório; torna a ficha imutável e devolve o processo à análise"),
    ("Vistoria", "Vistoria - validar polígono do imóvel", "EE", 4, 1,
     "Polígono redesenhado, área calculada e marca de validação"),
    ("Vistoria", "Vistoria - anexar foto ou documento", "EE", 6, 1,
     "Arquivo, nome, tipo, tamanho e código de integridade"),
    ("Vistoria", "Vistoria - baixar anexo", "CE", 2, 1, "Download autenticado do anexo da ficha"),
    ("Vistoria", "Vistoria - remover anexo", "EE", 3, 1, "Remove arquivo e registro enquanto a ficha está em preenchimento"),
    ("Vistoria", "Vistorias - consultar lista", "CE", 10, 3, "Processos encaminhados, situação da ficha, vistoriador e prazos, com filtros"),
    # TLL
    ("Valores da TLL", "Dados - Valor da TLL", "ALI", 10, 1,
     "Código TLL, código SEFAZ, serviço, especificação, taxa, valor, exercício e situação"),
    ("Valores da TLL", "TLL - consultar valores", "CE", 10, 1, "Tabela do exercício com filtros"),
    ("Valores da TLL", "TLL - consultar CNAEs do valor", "CE", 3, 2, "CNAEs ligados ao código da TLL"),
    ("Valores da TLL", "TLL - incluir valor", "EE", 11, 2, "Nove campos do formulário; valida o exercício na versão de regras"),
    ("Valores da TLL", "TLL - alterar valor", "EE", 11, 2, "Mesmos campos da inclusão"),
    ("Valores da TLL", "TLL - ativar ou inativar valor", "EE", 3, 1, "Muda a situação do valor"),
    ("Valores da TLL", "TLL - criar exercício por fator de reajuste", "EE", 6, 2,
     "Exercício de origem, exercício de destino, fator e decreto; copia a tabela reajustada"),
    ("Valores da TLL", "TLL - publicar exercício", "EE", 3, 2, "Torna vigente a tabela do exercício"),
    # Vias
    ("Vias", "Dados - Via", "ALI", 3, 1, "Código, nome e situação do logradouro na classificação viária"),
    ("Vias", "Vias - consultar", "CE", 3, 1, "Lista de vias cadastradas"),
    ("Vias", "Vias - incluir", "EE", 5, 1, "Código, nome e situação"),
    ("Vias", "Vias - alterar", "EE", 4, 1, "Nome e situação"),
    ("Vias", "Vias - ativar ou inativar", "EE", 3, 1, "Muda a situação da via"),
    # Regras territoriais
    ("Regras territoriais", "Dados - Regra territorial", "ALI", 20, 3,
     "Código, título, camada, filtro, zona alvo, base legal, motivo de análise e texto decisório; subgrupos endereços e CNAEs"),
    ("Regras territoriais", "Regras territoriais - salvar rascunho", "EE", 16, 4,
     "Catorze campos com endereços e CNAEs; valida zona, texto decisório e CNAE"),
    ("Regras territoriais", "Regras territoriais - publicar versão", "EE", 3, 2, "Publica o rascunho como nova versão de regras"),
    ("Regras territoriais", "Regras territoriais - ativar versão", "EE", 3, 2, "Torna vigente uma versão publicada"),
    ("Regras territoriais", "Regras territoriais - descartar rascunho", "EE", 3, 2, "Descarta o rascunho sem afetar a versão vigente"),
    # Planilha de enquadramento
    ("Planilha de enquadramento", "Dados - Planilha de enquadramento", "ALI", 50, 9,
     "Enquadramentos, regras, ramos, perguntas, condicionantes, notas, pendências, itens CNLU e vínculos de CNAE"),
    ("Planilha de enquadramento", "Enquadramento - importar planilha", "EE", 50, 2,
     "Arquivo da planilha com todos os campos dos nove subgrupos; grava como rascunho de versão"),
    ("Planilha de enquadramento", "Enquadramento - publicar versão", "EE", 3, 2, "Publica a versão importada"),
    # Textos decisórios
    ("Textos decisórios", "Dados - Texto decisório", "ALI", 3, 1, "Chave, descrição e modelo do texto"),
    ("Textos decisórios", "Textos decisórios - consultar", "CE", 4, 1, "Lista de textos com descrição e modelo"),
    ("Textos decisórios", "Textos decisórios - alterar", "EE", 4, 1, "Descrição e modelo"),
    # Renovação
    ("Renovação do TVL", "Dados - Aceite de declaração de serviço", "ALI", 5, 1,
     "Declaração aceita, solicitação, usuário e data do aceite"),
    ("Renovação do TVL", "Renovação - consultar TVLs renováveis", "CE", 8, 2, "TVLs provisórios do requerente com validade"),
    ("Renovação do TVL", "Renovação - localizar TVL por CNPJ", "CE", 3, 2, "Busca o TVL de origem a partir do CNPJ"),
    ("Renovação do TVL", "Renovação - consultar dados da renovação", "CE", 10, 3,
     "Dados do TVL de origem, validade e declarações exigidas pelo tipo de serviço"),
    ("Renovação do TVL", "Renovação - protocolar renovação", "EE", 6, 4,
     "Declarações aceitas, e-mail, celular e telefone; cria a solicitação de renovação"),
    # Log de integrações
    ("Log de integrações", "Dados - Chamada de integração", "ALI", 16, 1,
     "Integração, operação, endereço, situação, tempo de resposta, requisição e resposta resumidas"),
    ("Log de integrações", "Integrações - consultar chamadas", "CE", 8, 1, "Lista com filtros por integração, situação e período"),
    ("Log de integrações", "Integrações - consultar detalhe da chamada", "CE", 16, 1, "Todos os campos da chamada"),
    # Configuração de APIs
    ("Configuração das APIs", "APIs - consultar configuração das integrações", "CE", 6, 1,
     "REGIN, inscrição imobiliária, CPF e CNPJ, autenticação SEDUR, gov.br, geocodificação e GeoServer; senhas nunca reexibidas"),
    ("Configuração das APIs", "APIs - alterar configuração de integração", "EE", 8, 1,
     "URLs de homologação e produção, credenciais e interruptor de ambiente"),
    # Rotinas automáticas
    ("Rotinas automáticas de prazos e controle", "Prazos - alertar vencimento de análise e de pendência", "SE", 5, 2,
     f"Aviso ao analista e ao requerente. {ROTINA}"),
    ("Rotinas automáticas de prazos e controle", "SLA - escalonar processos em atraso", "SE", 5, 3,
     f"Amarelo ao analista, vencido ao gestor. {ROTINA}"),
    ("Rotinas automáticas de prazos e controle", "Pendências - expirar convites vencidos e indeferir", "EE", 3, 3,
     f"Expira o convite sem resposta e indefere o processo. {ROTINA}"),
    ("Rotinas automáticas de prazos e controle", "Abuso - detectar padrões de abuso", "EE", 3, 3,
     f"Gera alerta e encaminha à malha fina. {ROTINA}"),
    ("Rotinas automáticas de prazos e controle", "Auditoria preditiva - varrer deferimentos do expresso", "EE", 3, 3,
     f"Gera anomalia para revisão humana. {ROTINA}"),
    ("Rotinas automáticas de prazos e controle", "TLL - alertar falta de tabela do exercício", "SE", 3, 1,
     f"Aviso aos gestores em dezembro e janeiro. {ROTINA}"),
    # Pacotes existentes
    ("Caixa do setor", "Caixa do setor - consultar central de distribuição", "CE", 8, 3,
     "Processos selecionados, carga de cada analista e histórico de distribuições"),
    ("Caixa do setor", "Caixa do setor - sugerir distribuição pela carga", "SE", 8, 2,
     "Calcula alocação equilibrada pela carga ativa e aptidão para vistoria"),
    ("Caixa do setor", "Caixa do setor - distribuir em lote", "EE", 6, 3, "Alocações revisadas, proposta e alterações"),
    ("Caixa do setor", "Caixa do setor - redistribuir processo", "EE", 4, 3, "Novo analista e processos"),
    ("Encaminhamento à malha fina", "Malha fina - consultar caixa", "CE", 10, 2, "Processos encaminhados com motivo e setor"),
    ("Encaminhamento à malha fina", "Malha fina - concluir revisão", "EE", 4, 2, "Resultado e observação"),
    ("Ficha de análise técnica", "Análise - concluir com deferimento, indeferimento, desativação, cassação ou revogação", "EE", 4, 4,
     "Parecer e resultado; grava decisão e muda a situação da solicitação"),
    ("Ficha de análise técnica", "Ficha de análise - sugerir justificativa", "EE", 4, 2, "Usuário aciona explicitamente; sugestão fica registrada para revisão"),
    ("Parâmetros do sistema", "Parâmetros - aprovar alteração proposta", "EE", 3, 2, "Aplica o valor proposto e grava histórico"),
    ("Parâmetros do sistema", "Parâmetros - rejeitar alteração proposta", "EE", 4, 2, "Rejeita a proposta com motivo"),
    ("Usuários", "Usuários - alterar forma de autenticação", "EE", 4, 1, "Tipo de autenticação e usuário de rede"),
    ("Perfil do usuário", "Perfil do usuário - consultar dados pessoais", "CE", 4, 1, "Nome, e-mail, telefone e CPF mascarado"),
    ("Dados externos e de referência", "Dados externos - Contato cadastral da SEDUR (CPF)", "AIE", 3, 1,
     "Nome vinculado ao CPF na base de contatos da SEDUR"),
    ("Dados externos e de referência", "Dados externos - Cadastro imobiliário da SEFAZ", "AIE", 12, 1,
     "Inscrição, endereço e dados do imóvel"),
    ("Dados externos e de referência", "Dados externos - Contato cadastral da SEDUR (CNPJ)", "AIE", 15, 1,
     "Razão social, situação, endereço e CNAEs da empresa na base de contatos da SEDUR"),
    ("Atendimento presencial", "Atendimento - consultar CPF na base da SEDUR", "CE", 3, 2,
     "Mesma lógica no atendimento e na contingência, conta uma vez"),
    ("Registro em contingência", "Contingência - consultar inscrição imobiliária", "CE", 8, 2,
     "Localiza o imóvel no cadastro da SEFAZ e no mapa"),
    ("Dados de negócio complementares", "Dados - Notificação ao usuário", "ALI", 6, 1,
     "Tipo, título, mensagem, destino, lida e data"),
    ("Dados de negócio complementares", "Dados - Registro de acesso", "ALI", 7, 1,
     "Usuário, data, origem, navegador e resultado do acesso"),
]


def item(nome: str, tipo: str, td: int, ar: int, justificativa: str) -> dict:
    rotulo = "TR" if tipo in ("ALI", "AIE") else "AR"
    return {
        "nome": nome,
        "tipo": tipo,
        "complexidade": complexidade(tipo, td, ar),
        "td": td,
        "ar": ar,
        "observacoes": f"{justificativa}. TD={td}, {rotulo}={ar}. {MARCA}",
        "citacao_cpm": f"Parte 2 Cap {CAPITULO[tipo]}.",
        "pf_local": calcular_pf(tipo, td, ar),
    }


def main() -> None:
    dados = json.loads(ARQUIVO.read_text(encoding="utf-8"))
    if any(MARCA in i.get("observacoes", "") for g in dados["grupos"] for i in g["itens"]):
        raise SystemExit("Recontagem já aplicada.")

    if not VERSAO_ANTERIOR.exists():
        shutil.copyfile(ARQUIVO, VERSAO_ANTERIOR)

    antes = sum(i["pf_local"] for g in dados["grupos"] for i in g["itens"])
    por_nome = {g["nome"]: g for g in dados["grupos"]}
    complementares = dados["grupos"].pop()

    for pacote, nome, tipo, td, ar, justificativa in NOVAS:
        grupo = por_nome.get(pacote)
        if grupo is None:
            grupo = {"id": "", "nome": pacote, "itens": []}
            dados["grupos"].append(grupo)
            por_nome[pacote] = grupo
        grupo["itens"].append(item(nome, tipo, td, ar, justificativa))

    dados["grupos"].append(complementares)
    for indice, grupo in enumerate(dados["grupos"], start=1):
        grupo["id"] = f"F{indice}"

    nomes = [i["nome"] for g in dados["grupos"] for i in g["itens"]]
    repetidos = [n for n, q in Counter(nomes).items() if q > 1]
    if repetidos:
        raise SystemExit(f"Nomes repetidos: {repetidos}")

    total = sum(calcular_pf(i["tipo"], i["td"], i["ar"]) for g in dados["grupos"] for i in g["itens"])
    dados["termo"]["total_pf"] = total
    dados["identificacao"]["versao"] = "2.1"

    ARQUIVO.write_text(json.dumps(dados, ensure_ascii=False, indent=2), encoding="utf-8")

    acrescimo = Counter()
    for _, _, tipo, td, ar, _ in NOVAS:
        acrescimo[tipo] += calcular_pf(tipo, td, ar)
    print(f"Antes: {antes} PF | acrescentados: {sum(acrescimo.values())} PF {dict(acrescimo)} | total: {total} PF")
    print(f"Pacotes: {len(dados['grupos'])}, funções: {len(nomes)}")


if __name__ == "__main__":
    main()

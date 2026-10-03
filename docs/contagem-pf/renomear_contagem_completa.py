#!/usr/bin/env python3
"""Troca nomes técnicos da contagem de 1.273 PF por nomes de negócio, sem alterar tipo, TD ou AR."""

from __future__ import annotations

import json
import re
import sys
from collections import Counter
from pathlib import Path

sys.path.insert(0, "/Users/filipefalcao/.cursor/skills/contagem-ponto-funcao/scripts")
from ifpug_calc import calcular_pf  # noqa: E402

ARQUIVO = Path(__file__).resolve().parent / "contagem-viabiliza-completa.json"

PACOTES = {
    "G_AIE": "Dados externos e de referência",
    "G_OUTROS": "Dados de negócio complementares",
    "VerificacaoDocumento": "Verificação pública de documentos",
    "NotificationCenter": "Central de notificações",
    "ComunicacaoHistorico": "Histórico de comunicações do processo",
    "Profile": "Perfil do usuário",
    "Password": "Senha do usuário",
    "SolicitacaoImovel": "Solicitação - dados do imóvel",
    "CompanyCnae": "Empresas - atividades econômicas",
    "ConsultaViabilidade": "Consulta prévia de viabilidade",
    "ConsultaProtocolo": "Acompanhamento da solicitação no portal",
    "Company": "Empresas",
    "AccessHistory": "Histórico de acessos",
    "HistoricoConsulta": "Histórico de consultas de viabilidade",
    "Dashboard": "Painel inicial",
    "SolicitacaoSimulacao": "Simulação da solicitação",
    "Protocolo": "Protocolo da solicitação",
    "CnaeSearch": "Pesquisa de CNAE",
    "CancelamentoSolicitacao": "Cancelamento da solicitação",
    "CompanyLink": "Vínculo do usuário com a empresa",
    "SolicitacaoAtividade": "Solicitação - atividades",
    "LgpdTerm": "Termo de consentimento LGPD no portal",
    "ConsultaProtocoloPublica": "Consulta pública de protocolo",
    "Procuration": "Procurações",
    "SolicitacaoDocumento": "Solicitação - documentos anexos",
    "Representation": "Representação de terceiros",
    "Solicitacao": "Solicitações de viabilidade no portal",
    "CnpjLookup": "Consulta de CNPJ na Receita Federal",
    "ComprovanteProtocolo": "Comprovante de protocolo",
    "GovBrLogin": "Acesso pelo gov.br",
    "PendenciaResposta": "Resposta a pendências no portal",
    "ReginRecebe": "Recebimento de solicitações do REGIN",
    "Exportacao": "Exportação de relatórios",
    "DocumentRequirement": "Requisitos documentais",
    "ProcessoPendencia": "Pendências do processo",
    "Precedente": "Precedentes de análise",
    "Holiday": "Feriados",
    "ForgotPassword": "Recuperação de senha",
    "Processo": "Consulta de processos",
    "GeoServerLayer": "Camadas do GeoServer",
    "CondicionanteAutocomplete": "Pesquisa de condicionantes",
    "Cnae": "CNAEs",
    "Sector": "Setores",
    "Login": "Acesso à gestão",
    "Contingencia": "Registro em contingência",
    "LouosSandbox": "Simulação de regras da LOUOS",
    "ReginIntegration": "Configuração da integração REGIN",
    "CaixaSetor": "Caixa do setor",
    "Abuso": "Alertas de abuso",
    "Role": "Perfis de acesso",
    "ProcessoDecisao": "Decisão técnica do processo",
    "UserManagement": "Usuários",
    "LouosDraft": "Rascunho dos quadros da LOUOS",
    "Parameter": "Parâmetros do sistema",
    "EscritorioVirtualDesvinculacao": "Desvinculação de escritório virtual",
    "ResultadoExpresso": "Resultados do fluxo expresso",
    "PropertyType": "Tipos de imóvel",
    "ResetPassword": "Redefinição de senha",
    "Geocode": "Localização de endereço no mapa",
    "MalhaFina": "Encaminhamento à malha fina",
    "EmailLog": "Monitoramento de e-mails",
    "GeoLayer": "Camadas geográficas",
    "TvlDocument": "Termo de Viabilidade de Localização (TVL)",
    "Louos": "Motor de regras da LOUOS",
    "EscritorioVirtualAnexos": "Anexos de escritório virtual",
    "AssistedAttendance": "Atendimento presencial",
    "ProcessoBusca": "Busca rápida de processos",
    "RiskTrigger": "Gatilhos de risco",
    "AuditoriaPreditiva": "Auditoria preditiva",
    "InscricaoImobiliariaIntegration": "Configuração da integração de inscrição imobiliária",
    "Relatorio": "Relatórios gerenciais",
    "AnalysisRecord": "Ficha de análise técnica",
    "AiConfiguration": "Configuração de inteligência artificial",
    "IndeferimentoDocument": "Documento de indeferimento",
    "ReginProtocoloSimulacao": "Simulação de protocolo REGIN",
    "ViabilityServiceType": "Tipos de serviço",
    "EnviarParaAnalise": "Envio de processo para análise",
    "LegalTerm": "Termos legais",
    "Territory": "Consulta territorial",
    "StandardText": "Minutas e textos-padrão",
    "Zona": "Zonas urbanísticas",
    "Auditoria": "Trilha de auditoria",
    "LgpdMonitor": "Monitoramento LGPD",
    "TermoLgpd": "Termo de consentimento LGPD na gestão",
    "EmailServer": "Servidores de e-mail",
}

PREFIXO_CADASTRO = {
    "Company": "Empresas",
    "DocumentRequirement": "Requisitos documentais",
    "Holiday": "Feriados",
    "GeoServerLayer": "Camadas do GeoServer",
    "Cnae": "CNAEs",
    "Sector": "Setores",
    "Role": "Perfis de acesso",
    "PropertyType": "Tipos de imóvel",
    "GeoLayer": "Camadas geográficas",
    "RiskTrigger": "Gatilhos de risco",
    "AiConfiguration": "Provedores de IA",
    "ViabilityServiceType": "Tipos de serviço",
    "LegalTerm": "Termos legais",
    "StandardText": "Textos-padrão",
    "Zona": "Zonas urbanísticas",
    "EmailServer": "Servidores de e-mail",
    "Parameter": "Parâmetros",
}

VERBO_CADASTRO = {
    "index": "consultar",
    "show": "consultar detalhe",
    "store": "incluir",
    "update": "alterar",
    "destroy": "excluir",
    "toggleActivation": "ativar ou inativar",
}

FUNCOES = {
    ("VerificacaoDocumento", "show"): "Documentos oficiais - verificar autenticidade",
    ("NotificationCenter", "index"): "Notificações - consultar",
    ("NotificationCenter", "markAsRead"): "Notificações - marcar como lida",
    ("NotificationCenter", "markAllAsRead"): "Notificações - marcar todas como lidas",
    ("ComunicacaoHistorico", "portal"): "Comunicações - consultar histórico no portal",
    ("ComunicacaoHistorico", "gestao"): "Comunicações - consultar histórico na gestão",
    ("Profile", "update"): "Perfil do usuário - alterar dados pessoais",
    ("Password", "show"): "Senha - abrir alteração de senha",
    ("Password", "update"): "Senha - alterar senha",
    ("SolicitacaoImovel", "update"): "Solicitação - informar dados do imóvel",
    ("CompanyCnae", "updatePrimary"): "Empresas - definir CNAE principal",
    ("CompanyCnae", "updateSecondaries"): "Empresas - definir CNAEs secundários",
    ("ConsultaViabilidade", "index"): "Consulta de viabilidade - abrir consulta",
    ("ConsultaViabilidade", "endereco"): "Consulta de viabilidade - consultar por endereço",
    ("ConsultaViabilidade", "cnae"): "Consulta de viabilidade - consultar por CNAE",
    ("ConsultaViabilidade", "inscricao"): "Consulta de viabilidade - consultar por inscrição imobiliária",
    ("ConsultaProtocolo", "show"): "Solicitações - consultar andamento no portal",
    ("AccessHistory", "__invoke"): "Histórico de acessos - consultar",
    ("HistoricoConsulta", "__invoke"): "Consultas de viabilidade - consultar histórico",
    ("Dashboard", "__invoke"): "Painel - consultar indicadores iniciais",
    ("SolicitacaoSimulacao", "store"): "Solicitação - simular viabilidade",
    ("Protocolo", "store"): "Solicitação - protocolar",
    ("CnaeSearch", "__invoke"): "CNAEs - pesquisar atividade",
    ("CancelamentoSolicitacao", "destroy"): "Solicitação - cancelar",
    ("CompanyLink", "destroy"): "Empresas - encerrar vínculo do usuário",
    ("SolicitacaoAtividade", "update"): "Solicitação - informar atividades",
    ("LgpdTerm", "show"): "Termo LGPD - consultar no portal",
    ("LgpdTerm", "accept"): "Termo LGPD - aceitar no portal",
    ("ConsultaProtocoloPublica", "show"): "Protocolo - consultar publicamente",
    ("Procuration", "index"): "Procurações - consultar",
    ("Procuration", "store"): "Procurações - incluir",
    ("Procuration", "destroy"): "Procurações - revogar",
    ("SolicitacaoDocumento", "store"): "Solicitação - anexar documento",
    ("SolicitacaoDocumento", "download"): "Solicitação - baixar documento anexo",
    ("SolicitacaoDocumento", "destroy"): "Solicitação - remover documento anexo",
    ("Representation", "store"): "Representação - iniciar atuação em nome de terceiro",
    ("Representation", "destroy"): "Representação - encerrar atuação em nome de terceiro",
    ("Solicitacao", "index"): "Solicitações - consultar minhas solicitações",
    ("Solicitacao", "store"): "Solicitações - incluir",
    ("CnpjLookup", "__invoke"): "Empresas - consultar CNPJ na Receita Federal",
    ("ComprovanteProtocolo", "show"): "Protocolo - emitir comprovante",
    ("GovBrLogin", "redirect"): "Acesso gov.br - iniciar autenticação",
    ("GovBrLogin", "callback"): "Acesso gov.br - concluir autenticação",
    ("PendenciaResposta", "show"): "Pendências - consultar no portal",
    ("PendenciaResposta", "responder"): "Pendências - responder",
    ("ReginRecebe", "ping"): "REGIN - verificar disponibilidade do serviço",
    ("ReginRecebe", "store"): "REGIN - receber solicitação",
    ("Exportacao", "download"): "Relatórios - baixar exportação",
    ("DocumentRequirement", "toggle"): "Requisitos documentais - ativar ou inativar",
    ("DocumentRequirement", "syncCnaes"): "Requisitos documentais - vincular CNAEs",
    ("ProcessoPendencia", "store"): "Pendências - abrir pendência no processo",
    ("ProcessoPendencia", "cancelar"): "Pendências - cancelar pendência",
    ("Precedente", "show"): "Análise - consultar precedentes",
    ("ForgotPassword", "show"): "Recuperação de senha - abrir solicitação",
    ("ForgotPassword", "store"): "Recuperação de senha - solicitar",
    ("Processo", "index"): "Processos - consultar lista",
    ("Processo", "fila"): "Processos - consultar caixa de entrada do analista",
    ("Processo", "show"): "Processos - consultar detalhe",
    ("Processo", "atualizarStatusAnalise"): "Processos - alterar situação da análise",
    ("CondicionanteAutocomplete", "__invoke"): "Condicionantes - pesquisar",
    ("Cnae", "updateSituacao"): "CNAEs - alterar situação",
    ("Cnae", "storeCondicionante"): "CNAEs - incluir condicionante",
    ("Cnae", "updateCondicionante"): "CNAEs - alterar condicionante",
    ("Cnae", "destroyCondicionante"): "CNAEs - excluir condicionante",
    ("Sector", "syncAnalysts"): "Setores - vincular analistas",
    ("Login", "store"): "Gestão - entrar no sistema",
    ("Login", "destroy"): "Gestão - sair do sistema",
    ("Contingencia", "store"): "Contingência - registrar solicitação",
    ("LouosSandbox", "index"): "Simulação de regras - abrir simulação",
    ("LouosSandbox", "simulate"): "Simulação de regras - simular aplicação",
    ("LouosSandbox", "publish"): "Simulação de regras - publicar versão simulada",
    ("ReginIntegration", "update"): "Integração REGIN - alterar configuração",
    ("ReginIntegration", "test"): "Integração REGIN - testar conexão",
    ("CaixaSetor", "index"): "Caixa do setor - consultar",
    ("CaixaSetor", "distribuir"): "Caixa do setor - distribuir processo",
    ("CaixaSetor", "assumir"): "Caixa do setor - assumir processo",
    ("Abuso", "index"): "Alertas de abuso - consultar",
    ("Abuso", "confirmar"): "Alertas de abuso - confirmar alerta",
    ("Abuso", "descartar"): "Alertas de abuso - descartar alerta",
    ("ProcessoDecisao", "__invoke"): "Análise - registrar decisão técnica",
    ("UserManagement", "index"): "Usuários - consultar",
    ("UserManagement", "toggleActivation"): "Usuários - ativar ou inativar",
    ("UserManagement", "updateRole"): "Usuários - alterar perfil de acesso",
    ("LouosDraft", "show"): "Quadros LOUOS - consultar rascunho",
    ("LouosDraft", "manual"): "Quadros LOUOS - consultar manual de preenchimento",
    ("LouosDraft", "open"): "Quadros LOUOS - abrir rascunho",
    ("LouosDraft", "storeLinha"): "Quadros LOUOS - incluir linha",
    ("LouosDraft", "updateLinha"): "Quadros LOUOS - alterar linha",
    ("LouosDraft", "destroyLinha"): "Quadros LOUOS - excluir linha",
    ("LouosDraft", "importar"): "Quadros LOUOS - importar planilha",
    ("LouosDraft", "publish"): "Quadros LOUOS - publicar rascunho",
    ("LouosDraft", "discard"): "Quadros LOUOS - descartar rascunho",
    ("LouosDraft", "modeloCsv"): "Quadros LOUOS - baixar modelo de planilha",
    ("Parameter", "history"): "Parâmetros - consultar histórico",
    ("EscritorioVirtualDesvinculacao", "__invoke"): "Escritório virtual - desvincular inscrição",
    ("ResultadoExpresso", "index"): "Resultados do expresso - consultar lista",
    ("ResultadoExpresso", "show"): "Resultados do expresso - consultar detalhe",
    ("ResetPassword", "show"): "Redefinição de senha - abrir redefinição",
    ("ResetPassword", "store"): "Redefinição de senha - redefinir",
    ("Geocode", "__invoke"): "Território - localizar endereço no mapa",
    ("MalhaFina", "store"): "Malha fina - encaminhar processos",
    ("EmailLog", "index"): "E-mails - consultar envios",
    ("TvlDocument", "store"): "TVL - emitir termo",
    ("TvlDocument", "download"): "TVL - baixar termo emitido",
    ("Louos", "index"): "Motor de regras - consultar quadros",
    ("Louos", "publish"): "Motor de regras - publicar nova versão",
    ("Louos", "activate"): "Motor de regras - ativar versão",
    ("EscritorioVirtualAnexos", "index"): "Anexos de escritório virtual - consultar",
    ("EscritorioVirtualAnexos", "store"): "Anexos de escritório virtual - carregar rascunho",
    ("EscritorioVirtualAnexos", "publicar"): "Anexos de escritório virtual - publicar",
    ("AssistedAttendance", "index"): "Atendimento presencial - consultar",
    ("AssistedAttendance", "store"): "Atendimento presencial - abrir atendimento",
    ("AssistedAttendance", "storeSolicitacao"): "Atendimento presencial - abrir solicitação para o cidadão",
    ("AssistedAttendance", "destroy"): "Atendimento presencial - encerrar atendimento",
    ("ProcessoBusca", "__invoke"): "Processos - busca rápida",
    ("AuditoriaPreditiva", "index"): "Auditoria preditiva - consultar anomalias",
    ("AuditoriaPreditiva", "confirmar"): "Auditoria preditiva - confirmar anomalia",
    ("AuditoriaPreditiva", "descartar"): "Auditoria preditiva - descartar anomalia",
    ("InscricaoImobiliariaIntegration", "update"): "Integração de inscrição imobiliária - alterar configuração",
    ("InscricaoImobiliariaIntegration", "test"): "Integração de inscrição imobiliária - testar conexão",
    ("Relatorio", "indicadores"): "Relatórios - consultar indicadores de viabilidade",
    ("Relatorio", "tempo"): "Relatórios - consultar tempo de análise",
    ("Relatorio", "produtividade"): "Relatórios - consultar produtividade",
    ("Relatorio", "quedas"): "Relatórios - consultar quedas do expresso",
    ("Relatorio", "geoBairro"): "Relatórios - consultar painel por bairro",
    ("Relatorio", "saturacao"): "Relatórios - consultar saturação locacional",
    ("Relatorio", "escritorioVirtual"): "Relatórios - consultar escritório virtual (sede e abrigados)",
    ("Relatorio", "tempoEmissaoTvl"): "Relatórios - consultar tempo de emissão de TVL",
    ("Relatorio", "slaVencimentos"): "Relatórios - consultar SLA e vencimentos",
    ("Relatorio", "pendencias"): "Relatórios - consultar pendências e exigências",
    ("Relatorio", "contingencia"): "Relatórios - consultar atendimentos em contingência",
    ("Relatorio", "comunicacoesFalhas"): "Relatórios - consultar falhas de comunicação",
    ("Relatorio", "trilha"): "Relatórios - consultar trilha por processo",
    ("Relatorio", "trilhaImprimir"): "Relatórios - imprimir trilha por processo",
    ("AnalysisRecord", "show"): "Ficha de análise - consultar",
    ("AnalysisRecord", "autosave"): "Ficha de análise - salvar rascunho",
    ("AnalysisRecord", "finalizar"): "Ficha de análise - finalizar",
    ("AnalysisRecord", "concluirProcesso"): "Ficha de análise - concluir processo",
    ("AnalysisRecord", "novaRevisao"): "Ficha de análise - abrir nova revisão",
    ("AnalysisRecord", "sugerirParecer"): "Ficha de análise - sugerir minuta de parecer",
    ("AnalysisRecord", "gerarResumo"): "Ficha de análise - obter resumo do processo",
    ("AnalysisRecord", "diff"): "Ficha de análise - comparar revisões",
    ("AiConfiguration", "test"): "Provedores de IA - testar conexão",
    ("IndeferimentoDocument", "store"): "Indeferimento - emitir documento",
    ("IndeferimentoDocument", "download"): "Indeferimento - baixar documento emitido",
    ("ReginProtocoloSimulacao", "index"): "Simulação REGIN - consultar",
    ("ReginProtocoloSimulacao", "simulate"): "Simulação REGIN - simular protocolo",
    ("ReginProtocoloSimulacao", "destroy"): "Simulação REGIN - excluir simulação",
    ("EnviarParaAnalise", "index"): "Envio para análise - abrir",
    ("EnviarParaAnalise", "pesquisar"): "Envio para análise - pesquisar processo",
    ("EnviarParaAnalise", "enviar"): "Envio para análise - enviar processo",
    ("LegalTerm", "publicar"): "Termos legais - publicar",
    ("Territory", "index"): "Território - consultar mapa",
    ("Territory", "identify"): "Território - identificar imóvel",
    ("Territory", "validateLocation"): "Território - validar localização",
    ("Auditoria", "index"): "Auditoria - consultar trilha",
    ("Auditoria", "export"): "Auditoria - exportar trilha",
    ("LgpdMonitor", "index"): "LGPD - consultar painel de monitoramento",
    ("TermoLgpd", "show"): "Termo LGPD - consultar na gestão",
    ("TermoLgpd", "accept"): "Termo LGPD - aceitar na gestão",
    ("EmailServer", "test"): "Servidores de e-mail - testar conexão",
}

DADOS = {
    "Company": "Empresa",
    "Procuration": "Procuração",
    "DocumentRequirement": "Requisito documental",
    "Holiday": "Feriado",
    "GeoServerLayer": "Camada do GeoServer",
    "Cnae": "CNAE",
    "Sector": "Setor",
    "Parameter": "Parâmetro",
    "PropertyType": "Tipo de imóvel",
    "GeoLayer": "Camada geográfica",
    "TvlDocument": "Termo de Viabilidade de Localização emitido",
    "AssistedAttendance": "Atendimento presencial",
    "RiskTrigger": "Gatilho de risco",
    "AnalysisRecord": "Ficha de análise técnica",
    "AiConfiguration": "Provedor de inteligência artificial",
    "IndeferimentoDocument": "Documento de indeferimento emitido",
    "ViabilityServiceType": "Tipo de serviço",
    "LegalTerm": "Termo legal",
    "StandardText": "Texto-padrão",
    "Zona": "Zona urbanística",
    "EmailServer": "Servidor de e-mail",
    "GovBrAccount": "Conta gov.br do usuário",
    "FineMeshReferral": "Encaminhamento à malha fina",
    "RiskClassification": "Classificação de risco municipal",
    "LouosQuadro10Permissao": "Permissão de uso por zona (Quadro 10)",
    "LouosQuadro7Faixa": "Faixa de área por atividade (Quadro 7)",
    "AnalysisDivergence": "Divergência entre analista e motor de regras",
    "ReginSimulacaoExecucao": "Execução de simulação REGIN",
    "CompanyUser": "Vínculo de usuário com empresa",
    "ExportFile": "Arquivo de exportação",
    "PredictiveAnomaly": "Anomalia preditiva",
    "AnalysisStatusTransition": "Histórico de situação da análise",
    "LegalTermAcceptance": "Aceite de termo legal",
    "LouosQuadro11CondicaoVia": "Condição de instalação por via (Quadro 11A)",
    "AiPrecedentEmbedding": "Índice de precedentes",
    "AbuseAlert": "Alerta de abuso",
    "RiskCondicionante": "Condicionante de risco",
    "User": "Usuário",
    "SanitaryRiskClassification": "Classificação de risco sanitário",
    "ViabilityRequest": "Solicitação de viabilidade",
    "ViabilityRequestDocument": "Documento anexado à solicitação",
    "PropertyTypeAlias": "Denominação alternativa de tipo de imóvel",
    "ViabilityQuery": "Consulta prévia de viabilidade realizada",
    "TvlSequence": "Numeração de TVL",
    "Communication": "Comunicação ao requerente",
    "SefazNotification": "Comunicação à SEFAZ",
    "ViabilityRequestTransition": "Histórico de tramitação da solicitação",
    "RuleVersion": "Versão de regras",
    "Activity": "Trilha de auditoria",
    "ViabilityDecision": "Decisão de viabilidade",
    "GeoFeature": "Feição geográfica",
    "ExpressoQueda": "Queda do fluxo expresso",
    "ProtocolSequence": "Numeração de protocolo",
    "VirtualOfficeInscriptionLock": "Bloqueio de inscrição de escritório virtual",
    "AnalysisPendency": "Pendência da análise",
    "AiSuggestion": "Sugestão de texto por IA",
    "ReginRecebimento": "Recebimento do REGIN",
    "VirtualOfficeActivityCnae": "Atividade permitida em escritório virtual",
}

DADOS_EXTERNOS = {
    "LouosEnquadramentoService": "Regras de enquadramento da LOUOS",
    "LouosMaintenanceService": "Manutenção dos quadros da LOUOS",
    "LouosQuadro10ImportService": "Carga oficial do Quadro 10",
    "LouosDraftService": "Rascunho dos quadros da LOUOS",
    "LouosQuadro7ImportService": "Carga oficial do Quadro 7",
    "LouosQuadro11ImportService": "Carga oficial do Quadro 11",
    "LouosSandboxSimulationService": "Simulação de regras da LOUOS",
    "GeoLayerService": "Camadas geográficas de referência",
    "GeoServerWfsZonaClient": "Zonas urbanísticas do GeoServer da SEDUR",
    "ReginRecebeService": "Dados de solicitação enviados pelo REGIN",
    "ReginProtocoloSimulacaoService": "Protocolo de simulação do REGIN",
    "RuleVersionService": "Versões de regras vigentes",
    "GeoBairroIndicadorService": "Bairros oficiais do GeoServer",
}

PAPEL = {
    "ALI": "Arquivo Lógico Interno. Grupo de dados de negócio mantido pelo Viabiliza",
    "AIE": "Arquivo de Interface Externa. Grupo de dados de referência usado pelo Viabiliza",
    "EE": "Entrada Externa. Ação do usuário que altera dados ou o andamento do negócio",
    "CE": "Consulta Externa. Recupera e apresenta informação ao usuário",
    "SE": "Saída Externa. Entrega informação ou documento ao usuário",
}


def origem(item: dict) -> str:
    obs = item.get("observacoes", "")
    padrao = re.search(r"método (\w+)", obs) or re.search(r"model (\w+)\.php", obs) or re.search(r"de (\w+)\.php", obs)
    if padrao is None:
        raise SystemExit(f"Origem não identificada: {item['nome']}")
    return padrao.group(1)


def chave_pacote(grupo: dict) -> str:
    if grupo.get("id") in ("G_AIE", "G_OUTROS"):
        return grupo["id"]
    return grupo["nome"].replace("Funcionalidade:", "").strip()


def nome_funcao(pacote: str, item: dict) -> str:
    fonte = origem(item)
    if item["tipo"] == "ALI":
        return f"Dados - {DADOS[fonte]}"
    if item["tipo"] == "AIE":
        return f"Dados externos - {DADOS_EXTERNOS[fonte]}"
    if (pacote, fonte) in FUNCOES:
        return FUNCOES[(pacote, fonte)]
    if pacote in PREFIXO_CADASTRO and fonte in VERBO_CADASTRO:
        return f"{PREFIXO_CADASTRO[pacote]} - {VERBO_CADASTRO[fonte]}"
    raise SystemExit(f"Sem nome de negócio: {pacote}.{fonte}")


def observacao(item: dict, nome: str) -> str:
    tipo = item["tipo"]
    rotulo = "TR" if tipo in ("ALI", "AIE") else "AR"
    objeto = nome.split(" - ", 1)[1]
    return (
        f"{PAPEL[tipo]}: {objeto[0].lower() + objeto[1:]}. "
        f"TD={item.get('td')}, {rotulo}={item.get('ar')}. "
        f"CPM IFPUG 4.3.1, Parte 2 Cap. {'6' if tipo in ('ALI', 'AIE') else '7'}."
    )


def main() -> None:
    dados = json.loads(ARQUIVO.read_text(encoding="utf-8"))
    total_antes = sum(calcular_pf(i["tipo"], i.get("td"), i.get("ar")) for g in dados["grupos"] for i in g["itens"])

    for indice, grupo in enumerate(dados["grupos"], start=1):
        pacote = chave_pacote(grupo)
        if pacote not in PACOTES:
            raise SystemExit(f"Sem nome de pacote: {pacote}")
        for item in grupo["itens"]:
            nome = nome_funcao(pacote, item)
            item["nome"] = nome
            item["observacoes"] = observacao(item, nome)
        grupo["id"] = f"F{indice}"
        grupo["nome"] = PACOTES[pacote]

    nomes = [i["nome"] for g in dados["grupos"] for i in g["itens"]]
    repetidos = [n for n, q in Counter(nomes).items() if q > 1]
    if repetidos:
        raise SystemExit(f"Nomes repetidos: {repetidos}")

    total_depois = sum(calcular_pf(i["tipo"], i.get("td"), i.get("ar")) for g in dados["grupos"] for i in g["itens"])
    if total_depois != total_antes:
        raise SystemExit(f"Total mudou: {total_antes} -> {total_depois}")

    dados["identificacao"]["projeto"] = "Contagem IFPUG - Projeto de Desenvolvimento"
    dados["identificacao"]["responsavel"] = "Equipe de Análise — Sudoeste Informática"
    dados["identificacao"]["proposito"] = (
        "Medir o tamanho funcional do Viabiliza entregue, como projeto de desenvolvimento, "
        "seguindo o Manual de Práticas de Contagem IFPUG 4.3.1. As funções estão descritas em "
        "linguagem de negócio para conferência pelo fiscal do contrato."
    )
    dados["escopo_macro"] = [
        "1. Portal do requerente: acesso, empresas, procurações, consulta prévia, solicitação, protocolo, pendências e termo LGPD.",
        "2. Gestão SEDUR: caixa do setor, processos, ficha de análise, decisão, malha fina, contingência e atendimento presencial.",
        "3. Regras: CNAEs, zonas, quadros da LOUOS, gatilhos de risco, requisitos documentais e simulações.",
        "4. Território: mapa, camadas geográficas, GeoServer e localização de endereço.",
        "5. Documentos: TVL, indeferimento, comprovante de protocolo e verificação pública.",
        "6. Indicadores, relatórios, auditoria, LGPD, usuários, perfis, setores, parâmetros e integrações.",
    ]
    dados["proposta"]["objeto"] = (
        "Contagem de pontos de função do Viabiliza, organizada por funcionalidade de negócio."
    )

    ARQUIVO.write_text(json.dumps(dados, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"Pacotes: {len(dados['grupos'])}, funções: {len(nomes)}, total: {total_depois} PF")


if __name__ == "__main__":
    main()

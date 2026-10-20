# Catálogo de Histórias de Usuário — Viabiliza

Gerado em: 2026-06-09
Revisado em: 2026-06-09 — alinhamento com as fontes oficiais (LOUOS Lei nº 9.148/2016, Decreto nº 32.636/2020, Portal Simplifica, CNAE 2.3 IBGE/CONCLA); épicas e HUs renumeradas em ordem cronológica de desenvolvimento.
Revisado em: 2026-10-16 — cada HU foi reescrita com o código-fonte como referência. O status (Implementada, Parcial, Bloqueada ou Não evidenciada) está no corpo de cada arquivo. A redação genérica anterior deixou de valer.
Revisado em: 2026-10-19 — retiradas as 11 HUs sem funcionalidade no sistema e renumeradas as demais, de HU-001 a HU-176. A correspondência entre os números antigos e os novos está em docs/requisitos/hus/mapa-renumeracao-hus.md.
Revisado em: 2026-10-19 — HUs reordenadas: retaguarda primeiro e portal depois, na ordem de uso das funcionalidades, de HU-001 a HU-177, e criada a HU-004 (Cadastrar usuário pela gestão). O de-para atualizado está no mesmo mapa.
Revisado em: 2026-10-20 — criadas 4 HUs para funções que já existiam no sistema e nenhuma HU descrevia (HU-011 alerta de tabela TLL, HU-086 desvinculação de escritório virtual, HU-149 painel do cidadão e HU-181 central de notificações). As HUs foram renumeradas e passam de HU-001 a HU-181. O de-para está em docs/requisitos/hus/mapa-renumeracao-hus.md.

Total: 181 HUs. O comportamento vigente é o descrito em cada arquivo, não o texto de junho de 2026.

## Parte I — Retaguarda (gestão da SEDUR)

### Acesso à gestão

- [HU-001 — Autenticar usuário](EP01-Identidade-Acesso-e-Seguranca/HU-001-Autenticar-usuario.md) · EP01
- [HU-002 — Recuperar senha](EP01-Identidade-Acesso-e-Seguranca/HU-002-Recuperar-senha.md) · EP01
- [HU-003 — Solicitar acesso ao console](EP01-Identidade-Acesso-e-Seguranca/HU-003-Solicitar-acesso-ao-console.md) · EP01 *(nova em 2026-10-16)*

### Administração de usuários e acessos

- [HU-004 — Cadastrar usuário pela gestão](EP02-Administracao/HU-004-Cadastrar-usuario-pela-gestao.md) · EP02 *(nova em 2026-10-19)*
- [HU-005 — Manter usuários](EP02-Administracao/HU-005-Manter-usuarios.md) · EP02
- [HU-006 — Manter perfis](EP02-Administracao/HU-006-Manter-perfis.md) · EP02
- [HU-007 — Manter setores](EP02-Administracao/HU-007-Manter-setores.md) · EP02 *(nova — SAPS legado)*

### Configuração do sistema

- [HU-008 — Manter parâmetros do sistema](EP02-Administracao/HU-008-Manter-parâmetros-do-sistema.md) · EP02
- [HU-009 — Manter feriados](EP02-Administracao/HU-009-Manter-feriados.md) · EP02 *(nova — reunião SEDUR 2026-06)*
- [HU-010 — Manter valores TLL](EP02-Administracao/HU-010-Manter-valores-tll.md) · EP02 *(nova em 2026-10-16)*
- [HU-011 — Alertar falta de tabela TLL do exercício](EP02-Administracao/HU-011-Alertar-falta-de-tabela-tll-do-exercicio.md) · EP02 *(nova em 2026-10-20)*
- [HU-012 — Manter termos legais](EP02-Administracao/HU-012-Manter-termos-legais.md) · EP02 *(nova em 2026-10-16)*
- [HU-013 — Manter textos decisórios](EP02-Administracao/HU-013-Manter-textos-decisorios.md) · EP02 *(nova em 2026-10-16)*
- [HU-014 — Manter minutas e textos-padrão](EP02-Administracao/HU-014-Manter-minutas-e-textos-padrao.md) · EP02 *(nova em 2026-10-16)*
- [HU-015 — Configurar APIs de integração](EP02-Administracao/HU-015-Configurar-apis-de-integracao.md) · EP02 *(nova em 2026-10-16)*
- [HU-016 — Configurar servidores de e-mail](EP02-Administracao/HU-016-Configurar-servidores-de-email.md) · EP02 *(nova em 2026-10-16)*
- [HU-017 — Monitorar envio de e-mails](EP02-Administracao/HU-017-Monitorar-envio-de-emails.md) · EP02 *(nova em 2026-10-16)*
- [HU-018 — Configurar provedores de IA](EP02-Administracao/HU-018-Configurar-provedores-de-ia.md) · EP02 *(nova em 2026-10-16)*

### Tabelas de apoio das regras

- [HU-019 — Manter tipos de imóvel](EP02-Administracao/HU-019-Manter-tipos-de-imovel.md) · EP02 *(nova em 2026-10-16)*
- [HU-020 — Manter tipos de serviço](EP02-Administracao/HU-020-Manter-tipos-de-servico.md) · EP02 *(nova em 2026-10-16)*
- [HU-021 — Manter requisitos documentais](EP02-Administracao/HU-021-Manter-requisitos-documentais.md) · EP02 *(nova em 2026-10-16)*
- [HU-022 — Manter anexos de escritório virtual](EP02-Administracao/HU-022-Manter-anexos-escritorio-virtual.md) · EP02 *(nova em 2026-10-16)*

### Território: dados e camadas

- [HU-023 — Manter zonas](EP02-Administracao/HU-023-Manter-zonas.md) · EP02 *(nova em 2026-10-16)*
- [HU-024 — Manter vias](EP02-Administracao/HU-024-Manter-vias.md) · EP02 *(nova em 2026-10-16)*
- [HU-025 — Manter camadas do GeoServer](EP04-Georreferenciamento-e-Território/HU-025-Manter-camadas-do-geoserver.md) · EP04 *(nova em 2026-10-16)*
- [HU-026 — Consultar camadas geográficas](EP04-Georreferenciamento-e-Território/HU-026-Consultar-camadas-geograficas.md) · EP04

### Regras da LOUOS: quadros e enquadramento

- [HU-027 — Manter Quadro 7](EP02-Administracao/HU-027-Manter-Quadro-7.md) · EP02
- [HU-028 — Manter Quadro 10](EP02-Administracao/HU-028-Manter-Quadro-10.md) · EP02
- [HU-029 — Manter Quadro 11A](EP02-Administracao/HU-029-Manter-Quadro-11A.md) · EP02
- [HU-030 — Manter condicionantes](EP02-Administracao/HU-030-Manter-condicionantes.md) · EP02
- [HU-031 — Manter regras territoriais](EP02-Administracao/HU-031-Manter-regras-territoriais.md) · EP02 *(nova em 2026-10-16)*
- [HU-032 — Versionar regras da LOUOS](EP05-Motor-de-Regras-da-LOUOS/HU-032-Versionar-regras-da-LOUOS.md) · EP05
- [HU-033 — Simular impacto de alteração de parametrização](EP02-Administracao/HU-033-Simular-impacto-de-parametrizacao.md) · EP02 *(nova — melhoria além do legado)*

### Classificação de risco: manutenção

- [HU-034 — Manter CNAEs](EP02-Administracao/HU-034-Manter-CNAEs.md) · EP02
- [HU-035 — Manter classificação de risco](EP02-Administracao/HU-035-Manter-classificacao-de-risco.md) · EP02
- [HU-036 — Manter gatilhos de risco](EP02-Administracao/HU-036-Manter-gatilhos-de-risco.md) · EP02 *(nova em 2026-10-16)*
- [HU-037 — Atualizar classificação de risco](EP06-Classificacao-de-Risco/HU-037-Atualizar-classificacao-de-risco.md) · EP06

### Território: consulta e identificação do imóvel

- [HU-038 — Geocodificar endereço](EP04-Georreferenciamento-e-Território/HU-038-Geocodificar-endereco.md) · EP04
- [HU-039 — Localizar imóvel no mapa](EP04-Georreferenciamento-e-Território/HU-039-Localizar-imovel-no-mapa.md) · EP04
- [HU-040 — Identificar zona urbanística](EP04-Georreferenciamento-e-Território/HU-040-Identificar-zona-urbanistica.md) · EP04
- [HU-041 — Identificar classificação da via](EP04-Georreferenciamento-e-Território/HU-041-Identificar-classificacao-da-via.md) · EP04
- [HU-042 — Identificar lote](EP04-Georreferenciamento-e-Território/HU-042-Identificar-lote.md) · EP04
- [HU-043 — Identificar bairro](EP04-Georreferenciamento-e-Território/HU-043-Identificar-bairro.md) · EP04
- [HU-044 — Identificar restrições territoriais](EP04-Georreferenciamento-e-Território/HU-044-Identificar-restricões-territoriais.md) · EP04
- [HU-045 — Validar localização do imóvel](EP04-Georreferenciamento-e-Território/HU-045-Validar-localizacao-do-imovel.md) · EP04

### Motor de decisão: enquadramento

- [HU-046 — Enquadrar atividade pelo Quadro 7](EP05-Motor-de-Regras-da-LOUOS/HU-046-Enquadrar-atividade-pelo-Quadro-7.md) · EP05
- [HU-047 — Aplicar Quadro 10](EP05-Motor-de-Regras-da-LOUOS/HU-047-Aplicar-Quadro-10.md) · EP05
- [HU-048 — Aplicar Quadro 11A](EP05-Motor-de-Regras-da-LOUOS/HU-048-Aplicar-Quadro-11A.md) · EP05
- [HU-049 — Aplicar condicionantes urbanísticas](EP05-Motor-de-Regras-da-LOUOS/HU-049-Aplicar-condicionantes-urbanisticas.md) · EP05
- [HU-050 — Aplicar restrições especiais](EP05-Motor-de-Regras-da-LOUOS/HU-050-Aplicar-restricões-especiais.md) · EP05
- [HU-051 — Consolidar resultado da análise](EP05-Motor-de-Regras-da-LOUOS/HU-051-Consolidar-resultado-da-analise.md) · EP05
- [HU-052 — Registrar fundamentação legal](EP05-Motor-de-Regras-da-LOUOS/HU-052-Registrar-fundamentacao-legal.md) · EP05

### Motor de decisão: risco

- [HU-053 — Classificar CNAE por risco](EP06-Classificacao-de-Risco/HU-053-Classificar-CNAE-por-risco.md) · EP06
- [HU-054 — Aplicar regra de baixo risco](EP06-Classificacao-de-Risco/HU-054-Aplicar-regra-de-baixo-risco.md) · EP06
- [HU-055 — Aplicar regra de médio risco](EP06-Classificacao-de-Risco/HU-055-Aplicar-regra-de-medio-risco.md) · EP06
- [HU-056 — Aplicar regra de alto risco](EP06-Classificacao-de-Risco/HU-056-Aplicar-regra-de-alto-risco.md) · EP06
- [HU-057 — Aplicar exceções de risco](EP06-Classificacao-de-Risco/HU-057-Aplicar-excecões-de-risco.md) · EP06
- [HU-058 — Consultar tabela de risco](EP06-Classificacao-de-Risco/HU-058-Consultar-tabela-de-risco.md) · EP06

### Fluxo expresso

- [HU-059 — Pré-analisar processo pelo motor](EP10-Análise-Tecnica-SEDUR/HU-059-Pre-analisar-processo-pelo-motor.md) · EP10 *(nova — melhoria além do legado)*
- [HU-060 — Identificar elegibilidade para fluxo expresso](EP09-Fluxo-Expresso/HU-060-Identificar-elegibilidade-para-fluxo-expresso.md) · EP09
- [HU-061 — Deferir automaticamente](EP09-Fluxo-Expresso/HU-061-Deferir-automaticamente.md) · EP09
- [HU-062 — Indeferir automaticamente](EP09-Fluxo-Expresso/HU-062-Indeferir-automaticamente.md) · EP09
- [HU-063 — Emitir resultado expresso](EP09-Fluxo-Expresso/HU-063-Emitir-resultado-expresso.md) · EP09
- [HU-064 — Notificar resultado ao cidadão](EP09-Fluxo-Expresso/HU-064-Notificar-resultado-ao-cidadao.md) · EP09
- [HU-065 — Registrar auditoria da decisão automática](EP09-Fluxo-Expresso/HU-065-Registrar-auditoria-da-decisao-automatica.md) · EP09
- [HU-066 — Indeferir sem atuação por prazo BAP](EP09-Fluxo-Expresso/HU-066-Indeferir-sem-atuação-por-prazo-BAP.md) · EP09 *(nova — reunião SEDUR 2026-06)*

### Entrada de solicitações pela retaguarda

- [HU-067 — Registrar solicitação em contingência](EP08-Solicitacao-de-Viabilidade/HU-067-Registrar-solicitacao-em-contingencia.md) · EP08 *(nova — melhoria além do legado)*
- [HU-068 — Atendimento presencial assistido](EP08-Solicitacao-de-Viabilidade/HU-068-Atendimento-presencial-assistido.md) · EP08 *(nova — melhoria além do legado)*
- [HU-069 — Encaminhar para análise técnica](EP10-Análise-Tecnica-SEDUR/HU-069-Encaminhar-para-analise-tecnica.md) · EP10

### Análise técnica dos processos

- [HU-070 — Fila de trabalho do analista com SLA visual](EP10-Análise-Tecnica-SEDUR/HU-070-Fila-de-trabalho-do-analista-com-SLA.md) · EP10 *(nova — melhoria além do legado)*
- [HU-071 — Distribuir processo](EP10-Análise-Tecnica-SEDUR/HU-071-Distribuir-processo.md) · EP10
- [HU-072 — Assumir análise](EP10-Análise-Tecnica-SEDUR/HU-072-Assumir-analise.md) · EP10
- [HU-073 — Consultar processo](EP10-Análise-Tecnica-SEDUR/HU-073-Consultar-processo.md) · EP10
- [HU-074 — Preencher ficha de análise](EP10-Análise-Tecnica-SEDUR/HU-074-Preencher-ficha-de-análise.md) · EP10 *(nova — SAPS legado)*
- [HU-075 — Exibir histórico decisório do imóvel e do CNAE na zona](EP10-Análise-Tecnica-SEDUR/HU-075-Exibir-historico-decisorio-do-imovel-e-CNAE.md) · EP10 *(nova — melhoria além do legado)*
- [HU-076 — Registrar vistoria](EP10-Análise-Tecnica-SEDUR/HU-076-Registrar-vistoria.md) · EP10 *(nova em 2026-10-16)*
- [HU-077 — Encaminhar para malha fina](EP10-Análise-Tecnica-SEDUR/HU-077-Encaminhar-para-malha-fina.md) · EP10 *(nova — SAPS legado)*
- [HU-078 — Emitir parecer](EP10-Análise-Tecnica-SEDUR/HU-078-Emitir-parecer.md) · EP10
- [HU-079 — Deferir solicitação](EP10-Análise-Tecnica-SEDUR/HU-079-Deferir-solicitacao.md) · EP10
- [HU-080 — Indeferir solicitação](EP10-Análise-Tecnica-SEDUR/HU-080-Indeferir-solicitacao.md) · EP10
- [HU-081 — Aplicar condicionantes](EP10-Análise-Tecnica-SEDUR/HU-081-Aplicar-condicionantes.md) · EP10
- [HU-082 — Encerrar processo](EP10-Análise-Tecnica-SEDUR/HU-082-Encerrar-processo.md) · EP10
- [HU-083 — Emitir TVL em PDF no backoffice](EP10-Análise-Tecnica-SEDUR/HU-083-Emitir-TVL-em-PDF-no-backoffice.md) · EP10 *(nova — reunião SEDUR 2026-06)*
- [HU-084 — Reprocessar processo](EP10-Análise-Tecnica-SEDUR/HU-084-Reprocessar-processo.md) · EP10 *(nova em 2026-10-16)*
- [HU-085 — Forçar encaminhamento para análise](EP10-Análise-Tecnica-SEDUR/HU-085-Forcar-encaminhamento-para-analise.md) · EP10 *(nova em 2026-10-16)*
- [HU-086 — Desvincular inscrição de escritório virtual](EP10-Análise-Tecnica-SEDUR/HU-086-Desvincular-inscricao-de-escritorio-virtual.md) · EP10 *(nova em 2026-10-20)*

### Pendências e comunicação

- [HU-087 — Solicitar pendência](EP10-Análise-Tecnica-SEDUR/HU-087-Solicitar-pendência.md) · EP10
- [HU-088 — Notificar pendência](EP11-Pendências-e-Comunicacao/HU-088-Notificar-pendência.md) · EP11
- [HU-089 — Reabrir análise](EP11-Pendências-e-Comunicacao/HU-089-Reabrir-analise.md) · EP11
- [HU-090 — Notificar vencimentos](EP11-Pendências-e-Comunicacao/HU-090-Notificar-vencimentos.md) · EP11
- [HU-091 — Escalonar processos parados por SLA](EP11-Pendências-e-Comunicacao/HU-091-Escalonar-processos-parados-por-SLA.md) · EP11 *(nova — melhoria além do legado)*
- [HU-092 — Enviar e-mail](EP11-Pendências-e-Comunicacao/HU-092-Enviar-e-mail.md) · EP11
- [HU-093 — Enviar WhatsApp](EP11-Pendências-e-Comunicacao/HU-093-Enviar-WhatsApp.md) · EP11
- [HU-094 — Consultar histórico de comunicações](EP11-Pendências-e-Comunicacao/HU-094-Consultar-historico-de-comunicacões.md) · EP11

### Auditoria e conformidade

- [HU-095 — Registrar log das decisões](EP12-Auditoria-e-Compliance/HU-095-Registrar-log-das-decisões.md) · EP12
- [HU-096 — Consultar histórico de alterações](EP12-Auditoria-e-Compliance/HU-096-Consultar-historico-de-alteracões.md) · EP12
- [HU-097 — Consultar regras aplicadas](EP12-Auditoria-e-Compliance/HU-097-Consultar-regras-aplicadas.md) · EP12
- [HU-098 — Consultar trilha de auditoria](EP12-Auditoria-e-Compliance/HU-098-Consultar-trilha-de-auditoria.md) · EP12
- [HU-099 — Exportar auditoria](EP12-Auditoria-e-Compliance/HU-099-Exportar-auditoria.md) · EP12
- [HU-100 — Monitorar conformidade LGPD](EP12-Auditoria-e-Compliance/HU-100-Monitorar-conformidade-LGPD.md) · EP12
- [HU-101 — Detectar padrões de abuso e fraude](EP12-Auditoria-e-Compliance/HU-101-Detectar-padroes-de-abuso-e-fraude.md) · EP12 *(nova — melhoria além do legado)*
- [HU-102 — Consultar log de integrações](EP12-Auditoria-e-Compliance/HU-102-Consultar-log-de-integracoes.md) · EP12 *(nova em 2026-10-16)*
- [HU-103 — Auditoria preditiva](EP12-Auditoria-e-Compliance/HU-103-Auditoria-preditiva.md) · EP12 *(nova em 2026-10-16)*

### Integrações externas

- [HU-104 — Integrar com REDESIM](EP13-Integracões/HU-104-Integrar-com-REDESIM.md) · EP13
- [HU-105 — Importar dados da REDESIM](EP03-Cadastro-Empresarial/HU-105-Importar-dados-da-REDESIM.md) · EP03
- [HU-106 — Integrar com Junta Comercial](EP13-Integracões/HU-106-Integrar-com-Junta-Comercial.md) · EP13
- [HU-107 — Integrar com Cadastro Imobiliário](EP13-Integracões/HU-107-Integrar-com-Cadastro-Imobiliario.md) · EP13
- [HU-108 — Integrar com GIS Municipal](EP13-Integracões/HU-108-Integrar-com-GIS-Municipal.md) · EP13
- [HU-109 — Integrar com SEFAZ municipal](EP13-Integracões/HU-109-Integrar-com-SEFAZ-municipal.md) · EP13 *(confirmada — API existente; contrato pendente)*
- [HU-110 — Vincular protocolo BAP](EP13-Integracões/HU-110-Vincular-protocolo-BAP.md) · EP13 *(nova — Regin 2026-06)*

### Inteligência artificial na análise

- [HU-111 — Realizar OCR documental](EP14-Inteligência-Artificial/HU-111-Realizar-OCR-documental.md) · EP14
- [HU-112 — Classificar documentos](EP14-Inteligência-Artificial/HU-112-Classificar-documentos.md) · EP14
- [HU-113 — Detectar documentos ilegíveis](EP14-Inteligência-Artificial/HU-113-Detectar-documentos-ilegiveis.md) · EP14
- [HU-114 — Identificar inconsistências](EP14-Inteligência-Artificial/HU-114-Identificar-inconsistências.md) · EP14
- [HU-115 — Gerar resumo para analista](EP14-Inteligência-Artificial/HU-115-Gerar-resumo-para-analista.md) · EP14
- [HU-116 — Sugerir parecer](EP14-Inteligência-Artificial/HU-116-Sugerir-parecer.md) · EP14
- [HU-117 — Assistente virtual do analista](EP14-Inteligência-Artificial/HU-117-Assistente-virtual-do-analista.md) · EP14

### Relatórios e indicadores

- [HU-118 — Dashboard executivo](EP15-Relatórios-e-Indicadores/HU-118-Dashboard-executivo.md) · EP15
- [HU-119 — Solicitações por período](EP15-Relatórios-e-Indicadores/HU-119-Solicitacões-por-periodo.md) · EP15
- [HU-120 — Solicitações por zona](EP15-Relatórios-e-Indicadores/HU-120-Solicitacões-por-zona.md) · EP15
- [HU-121 — Solicitações por CNAE](EP15-Relatórios-e-Indicadores/HU-121-Solicitacões-por-CNAE.md) · EP15
- [HU-122 — Solicitações por risco](EP15-Relatórios-e-Indicadores/HU-122-Solicitacões-por-risco.md) · EP15
- [HU-123 — Taxa de deferimento](EP15-Relatórios-e-Indicadores/HU-123-Taxa-de-deferimento.md) · EP15
- [HU-124 — Taxa de indeferimento](EP15-Relatórios-e-Indicadores/HU-124-Taxa-de-indeferimento.md) · EP15
- [HU-125 — Tempo médio de análise](EP15-Relatórios-e-Indicadores/HU-125-Tempo-medio-de-analise.md) · EP15
- [HU-126 — Produtividade por analista](EP15-Relatórios-e-Indicadores/HU-126-Produtividade-por-analista.md) · EP15
- [HU-127 — Exportar relatórios e listagens](EP15-Relatórios-e-Indicadores/HU-127-Exportar-relatorios.md) · EP15 *(escopo ampliado — padrão transversal de exportação CSV/XLSX/PDF em toda datatable da gestão)*
- [HU-128 — Relatório de quedas por gatilho (expansão do expresso)](EP15-Relatórios-e-Indicadores/HU-128-Relatorio-de-quedas-por-gatilho.md) · EP15 *(nova — melhoria além do legado)*
- [HU-129 — Consultar sala de controle](EP15-Relatórios-e-Indicadores/HU-129-Consultar-sala-de-controle.md) · EP15 *(nova em 2026-10-16)*
- [HU-130 — Consultar SLA e vencimentos](EP15-Relatórios-e-Indicadores/HU-130-Consultar-sla-e-vencimentos.md) · EP15 *(nova em 2026-10-16)*
- [HU-131 — Consultar tempo de emissão da viabilidade](EP15-Relatórios-e-Indicadores/HU-131-Consultar-tempo-de-emissao-da-viabilidade.md) · EP15 *(nova em 2026-10-16)*
- [HU-132 — Consultar benchmark nacional](EP15-Relatórios-e-Indicadores/HU-132-Consultar-benchmark-nacional.md) · EP15 *(nova em 2026-10-16)*
- [HU-133 — Relatório de pendências e exigências](EP15-Relatórios-e-Indicadores/HU-133-Relatorio-de-pendencias-e-exigencias.md) · EP15 *(nova em 2026-10-16)*
- [HU-134 — Relatório de trilha por processo](EP15-Relatórios-e-Indicadores/HU-134-Relatorio-de-trilha-por-processo.md) · EP15 *(nova em 2026-10-16)*
- [HU-135 — Painel por bairro](EP15-Relatórios-e-Indicadores/HU-135-Painel-por-bairro.md) · EP15 *(nova em 2026-10-16)*
- [HU-136 — Relatório de saturação locacional](EP15-Relatórios-e-Indicadores/HU-136-Relatorio-de-saturacao-locacional.md) · EP15 *(nova em 2026-10-16)*
- [HU-137 — Relatório de escritório virtual](EP15-Relatórios-e-Indicadores/HU-137-Relatorio-de-escritorio-virtual.md) · EP15 *(nova em 2026-10-16)*
- [HU-138 — Relatório de atendimento em contingência](EP15-Relatórios-e-Indicadores/HU-138-Relatorio-de-atendimento-em-contingencia.md) · EP15 *(nova em 2026-10-16)*
- [HU-139 — Relatório de falhas de comunicação](EP15-Relatórios-e-Indicadores/HU-139-Relatorio-de-falhas-de-comunicacao.md) · EP15 *(nova em 2026-10-16)*

## Parte II — Portal do cidadão

### Conta do cidadão

- [HU-140 — Cadastrar usuário](EP01-Identidade-Acesso-e-Seguranca/HU-140-Cadastrar-usuario.md) · EP01
- [HU-141 — Confirmar e-mail](EP01-Identidade-Acesso-e-Seguranca/HU-141-Confirmar-e-mail.md) · EP01
- [HU-142 — Autenticar com GOV.BR](EP01-Identidade-Acesso-e-Seguranca/HU-142-Autenticar-com-govbr.md) · EP01
- [HU-143 — Alterar senha](EP01-Identidade-Acesso-e-Seguranca/HU-143-Alterar-senha.md) · EP01
- [HU-144 — Gerenciar perfil do usuário](EP01-Identidade-Acesso-e-Seguranca/HU-144-Gerenciar-perfil-do-usuario.md) · EP01
- [HU-145 — Aceitar termo LGPD](EP01-Identidade-Acesso-e-Seguranca/HU-145-Aceitar-termo-LGPD.md) · EP01
- [HU-146 — Consultar histórico de acessos](EP01-Identidade-Acesso-e-Seguranca/HU-146-Consultar-historico-de-acessos.md) · EP01
- [HU-147 — Vincular procurador](EP01-Identidade-Acesso-e-Seguranca/HU-147-Vincular-procurador.md) · EP01
- [HU-148 — Revogar procuração](EP01-Identidade-Acesso-e-Seguranca/HU-148-Revogar-procuracao.md) · EP01
- [HU-149 — Consultar painel do cidadão](EP08-Solicitacao-de-Viabilidade/HU-149-Consultar-painel-do-cidadao.md) · EP08 *(nova em 2026-10-20)*

### Empresas do cidadão

- [HU-150 — Consultar dados do CNPJ](EP03-Cadastro-Empresarial/HU-150-Consultar-dados-do-CNPJ.md) · EP03
- [HU-151 — Cadastrar empresa](EP03-Cadastro-Empresarial/HU-151-Cadastrar-empresa.md) · EP03
- [HU-152 — Atualizar dados empresariais](EP03-Cadastro-Empresarial/HU-152-Atualizar-dados-empresariais.md) · EP03
- [HU-153 — Vincular CNAE principal](EP03-Cadastro-Empresarial/HU-153-Vincular-CNAE-principal.md) · EP03
- [HU-154 — Vincular CNAEs secundários](EP03-Cadastro-Empresarial/HU-154-Vincular-CNAEs-secundarios.md) · EP03
- [HU-155 — Consultar empresas vinculadas](EP03-Cadastro-Empresarial/HU-155-Consultar-empresas-vinculadas.md) · EP03
- [HU-156 — Encerrar vínculo empresarial](EP03-Cadastro-Empresarial/HU-156-Encerrar-vinculo-empresarial.md) · EP03

### Consulta prévia de viabilidade

- [HU-157 — Consultar viabilidade por endereço](EP07-Consulta-Previa-de-Viabilidade/HU-157-Consultar-viabilidade-por-endereco.md) · EP07
- [HU-158 — Consultar viabilidade por inscrição imobiliária](EP07-Consulta-Previa-de-Viabilidade/HU-158-Consultar-viabilidade-por-inscricao-imobiliaria.md) · EP07
- [HU-159 — Consultar viabilidade por CNAE](EP07-Consulta-Previa-de-Viabilidade/HU-159-Consultar-viabilidade-por-CNAE.md) · EP07
- [HU-160 — Simular enquadramento da atividade](EP07-Consulta-Previa-de-Viabilidade/HU-160-Simular-enquadramento-da-atividade.md) · EP07
- [HU-161 — Simular classificação de risco](EP07-Consulta-Previa-de-Viabilidade/HU-161-Simular-classificacao-de-risco.md) · EP07
- [HU-162 — Simular restrições urbanísticas](EP07-Consulta-Previa-de-Viabilidade/HU-162-Simular-restricões-urbanisticas.md) · EP07
- [HU-163 — Consultar histórico de consultas](EP07-Consulta-Previa-de-Viabilidade/HU-163-Consultar-historico-de-consultas.md) · EP07

### Solicitação de viabilidade

- [HU-164 — Criar solicitação de viabilidade](EP08-Solicitacao-de-Viabilidade/HU-164-Criar-solicitacao-de-viabilidade.md) · EP08
- [HU-165 — Informar imóvel](EP08-Solicitacao-de-Viabilidade/HU-165-Informar-imovel.md) · EP08
- [HU-166 — Informar área utilizada](EP08-Solicitacao-de-Viabilidade/HU-166-Informar-area-utilizada.md) · EP08
- [HU-167 — Informar atividade econômica](EP08-Solicitacao-de-Viabilidade/HU-167-Informar-atividade-economica.md) · EP08
- [HU-168 — Informar CNAEs complementares](EP08-Solicitacao-de-Viabilidade/HU-168-Informar-CNAEs-complementares.md) · EP08
- [HU-169 — Anexar documentos](EP08-Solicitacao-de-Viabilidade/HU-169-Anexar-documentos.md) · EP08
- [HU-170 — Validar documentos obrigatórios](EP08-Solicitacao-de-Viabilidade/HU-170-Validar-documentos-obrigatorios.md) · EP08
- [HU-171 — Protocolar solicitação](EP08-Solicitacao-de-Viabilidade/HU-171-Protocolar-solicitacao.md) · EP08
- [HU-172 — Simular viabilidade antes de protocolar](EP08-Solicitacao-de-Viabilidade/HU-172-Simular-viabilidade-antes-de-protocolar.md) · EP08 *(nova — melhoria além do legado)*
- [HU-173 — Cancelar solicitação](EP08-Solicitacao-de-Viabilidade/HU-173-Cancelar-solicitacao.md) · EP08
- [HU-174 — Renovar viabilidade](EP08-Solicitacao-de-Viabilidade/HU-174-Renovar-viabilidade.md) · EP08 *(nova em 2026-10-16)*

### Acompanhamento e resposta

- [HU-175 — Consultar protocolo](EP08-Solicitacao-de-Viabilidade/HU-175-Consultar-protocolo.md) · EP08
- [HU-176 — Receber complementação](EP10-Análise-Tecnica-SEDUR/HU-176-Receber-complementacao.md) · EP10
- [HU-177 — Responder pendência](EP11-Pendências-e-Comunicacao/HU-177-Responder-pendência.md) · EP11
- [HU-178 — Explicar resultado ao cidadão](EP14-Inteligência-Artificial/HU-178-Explicar-resultado-ao-cidadao.md) · EP14
- [HU-179 — Gerar resumo da solicitação](EP14-Inteligência-Artificial/HU-179-Gerar-resumo-da-solicitacao.md) · EP14
- [HU-180 — Verificar autenticidade de documento](EP13-Integracões/HU-180-Verificar-autenticidade-de-documento.md) · EP13 *(nova em 2026-10-16)*
- [HU-181 — Consultar central de notificações](EP11-Pendências-e-Comunicacao/HU-181-Consultar-central-de-notificacoes.md) · EP11 *(nova em 2026-10-20)*

## Mapa de renumeração (2026-10-19)

Em 19/10/2026 foram retiradas 11 HUs sem funcionalidade no sistema e as demais foram reordenadas: primeiro as da retaguarda e depois as do portal, na ordem de uso. Em 20/10/2026 foram criadas 4 HUs (HU-011, HU-086, HU-149 e HU-181), além da HU-004 de 19/10, e as HUs ficaram de HU-001 a HU-181. O de-para completo, com o motivo de cada retirada e as três numerações (até 18/10, manhã de 19/10 e atual, de 20/10), está em `docs/requisitos/hus/mapa-renumeracao-hus.md`. A seção a seguir é o registro da renumeração de 09/06/2026 e usa os números daquela data.

## Mapa de renumeração (2026-06-09)

As épicas e HUs foram renumeradas em ordem cronológica de desenvolvimento. De-para:

| Épica (antiga → nova) | Nome | HUs (antigas → novas) |
|---|---|---|
| EP01 → EP01 | Identidade, Acesso e Segurança | HU-001 a HU-010 → HU-001 a HU-010 (sem mudança) |
| EP13 → EP02 | Administração | HU-102/110/111/109 → HU-011/012/013/014; HU-103 a HU-108 → HU-015 a HU-020 |
| EP02 → EP03 | Cadastro Empresarial | HU-011 a HU-018 → HU-021 a HU-028 |
| EP05 → EP04 | Georreferenciamento e Território | HU-036 a HU-044 → HU-029 a HU-037 |
| EP06 → EP05 | Motor de Regras da LOUOS | HU-045 a HU-053 → HU-038 a HU-046 |
| EP07 → EP06 | Classificação de Risco | HU-054 a HU-060 → HU-047 a HU-053 |
| EP03 → EP07 | Consulta Prévia de Viabilidade | HU-019 a HU-025 → HU-054 a HU-060 |
| EP04 → EP08 | Solicitação de Viabilidade | HU-026 a HU-035 → HU-061 a HU-070; HU-128/129 (DAM) → HU-071/072 |
| EP08 → EP09 | Fluxo Expresso | HU-061 a HU-066 → HU-073 a HU-078 |
| EP09 → EP10 | Análise Técnica SEDUR | HU-067 a HU-077 → HU-079 a HU-089 |
| EP10 → EP11 | Pendências e Comunicação | HU-078 a HU-084 → HU-090 a HU-096 |
| EP14 → EP12 | Auditoria e Compliance | HU-112 a HU-117 → HU-097 a HU-102 |
| EP12 → EP13 | Integrações | HU-095 a HU-101 → HU-103 a HU-109; HU-130/131 → HU-110/111 |
| EP11 → EP14 | Inteligência Artificial | HU-085 a HU-094 → HU-112 a HU-121 |
| EP15 → EP15 | Relatórios e Indicadores | HU-118 a HU-127 → HU-122 a HU-131 |

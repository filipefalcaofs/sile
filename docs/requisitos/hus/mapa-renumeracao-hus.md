# Renumeração e ordem das HUs — 19 e 20/10/2026

Em 19/10/2026 foram retiradas 11 HUs que o sistema não oferece e as demais foram reordenadas: primeiro a retaguarda e depois o portal, na ordem de uso das funcionalidades, com a nova HU-004. Em 20/10/2026 foram criadas mais 4 HUs para funções que existiam no sistema e nenhuma HU descrevia: HU-011 (Alertar falta de tabela TLL do exercício), HU-086 (Desvincular inscrição de escritório virtual), HU-149 (Consultar painel do cidadão) e HU-181 (Consultar central de notificações), e as HUs foram renumeradas. As 181 HUs (176 mantidas e 5 novas) vão de HU-001 a HU-181. A Retaguarda vai de HU-001 a HU-139 e o Portal, de HU-140 a HU-181.

Em 23/10/2026 foi criada a HU-183 (fichas de análise por analista e histórico de tramitação), numerada depois da HU-182 e fora das faixas acima; não altera a numeração das demais.

Há três numerações:

- **Até 18/10/2026:** usada em `.planning`, especificações e planos datados, ofícios, atas e nas contagens anteriores.
- **Manhã de 19/10/2026:** usada nos comentários, nos testes e nas rotas do código, que não foram alterados na reordenação.
- **Atual (20/10/2026):** usada nos documentos de HU, no catálogo, no consolidado em PDF, na contagem de PF e na rastreabilidade.

## HUs retiradas
| HU antiga | Título | Motivo |
|---:|---|---|
| HU-017 | Manter Quadro 11 | O sistema não oferece manutenção do Quadro 11 (só 10 e 11A). |
| HU-040 | Aplicar Quadro 11 | O motor não aplica um Quadro 11 distinto do 11A. |
| HU-071 | Visualizar DAM da solicitação | A visualização do DAM não é oferecida. |
| HU-072 | Confirmar pagamento do DAM | A confirmação de pagamento do DAM não é oferecida. |
| HU-105 | Integrar com Receita Federal | A consulta à Receita Federal não é oferecida. |
| HU-108 | Integrar com Protocolo Municipal | A integração com o protocolo municipal não é oferecida. |
| HU-109 | Integrar com Portal do Contribuinte | A integração com o Portal do Contribuinte não é oferecida. |
| HU-111 | Migrar dados do sistema legado | A migração do sistema legado não é oferecida. |
| HU-120 | Assistente virtual do cidadão | O assistente virtual do cidadão não é oferecido. |
| HU-139 | Manter edifícios comerciais e complementos | Não há cadastro de edifícios; o complemento é texto livre. |
| HU-146 | Painel de saúde das integrações | O painel de saúde das integrações não é oferecido. |

## De-para de todas as HUs

| HU atual (20/10) | Manhã de 19/10 | Até 18/10 | Título | Parte e bloco |
|---:|---:|---:|---|---|
| HU-001 | HU-002 | HU-002 | Autenticar usuário | Retaguarda — Acesso à gestão |
| HU-002 | HU-003 | HU-003 | Recuperar senha | Retaguarda — Acesso à gestão |
| HU-003 | HU-141 | HU-152 | Solicitar acesso ao console | Retaguarda — Acesso à gestão |
| HU-004 | — | — | Cadastrar usuário pela gestão (nova) | Retaguarda — Administração de usuários e acessos |
| HU-005 | HU-012 | HU-012 | Manter usuários | Retaguarda — Administração de usuários e acessos |
| HU-006 | HU-013 | HU-013 | Manter perfis | Retaguarda — Administração de usuários e acessos |
| HU-007 | HU-129 | HU-138 | Manter setores | Retaguarda — Administração de usuários e acessos |
| HU-008 | HU-014 | HU-014 | Manter parâmetros do sistema | Retaguarda — Configuração do sistema |
| HU-009 | HU-128 | HU-137 | Manter feriados | Retaguarda — Configuração do sistema |
| HU-010 | HU-145 | HU-156 | Manter valores TLL | Retaguarda — Configuração do sistema |
| HU-011 | — | — | Alertar falta de tabela TLL do exercício (nova em 20/10) | Retaguarda — Configuração do sistema |
| HU-012 | HU-146 | HU-157 | Manter termos legais | Retaguarda — Configuração do sistema |
| HU-013 | HU-147 | HU-158 | Manter textos decisórios | Retaguarda — Configuração do sistema |
| HU-014 | HU-148 | HU-159 | Manter minutas e textos-padrão | Retaguarda — Configuração do sistema |
| HU-015 | HU-149 | HU-160 | Configurar APIs de integração | Retaguarda — Configuração do sistema |
| HU-016 | HU-150 | HU-161 | Configurar servidores de e-mail | Retaguarda — Configuração do sistema |
| HU-017 | HU-151 | HU-162 | Monitorar envio de e-mails | Retaguarda — Configuração do sistema |
| HU-018 | HU-152 | HU-163 | Configurar provedores de IA | Retaguarda — Configuração do sistema |
| HU-019 | HU-143 | HU-154 | Manter tipos de imóvel | Retaguarda — Tabelas de apoio das regras |
| HU-020 | HU-142 | HU-153 | Manter tipos de serviço | Retaguarda — Tabelas de apoio das regras |
| HU-021 | HU-144 | HU-155 | Manter requisitos documentais | Retaguarda — Tabelas de apoio das regras |
| HU-022 | HU-156 | HU-167 | Manter anexos de escritório virtual | Retaguarda — Tabelas de apoio das regras |
| HU-023 | HU-154 | HU-165 | Manter zonas do motor | Retaguarda — Território: dados e camadas |
| HU-024 | HU-155 | HU-166 | Manter vias do motor | Retaguarda — Território: dados e camadas |
| HU-025 | HU-158 | HU-169 | Manter camadas do GeoServer | Retaguarda — Território: dados e camadas |
| HU-026 | HU-035 | HU-036 | Consultar camadas geográficas | Retaguarda — Território: dados e camadas |
| HU-027 | HU-015 | HU-015 | Manter Quadro 7 | Retaguarda — Regras da LOUOS: quadros e enquadramento |
| HU-028 | HU-016 | HU-016 | Manter Quadro 10 | Retaguarda — Regras da LOUOS: quadros e enquadramento |
| HU-029 | HU-017 | HU-018 | Manter Quadro 11A | Retaguarda — Regras da LOUOS: quadros e enquadramento |
| HU-030 | HU-018 | HU-019 | Manter condicionantes | Retaguarda — Regras da LOUOS: quadros e enquadramento |
| HU-031 | HU-176 | HU-187 | Manter regras territoriais | Retaguarda — Regras da LOUOS: quadros e enquadramento |
| HU-032 | HU-044 | HU-046 | Versionar regras da LOUOS | Retaguarda — Regras da LOUOS: quadros e enquadramento |
| HU-033 | HU-133 | HU-143 | Simular impacto de parametrização | Retaguarda — Regras da LOUOS: quadros e enquadramento |
| HU-034 | HU-011 | HU-011 | Manter CNAEs | Retaguarda — Classificação de risco: manutenção |
| HU-035 | HU-019 | HU-020 | Manter classificação de risco | Retaguarda — Classificação de risco: manutenção |
| HU-036 | HU-153 | HU-164 | Manter gatilhos de risco | Retaguarda — Classificação de risco: manutenção |
| HU-037 | HU-051 | HU-053 | Atualizar classificação de risco | Retaguarda — Classificação de risco: manutenção |
| HU-038 | HU-028 | HU-029 | Geocodificar endereço | Retaguarda — Território: consulta e identificação do imóvel |
| HU-039 | HU-029 | HU-030 | Localizar imóvel no mapa | Retaguarda — Território: consulta e identificação do imóvel |
| HU-040 | HU-030 | HU-031 | Identificar zona urbanística | Retaguarda — Território: consulta e identificação do imóvel |
| HU-041 | HU-031 | HU-032 | Identificar classificação da via | Retaguarda — Território: consulta e identificação do imóvel |
| HU-042 | HU-032 | HU-033 | Identificar lote | Retaguarda — Território: consulta e identificação do imóvel |
| HU-043 | HU-033 | HU-034 | Identificar bairro | Retaguarda — Território: consulta e identificação do imóvel |
| HU-044 | HU-034 | HU-035 | Identificar restrições territoriais | Retaguarda — Território: consulta e identificação do imóvel |
| HU-045 | HU-036 | HU-037 | Validar localização do imóvel | Retaguarda — Território: consulta e identificação do imóvel |
| HU-046 | HU-037 | HU-038 | Enquadrar atividade pelo Quadro 7 | Retaguarda — Motor de decisão: enquadramento |
| HU-047 | HU-038 | HU-039 | Aplicar Quadro 10 | Retaguarda — Motor de decisão: enquadramento |
| HU-048 | HU-039 | HU-041 | Aplicar Quadro 11A | Retaguarda — Motor de decisão: enquadramento |
| HU-049 | HU-040 | HU-042 | Aplicar condicionantes urbanísticas | Retaguarda — Motor de decisão: enquadramento |
| HU-050 | HU-041 | HU-043 | Aplicar restrições especiais | Retaguarda — Motor de decisão: enquadramento |
| HU-051 | HU-042 | HU-044 | Consolidar resultado da análise | Retaguarda — Motor de decisão: enquadramento |
| HU-052 | HU-043 | HU-045 | Registrar fundamentação legal | Retaguarda — Motor de decisão: enquadramento |
| HU-053 | HU-045 | HU-047 | Classificar CNAE por risco | Retaguarda — Motor de decisão: risco |
| HU-054 | HU-046 | HU-048 | Aplicar regra de baixo risco | Retaguarda — Motor de decisão: risco |
| HU-055 | HU-047 | HU-049 | Aplicar regra de médio risco | Retaguarda — Motor de decisão: risco |
| HU-056 | HU-048 | HU-050 | Aplicar regra de alto risco | Retaguarda — Motor de decisão: risco |
| HU-057 | HU-049 | HU-051 | Aplicar exceções de risco | Retaguarda — Motor de decisão: risco |
| HU-058 | HU-050 | HU-052 | Consultar tabela de risco | Retaguarda — Motor de decisão: risco |
| HU-059 | HU-130 | HU-140 | Pré-analisar processo pelo motor | Retaguarda — Fluxo expresso |
| HU-060 | HU-069 | HU-073 | Identificar elegibilidade para fluxo expresso | Retaguarda — Fluxo expresso |
| HU-061 | HU-070 | HU-074 | Deferir automaticamente | Retaguarda — Fluxo expresso |
| HU-062 | HU-071 | HU-075 | Indeferir automaticamente | Retaguarda — Fluxo expresso |
| HU-063 | HU-072 | HU-076 | Emitir resultado expresso | Retaguarda — Fluxo expresso |
| HU-064 | HU-073 | HU-077 | Notificar resultado ao cidadão | Retaguarda — Fluxo expresso |
| HU-065 | HU-074 | HU-078 | Registrar auditoria da decisão automática | Retaguarda — Fluxo expresso |
| HU-066 | HU-125 | HU-134 | Indeferir sem atuação por prazo BAP | Retaguarda — Fluxo expresso |
| HU-067 | HU-137 | HU-148 | Registrar solicitação em contingência | Retaguarda — Entrada de solicitações pela retaguarda |
| HU-068 | HU-139 | HU-150 | Atendimento presencial assistido | Retaguarda — Entrada de solicitações pela retaguarda |
| HU-069 | HU-075 | HU-079 | Encaminhar para análise técnica | Retaguarda — Entrada de solicitações pela retaguarda |
| HU-070 | HU-134 | HU-144 | Fila de trabalho do analista com SLA | Retaguarda — Análise técnica dos processos |
| HU-071 | HU-076 | HU-080 | Distribuir processo | Retaguarda — Análise técnica dos processos |
| HU-072 | HU-077 | HU-081 | Assumir análise | Retaguarda — Análise técnica dos processos |
| HU-073 | HU-078 | HU-082 | Consultar processo | Retaguarda — Análise técnica dos processos |
| HU-074 | HU-126 | HU-135 | Preencher ficha de análise | Retaguarda — Análise técnica dos processos |
| HU-075 | HU-132 | HU-142 | Exibir histórico decisório do imóvel e do CNAE na zona | Retaguarda — Análise técnica dos processos |
| HU-076 | HU-159 | HU-170 | Registrar vistoria | Retaguarda — Análise técnica dos processos |
| HU-077 | HU-127 | HU-136 | Encaminhar para malha fina | Retaguarda — Análise técnica dos processos |
| HU-078 | HU-081 | HU-085 | Emitir parecer | Retaguarda — Análise técnica dos processos |
| HU-079 | HU-082 | HU-086 | Deferir solicitação | Retaguarda — Análise técnica dos processos |
| HU-080 | HU-083 | HU-087 | Indeferir solicitação | Retaguarda — Análise técnica dos processos |
| HU-081 | HU-084 | HU-088 | Aplicar condicionantes | Retaguarda — Análise técnica dos processos |
| HU-082 | HU-085 | HU-089 | Encerrar processo | Retaguarda — Análise técnica dos processos |
| HU-083 | HU-123 | HU-132 | Emitir TVL em PDF no backoffice | Retaguarda — Análise técnica dos processos |
| HU-084 | HU-160 | HU-171 | Reprocessar processo | Retaguarda — Análise técnica dos processos |
| HU-085 | HU-161 | HU-172 | Forçar encaminhamento para análise | Retaguarda — Análise técnica dos processos |
| HU-086 | — | — | Desvincular inscrição de escritório virtual (nova em 20/10) | Retaguarda — Análise técnica dos processos |
| HU-087 | HU-079 | HU-083 | Solicitar pendência | Retaguarda — Pendências e comunicação |
| HU-088 | HU-086 | HU-090 | Notificar pendência | Retaguarda — Pendências e comunicação |
| HU-089 | HU-088 | HU-092 | Reabrir análise | Retaguarda — Pendências e comunicação |
| HU-090 | HU-089 | HU-093 | Notificar vencimentos | Retaguarda — Pendências e comunicação |
| HU-091 | HU-136 | HU-147 | Escalonar processos parados por SLA | Retaguarda — Pendências e comunicação |
| HU-092 | HU-090 | HU-094 | Enviar e-mail | Retaguarda — Pendências e comunicação |
| HU-093 | HU-091 | HU-095 | Enviar WhatsApp | Retaguarda — Pendências e comunicação |
| HU-094 | HU-092 | HU-096 | Consultar histórico de comunicações | Retaguarda — Pendências e comunicação |
| HU-095 | HU-093 | HU-097 | Registrar log das decisões | Retaguarda — Auditoria e conformidade |
| HU-096 | HU-094 | HU-098 | Consultar histórico de alterações | Retaguarda — Auditoria e conformidade |
| HU-097 | HU-095 | HU-099 | Consultar regras aplicadas | Retaguarda — Auditoria e conformidade |
| HU-098 | HU-096 | HU-100 | Consultar trilha de auditoria | Retaguarda — Auditoria e conformidade |
| HU-099 | HU-097 | HU-101 | Exportar auditoria | Retaguarda — Auditoria e conformidade |
| HU-100 | HU-098 | HU-102 | Monitorar conformidade LGPD | Retaguarda — Auditoria e conformidade |
| HU-101 | HU-138 | HU-149 | Detectar padrões de abuso e fraude | Retaguarda — Auditoria e conformidade |
| HU-102 | HU-173 | HU-184 | Consultar log de integrações | Retaguarda — Auditoria e conformidade |
| HU-103 | HU-174 | HU-185 | Auditoria preditiva | Retaguarda — Auditoria e conformidade |
| HU-104 | HU-099 | HU-103 | Integrar com REDESIM | Retaguarda — Integrações externas |
| HU-105 | HU-021 | HU-022 | Importar dados da REDESIM | Retaguarda — Integrações externas |
| HU-106 | HU-100 | HU-104 | Integrar com Junta Comercial | Retaguarda — Integrações externas |
| HU-107 | HU-101 | HU-106 | Integrar com Cadastro Imobiliário | Retaguarda — Integrações externas |
| HU-108 | HU-102 | HU-107 | Integrar com GIS Municipal | Retaguarda — Integrações externas |
| HU-109 | HU-103 | HU-110 | Integrar com SEFAZ municipal | Retaguarda — Integrações externas |
| HU-110 | HU-124 | HU-133 | Vincular protocolo BAP | Retaguarda — Integrações externas |
| HU-111 | HU-104 | HU-112 | Realizar OCR documental | Retaguarda — Inteligência artificial na análise |
| HU-112 | HU-105 | HU-113 | Classificar documentos | Retaguarda — Inteligência artificial na análise |
| HU-113 | HU-106 | HU-114 | Detectar documentos ilegíveis | Retaguarda — Inteligência artificial na análise |
| HU-114 | HU-107 | HU-115 | Identificar inconsistências | Retaguarda — Inteligência artificial na análise |
| HU-115 | HU-109 | HU-117 | Gerar resumo para analista | Retaguarda — Inteligência artificial na análise |
| HU-116 | HU-110 | HU-118 | Sugerir parecer | Retaguarda — Inteligência artificial na análise |
| HU-117 | HU-112 | HU-121 | Assistente virtual do analista | Retaguarda — Inteligência artificial na análise |
| HU-118 | HU-113 | HU-122 | Dashboard executivo | Retaguarda — Relatórios e indicadores |
| HU-119 | HU-114 | HU-123 | Solicitações por período | Retaguarda — Relatórios e indicadores |
| HU-120 | HU-115 | HU-124 | Solicitações por zona | Retaguarda — Relatórios e indicadores |
| HU-121 | HU-116 | HU-125 | Solicitações por CNAE | Retaguarda — Relatórios e indicadores |
| HU-122 | HU-117 | HU-126 | Solicitações por risco | Retaguarda — Relatórios e indicadores |
| HU-123 | HU-118 | HU-127 | Taxa de deferimento | Retaguarda — Relatórios e indicadores |
| HU-124 | HU-119 | HU-128 | Taxa de indeferimento | Retaguarda — Relatórios e indicadores |
| HU-125 | HU-120 | HU-129 | Tempo médio de análise | Retaguarda — Relatórios e indicadores |
| HU-126 | HU-121 | HU-130 | Produtividade por analista | Retaguarda — Relatórios e indicadores |
| HU-127 | HU-122 | HU-131 | Exportar relatórios | Retaguarda — Relatórios e indicadores |
| HU-128 | HU-135 | HU-145 | Relatório de quedas por gatilho | Retaguarda — Relatórios e indicadores |
| HU-129 | HU-162 | HU-173 | Consultar sala de controle | Retaguarda — Relatórios e indicadores |
| HU-130 | HU-163 | HU-174 | Consultar SLA e vencimentos | Retaguarda — Relatórios e indicadores |
| HU-131 | HU-164 | HU-175 | Consultar tempo de emissão da viabilidade | Retaguarda — Relatórios e indicadores |
| HU-132 | HU-165 | HU-176 | Consultar benchmark nacional | Retaguarda — Relatórios e indicadores |
| HU-133 | HU-166 | HU-177 | Relatório de pendências e exigências | Retaguarda — Relatórios e indicadores |
| HU-134 | HU-167 | HU-178 | Relatório de trilha por processo | Retaguarda — Relatórios e indicadores |
| HU-135 | HU-168 | HU-179 | Painel por bairro | Retaguarda — Relatórios e indicadores |
| HU-136 | HU-169 | HU-180 | Relatório de saturação locacional | Retaguarda — Relatórios e indicadores |
| HU-137 | HU-170 | HU-181 | Relatório de escritório virtual | Retaguarda — Relatórios e indicadores |
| HU-138 | HU-171 | HU-182 | Relatório de atendimento em contingência | Retaguarda — Relatórios e indicadores |
| HU-139 | HU-172 | HU-183 | Relatório de falhas de comunicação | Retaguarda — Relatórios e indicadores |
| HU-140 | HU-001 | HU-001 | Cadastrar usuário | Portal — Conta do cidadão |
| HU-141 | HU-005 | HU-005 | Confirmar e-mail | Portal — Conta do cidadão |
| HU-142 | HU-140 | HU-151 | Autenticar pelo portal de serviços da SEDUR (até 21/10/2026: Autenticar com GOV.BR) | Portal — Conta do cidadão |
| HU-143 | HU-004 | HU-004 | Alterar senha | Portal — Conta do cidadão |
| HU-144 | HU-007 | HU-007 | Gerenciar perfil do usuário | Portal — Conta do cidadão |
| HU-145 | HU-006 | HU-006 | Aceitar termo LGPD | Portal — Conta do cidadão |
| HU-146 | HU-010 | HU-010 | Consultar histórico de acessos | Portal — Conta do cidadão |
| HU-147 | HU-008 | HU-008 | Vincular procurador | Portal — Conta do cidadão |
| HU-148 | HU-009 | HU-009 | Revogar procuração | Portal — Conta do cidadão |
| HU-149 | — | — | Consultar painel do cidadão (nova em 20/10) | Portal — Conta do cidadão |
| HU-150 | HU-020 | HU-021 | Consultar dados do CNPJ | Portal — Empresas do cidadão |
| HU-151 | HU-022 | HU-023 | Cadastrar empresa | Portal — Empresas do cidadão |
| HU-152 | HU-023 | HU-024 | Atualizar dados empresariais | Portal — Empresas do cidadão |
| HU-153 | HU-024 | HU-025 | Vincular CNAE principal | Portal — Empresas do cidadão |
| HU-154 | HU-025 | HU-026 | Vincular CNAEs secundários | Portal — Empresas do cidadão |
| HU-155 | HU-026 | HU-027 | Consultar empresas vinculadas | Portal — Empresas do cidadão |
| HU-156 | HU-027 | HU-028 | Encerrar vínculo empresarial | Portal — Empresas do cidadão |
| HU-157 | HU-052 | HU-054 | Consultar viabilidade por endereço | Portal — Consulta prévia de viabilidade |
| HU-158 | HU-053 | HU-055 | Consultar viabilidade por inscrição imobiliária | Portal — Consulta prévia de viabilidade |
| HU-159 | HU-054 | HU-056 | Consultar viabilidade por CNAE | Portal — Consulta prévia de viabilidade |
| HU-160 | HU-055 | HU-057 | Simular enquadramento da atividade | Portal — Consulta prévia de viabilidade |
| HU-161 | HU-056 | HU-058 | Simular classificação de risco | Portal — Consulta prévia de viabilidade |
| HU-162 | HU-057 | HU-059 | Simular restrições urbanísticas | Portal — Consulta prévia de viabilidade |
| HU-163 | HU-058 | HU-060 | Consultar histórico de consultas | Portal — Consulta prévia de viabilidade |
| HU-164 | HU-059 | HU-061 | Criar solicitação de viabilidade | Portal — Solicitação de viabilidade |
| HU-165 | HU-060 | HU-062 | Informar imóvel | Portal — Solicitação de viabilidade |
| HU-166 | HU-061 | HU-063 | Informar área utilizada | Portal — Solicitação de viabilidade |
| HU-167 | HU-062 | HU-064 | Informar atividade econômica | Portal — Solicitação de viabilidade |
| HU-168 | HU-063 | HU-065 | Informar CNAEs complementares | Portal — Solicitação de viabilidade |
| HU-169 | HU-064 | HU-066 | Anexar documentos | Portal — Solicitação de viabilidade |
| HU-170 | HU-065 | HU-067 | Validar documentos obrigatórios | Portal — Solicitação de viabilidade |
| HU-171 | HU-066 | HU-068 | Protocolar solicitação | Portal — Solicitação de viabilidade |
| HU-172 | HU-131 | HU-141 | Simular viabilidade antes de protocolar | Portal — Solicitação de viabilidade |
| HU-173 | HU-068 | HU-070 | Cancelar solicitação | Portal — Solicitação de viabilidade |
| HU-174 | HU-157 | HU-168 | Renovar viabilidade | Portal — Solicitação de viabilidade |
| HU-175 | HU-067 | HU-069 | Consultar protocolo | Portal — Acompanhamento e resposta |
| HU-176 | HU-080 | HU-084 | Receber complementação | Portal — Acompanhamento e resposta |
| HU-177 | HU-087 | HU-091 | Responder pendência | Portal — Acompanhamento e resposta |
| HU-178 | HU-111 | HU-119 | Explicar resultado ao cidadão | Portal — Acompanhamento e resposta |
| HU-179 | HU-108 | HU-116 | Gerar resumo da solicitação | Portal — Acompanhamento e resposta |
| HU-180 | HU-175 | HU-186 | Verificar autenticidade de documento | Portal — Acompanhamento e resposta |
| HU-181 | — | — | Consultar central de notificações (nova em 20/10) | Portal — Acompanhamento e resposta |
| HU-183 | — | — | Manter fichas de análise por analista e histórico de tramitação (nova em 23/10, para validação da SEDUR) | Retaguarda — Análise técnica dos processos |

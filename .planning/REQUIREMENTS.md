# Requirements: SILE — Sistema de Licenciamento Eletrônico

**Defined:** 2026-06-09
**Core Value:** Responder a viabilidade locacional de atividade econômica de forma automática, correta e auditável — fluxo expresso quando a lei permite, fundamentação legal em toda decisão.

Fonte de verdade: 131 Histórias de Usuário em `docs/SILE_HUs_Completas_MD/` (índice: `README-CATALOGO-HUs-SILE.md`). Cada requisito é rastreado pelo ID da HU. Os critérios de aceite BDD (CA) de cada HU viram feature tests PHPUnit — nenhuma HU é concluída sem seus CAs cobertos por testes passando.

## v1 Requirements

### EP01 — Identidade, Acesso e Segurança

- [x] **HU-001**: Cadastrar usuário
- [x] **HU-002**: Autenticar usuário
- [x] **HU-003**: Recuperar senha
- [x] **HU-004**: Alterar senha
- [x] **HU-005**: Confirmar e-mail
- [x] **HU-006**: Aceitar termo LGPD
- [x] **HU-007**: Gerenciar perfil do usuário
- [x] **HU-008**: Vincular procurador
- [x] **HU-009**: Revogar procuração
- [x] **HU-010**: Consultar histórico de acessos

### EP02 — Administração

- [x] **HU-011**: Manter CNAEs
- [x] **HU-012**: Manter usuários
- [x] **HU-013**: Manter perfis
- [x] **HU-014**: Manter parâmetros do sistema
- [ ] **HU-015**: Manter Quadro 7
- [ ] **HU-016**: Manter Quadro 10
- [ ] **HU-017**: Manter Quadro 11
- [ ] **HU-018**: Manter Quadro 11A
- [ ] **HU-019**: Manter condicionantes
- [ ] **HU-020**: Manter classificação de risco

### EP03 — Cadastro Empresarial

- [x] **HU-021**: Consultar dados do CNPJ
- [x] **HU-022**: Importar dados da REDESIM
- [x] **HU-023**: Cadastrar empresa
- [x] **HU-024**: Atualizar dados empresariais
- [ ] **HU-025**: Vincular CNAE principal
- [ ] **HU-026**: Vincular CNAEs secundários
- [x] **HU-027**: Consultar empresas vinculadas
- [x] **HU-028**: Encerrar vínculo empresarial

### EP04 — Georreferenciamento e Território

- [x] **HU-029**: Geocodificar endereço
- [x] **HU-030**: Localizar imóvel no mapa
- [~] **HU-031**: Identificar zona urbanística — BLOQUEADA (pendente SEDUR: zona LOUOS sem fonte vetorial pública)
- [x] **HU-032**: Identificar classificação da via (geometria; atributo LOUOS pendente confirmação SEDUR)
- [~] **HU-033**: Identificar lote — BLOQUEADA (pendente SEDUR: Cadastro Multifinalitário/SEFAZ)
- [x] **HU-034**: Identificar bairro
- [x] **HU-035**: Identificar restrições territoriais
- [x] **HU-036**: Consultar camadas geográficas
- [x] **HU-037**: Validar localização do imóvel (infra de sobreposição pronta; RN-005/lote pendente SEDUR)

### EP05 — Motor de Regras da LOUOS

- [ ] **HU-038**: Enquadrar atividade pelo Quadro 7
- [ ] **HU-039**: Aplicar Quadro 10
- [ ] **HU-040**: Aplicar Quadro 11
- [ ] **HU-041**: Aplicar Quadro 11A
- [ ] **HU-042**: Aplicar condicionantes urbanísticas
- [ ] **HU-043**: Aplicar restrições especiais
- [ ] **HU-044**: Consolidar resultado da análise
- [ ] **HU-045**: Registrar fundamentação legal
- [ ] **HU-046**: Versionar regras da LOUOS

### EP06 — Classificação de Risco

- [ ] **HU-047**: Classificar CNAE por risco
- [ ] **HU-048**: Aplicar regra de baixo risco
- [ ] **HU-049**: Aplicar regra de médio risco
- [ ] **HU-050**: Aplicar regra de alto risco
- [ ] **HU-051**: Aplicar exceções de risco
- [ ] **HU-052**: Consultar tabela de risco
- [ ] **HU-053**: Atualizar classificação de risco

### EP07 — Consulta Prévia de Viabilidade

- [ ] **HU-054**: Consultar viabilidade por endereço
- [ ] **HU-055**: Consultar viabilidade por inscrição imobiliária
- [ ] **HU-056**: Consultar viabilidade por CNAE
- [ ] **HU-057**: Simular enquadramento da atividade
- [ ] **HU-058**: Simular classificação de risco
- [ ] **HU-059**: Simular restrições urbanísticas
- [ ] **HU-060**: Consultar histórico de consultas

### EP08 — Solicitação de Viabilidade

- [ ] **HU-061**: Criar solicitação de viabilidade
- [ ] **HU-062**: Informar imóvel
- [ ] **HU-063**: Informar área utilizada
- [ ] **HU-064**: Informar atividade econômica
- [ ] **HU-065**: Informar CNAEs complementares
- [ ] **HU-066**: Anexar documentos
- [ ] **HU-067**: Validar documentos obrigatórios
- [ ] **HU-068**: Protocolar solicitação
- [ ] **HU-069**: Consultar protocolo
- [ ] **HU-070**: Cancelar solicitação
- [ ] **HU-071**: Gerar DAM da solicitação ⚠️ *pendente de confirmação de escopo com a SEDUR*
- [ ] **HU-072**: Confirmar pagamento do DAM ⚠️ *pendente de confirmação de escopo com a SEDUR*

### EP09 — Fluxo Expresso

- [ ] **HU-073**: Identificar elegibilidade para fluxo expresso
- [ ] **HU-074**: Deferir automaticamente
- [ ] **HU-075**: Indeferir automaticamente
- [ ] **HU-076**: Emitir resultado expresso
- [ ] **HU-077**: Notificar resultado ao cidadão
- [ ] **HU-078**: Registrar auditoria da decisão automática

### EP10 — Análise Técnica SEDUR

- [ ] **HU-079**: Encaminhar para análise técnica
- [ ] **HU-080**: Distribuir processo
- [ ] **HU-081**: Assumir análise
- [ ] **HU-082**: Consultar processo
- [ ] **HU-083**: Solicitar pendência
- [ ] **HU-084**: Receber complementação
- [ ] **HU-085**: Emitir parecer
- [ ] **HU-086**: Deferir solicitação
- [ ] **HU-087**: Indeferir solicitação
- [ ] **HU-088**: Aplicar condicionantes
- [ ] **HU-089**: Encerrar processo

### EP11 — Pendências e Comunicação

- [ ] **HU-090**: Notificar pendência
- [ ] **HU-091**: Responder pendência
- [ ] **HU-092**: Reabrir análise
- [ ] **HU-093**: Notificar vencimentos
- [ ] **HU-094**: Enviar e-mail
- [ ] **HU-095**: Enviar WhatsApp
- [ ] **HU-096**: Consultar histórico de comunicações

### EP12 — Auditoria e Compliance

- [ ] **HU-097**: Registrar log das decisões
- [ ] **HU-098**: Consultar histórico de alterações
- [ ] **HU-099**: Consultar regras aplicadas
- [ ] **HU-100**: Consultar trilha de auditoria
- [ ] **HU-101**: Exportar auditoria
- [ ] **HU-102**: Monitorar conformidade LGPD

### EP13 — Integrações

- [ ] **HU-103**: Integrar com REDESIM
- [ ] **HU-104**: Integrar com Junta Comercial
- [ ] **HU-105**: Integrar com Receita Federal
- [ ] **HU-106**: Integrar com Cadastro Imobiliário
- [ ] **HU-107**: Integrar com GIS Municipal
- [ ] **HU-108**: Integrar com Protocolo Municipal
- [ ] **HU-109**: Integrar com Portal do Contribuinte
- [ ] **HU-110**: Integrar com SEFAZ municipal *(confirmada — API existente; endpoint de deferimento e credenciais pendentes)*
- [ ] **HU-111**: Migrar dados do sistema legado ⚠️ *pendente de confirmação de escopo/estratégia com a SEDUR*

### EP14 — Inteligência Artificial

- [ ] **HU-112**: Realizar OCR documental
- [ ] **HU-113**: Classificar documentos
- [ ] **HU-114**: Detectar documentos ilegíveis
- [ ] **HU-115**: Identificar inconsistências
- [ ] **HU-116**: Gerar resumo da solicitação
- [ ] **HU-117**: Gerar resumo para analista
- [ ] **HU-118**: Sugerir parecer
- [ ] **HU-119**: Explicar resultado ao cidadão
- [ ] **HU-120**: Assistente virtual do cidadão
- [ ] **HU-121**: Assistente virtual do analista

### EP15 — Relatórios e Indicadores

- [ ] **HU-122**: Dashboard executivo
- [ ] **HU-123**: Solicitações por período
- [ ] **HU-124**: Solicitações por zona
- [ ] **HU-125**: Solicitações por CNAE
- [ ] **HU-126**: Solicitações por risco
- [ ] **HU-127**: Taxa de deferimento
- [ ] **HU-128**: Taxa de indeferimento
- [ ] **HU-129**: Tempo médio de análise
- [ ] **HU-130**: Produtividade por analista
- [ ] **HU-131**: Exportar relatórios

## v2 Requirements

(Nenhum — as 131 HUs constituem o escopo do milestone v1; itens marcados como pendentes de confirmação podem migrar para v2/out of scope conforme resposta da SEDUR.)

## Out of Scope

| Feature | Reason |
|---------|--------|
| Features de fachada (resultado simulado, adaptador falso, botão sem ação) | Proibidas pelas regras de execução; dependência indisponível = feature bloqueada e registrada |
| Cálculo de taxa no modelo TVS (SIGVISA) | O SILE usa TLL (atividade de maior valor + taxa de serviço); mecânica de DAM reaproveitável, fórmula não |
| Porte direto do frontend SIGVISA (Vue/PrimeVue) | Stack do SILE é React 19 + Inertia v3; apenas padrões de fluxo/tela servem de referência |

## Pendências de confirmação (SEDUR)

| Item | HUs afetadas | Status |
|------|--------------|--------|
| Escopo de DAM/pagamento dentro do SILE | HU-071, HU-072 | Aguardando confirmação |
| Estratégia de migração/convivência com legado .NET | HU-111 | Aguardando definição |
| Endpoint e credenciais SEFAZ (envio de deferimento) | HU-110 | API confirmada; contrato pendente |
| Correspondência "Quadro 11" ↔ Quadro 11B oficial | HU-017, HU-018, HU-040, HU-041 | Aguardando confirmação |
| Contrato REDESIM/integrador (entrada e devolução) | HU-103, HU-104, HU-022 | Aguardando documentação |
| Base GIS municipal (camadas, formato, acesso) | HU-107, EP04 | Aguardando acesso |
| Quadros parametrizados da LOUOS usados pelo sistema atual | HU-015 a HU-018, EP05 | Aguardando planilhas |

Pauta completa: `docs/ANALISE-HUs-REUNIAO-SEDUR.md` seção 5.

## Traceability

Mapeamento requisito → fase do roadmap (`.planning/ROADMAP.md`). Cada HU pertence a exatamente uma fase. Os mantenedores HU-015 a HU-018 entram na Fase 5 (junto com o motor da LOUOS) e HU-019/HU-020 na Fase 6 (junto com a classificação de risco) — fatia vertical, conforme decisão registrada em PROJECT.md.

| Requirement | Phase | Status |
|-------------|-------|--------|
| HU-001 | Fase 1 | Complete |
| HU-002 | Fase 1 | Complete |
| HU-003 | Fase 1 | Complete |
| HU-004 | Fase 1 | Complete |
| HU-005 | Fase 1 | Complete |
| HU-006 | Fase 1 | Complete |
| HU-007 | Fase 1 | Complete |
| HU-008 | Fase 1 | Complete |
| HU-009 | Fase 1 | Complete |
| HU-010 | Fase 1 | Complete |
| HU-011 | Fase 2 | Complete |
| HU-012 | Fase 2 | Complete |
| HU-013 | Fase 2 | Complete |
| HU-014 | Fase 2 | Complete |
| HU-015 | Fase 5 | Pending |
| HU-016 | Fase 5 | Pending |
| HU-017 | Fase 5 | Pending |
| HU-018 | Fase 5 | Pending |
| HU-019 | Fase 6 | Pending |
| HU-020 | Fase 6 | Pending |
| HU-021 | Fase 3 | Done |
| HU-022 | Fase 3 | Complete (03-03) |
| HU-023 | Fase 3 | Complete (03-04) |
| HU-024 | Fase 3 | Complete (03-05) |
| HU-025 | Fase 3 | Complete (03-06) |
| HU-026 | Fase 3 | Complete (03-06) |
| HU-027 | Fase 3 | Complete (03-04) |
| HU-028 | Fase 3 | Complete (03-05) |
| HU-029 | Fase 4 | Complete (04-03) |
| HU-030 | Fase 4 | Complete (04-06/04-07) |
| HU-031 | Fase 4 | Blocked — pendente SEDUR (zona LOUOS sem fonte pública) |
| HU-032 | Fase 4 | Complete (04-04/04-05) — atributo LOUOS pendente SEDUR |
| HU-033 | Fase 4 | Blocked — pendente SEDUR (lote/SEFAZ) |
| HU-034 | Fase 4 | Complete (04-04/04-05) |
| HU-035 | Fase 4 | Complete (04-04/04-05) |
| HU-036 | Fase 4 | Complete (04-04/04-06/04-07) |
| HU-037 | Fase 4 | Complete (04-06/04-07) — RN-005/lote pendente SEDUR |
| HU-038 | Fase 5 | Pending |
| HU-039 | Fase 5 | Pending |
| HU-040 | Fase 5 | Pending |
| HU-041 | Fase 5 | Pending |
| HU-042 | Fase 5 | Pending |
| HU-043 | Fase 5 | Pending |
| HU-044 | Fase 5 | Pending |
| HU-045 | Fase 5 | Pending |
| HU-046 | Fase 5 | Pending |
| HU-047 | Fase 6 | Pending |
| HU-048 | Fase 6 | Pending |
| HU-049 | Fase 6 | Pending |
| HU-050 | Fase 6 | Pending |
| HU-051 | Fase 6 | Pending |
| HU-052 | Fase 6 | Pending |
| HU-053 | Fase 6 | Pending |
| HU-054 | Fase 7 | Pending |
| HU-055 | Fase 7 | Pending |
| HU-056 | Fase 7 | Pending |
| HU-057 | Fase 7 | Pending |
| HU-058 | Fase 7 | Pending |
| HU-059 | Fase 7 | Pending |
| HU-060 | Fase 7 | Pending |
| HU-061 | Fase 8 | Pending |
| HU-062 | Fase 8 | Pending |
| HU-063 | Fase 8 | Pending |
| HU-064 | Fase 8 | Pending |
| HU-065 | Fase 8 | Pending |
| HU-066 | Fase 8 | Pending |
| HU-067 | Fase 8 | Pending |
| HU-068 | Fase 8 | Pending |
| HU-069 | Fase 8 | Pending |
| HU-070 | Fase 8 | Pending |
| HU-071 | Fase 8 | Pending ⚠️ escopo a confirmar (SEDUR) |
| HU-072 | Fase 8 | Pending ⚠️ escopo a confirmar (SEDUR) |
| HU-073 | Fase 9 | Pending |
| HU-074 | Fase 9 | Pending |
| HU-075 | Fase 9 | Pending |
| HU-076 | Fase 9 | Pending |
| HU-077 | Fase 9 | Pending |
| HU-078 | Fase 9 | Pending |
| HU-079 | Fase 10 | Pending |
| HU-080 | Fase 10 | Pending |
| HU-081 | Fase 10 | Pending |
| HU-082 | Fase 10 | Pending |
| HU-083 | Fase 10 | Pending |
| HU-084 | Fase 10 | Pending |
| HU-085 | Fase 10 | Pending |
| HU-086 | Fase 10 | Pending |
| HU-087 | Fase 10 | Pending |
| HU-088 | Fase 10 | Pending |
| HU-089 | Fase 10 | Pending |
| HU-090 | Fase 11 | Pending |
| HU-091 | Fase 11 | Pending |
| HU-092 | Fase 11 | Pending |
| HU-093 | Fase 11 | Pending |
| HU-094 | Fase 11 | Pending |
| HU-095 | Fase 11 | Pending |
| HU-096 | Fase 11 | Pending |
| HU-097 | Fase 12 | Pending |
| HU-098 | Fase 12 | Pending |
| HU-099 | Fase 12 | Pending |
| HU-100 | Fase 12 | Pending |
| HU-101 | Fase 12 | Pending |
| HU-102 | Fase 12 | Pending |
| HU-103 | Fase 13 | Pending |
| HU-104 | Fase 13 | Pending |
| HU-105 | Fase 13 | Pending |
| HU-106 | Fase 13 | Pending |
| HU-107 | Fase 13 | Pending |
| HU-108 | Fase 13 | Pending |
| HU-109 | Fase 13 | Pending |
| HU-110 | Fase 13 | Pending ⚠️ endpoint/credenciais SEFAZ pendentes |
| HU-111 | Fase 13 | Pending ⚠️ estratégia a confirmar (SEDUR) |
| HU-112 | Fase 14 | Pending |
| HU-113 | Fase 14 | Pending |
| HU-114 | Fase 14 | Pending |
| HU-115 | Fase 14 | Pending |
| HU-116 | Fase 14 | Pending |
| HU-117 | Fase 14 | Pending |
| HU-118 | Fase 14 | Pending |
| HU-119 | Fase 14 | Pending |
| HU-120 | Fase 14 | Pending |
| HU-121 | Fase 14 | Pending |
| HU-122 | Fase 15 | Pending |
| HU-123 | Fase 15 | Pending |
| HU-124 | Fase 15 | Pending |
| HU-125 | Fase 15 | Pending |
| HU-126 | Fase 15 | Pending |
| HU-127 | Fase 15 | Pending |
| HU-128 | Fase 15 | Pending |
| HU-129 | Fase 15 | Pending |
| HU-130 | Fase 15 | Pending |
| HU-131 | Fase 15 | Pending |

**Coverage:**
- v1 requirements: 131 total
- Mapped to phases: 131
- Unmapped: 0 ✓

---
*Requirements defined: 2026-06-09*
*Last updated: 2026-06-09 after roadmap creation (traceability 131/131)*

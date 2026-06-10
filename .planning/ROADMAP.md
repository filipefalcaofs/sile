# Roadmap: SILE — Sistema de Licenciamento Eletrônico

## Overview

O SILE será construído em 15 fases que partem da fundação (identidade, acesso e trilha de auditoria transversal) e dos cadastros estruturantes (CNAEs, usuários, perfis, parâmetros), passam pelos dados territoriais e pelos dois motores de decisão — regras da LOUOS (Lei nº 9.148/2016) e classificação de risco (Decreto nº 32.636/2020) —, sempre parametrizáveis com regras como dados versionados, e então entregam a jornada completa do licenciamento: consulta prévia, solicitação formal, fluxo expresso automático e análise técnica humana com pendências e comunicação. Fecham o ciclo a auditoria/compliance sobre a trilha registrada desde a Fase 1, as integrações reais validadas contra homologação (REDESIM, SEFAZ, GIS, legado), a camada de inteligência artificial e os relatórios gerenciais.

## Justificativa da ordem

A estrutura segue a ordem sugerida em `docs/PROMPT-INICIO-PROJETO-SILE.md`, que reflete as dependências reais entre épicas. Decisões estruturais:

- **Auditoria transversal na Fase 1**: a RN-002 (usuário, data/hora, origem, ação, resultado, versão de regras) é exigida por todas as 131 HUs. O mecanismo nasce como infraestrutura reutilizável na Fase 1; a Fase 12 (EP12) cobre apenas consulta, exportação e LGPD sobre dados já registrados.
- **Mantenedores junto com os motores**: HU-015 a HU-018 (Quadros 7/10/11/11A) entram na Fase 5 e HU-019/HU-020 (condicionantes, risco) na Fase 6 — mantenedor sem motor que consuma os dados é feature morta; juntos formam fatia vertical verificável.
- **Consulta prévia (Fase 7) depois dos motores**: EP07 consome território (Fase 4), motor LOUOS (Fase 5) e risco (Fase 6) — é a primeira entrega que executa o fluxo de decisão de ponta a ponta.
- **Integrações na Fase 13, contratos desde cedo**: os serviços externos ficam atrás de interfaces (contratos) definidas quando o domínio consumidor é construído (ex.: HU-022 na Fase 3 importa dados no formato REDESIM); os adaptadores reais são implementados contra homologação na Fase 13, quando documentação, credenciais e acesso forem disponibilizados pela SEDUR. Sem acesso disponível = feature explicitamente bloqueada, nunca adaptador falso.
- **Paralelização possível** (config `parallelization: true`): Fase 3 ‖ Fase 4 (não dependem entre si) e Fase 5 ‖ Fase 6 (motores independentes que convergem na Fase 7).

## Critério de pronto transversal (todas as fases)

1. Todos os CAs BDD das HUs da fase cobertos por feature tests PHPUnit passando (`php artisan test --compact`).
2. Funcionalidade navegável de ponta a ponta no browser, executando lógica real — nunca simulada, sem etapas "em construção" disfarçadas de prontas.
3. Nenhum valor de negócio hardcoded — parametrização via HU-014; funcionalidades acopláveis com feature toggle administrável.
4. Integrações da fase validadas contra homologação real, quando houver.
5. `vendor/bin/pint --dirty --format agent` sem pendências.

## Phases

**Phase Numbering:**
- Fases inteiras (1, 2, 3): trabalho planejado do milestone
- Fases decimais (2.1, 2.2): inserções urgentes (marcadas com INSERTED)

- [ ] **Phase 1: Identidade, Acesso e Auditoria Transversal** — Autenticação, perfis, procuração e trilha de auditoria reutilizável (EP01)
- [ ] **Phase 2: Administração Base** — CNAEs, usuários, perfis e parâmetros do sistema (EP02 parcial)
- [ ] **Phase 3: Cadastro Empresarial** — Empresas, CNPJ e vínculos com CNAEs (EP03)
- [ ] **Phase 4: Georreferenciamento e Território** — Geocodificação, zona, via, lote, bairro e restrições (EP04)
- [ ] **Phase 5: Motor de Regras da LOUOS** — Quadros 7/10/11/11A como dados versionados + motor de enquadramento (EP05 + HU-015 a HU-018)
- [ ] **Phase 6: Classificação de Risco** — Risco por CNAE com condicionante-pergunta reclassificadora (EP06 + HU-019, HU-020)
- [ ] **Phase 7: Consulta Prévia de Viabilidade** — Simulação consumindo território + motores (EP07)
- [ ] **Phase 8: Solicitação de Viabilidade** — Processo formal: criação, documentos, protocolo (EP08)
- [ ] **Phase 9: Fluxo Expresso** — Deferimento/indeferimento automático com documento e auditoria (EP09)
- [ ] **Phase 10: Análise Técnica SEDUR** — Distribuição, análise humana, parecer e decisão (EP10)
- [ ] **Phase 11: Pendências e Comunicação** — Notificações, respostas e canais administráveis (EP11)
- [ ] **Phase 12: Auditoria e Compliance** — Consulta, exportação e LGPD sobre a trilha registrada (EP12)
- [ ] **Phase 13: Integrações** — REDESIM, Junta, Receita, GIS, SEFAZ e legado contra homologação real (EP13)
- [ ] **Phase 14: Inteligência Artificial** — OCR, resumos, sugestão de parecer e assistentes (EP14)
- [ ] **Phase 15: Relatórios e Indicadores** — Dashboard executivo e relatórios exportáveis (EP15)

## Phase Details

### Phase 1: Identidade, Acesso e Auditoria Transversal
**Goal**: Usuários (requerentes, analistas, gestores) acessam o sistema com segurança — cadastro, autenticação, perfis e procuração — e toda ação fica registrada por um mecanismo de auditoria reutilizável desde o primeiro dia.
**Depends on**: Nada (primeira fase)
**Requirements**: HU-001, HU-002, HU-003, HU-004, HU-005, HU-006, HU-007, HU-008, HU-009, HU-010
**UI hint**: yes (cadastro, login, recuperação de senha, perfil, procuração, histórico de acessos)
**Success Criteria** (o que deve ser VERDADE):
  1. Usuário consegue se cadastrar, confirmar e-mail, autenticar, recuperar e alterar senha.
  2. Usuário aceita o termo LGPD no primeiro acesso e gerencia os dados do próprio perfil.
  3. Requerente vincula e revoga procurador, e ações feitas em nome de terceiros ficam identificadas.
  4. Usuário consulta o próprio histórico de acessos.
  5. Toda ação relevante gera registro de auditoria com usuário, data/hora, origem, ação, resultado e versão de regras — mecanismo único (RN-002) reutilizável por todas as fases seguintes.
**Plans**: 9 plans

Plans:
- [x] 01-01-PLAN.md — Wave 0/fundação: dependências (Fortify/permission/activitylog), pt-BR, config/sile.php + Settings, SecurityHeaders (wave 1) ✓ 2026-06-09
- [x] 01-02-PLAN.md — Auditoria transversal RN-002: activitylog estendido, HasAuditoria, AuditService, 403 auditado, access_logs + listeners (wave 2) ✓ 2026-06-10
- [x] 01-03-PLAN.md — Perfis/permissões seedados, rotas portal × gestão, landing pública, shared props, layouts e dashboards (wave 3) ✓ 2026-06-10
- [x] 01-04-PLAN.md — HU-001 + HU-005: cadastro com CPF validado, papel cidadao e confirmação de e-mail (wave 4) ✓ 2026-06-10
- [x] 01-05-PLAN.md — HU-006: termo LGPD versionado com middleware de aceite e re-aceite por versão (wave 5) ✓ 2026-06-10
- [ ] 01-06-PLAN.md — HU-002 + HU-003 + HU-004: login por perfil com lockout, recuperação e alteração de senha (wave 6)
- [x] 01-07-PLAN.md — HU-008 + HU-009: procuração e representação "em nome de" com revogação imediata (wave 6) ✓ 2026-06-10
- [ ] 01-08-PLAN.md — HU-007 + HU-010: perfil do usuário e histórico de acessos portal/gestão (wave 7)
- [ ] 01-09-PLAN.md — Seeds dev + verificação integral + smoke E2E navegável (checkpoint humano) (wave 8)

### Phase 2: Administração Base
**Goal**: Administradores mantêm os cadastros estruturantes (CNAEs, usuários, perfis) e os parâmetros de negócio do sistema sem depender de desenvolvedor.
**Depends on**: Phase 1
**Requirements**: HU-011, HU-012, HU-013, HU-014
**UI hint**: yes (telas administrativas de CNAEs, usuários, perfis e parâmetros)
**Success Criteria** (o que deve ser VERDADE):
  1. Administrador consulta e mantém a tabela de CNAEs, carregada com a estrutura oficial CNAE-Subclasses 2.3 (IBGE/CONCLA, 1.331 códigos).
  2. Administrador gerencia usuários (ativação, inativação, vínculo de perfil) pela interface.
  3. Administrador cria e edita perfis com permissões granulares por funcionalidade.
  4. Administrador altera parâmetros de negócio por interface, com tipo, validação, valor padrão, histórico auditado e efeito sem novo deploy.
**Plans**: TBD

### Phase 3: Cadastro Empresarial
**Goal**: Empresas são cadastradas e mantidas com seus CNAEs e vínculos com usuários, prontas para sustentar solicitações de viabilidade.
**Depends on**: Phase 2 (CNAEs, perfis). Pode executar em paralelo com a Phase 4.
**Requirements**: HU-021, HU-022, HU-023, HU-024, HU-025, HU-026, HU-027, HU-028
**UI hint**: yes (cadastro e consulta de empresas, vínculos de CNAE)
**Success Criteria** (o que deve ser VERDADE):
  1. Requerente consulta dados de um CNPJ e cadastra a empresa com seus dados.
  2. Dados no formato REDESIM são importados e criam/atualizam o cadastro empresarial — lógica real de importação atrás de contrato; a conexão com o integrador é a HU-103 (Fase 13).
  3. Empresa possui CNAE principal e CNAEs secundários vinculados a partir da tabela oficial.
  4. Usuário consulta as empresas às quais está vinculado, atualiza dados e encerra vínculo, com auditoria.
**Plans**: TBD

### Phase 4: Georreferenciamento e Território
**Goal**: O sistema localiza imóveis no território de Salvador e identifica zona urbanística, via, lote, bairro e restrições — insumos do motor de regras.
**Depends on**: Phase 2 (parâmetros). Pode executar em paralelo com a Phase 3.
**Requirements**: HU-029, HU-030, HU-031, HU-032, HU-033, HU-034, HU-035, HU-036, HU-037
**UI hint**: yes (mapa interativo, confirmação de localização, camadas)
**Success Criteria** (o que deve ser VERDADE):
  1. Endereço informado é geocodificado e o imóvel é exibido em mapa interativo.
  2. Para uma localização, o sistema identifica zona urbanística, classificação da via, lote e bairro a partir das camadas carregadas.
  3. Restrições territoriais incidentes são identificadas e as camadas geográficas são consultáveis no mapa.
  4. Localização do imóvel é validada (confirmada ou ajustada pelo usuário) antes de prosseguir nos fluxos de viabilidade.
**Plans**: TBD

Nota: a geocodificação (Nominatim/OSM) e a lógica de identificação operam sobre camadas carregadas de dados oficiais da LOUOS; a base GIS municipal oficial (camadas, formato, acesso) está pendente com a SEDUR — a integração viva é a HU-107 (Fase 13).

### Phase 5: Motor de Regras da LOUOS
**Goal**: O motor aplica os quadros da LOUOS (7, 10, 11, 11A) de forma parametrizável — regras como dados versionados, nunca código — consolidando resultado com fundamentação legal.
**Depends on**: Phases 2 e 4. Pode executar em paralelo com a Phase 6.
**Requirements**: HU-015, HU-016, HU-017, HU-018, HU-038, HU-039, HU-040, HU-041, HU-042, HU-043, HU-044, HU-045, HU-046
**UI hint**: yes (mantenedores dos quadros, visualização do resultado da análise)
**Success Criteria** (o que deve ser VERDADE):
  1. Administrador mantém os Quadros 7, 10, 11 e 11A como dados versionados, com vigência e histórico auditado.
  2. Motor enquadra a atividade (grupo/subgrupo de uso) pelo Quadro 7 usando a área informada pelo requerente.
  3. Motor verifica a permissão da atividade na zona (Quadro 10) e as condições de instalação pela via (Quadros 11/11A).
  4. Condicionantes urbanísticas e restrições especiais são aplicadas e o resultado é consolidado em parecer único (permitido / permitido com condições / não permitido).
  5. Toda execução do motor registra fundamentação legal e a versão das regras aplicadas.
**Plans**: TBD

Nota: a correspondência "Quadro 11" ↔ Quadro 11B oficial e as planilhas parametrizadas vigentes estão pendentes de confirmação com a SEDUR; o motor nasce parametrizável e recebe a carga oficial quando entregue (seeds derivados da Lei nº 9.148/2016 até lá).

### Phase 6: Classificação de Risco
**Goal**: CNAEs classificados por risco municipal (Decreto nº 32.636/2020) com condicionantes operacionalizadas como perguntas que reclassificam o risco, determinando o encaminhamento (expresso ou análise humana).
**Depends on**: Phase 2 (CNAEs). Pode executar em paralelo com a Phase 5.
**Requirements**: HU-019, HU-020, HU-047, HU-048, HU-049, HU-050, HU-051, HU-052, HU-053
**UI hint**: yes (mantenedores de condicionantes e risco, consulta da tabela de risco)
**Success Criteria** (o que deve ser VERDADE):
  1. Administrador mantém condicionantes e classificação de risco como dados versionados, com seed oficial do Decreto nº 32.636/2020 (767 Baixo A / 328 Baixo B / 236 Alto).
  2. Sistema classifica qualquer CNAE por risco, mantendo risco municipal e risco sanitário como dimensões separadas.
  3. Condicionante operacionalizada como pergunta ao requerente reclassifica o risco conforme a resposta (mecanismo "DI" do decreto).
  4. Regras de baixo, médio e alto risco produzem o encaminhamento correto, incluindo exceções por localização (CNAE de alto risco liberado expressamente conforme local).
  5. Tabela de risco vigente é consultável e atualizável com trilha de auditoria.
**Plans**: TBD

### Phase 7: Consulta Prévia de Viabilidade
**Goal**: Cidadão consulta a viabilidade de uma atividade em um endereço sem criar processo formal — primeira entrega que executa o fluxo de decisão de ponta a ponta.
**Depends on**: Phases 4, 5 e 6
**Requirements**: HU-054, HU-055, HU-056, HU-057, HU-058, HU-059, HU-060
**UI hint**: yes (consulta pública, resultado da simulação, histórico)
**Success Criteria** (o que deve ser VERDADE):
  1. Cidadão consulta a viabilidade por endereço, por inscrição imobiliária ou por CNAE, sem criar processo formal.
  2. Simulação retorna enquadramento, classificação de risco e restrições urbanísticas com fundamentação legal — executando os motores reais das Fases 5 e 6.
  3. Usuário autenticado consulta o histórico das próprias consultas.
**Plans**: TBD

### Phase 8: Solicitação de Viabilidade
**Goal**: Requerente cria, instrui e protocola a solicitação formal de viabilidade que alimentará o fluxo expresso e a análise técnica.
**Depends on**: Phases 3 e 4
**Requirements**: HU-061, HU-062, HU-063, HU-064, HU-065, HU-066, HU-067, HU-068, HU-069, HU-070, HU-071 ⚠, HU-072 ⚠
**UI hint**: yes (formulário multi-etapas, anexos, protocolo)
**Success Criteria** (o que deve ser VERDADE):
  1. Requerente cria solicitação informando imóvel, área utilizada, atividade principal e CNAEs complementares.
  2. Documentos são anexados e a obrigatoriedade documental por CNAE é validada antes do protocolo.
  3. Solicitação é protocolada com número único rastreável; o protocolo é consultável e a solicitação pode ser cancelada conforme regras.
  4. (Condicional — bloqueada) DAM gerado e pagamento conciliado, caso a SEDUR confirme o escopo de HU-071/HU-072.
**Plans**: TBD

⚠ HU-071 (Gerar DAM) e HU-072 (Confirmar pagamento do DAM): **pendentes de confirmação de escopo com a SEDUR** — não implementar antes da confirmação; se confirmadas, a mecânica de DAM do SIGVISA é a referência (fórmula TLL própria do SILE).

### Phase 9: Fluxo Expresso
**Goal**: Solicitações elegíveis são deferidas ou indeferidas automaticamente — obrigação legal para baixo risco — com documento emitido, notificação e auditoria integral da decisão.
**Depends on**: Phases 5, 6 e 8
**Requirements**: HU-073, HU-074, HU-075, HU-076, HU-077, HU-078
**UI hint**: yes (resultado expresso, documento TVL com verificação pública)
**Success Criteria** (o que deve ser VERDADE):
  1. Sistema identifica automaticamente a elegibilidade da solicitação para o fluxo expresso conforme risco e regras vigentes.
  2. Deferimento e indeferimento automáticos executam os motores reais (LOUOS + risco) e produzem decisão fundamentada.
  3. Resultado expresso é emitido como documento com verificação pública de autenticidade.
  4. Cidadão é notificado do resultado pelos canais habilitados.
  5. Decisão automática fica integralmente auditada: dados de entrada, regras aplicadas, versão das regras e resultado.
**Plans**: TBD

### Phase 10: Análise Técnica SEDUR
**Goal**: Analistas da SEDUR recebem, distribuem, analisam e decidem os processos não elegíveis ao fluxo expresso, com parecer fundamentado.
**Depends on**: Phases 8 e 9
**Requirements**: HU-079, HU-080, HU-081, HU-082, HU-083, HU-084, HU-085, HU-086, HU-087, HU-088, HU-089
**UI hint**: yes (fila de análise, painel do analista, emissão de parecer)
**Success Criteria** (o que deve ser VERDADE):
  1. Solicitações não elegíveis ao expresso são encaminhadas e distribuídas para a fila de análise técnica.
  2. Analista assume o processo, consulta todo o conteúdo da solicitação e solicita pendências ao requerente.
  3. Complementação recebida retorna o processo ao fluxo de análise.
  4. Analista emite parecer fundamentado e defere/indefere, aplicando condicionantes quando cabíveis.
  5. Processo é encerrado com status final e trilha completa.
**Plans**: TBD

### Phase 11: Pendências e Comunicação
**Goal**: Requerentes são notificados de pendências e vencimentos pelos canais configurados e respondem pelo próprio sistema, reabrindo a análise.
**Depends on**: Phase 10
**Requirements**: HU-090, HU-091, HU-092, HU-093, HU-094, HU-095, HU-096
**UI hint**: yes (central de pendências, resposta do requerente, histórico de comunicações)
**Success Criteria** (o que deve ser VERDADE):
  1. Requerente é notificado de pendências e responde diretamente pelo sistema.
  2. Resposta de pendência reabre a análise automaticamente.
  3. Vencimentos e prazos geram notificações automáticas conforme parâmetros administráveis.
  4. E-mail e WhatsApp funcionam como canais administráveis por feature toggle, com histórico de comunicações consultável e degradação controlada quando desativados.
**Plans**: TBD

### Phase 12: Auditoria e Compliance
**Goal**: A trilha de auditoria registrada desde a Fase 1 é consultável, exportável e monitorada para conformidade com a LGPD.
**Depends on**: Phase 1 (infraestrutura de auditoria); dados acumulados das Fases 1 a 11
**Requirements**: HU-097, HU-098, HU-099, HU-100, HU-101, HU-102
**UI hint**: yes (consulta da trilha, filtros, exportação, painel LGPD)
**Success Criteria** (o que deve ser VERDADE):
  1. Gestor consulta logs de decisões e histórico de alterações com filtros (período, usuário, entidade, ação).
  2. Para qualquer decisão, as regras aplicadas e suas versões são consultáveis.
  3. Trilha de auditoria completa é consultável e exportável.
  4. Conformidade LGPD é monitorada (consentimentos, acessos a dados pessoais, retenção).
**Plans**: TBD

### Phase 13: Integrações
**Goal**: Serviços externos integrados de verdade — cada adaptador implementado atrás de contrato e validado contra o ambiente de homologação real, com evidência de chamada registrada.
**Depends on**: Phases 3, 8, 9 e 10
**Requirements**: HU-103, HU-104, HU-105, HU-106, HU-107, HU-108, HU-109, HU-110 ⚠, HU-111 ⚠
**UI hint**: no (adaptadores backend; telas de credenciais/teste de conexão seguem o padrão de parametrização da HU-014)
**Success Criteria** (o que deve ser VERDADE):
  1. Solicitações entram via integrador federal (REDESIM/Junta Comercial) e o parecer é devolvido ao integrador — validado em homologação real.
  2. Deferimento de viabilidade é enviado à SEFAZ municipal via API com evidência de chamada real em homologação.
  3. Receita Federal, Cadastro Imobiliário, GIS municipal, Protocolo e Portal do Contribuinte integrados atrás de contratos, cada um validado contra ambiente real.
  4. Dados do sistema legado .NET migrados/em convivência conforme estratégia confirmada com a SEDUR.
**Plans**: TBD

**Bloqueios conhecidos da fase** (detalhes em `docs/ANALISE-HUs-REUNIAO-SEDUR.md` seção 5):
- Contrato REDESIM/integrador (entrada da solicitação e devolução do parecer) — aguardando documentação (HU-103, HU-104).
- Base GIS municipal (camadas, formato, acesso) — aguardando acesso (HU-107).
- HU-110 (SEFAZ): **confirmada** — API existente, base conhecida via SIGVISA; **endpoint específico de envio do deferimento e credenciais SenhaWeb pendentes**.
- HU-111 (migração do legado): **pendente de confirmação de estratégia com a SEDUR**.
- Acesso a ambiente de homologação (integrador/SEFAZ/GIS) — solicitado.

Regra da fase: integração sem documentação/credencial/acesso permanece **explicitamente bloqueada** neste roadmap e no STATE.md — nunca adaptador falso para "destravar".

### Phase 14: Inteligência Artificial
**Goal**: IA acelera a análise documental e a comunicação — OCR, classificação, inconsistências, resumos, sugestão de parecer e assistentes — sempre com toggle administrável e configuração multi-provider.
**Depends on**: Phases 8 e 10
**Requirements**: HU-112, HU-113, HU-114, HU-115, HU-116, HU-117, HU-118, HU-119, HU-120, HU-121
**UI hint**: yes (resultados de análise documental, resumos, assistentes no portal e na retaguarda)
**Success Criteria** (o que deve ser VERDADE):
  1. Documentos anexados passam por OCR, classificação automática e detecção de ilegibilidade.
  2. Inconsistências entre documentos e dados informados são apontadas ao analista.
  3. Resumos automáticos da solicitação são gerados para o requerente e para o analista.
  4. IA sugere parecer ao analista e explica o resultado ao cidadão em linguagem simples.
  5. Assistentes virtuais (cidadão e analista) respondem com base no contexto real do processo; toda função de IA tem toggle administrável e configuração multi-provider (provider, modelo, credencial criptografada, teste de conexão).
**Plans**: TBD

### Phase 15: Relatórios e Indicadores
**Goal**: Gestores acompanham a operação com dashboard executivo e relatórios exportáveis calculados sobre os dados reais do sistema.
**Depends on**: Phases 8, 9 e 10
**Requirements**: HU-122, HU-123, HU-124, HU-125, HU-126, HU-127, HU-128, HU-129, HU-130, HU-131
**UI hint**: yes (dashboard, relatórios com filtros, exportação)
**Success Criteria** (o que deve ser VERDADE):
  1. Gestor acompanha dashboard executivo com indicadores reais da operação.
  2. Relatórios por período, zona, CNAE e risco refletem os dados reais das solicitações.
  3. Taxas de deferimento/indeferimento e tempo médio de análise são calculados sobre os processos reais.
  4. Produtividade por analista é consultável e todos os relatórios são exportáveis.
**Plans**: TBD

## Pendências de confirmação (SEDUR)

| Item | HUs afetadas | Fase | Status |
|------|--------------|------|--------|
| Escopo de DAM/pagamento dentro do SILE | HU-071, HU-072 | 8 | Aguardando confirmação — não implementar antes |
| Endpoint e credenciais SEFAZ (envio de deferimento) | HU-110 | 13 | API confirmada; contrato e credenciais pendentes |
| Estratégia de migração/convivência com legado .NET | HU-111 | 13 | Aguardando definição |
| Contrato REDESIM/integrador | HU-022, HU-103, HU-104 | 3, 13 | Aguardando documentação |
| Base GIS municipal (camadas, formato, acesso) | HU-107 e EP04 | 4, 13 | Aguardando acesso |
| Quadros parametrizados vigentes da LOUOS; "Quadro 11" ↔ 11B | HU-015 a HU-018, HU-040, HU-041 | 5 | Aguardando planilhas e confirmação |
| Acesso a ambiente de homologação | EP13 | 13 | Solicitado |

Pauta completa: `docs/ANALISE-HUs-REUNIAO-SEDUR.md` seção 5. Nenhum desses itens bloqueia as Fases 1 a 3; os motores (Fases 5 e 6) nascem parametrizáveis e recebem a carga oficial quando entregue.

## Progress

**Execution Order:**
As fases executam em ordem numérica: 1 → 2 → 3 → … → 15. Pares paralelizáveis: 3 ‖ 4 e 5 ‖ 6.

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Identidade, Acesso e Auditoria Transversal | 1/9 | In progress | - |
| 2. Administração Base | 0/TBD | Not started | - |
| 3. Cadastro Empresarial | 0/TBD | Not started | - |
| 4. Georreferenciamento e Território | 0/TBD | Not started | - |
| 5. Motor de Regras da LOUOS | 0/TBD | Not started | - |
| 6. Classificação de Risco | 0/TBD | Not started | - |
| 7. Consulta Prévia de Viabilidade | 0/TBD | Not started | - |
| 8. Solicitação de Viabilidade | 0/TBD | Not started | - |
| 9. Fluxo Expresso | 0/TBD | Not started | - |
| 10. Análise Técnica SEDUR | 0/TBD | Not started | - |
| 11. Pendências e Comunicação | 0/TBD | Not started | - |
| 12. Auditoria e Compliance | 0/TBD | Not started | - |
| 13. Integrações | 0/TBD | Not started | - |
| 14. Inteligência Artificial | 0/TBD | Not started | - |
| 15. Relatórios e Indicadores | 0/TBD | Not started | - |

**Cobertura de requisitos:** 131/131 HUs mapeadas (HU-001 a HU-131) — nenhum órfão, nenhuma duplicada. Rastreabilidade detalhada em `.planning/REQUIREMENTS.md`.

---
*Roadmap criado: 2026-06-09*

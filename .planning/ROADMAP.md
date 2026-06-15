# Roadmap: SILE — Sistema de Licenciamento Eletrônico

## Overview

O SILE será construído em 15 fases que partem da fundação (identidade, acesso e trilha de auditoria transversal) e dos cadastros estruturantes (CNAEs, usuários, perfis, parâmetros), passam pelos dados territoriais e pelos dois motores de decisão — regras da LOUOS (Lei nº 9.148/2016) e classificação de risco (Decreto nº 32.636/2020) —, sempre parametrizáveis com regras como dados versionados, e então entregam a jornada completa do licenciamento: consulta prévia, solicitação formal, fluxo expresso automático e análise técnica humana com pendências e comunicação. Fecham o ciclo a auditoria/compliance sobre a trilha registrada desde a Fase 1, as integrações reais validadas contra homologação (REDESIM, SEFAZ, GIS, legado), a camada de inteligência artificial e os relatórios gerenciais.

## Justificativa da ordem

A estrutura segue a ordem sugerida em `docs/PROMPT-INICIO-PROJETO-SILE.md`, que reflete as dependências reais entre épicas. Decisões estruturais:

- **Auditoria transversal na Fase 1**: a RN-002 (usuário, data/hora, origem, ação, resultado, versão de regras) é exigida por todas as HUs. O mecanismo nasce como infraestrutura reutilizável na Fase 1; a Fase 12 (EP12) cobre apenas consulta, exportação e LGPD sobre dados já registrados.
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
6. Telas novas responsivas (mobile-first no portal do cidadão) e acessíveis (WCAG/eMAG — obrigação legal de órgão público); validação visual inclui viewport mobile.
7. Observabilidade mínima nas rotas novas: logs estruturados e medição de duração (base para SLO de p95 — resposta de engenharia à reclamação "sistema muito lento" do legado).
8. Toda tela de gestão com datatable nasce com **exportação CSV/XLSX/PDF** do conjunto filtrado, via componente compartilhado (padrão HU-131 RN-004 a RN-009: assíncrono acima de limiar, permissões/LGPD, auditada). Listagens já entregues nas Fases 1–2 recebem retrofit quando o componente for construído.

## Phases

**Phase Numbering:**
- Fases inteiras (1, 2, 3): trabalho planejado do milestone
- Fases decimais (2.1, 2.2): inserções urgentes (marcadas com INSERTED)

- [x] **Phase 1: Identidade, Acesso e Auditoria Transversal** — Autenticação, perfis, procuração e trilha de auditoria reutilizável (EP01) — concluída em 2026-06-10 (verificação: passed 47/47; smoke E2E aprovado)
- [x] **Phase 2: Administração Base** — CNAEs, usuários, perfis e parâmetros do sistema (EP02 parcial) — concluída em 2026-06-10 (verificação: passed 41/41; smoke E2E aprovado)
- [x] **Phase 2.1 (INSERTED): Template TailAdmin** — aplica o template visual TailAdmin (React 19 + Tailwind v4) em todos os layouts e páginas existentes, sem mudança de comportamento — concluída em 2026-06-10 (197/197 testes, typecheck/build verdes, validação visual por screenshots)
- [x] **Phase 2.2 (INSERTED): Refinamento premium de UI/UX** — landing institucional completa, sidebar subdividida em grupos, auth premium com painel institucional, CRUDs padronizados (modais de criar/editar, ConfirmDialog em ações destrutivas, Pagination com contador, EmptyState) — concluída em 2026-06-10 (197/197 testes, typecheck/build verdes, screenshots validados)
- [x] **Phase 2.3 (INSERTED): Segregação de rotas portal × retaguarda** — portal público do cidadão sob /portal/* (landing, login, cadastro, painel) e login interno próprio da retaguarda em /gestao/login; redirecionamento de guests por contexto (padrão Laravel) — concluída em 2026-06-10 (201/201 testes, pint/typecheck/build verdes, screenshots validados)
- [x] **Phase 2.4 (INSERTED): Template SaaS de listagens e dashboard** — biblioteca de componentes reutilizáveis (DataTable tipada com ordenação/filtros/busca/page size, KPI cards, PageHeader, Card, Skeleton, Avatar, ProgressBar, TableAction) aplicada às listagens reais da gestão e KPIs reais no dashboard; re-tematização "Console SEDUR" (sidebar escura permanente na gestão, KPIs com wells coloridos, thead com fundo, densidade compacta) — concluída em 2026-06-11 (209/209 testes com 8 novos, pint/typecheck/build verdes, screenshots validados claro/escuro/mobile; corrige busca case-sensitive no PostgreSQL)
- [x] **Phase 3: Cadastro Empresarial** — Empresas, CNPJ e vínculos com CNAEs (EP03) — concluída em 2026-06-13 (verificação: passed; suíte 367/367, grupo Companies 83/83; smoke E2E navegável com chamada REAL à BrasilAPI e import REDESIM idempotente)
- [x] **Phase 3.1 (INSERTED): Fundação assíncrona — scheduler, jobs e retenção** — ativa capacidades prontas do Laravel 13 ainda não usadas (levantamento 2026-06-12): scheduler com primeira rotina real, importações REDESIM/CNAE como jobs em fila com retry e relatório, throttle parametrizado nas rotas públicas, retenção de access_logs via pruning agendado e retry/backoff no HTTP client — infraestrutura habilitadora para prazo BAP (HU-134), SLA (HU-144/147) e integrações (HU-146) — concluída em 2026-06-13 (verificação: passed 5/5; suíte 385/385; produção documentada em docs/deploy/producao-assincrona.md)
- [ ] **Phase 3.2 (INSERTED): Autenticação GOV.BR no portal** — Login Único (OAuth/OIDC, Authorization Code + PKCE S256) convivendo com o login local: vínculo determinístico por CPF, criação de conta real no primeiro acesso, nível de confiabilidade parametrizável, credenciais sensíveis administráveis (HU-014) e toggle `features.govbr_login` (HU-151); sem credenciamento da SEDUR a validação contra staging real permanece bloqueada — spec `docs/superpowers/specs/2026-06-12-autenticacao-govbr-design.md`
- [x] **Phase 4: Georreferenciamento e Território** — Geocodificação, zona, via, lote, bairro e restrições (EP04) — concluída em 2026-06-13 (verificação: passed 5/5; suíte 448/448 SQLite + 15/15 @group postgis; geocodificação Nominatim e consulta espacial reais). ESCOPO HONESTO: bairro/via/restrições entregues com dado público real do GeoSalvador; **zona urbanística (HU-031) e lote cadastral (HU-033) BLOQUEADOS pendente SEDUR** (sem fonte vetorial pública — comunicados na UI, nunca inventados). A zona é insumo crítico da Fase 5.
- [x] **Phase 5: Motor de Regras da LOUOS** — Quadros 7/10/11/11A como dados versionados + motor de enquadramento (EP05 + HU-015 a HU-018) — concluída em 2026-06-14 (guardião de entrega: APROVADO; suíte 600/600 SQLite + 15/15 @group postgis; motor real decidindo permitido/não permitido/pendente). ESCOPO HONESTO: Quadro 7 (área) entregue com seed real da Lei 9.148/2016; **Quadro 10 (permissão por zona) degrada para "pendente" sem a zona — BLOQUEADO pendente SEDUR (SIGIS/CA 2000)**; Quadros 11/11A parciais (atributo viário LOUOS pendente). Reusa rule_versions (Fase 6); sandbox HU-143 com 4 olhos.
- [x] **Phase 6: Classificação de Risco** — Risco por CNAE com condicionante-pergunta reclassificadora (EP06 + HU-019, HU-020) — concluída em 2026-06-14 (guardião de entrega: APROVADO; suíte 519/519 SQLite + 15/15 @group postgis; motor real classificando sobre o Decreto 32.636/2020 com encaminhamento parametrizável e auditoria). Regras como dados versionados (rule_versions — infra herdada pela Fase 5)
- [x] **Phase 7: Consulta Prévia de Viabilidade** — Simulação consumindo território + motores (EP07) — concluída em 2026-06-14 (guardião de entrega: APROVADO; suíte 657/657 SQLite + 15/15 @group postgis; comando viabilidade:consultar com geocode real). PRIMEIRO fluxo de decisão de ponta a ponta: orquestra território (4) + LOUOS (5) + risco (6) e PROPAGA o veredito (não recomputa). Honesto: risco/Quadro 7/restrições reais; veredito locacional fica "pendente" sem a zona (bloqueada SEDUR); inscrição imobiliária bloqueada atrás do contrato PropertyRegistryLookup.
- [x] **Phase 8: Solicitação de Viabilidade** — Processo formal: criação, documentos, protocolo (EP08) — implementada; smoke navegável aguardando aprovação humana; DAM bloqueado → Fase 13
- [x] **Phase 9: Fluxo Expresso** — Deferimento/indeferimento automático, Regin + SEFAZ, prazo BAP (EP09 + HU-134) — implementada em 2026-06-14 (12/12 planos; suíte 931/931 SQLite + 22/22 @group postgis; motor real deferindo/indeferindo/encaminhando, evidência `expresso:decidir` → DEFERIDA + TVL). Regin/SEFAZ (HU-104/110) e HU-134 ativa bloqueados → Fase 13; TVL PDF (HU-132) → Fase 10; zona oficial Quadro 10 pendente SEDUR (sem ela, degradação honesta em_analise). Smoke navegável aguardando aprovação humana.
- [x] **Phase 10: Análise Técnica SEDUR** — Fila com SLA, ficha pré-analisada pelo motor, precedentes, malha fina, TVL PDF backoffice (EP10 + HU-132/135/136/140/142/144) — implementada em 2026-06-15 (18/18 planos; suíte 1161/1161 = SQLite + @group postgis com POSTGIS_TESTS_REQUIRED; decisão humana real flow `analise_tecnica` + TVL, evidência `analise:decidir 7` → DEFERIDA + TVL-2026-000003). Regin/SEFAZ (HU-104/110) → Fase 13; HU-083/084 pleno → EP11; export HU-131 → Fase 15; zona oficial Quadro 10 e **roteamento automático ao setor** pendentes SEDUR (no dev: caixa de triagem). Smoke navegável aguardando aprovação humana.
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
- [x] 01-06-PLAN.md — HU-002 + HU-003 + HU-004: login por perfil com lockout, recuperação e alteração de senha (wave 6) ✓ 2026-06-10
- [x] 01-07-PLAN.md — HU-008 + HU-009: procuração e representação "em nome de" com revogação imediata (wave 6) ✓ 2026-06-10
- [x] 01-08-PLAN.md — HU-007 + HU-010: perfil do usuário e histórico de acessos portal/gestão (wave 7) ✓ 2026-06-10
- [x] 01-09-PLAN.md — Seeds dev + verificação integral + smoke E2E navegável (checkpoint humano) (wave 8) ✓ 2026-06-10

### Phase 2: Administração Base
**Goal**: Administradores mantêm os cadastros estruturantes (CNAEs, usuários, perfis) e os parâmetros de negócio do sistema sem depender de desenvolvedor.
**Depends on**: Phase 1
**Requirements**: HU-011, HU-012, HU-013, HU-014 *(novas HUs de administração descobertas no legado entram nas fases consumidoras: HU-137 feriados e HU-138 setores antes da Fase 9/10; HU-139 edifícios comerciais na Fase 8 — ver fases respectivas)*
**UI hint**: yes (telas administrativas de CNAEs, usuários, perfis e parâmetros)
**Success Criteria** (o que deve ser VERDADE):
  1. Administrador consulta e mantém a tabela de CNAEs, carregada com a estrutura oficial CNAE-Subclasses 2.3 (IBGE/CONCLA, 1.331 códigos).
  2. Administrador gerencia usuários (ativação, inativação, vínculo de perfil) pela interface.
  3. Administrador cria e edita perfis com permissões granulares por funcionalidade.
  4. Administrador altera parâmetros de negócio por interface, com tipo, validação, valor padrão, histórico auditado e efeito sem novo deploy.
**Plans**: 8 plans

Plans:
- [x] 02-01-PLAN.md — Fundação: conversão xlsx→CSV oficial (Wave 0) + permissões granulares com seeder aditivo (wave 1) ✓ 2026-06-10
- [x] 02-02-PLAN.md — Registry de parâmetros: tabela parameters, Settings banco+cache+fallback e catálogo seedado (wave 1) ✓ 2026-06-10
- [x] 02-03-PLAN.md — Inativação de usuário: bloqueio no login (Fortify) e middleware de sessão ativa (wave 1) ✓ 2026-06-10
- [x] 02-04-PLAN.md — HU-011: import oficial de 1.331 CNAEs com relatório auditado + CRUD + tela com busca (wave 2) ✓ 2026-06-10
- [x] 02-05-PLAN.md — HU-012: gestão de usuários (busca, papel, inativação auditada) + link para acessos — fecha concern da Fase 1 (wave 3) ✓ 2026-06-10
- [x] 02-06-PLAN.md — HU-013: perfis com permissões granulares, proteções estruturais e anti-lockout (wave 4) ✓ 2026-06-10
- [x] 02-07-PLAN.md — HU-014: tela de parâmetros, CA-05 efeito sem deploy, CA-06 toggle real de procurações, CA-07 histórico (wave 5) ✓ 2026-06-10
- [ ] 02-08-PLAN.md — Fechamento: seeds dev integrados, navegação da gestão, verificação integral + smoke E2E (checkpoint humano) (wave 6)

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
**Plans**: 9 plans

Plans:
- [x] 03-01-PLAN.md — Fundação: ValidCnpj alfanumérico, schema companies/vínculos/CNAEs, parâmetros novos e pendência herdada do CnaeController (wave 1) ✓ 2026-06-12
- [x] 03-02-PLAN.md — HU-021: contrato CnpjLookup + provider BrasilAPI real com cache/toggle + endpoint auditado (wave 2) ✓ 2026-06-12
- [x] 03-03-PLAN.md — HU-022: payload REDESIM de referência + RedesimImportService + comando redesim:importar (wave 2) ✓ 2026-06-12
- [x] 03-04-PLAN.md — HU-023 + HU-027: policy com representação, cadastro transacional com vínculo e listagem Minhas empresas (wave 3) ✓ 2026-06-12
- [x] 03-05-PLAN.md — HU-024 + HU-028: detalhe/atualização com CNPJ imutável e encerramento de vínculo com proteção do último responsável (wave 4) ✓ 2026-06-12
- [x] 03-06-PLAN.md — HU-025 + HU-026: CompanyCnaeService transacional, endpoints de CNAE e busca server-side da tabela oficial (wave 5) ✓ 2026-06-12
- [x] 03-07-PLAN.md — UI: sidebar + tela Minhas empresas + página de cadastro com lookup vivo (wave 6, ‖ 03-08) ✓ 2026-06-12
- [x] 03-08-PLAN.md — UI: página de detalhe (dados, CNAEs com ConfirmDialog, vínculos) (wave 6, ‖ 03-07) ✓ 2026-06-12
- [x] 03-09-PLAN.md — Fechamento: seeds dev + verificação integral + smoke E2E com chamada real ao provider (wave 7) ✓ 2026-06-13

### Phase 3.1: Fundação assíncrona — scheduler, jobs e retenção (INSERTED)
**Goal**: O sistema opera com a infraestrutura assíncrona que o Laravel 13 entrega pronta e que ainda não usamos — scheduler, jobs em fila com retry e retenção de dados — fechando lacunas que travariam prazos automáticos, SLA e integrações nas fases seguintes.
**Depends on**: Phase 3
**Requirements**: Nenhuma HU nova — infraestrutura habilitadora: HU-011/HU-022 (importações como jobs), HU-021 (throttle e retry no lookup CNPJ), HU-010/LGPD (retenção de access_logs), HU-134/HU-144/HU-146/HU-147 (rotinas agendadas consumidas nas Fases 9–13)
**UI hint**: no (infraestrutura; visibilidade via auditoria e parâmetros já existentes)
**Success Criteria** (o que deve ser VERDADE):
  1. Scheduler do Laravel ativo em dev (`composer dev`) e documentado para produção, com pelo menos uma rotina real agendada, idempotente e auditada — o padrão que HU-134 (prazo BAP) e HU-147 (escalonamento por SLA) reutilizam.
  2. Importações REDESIM e CNAE executam como jobs em fila com retry, timeout e relatório auditado (`Bus::batch` onde houver volume); jobs falhos ficam visíveis e reprocessáveis (`failed_jobs`), nunca falha silenciosa.
  3. Rotas públicas têm rate limiting (`throttle`) com limites parametrizados via HU-014 — consulta de CNPJ hoje; padrão estabelecido para protocolo (HU-069) e consulta prévia (EP07).
  4. Access_logs têm retenção parametrizada aplicada por pruning agendado (`MassPrunable`) — sem crescimento ilimitado; a trilha de auditoria de decisões (RN-002) fica FORA do pruning (retenção longa por compliance; política completa na Fase 12).
  5. HTTP client de integrações com retry/backoff parametrizado (lookup CNPJ hoje; padrão herdado pelos adaptadores do EP13).
**Plans**: 5 plans

Plans:
- [x] 03.1-01-PLAN.md — Fundação: 5 parâmetros novos (retenção, throttle, retries/timeout/backoff) + fallbacks em config/sile.php + testes de seeder (wave 1) ✓ 2026-06-13
- [x] 03.1-02-PLAN.md — Jobs REDESIM/CNAE com retry/timeout/backoff, flag --queue, comando cnae:importar e visibilidade de failed_jobs (wave 1) ✓ 2026-06-13
- [x] 03.1-03-PLAN.md — Scheduler ativo (composer dev) + pruning diário de access_logs com retenção parametrizada; activity_log fora (wave 2) ✓ 2026-06-13
- [x] 03.1-04-PLAN.md — Throttle parametrizado na consulta de CNPJ + HTTP client com retries/timeout/backoff parametrizados (wave 2) ✓ 2026-06-13
- [x] 03.1-05-PLAN.md — Documentação de produção assíncrona + verificação integral fresca da fase (wave 3) ✓ 2026-06-13

Nota: fase originada do levantamento "Laravel 13 — recursos prontos não usados" (2026-06-12). Demais recursos identificados ficaram anotados nas fases consumidoras: Storage/URLs assinadas (Fases 8 e 10), atomic locks e eventos de domínio (Fase 9), canal database de notificações (Fase 11), `Concurrency`/`Http::pool`/`Queue::route()` (Fase 13).

### Phase 3.2: Autenticação GOV.BR no portal (INSERTED)
**Goal**: Cidadão entra no portal com a conta GOV.BR (Login Único) — convivendo com o login local — com vínculo determinístico por CPF, criação de conta real no primeiro acesso e governança completa por parâmetros (credenciais criptografadas, URLs, nível mínimo e toggle).
**Depends on**: Phase 1 (identidade/CPF, auditoria), Phase 2 (registry de parâmetros)
**Requirements**: HU-151
**UI hint**: yes (botão "Entrar com gov.br" no login e no cadastro do portal)
**Success Criteria** (o que deve ser VERDADE):
  1. Com toggle ligado e credenciais configuradas, o botão aparece e o fluxo Authorization Code + PKCE S256 (state + nonce + id_token validado via JWK) executa contra a URL parametrizada (staging/produção sem deploy).
  2. CPF do `sub` vincula conta existente ou cria conta real (papel `cidadao`, e-mail verificado conforme claim, termo LGPD exigido no primeiro acesso); e-mail nunca vincula sozinho (conflito = bloqueio comunicado).
  3. Nível de confiabilidade mínimo é parametrizável (default bronze); conta abaixo do mínimo e conta local inativada não autenticam, com mensagens claras e auditoria.
  4. Toggle desligado ou credenciais ausentes = botão oculto e rotas degradando com aviso — nunca falha silenciosa.
  5. Todos os CAs BDD da HU-151 cobertos por feature tests PHPUnit passando.
**Plans**: 1 plan

Nota: feature entra completa e DESLIGADA por default — o credenciamento da SEDUR no Login Único (Termo de Adesão, Secretaria de Governo Digital) é pendência externa registrada; a validação contra `sso.staging.acesso.gov.br` real é critério de conclusão da integração (regra do projeto: nunca adaptador falso). Spec aprovada: `docs/superpowers/specs/2026-06-12-autenticacao-govbr-design.md`.

### Phase 4: Georreferenciamento e Território
**Goal**: O sistema localiza imóveis no território de Salvador e identifica zona urbanística, via, lote, bairro e restrições — insumos do motor de regras.
**Depends on**: Phase 2 (parâmetros). Pode executar em paralelo com a Phase 3.
**Requirements**: HU-029, HU-030, HU-031, HU-032, HU-033, HU-034, HU-035, HU-036, HU-037
**UI hint**: yes (mapa interativo, confirmação de localização, camadas)
**Success Criteria** (o que deve ser VERDADE):
  1. Endereço informado é geocodificado e o imóvel é exibido em mapa interativo.
  2. Para uma localização, o sistema identifica zona urbanística, classificação da via, lote e bairro a partir das camadas carregadas.
  3. Restrições territoriais incidentes são identificadas e as camadas geográficas são consultáveis no mapa.
  4. Localização do imóvel é validada (confirmada ou ajustada pelo usuário) antes de prosseguir nos fluxos de viabilidade; polígono comparado ao lote oficial com alerta por baixa sobreposição (HU-037 RN-004).
  5. Camadas geográficas são **dados versionados com vigência** (HU-036 RN-004): decisões registram a versão da camada consultada e a reprodução usa a versão da época — mesma disciplina do versionamento de regras.
**Plans**: 8 plans

Plans:
- [x] 04-01-PLAN.md — Fundação PostGIS: migrations geo_layers/geo_features driver-aware, models/enums, infra de teste PostGIS (wave 1) ✓ 2026-06-13
- [x] 04-02-PLAN.md — Catálogo de parâmetros (25→29) + permissão consultar-territorio (wave 1) ✓ 2026-06-13
- [x] 04-03-PLAN.md — HU-029: contrato Geocoder + NominatimGeocoder + throttle/retry + endpoint auditado (wave 2) ✓ 2026-06-13
- [x] 04-04-PLAN.md — HU-036: camadas versionadas, comando geo:importar auditado + carga real GeoSalvador (bairro/via/restrição); zona/lote pendente_fonte (wave 2) ✓ 2026-06-13
- [x] 04-05-PLAN.md — HU-031–035: TerritoryService + SpatialRepository PostGIS (bairro/via/restrição reais; zona/lote indisponível) (wave 3) ✓ 2026-06-13
- [x] 04-06-PLAN.md — HU-030/036/037: backend do mapa (página + identificar + validar-localizacao com sobreposição parametrizada) (wave 4) ✓ 2026-06-13
- [x] 04-07-PLAN.md — HU-030/036/037 UI: mapa Leaflet reutilizável + página de consulta territorial (wave 5) ✓ 2026-06-13
- [x] 04-08-PLAN.md — Fechamento: verificação integral + evidência real (Nominatim + consulta espacial sobre dado oficial) (wave 6) ✓ 2026-06-13

Nota: a geocodificação (Nominatim/OSM) e a lógica de identificação operam sobre camadas carregadas de dados oficiais da LOUOS; a base GIS municipal oficial é o **SIGIS** com migração **S69 → CA 2000** (reunião SEDUR 2026-06-11) — a integração viva é a HU-107 (Fase 13). Polígono de 4 pontos validado no formulário Regin (HU-062).

**Escopo honesto da fase (sem fachada — confirmado pela pesquisa 2026-06-13):** entregáveis com dado público REAL do GeoSalvador — **bairro** (HU-034), **eixo viário** (HU-032) e **restrições ambientais** (HU-035). **BLOQUEADAS pendente SEDUR** (sem fonte vetorial pública): **zona urbanística LOUOS** (HU-031, só PDF) e **lote cadastral** (HU-033, SEFAZ restrito) — e por consequência **HU-037 RN-005** (divergência por inscrição imobiliária). A arquitetura (camadas versionadas, TerritoryService, sobreposição, mapa) entra completa e genérica; as camadas bloqueadas são modeladas como `pendente_fonte` (feature_count 0) e comunicadas na UI — nunca polígono inventado. Quando a SEDUR entregar a base, a carga muda, a lógica não.

### Phase 5: Motor de Regras da LOUOS
**Goal**: O motor aplica os quadros da LOUOS (7, 10, 11, 11A) de forma parametrizável — regras como dados versionados, nunca código — consolidando resultado com fundamentação legal.
**Depends on**: Phases 2 e 4. Pode executar em paralelo com a Phase 6.
**Requirements**: HU-015, HU-016, HU-017, HU-018, HU-038, HU-039, HU-040, HU-041, HU-042, HU-043, HU-044, HU-045, HU-046, HU-143 (sandbox de parametrização — pode concluir na Fase 6)
**UI hint**: yes (mantenedores dos quadros, visualização do resultado da análise)
**Success Criteria** (o que deve ser VERDADE):
  1. Administrador mantém os Quadros 7, 10, 11 e 11A como dados versionados, com vigência e histórico auditado.
  2. Motor enquadra a atividade (grupo/subgrupo de uso) pelo Quadro 7 usando a área informada pelo requerente.
  3. Motor verifica a permissão da atividade na zona (Quadro 10) e as condições de instalação pela via (Quadros 11/11A).
  4. Condicionantes urbanísticas e restrições especiais são aplicadas e o resultado é consolidado em parecer único (permitido / permitido com condições / não permitido).
  5. Toda execução do motor registra fundamentação legal e a versão das regras aplicadas.
  6. Gestor simula o impacto de uma alteração de regra contra processos/cenários reais antes de publicar (HU-143 — sandbox; publicação 4 olhos para domínios sensíveis).
  7. Suíte de **golden cases** do motor (casos de entrada → resultado esperado, validados pela SEDUR) roda verde no CI a cada mudança de regra ou deploy — proteção contra regressão de domínio, complementar ao sandbox da HU-143.
**Plans**: 9 plans

Plans:
- [x] 05-01-PLAN.md — Fundação: RuleDomain louos_* (reusa rule_versions) + tabelas tipadas + enums + DTOs EnquadramentoInput/Result + parâmetros vagas/sandbox (wave 1)
- [x] 05-02-PLAN.md — Seeds: Quadro 7 REAL (Lei 9.148/2016) + Quadros 10/11/11A modelados, versionados e auditados + distribuição (wave 2)
- [x] 05-03-PLAN.md — Motor Quadro 7: enquadramento por área + resolução de versão (3 modos) + auditoria (HU-038/046) (wave 3)
- [x] 05-04-PLAN.md — Motor Quadro 10 (degradação sem zona) + Quadros 11/11A parciais (HU-039/040/041) (wave 4)
- [x] 05-05-PLAN.md — Consolidação + fundamentação legal + vagas + restrições ZEIS (HU-044/045/042/043) (wave 5)
- [x] 05-06-PLAN.md — Mantenedores backend dos Quadros: permissões + publicação 4-olhos (HU-015/016/017/018) (wave 3, ‖ 05-03)
- [x] 05-07-PLAN.md — Mantenedores UI no console + navegação (HU-015..018) (wave 4, ‖ 05-04)
- [x] 05-08-PLAN.md — Sandbox HU-143: simula rascunho contra cenários reais (motor real) + publicação 4-olhos (wave 6)
- [x] 05-09-PLAN.md — Golden cases (#[DataProvider], requer_zona=pendente) + comando louos:enquadrar + verificação integral (wave 7)

Nota: a correspondência "Quadro 11" ↔ Quadro 11B oficial e as planilhas parametrizadas vigentes estão pendentes de confirmação com a SEDUR; o motor nasce parametrizável e recebe a carga oficial quando entregue (seeds derivados da Lei nº 9.148/2016 até lá). Mapeamento de HUs confirmado pelos arquivos oficiais: HU-015=Quadro 7, HU-016=Quadro 10, HU-017=Quadro 11, HU-018=Quadro 11A (o spec havia invertido 015↔016).

### Phase 6: Classificação de Risco
**Goal**: CNAEs classificados por risco municipal (Decreto nº 32.636/2020) com condicionantes operacionalizadas como perguntas que reclassificam o risco, determinando o encaminhamento — **baixo e médio risco → fluxo expresso**; **alto risco → análise humana**; gatilhos CNAE parametrizados derrubam casos pontuais para análise (semi-expresso).
**Depends on**: Phase 2 (CNAEs). Pode executar em paralelo com a Phase 5.
**Requirements**: HU-019, HU-020, HU-047, HU-048, HU-049, HU-050, HU-051, HU-052, HU-053
**UI hint**: yes (mantenedores de condicionantes e risco, consulta da tabela de risco)
**Success Criteria** (o que deve ser VERDADE):
  1. Administrador mantém condicionantes e classificação de risco como dados versionados, com seed oficial do Decreto nº 32.636/2020 (767 Baixo A / 328 Baixo B / 236 Alto).
  2. Sistema classifica qualquer CNAE por risco, mantendo risco municipal e risco sanitário como dimensões separadas.
  3. Condicionante operacionalizada como pergunta ao requerente reclassifica o risco conforme a resposta (mecanismo "DI" do decreto).
  4. Regras de baixo risco (baixo_a/baixo_b do Decreto — o decreto municipal NÃO tem "médio"; o "médio operacional" mapeia para baixo_b via parâmetro `risco.mapa_encaminhamento`) produzem encaminhamento ao fluxo expresso quando elegíveis; alto risco e gatilhos CNAE encaminham à análise técnica, incluindo exceções por localização (HU-051).
  5. Tabela de risco vigente é consultável e atualizável com trilha de auditoria.
  6. Golden cases de classificação de risco (incluindo reclassificação por condicionante-pergunta) integram a suíte de regressão de domínio iniciada na Fase 5.
**Plans**: 9 plans

Plans:
- [x] 06-01-PLAN.md — Fundação: regras como dados versionados (rule_versions + RuleVersion + RuleVersionService openDraft/publish/4-olhos + enums) — infra que a Fase 5 herda (wave 1)
- [x] 06-02-PLAN.md — HU-020/047: classificação municipal — risk_classifications + seed oficial do Decreto 32.636/2020 (767/328/236) versionado e auditado (wave 2)
- [x] 06-03-PLAN.md — HU-019/047/048: dimensão sanitária (VISA) separada + condicionante-pergunta com regra de reclassificação (golden 1031-7/00) (wave 3)
- [x] 06-04-PLAN.md — HU-049/050/051: encaminhamento parametrizável (mapa risk_level→fluxo + dimensao_tvl) + gatilhos (tabela) + DTOs RiscoInput/RiscoResult (wave 4)
- [x] 06-05-PLAN.md — HU-047/048/049/050/051: motor RiscoClassificationService (2 dimensões separadas + reclassificação + encaminhamento + gatilhos/ZEIS + auditoria) (wave 5)
- [x] 06-06-PLAN.md — HU-019/020/052/053: mantenedores backend + consulta da tabela vigente + publicação versionada 4-olhos + permissões (wave 6, ‖ 06-07)
- [x] 06-07-PLAN.md — Golden cases entrada→esperado (#[DataProvider]) sobre o seed real + regressão da distribuição 767/328/236 (wave 6, ‖ 06-06)
- [x] 06-08-PLAN.md — HU-019/020/052/053 UI: telas do console (consulta/publicação de risco + condicionantes) + navegação por permissão (wave 7)
- [x] 06-09-PLAN.md — Fechamento: comando risco:classificar (evidência real) + verificação integral + checkpoint humano (wave 8)

### Phase 7: Consulta Prévia de Viabilidade
**Goal**: Cidadão consulta a viabilidade de uma atividade em um endereço sem criar processo formal — primeira entrega que executa o fluxo de decisão de ponta a ponta.
**Depends on**: Phases 4, 5 e 6
**Requirements**: HU-054, HU-055, HU-056, HU-057, HU-058, HU-059, HU-060
**UI hint**: yes (consulta pública, resultado da simulação, histórico)
**Success Criteria** (o que deve ser VERDADE):
  1. Cidadão consulta a viabilidade por endereço, por inscrição imobiliária ou por CNAE, sem criar processo formal.
  2. Simulação retorna enquadramento, classificação de risco e restrições urbanísticas com fundamentação legal — executando os motores reais das Fases 5 e 6.
  3. Usuário autenticado consulta o histórico das próprias consultas.
**Plans**: 10 plans

Plans:
- [x] 07-01-PLAN.md — Fundação: parâmetros toggle/throttle + RateLimiter + fallbacks + 2 testes de seeder (33→35) (wave 1)
- [x] 07-02-PLAN.md — HU-055: contrato PropertyRegistryLookup (inscrição bloqueada) + provider indisponível + binding (wave 1, ‖ 07-01)
- [x] 07-03-PLAN.md — HU-060: persistência viability_queries (tabela imutável + model + factory) (wave 1, ‖ 07-01)
- [x] 07-04-PLAN.md — DTOs ConsultaViabilidadeInput/Result (veredito propagado + versoes de todas as regras) (wave 1, ‖ 07-01)
- [x] 07-05-PLAN.md — ConsultaViabilidadeService: orquestra Geocoder→Território→LOUOS→Risco; propaga degradação; audita (HU-054/056/055) (wave 2)
- [x] 07-06-PLAN.md — Controller público + rotas + requests + throttle + toggle + render JSON (HU-054/055/056) (wave 3)
- [x] 07-07-PLAN.md — HU-060: persistência quando autenticado + listagem escopada ao dono (wave 4)
- [x] 07-08-PLAN.md — UI pública: 3 entradas + mapa reusado + resultado honesto (HU-054/055/056/057/058/059) (wave 4, ‖ 07-07)
- [x] 07-09-PLAN.md — HU-060 UI: página do histórico autenticado + navegação (wave 5)
- [x] 07-10-PLAN.md — Golden cases de consulta + comando viabilidade:consultar + verificação integral (checkpoint) (wave 6)

### Phase 8: Solicitação de Viabilidade
**Goal**: Requerente cria, instrui e protocola a solicitação formal de viabilidade que alimentará o fluxo expresso e a análise técnica.
**Depends on**: Phases 3 e 4
**Requirements**: HU-061, HU-062, HU-063, HU-064, HU-065, HU-066, HU-067, HU-068, HU-069, HU-070, HU-071, HU-072, HU-139, HU-141, HU-148, HU-150
**UI hint**: yes (formulário multi-etapas — incl. embed Regin — anexos, protocolo)
**Success Criteria** (o que deve ser VERDADE):
  1. Requerente cria solicitação com origem (Regin ou portal direto) e tipo de serviço parametrizável, informando imóvel (polígono, fachada, escritório virtual, área pública, complemento por edifício comercial — HU-139), área utilizada, atividade principal e CNAEs complementares (até 99).
  2. Documentos são anexados e a obrigatoriedade documental por CNAE é validada antes do protocolo.
  3. Solicitação é protocolada com número único rastreável; o protocolo é consultável (status em linguagem simples, timeline e prazo estimado real para o cidadão — HU-069) e a solicitação pode ser cancelada conforme regras.
  4. Analista **visualiza** DAM de viabilidade quando emitido pela SEFAZ (HU-071); status de pagamento sincronizado quando aplicável (HU-072) — **sem geração de DAM** no SILE para fluxo Regin. **[BLOQUEADO → Fase 13]** — escopo SEFAZ não confirmado; nenhum contrato agora (registrado, não simulado; 08-16).
  5. Antes do protocolo, o requerente vê a simulação de viabilidade com os dados digitados (HU-141 — motores reais do EP07; orientativa, não bloqueia) e é alertado de duplicidade/reincidência (HU-061 RN-007) e de inconsistências área × polígono (HU-063/HU-037).
  6. Operador autorizado registra solicitação em **contingência** com origem auditada (HU-148) — também é o caminho de operação real enquanto o contrato Regin não chega — e atende cidadão presencialmente "em nome de" com trilha completa (HU-150, reusando a representação da Fase 1).
**Plans**: 16 plans

Plans:
- [x] 08-01-PLAN.md — Fundação: schema driver-aware (todas as tabelas), enums Status/Origin (com ganchos), models+factories, ProtocolNumberGenerator e ViabilityRequestStateMachine (wave 1, ‖ 08-02)
- [x] 08-02-PLAN.md — Fundação: 13 parâmetros novos (grupo solicitacao) + fallbacks + 5 permissões aditivas; catálogo 35→48, permissões 14→19 (wave 1, ‖ 08-01)
- [x] 08-03-PLAN.md — HU-061 RN-005: tipos de serviço (CRUD backend + UI console) (wave 2, ‖ 08-04)
- [x] 08-04-PLAN.md — HU-067: requisitos documentais por CNAE (CRUD + vínculo N:N + UI console) (wave 2, ‖ 08-03)
- [x] 08-05-PLAN.md — HU-061: criar rascunho + ViabilityRequestPolicy (representação) + duplicidade RN-007 (wave 3)
- [x] 08-06-PLAN.md — HU-062/063: imóvel (polígono+geometry driver-aware+TerritoryService+validação) + área/tolerância — @group postgis (wave 4, ‖ 08-07)
- [x] 08-07-PLAN.md — HU-064/065: atividade principal + CNAEs complementares (até 99) (wave 4, ‖ 08-06)
- [x] 08-08-PLAN.md — HU-066/067: anexar (Storage, disk param, sha256) + DocumentRequirementResolver (wave 5, ‖ 08-09)
- [x] 08-09-PLAN.md — HU-141: SimulacaoSolicitacaoService reusando o motor da Fase 7 (ponto+CNAE) (wave 5, ‖ 08-08)
- [x] 08-10-PLAN.md — HU-068: protocolar (número único, transição, SolicitacaoProtocolada+listener, bloqueio documental, ciência) — concorrência @group postgis (wave 6)
- [x] 08-11-PLAN.md — HU-069: consultar protocolo (autenticado + público assinado + throttle + timeline) backend+UI (wave 7, ‖ 08-12/14/15)
- [x] 08-12-PLAN.md — HU-070: cancelar (estados canceláveis parametrizáveis + transição auditada) backend+UI (wave 7, ‖ 08-11/14/15)
- [x] 08-13-PLAN.md — UI: wizard de solicitação (imóvel/área/atividades/documentos/simulação/protocolar) + Minhas solicitações (wave 8)
- [x] 08-14-PLAN.md — HU-148: contingência (mesmo fluxo/motor, origem auditada) backend+UI console (wave 7, ‖ 08-15)
- [x] 08-15-PLAN.md — HU-150: atendimento presencial assistido (AssistedAttendance + middleware reusando representação) backend+UI (wave 7, ‖ 08-14)
- [x] 08-16-PLAN.md — Fechamento: seeds dev + comando + golden/smoke + verificação integral (@group postgis) + smoke navegável (checkpoint humano) (wave 9)

Nota (reunião SEDUR 2026-06-11): HU-071/072 tiveram escopo **revisado** — DAM de viabilidade via Regin é da SEFAZ; SILE consulta/exibe. Renovação direta pelo portal Simplifica pode ter regras distintas (confirmar).

Nota (recursos do framework — levantamento 2026-06-12): anexos de documentos via Storage/Filesystem com disk parametrizado (local/S3) e download por streaming; consulta de protocolo sem login (HU-069) com URLs temporárias assinadas (`temporarySignedRoute`); protocolo dispara evento de domínio (`SolicitacaoProtocolada`) — primeiro evento próprio do sistema, base para notificação, auditoria e integrações desacopladas (Fases 9, 11 e 13).

Status (fechamento 2026-06-14, plano 08-16): 16/16 planos implementados; verificação integral FRESCA verde (pint/typecheck/build; suíte 848/848 = 829 SQLite + 19 @group postgis). Critérios 1, 2, 3, 5 e 6 validados com evidência fresca (seeds dev + comando `solicitacao:protocolar` + golden/smoke provando bloqueio documental e pendente sem zona). **Critério 4 (DAM HU-071/072) BLOQUEADO → Fase 13** (escopo SEFAZ não confirmado; não simulado). Origem `regin` bloqueada → Fase 13 (contingência HU-148 é o caminho real). Pendências SEDUR: lista oficial de tipos de serviço (seed mínimo, substituível sem deploy), requisitos documentais por CNAE (`cnae_document_requirement` vazio até a planilha), HU-139 edifício comercial (texto livre), prazo estimado real (HU-129/Fase 15), zona urbanística (Quadro 10), ciência presencial e regra fina de duplicidade/cancelamento (HU-150/HU-070). **Gate restante: aprovação do smoke navegável (checkpoint humano).**

### Phase 9: Fluxo Expresso
**Goal**: Solicitações elegíveis (baixo e médio risco) são deferidas ou indeferidas automaticamente — com parecer ao Regin, envio à SEFAZ quando deferido, indeferimento por prazo BAP e auditoria integral. PDF/TVL **não** vai ao cidadão (HU-132 na Fase 10).
**Depends on**: Phases 5, 6 e 8
**Requirements**: HU-073, HU-074, HU-075, HU-076, HU-077, HU-078, HU-134
**UI hint**: yes (resultado expresso na retaguarda; cidadão acompanha via Regin)
**Success Criteria** (o que deve ser VERDADE):
  1. Sistema identifica automaticamente a elegibilidade da solicitação para o fluxo expresso conforme risco e regras vigentes.
  2. Deferimento e indeferimento automáticos executam os motores reais (LOUOS + risco) e produzem decisão fundamentada.
  3. Resultado expresso comunica parecer ao **Regin/Junta** (HU-104) e envia dados à **SEFAZ** quando deferido (HU-110); número de produto TVL registrado para emissão PDF no backoffice (HU-132).
  4. Processos sem BAP vinculado no prazo parametrizado são indeferidos automaticamente (HU-134).
  5. Cidadão é notificado pelos canais habilitados **sem** anexo de TVL (HU-077).
  6. Decisão automática fica integralmente auditada: dados de entrada, regras aplicadas, versão das regras e resultado.
**Plans**: 12 planos (12/12 implementados) — planejados e executados em 2026-06-14 (7 waves do CONTEXT)

Status (fechamento 2026-06-14, plano 09-12): 12/12 planos implementados; verificação integral FRESCA verde (pint limpo; tsc/build; suíte 953/953 = 931 SQLite + 22 @group postgis). Critérios 1, 2, 5 e 6 validados com evidência fresca: o motor real defere/indefere/encaminha (seeds dev + zona fictícia SÓ dev/teste tornam um deferimento NAVEGÁVEL — `expresso:decidir 8` → DEFERIDA + TVL-2026-000001; `expresso:decidir 9` → EM ANÁLISE honesto sem zona; golden/smoke travam os três caminhos + a degradação anti-fachada). **Critério 3 (Regin HU-104 / SEFAZ HU-110) com a DECISÃO e a AUDITORIA reais, mas a TRANSMISSÃO BLOQUEADA → Fase 13** (contratos `Unavailable*` auditam pendência `bloqueado`, nunca "enviado"). **Critério 4 (HU-134 prazo BAP) com a rotina/estado/parâmetro prontos e DORMENTES (no-op) → ativação na Fase 13** (depende do Regin alimentar `bap_due_at`). TVL PDF (HU-132) → Fase 10 (mesma fonte `ViabilityDecision`). Zona oficial (Quadro 10/SEDUR) pendente — sem ela a maioria cai honestamente em `em_analise`; HU-137 (feriados) e canais plenos (EP11) registrados. Parâmetros 56 / permissões 19 (ZERO permissão nova). **Gate restante: aprovação do smoke navegável (checkpoint humano).**

Plans:
- [x] 09-01-PLAN.md — Parâmetros HU-014 + fallback config (wave 1) ✓ 2026-06-14
- [x] 09-02-PLAN.md — Schema da decisão + DecisionOutcome + ViabilityDecision + TvlNumberGenerator + transições da StateMachine (wave 1) ✓ 2026-06-14
- [x] 09-03-PLAN.md — Contratos Regin/SEFAZ/BAP (bloqueados) + bindings + evento ResultadoEmitido (wave 1) ✓ 2026-06-14
- [x] 09-04-PLAN.md — SolicitacaoViabilityResolver (extração de SimulacaoSolicitacaoService) (wave 1) ✓ 2026-06-14
- [x] 09-05-PLAN.md — FluxoExpressoService: elegibilidade + defere/indefere + lock + auditoria síncrona + TVL + idempotência (wave 2) ✓ 2026-06-14
- [x] 09-06-PLAN.md — Gatilho: listener AvaliarFluxoExpresso + DecidirFluxoExpressoJob + expresso:reavaliar (wave 3) ✓ 2026-06-14
- [x] 09-07-PLAN.md — NotificarResultadoExpresso + ResultadoExpressoNotification (HU-077, sem anexo) (wave 4) ✓ 2026-06-14
- [x] 09-08-PLAN.md — ComunicarResultadoRegin (HU-104, pendência auditada) (wave 4) ✓ 2026-06-14
- [x] 09-09-PLAN.md — EnviarViabilidadeSefaz (HU-110, só deferida, pendência auditada) (wave 4) ✓ 2026-06-14
- [x] 09-10-PLAN.md — HU-134 BAP dormente: expresso:indeferir-sem-bap + seam de prazo (wave 5) ✓ 2026-06-14
- [x] 09-11-PLAN.md — UI retaguarda: resultado expresso (lista + detalhe ViabilityDecision) (wave 6) ✓ 2026-06-14
- [x] 09-12-PLAN.md — Fechamento: seeds dev + zona fictícia + expresso:decidir + golden/smoke + verificação integral + checkpoint humano (wave 7) ✓ 2026-06-14

Nota (recursos do framework — levantamento 2026-06-12): emissão de resultado protegida por atomic lock (`Cache::lock`) — idempotência sob concorrência, sem dupla emissão; decisão dispara evento de domínio (`ResultadoEmitido`) consumido por notificação (HU-077), integrações Regin/SEFAZ e auditoria; indeferimento por prazo BAP (HU-134) roda como rotina agendada no scheduler ativado na Fase 3.1.

### Phase 10: Análise Técnica SEDUR
**Goal**: Analistas da SEDUR recebem, distribuem, analisam e decidem processos não elegíveis ao expresso (alto risco, gatilhos, semi-expresso), com ficha de análise versionada, malha fina e emissão opcional de TVL em PDF no backoffice.
**Depends on**: Phases 8 e 9
**Requirements**: HU-079, HU-080, HU-081, HU-082, HU-083, HU-084, HU-085, HU-086, HU-087, HU-088, HU-089, HU-132, HU-135, HU-136, HU-138, HU-140, HU-142, HU-144
**UI hint**: yes (fila de análise, ficha de análise SAPS, malha fina, emissão TVL PDF)
**Success Criteria** (o que deve ser VERDADE):
  1. Solicitações não elegíveis ao expresso são encaminhadas e distribuídas para a fila de análise técnica — modelo caixa do setor (HU-138): atribuição a analista sem sair da caixa, cobrindo férias/ausências.
  1b. Consulta de processos supera o legado: filtros completos do SAPS + busca por analista + categorias (Expresso/Semi-Expresso/Malha Fina/Sede de Escritório), com paginação server-side performática (HU-082).
  2. Analista trabalha em **fila priorizada por SLA** (HU-144 — semáforo por etapa, "meus processos"/"caixa do setor") e abre a **ficha de análise pré-preenchida pelo motor** (HU-140), com **precedentes** do imóvel e do CNAE na zona (HU-142), mini-mapa, diff de revisões e autosave (HU-135).
  2b. Divergências analista × motor ficam registradas (HU-140) — insumo do feedback loop de parametrização (HU-145).
  3. Analista solicita **convites**/pendências ao requerente via portal Simplifica (HU-083); complementação retorna ao fluxo (HU-084).
  4. Analista emite parecer e defere/indefere (todas CNAEs deferidas para deferir processo); integrações Regin + SEFAZ disparam na conclusão.
  5. Analista pode **emitir TVL em PDF** no backoffice sob demanda (HU-132) — relatório interno, não entrega ao cidadão.
  6. Qualquer processo pode ser encaminhado à **malha fina** para revisão humana provocada (HU-136).
  7. Processo encerrado com status final e trilha completa.
**Plans**: 18 plans (8 waves do CONTEXT) — planejados em 2026-06-14

Status (fechamento 2026-06-15, plano 10-18): 18/18 planos implementados; verificação integral FRESCA verde (pint limpo; tsc/build; suíte **1161/1161** = SQLite + @group postgis com `POSTGIS_TESTS_REQUIRED=true`). A análise técnica humana opera de ponta a ponta com LÓGICA REAL: encaminhar→fila/SLA→caixa/distribuir→ficha pré-analisada→divergências→decidir (ViabilityDecision flow `analise_tecnica` + TVL PDF interno)→pendência↔→malha fina. Critérios 1, 1b, 2, 2b, 5, 6, 7 validados; critério 4 com a DECISÃO/TVL/auditoria reais e a **transmissão Regin/SEFAZ BLOQUEADA → Fase 13** (auditam `bloqueado`, nunca "enviado"); critério 3 (HU-083/084) PARCIAL (ciclo interno portal+e-mail) — convite Simplifica/Regin + multicanal → EP11. Seeds dev (dados fictícios, lógica real) + comando `analise:decidir` (evidência: `analise:decidir 7 --finalizar` → DEFERIDA + TVL-2026-000003) + golden/smoke + precedentes @group postgis. **Roteamento automático ao setor** é pendência SEDUR (no dev: caixa de triagem; em produção o gestor atribui manualmente). ZERO dependência nova além do `barryvdh/laravel-dompdf` (10-13). Parâmetros 67 / permissões 24. ÚNICO gate restante: smoke navegável humano.

Plans:
- [x] 10-01-PLAN.md — Fundação: 11 parâmetros grupo `analise` (56→67) + 5 permissões (19→24) + fallback config (dono do catálogo) (wave 1)
- [x] 10-02-PLAN.md — Schema: 8 tabelas (setores+pivot, ficha versionada, divergências, pendências, malha fina, textos-padrão, tvl_documents) + colunas de análise em viability_requests + 4 enums + models/factories (wave 1)
- [x] 10-03-PLAN.md — StateMachine: transições da análise humana (em_analise/em_pendencia, decisão final) + evento EncaminhadoParaAnalise (wave 1)
- [x] 10-04-PLAN.md — HU-138 setores (CRUD + analistas N:N) + HU-085 textos-padrão (CRUD versionado) — backend, route-owner W2 (wave 2)
- [x] 10-05-PLAN.md — AnalysisSlaService (reusa BusinessDeadlineCalculator) + semáforo on-the-fly (HU-144) (wave 2)
- [x] 10-06-PLAN.md — PrecedentRepository (contrato+postgis+fake) + PrecedentService (HU-142, @group postgis, LGPD) (wave 2)
- [x] 10-07-PLAN.md — HU-079/080/081: encaminhar (dispara EncaminhadoParaAnalise + SLA) + caixa do setor/distribuir(lote)/assumir, route-owner W3 (wave 3)
- [x] 10-08-PLAN.md — HU-140 PreAnalisarProcesso (listener auto-descoberto, reusa resolver, ficha rev 1; FA-01) (wave 3)
- [x] 10-09-PLAN.md — HU-135 ficha backend (autosave/finalizar imutável/revisões/diff) + HU-140 divergências + HU-142 endpoint, route-owner W4 (wave 4)
- [x] 10-10-PLAN.md — HU-085/086/087/088/089 AnaliseTecnicaDecisionService → ViabilityDecision (flow analise_tecnica) + ResultadoEmitido (serviço) (wave 5)
- [x] 10-11-PLAN.md — HU-083/084 pendências (ciclo interno: serviço + evento gancho EP11 + e-mail + resposta no portal), route-owner portal W5 (wave 5)
- [x] 10-12-PLAN.md — HU-136 malha fina (flag+tabela ortogonal ao status, qualquer status, lote) — serviço (wave 5)
- [x] 10-13-PLAN.md — HU-132 TVL PDF (barryvdh/laravel-dompdf — única dep nova) + TvlPdfService (só deferida, disco não público, auditado) (wave 6)
- [x] 10-14-PLAN.md — HU-082 consulta (filtros SAPS+analista+categoria, índices, busca global, CSV) + HU-144 fila por SLA, route-owner W6 (wave 6)
- [x] 10-15-PLAN.md — Ações backend (decidir/encerrar, abrir pendência, malha fina lote, emitir/baixar TVL por URL assinada), route-owner W7 (wave 7)
- [x] 10-16-PLAN.md — UI: fila com SLA + consulta + detalhe (timeline/mini-mapa) + lote de malha fina/CSV (wave 7)
- [x] 10-17-PLAN.md — UI: ficha SAPS (HU-135/140/142 + ações) + telas admin (setores/textos-padrão) + navegação do console + busca global Cmd+K (wave 7)
- [x] 10-18-PLAN.md — Fechamento: seeds dev + analise:decidir + golden/smoke + precedentes @group postgis + verificação integral + smoke navegável (checkpoint humano) (wave 8)

Nota (recursos do framework — levantamento 2026-06-12): TVL em PDF (HU-132) gerado e armazenado via Storage/Filesystem, com download no backoffice por URL temporária assinada — nunca arquivo público.

Nota de planejamento (2026-06-14): 18 planos em 8 waves (CONTEXT). Estratégia de paralelismo sem sobreposição de arquivos — lógica de negócio em serviços route-free nas waves 2–6, com UM único dono de `routes/gestao.php` por wave (10-04 W2, 10-07 W3, 10-09 W4, 10-14 W6, 10-15 W7) e UM dono do catálogo de parâmetros/permissões (10-01); UI (páginas React) concentrada na wave 7. Dependência nova pré-aprovada: `barryvdh/laravel-dompdf` (HU-132, isolada em 10-13). Bloqueios herdados (degradam honesto): transmissão Regin/SEFAZ na conclusão (HU-104/110) → Fase 13; HU-083/084 convite Simplifica/Regin + multicanal → EP11; exportação plena (HU-131) → Fase 15; zona oficial Quadro 10/SEDUR; HU-137 feriados (seam pronto).

### Phase 11: Pendências e Comunicação
**Goal**: Requerentes são notificados de pendências e vencimentos pelos canais configurados e respondem pelo próprio sistema, reabrindo a análise.
**Depends on**: Phase 10
**Requirements**: HU-090, HU-091, HU-092, HU-093, HU-094, HU-095, HU-096, HU-147
**UI hint**: yes (central de pendências, resposta do requerente, histórico de comunicações)
**Success Criteria** (o que deve ser VERDADE):
  1. Requerente é notificado de pendências e responde diretamente pelo sistema (portal Simplifica — HU-091).
  2. Resposta de pendência reabre a análise automaticamente.
  3. Vencimentos e prazos geram notificações automáticas conforme parâmetros administráveis.
  4. E-mail e WhatsApp funcionam como canais administráveis por feature toggle, com histórico de comunicações consultável e degradação controlada quando desativados.
  5. Processos parados além do SLA da etapa escalonam automaticamente (analista → gestor), com rotina agendada idempotente e fonte única de prazo (HU-147).
**Plans**: TBD

Nota (recursos do framework — levantamento 2026-06-12): central de notificações in-app usa o canal `database` nativo de Notifications (lidas/não lidas, sem tabela própria); WhatsApp entra como canal customizado de Notification com feature toggle e degradação controlada; o escalonamento por SLA (HU-147) roda no scheduler ativado na Fase 3.1.

### Phase 12: Auditoria e Compliance
**Goal**: A trilha de auditoria registrada desde a Fase 1 é consultável, exportável e monitorada para conformidade com a LGPD.
**Depends on**: Phase 1 (infraestrutura de auditoria); dados acumulados das Fases 1 a 11
**Requirements**: HU-097, HU-098, HU-099, HU-100, HU-101, HU-102, HU-149
**UI hint**: yes (consulta da trilha, filtros, exportação, painel LGPD, painel de alertas de abuso)
**Success Criteria** (o que deve ser VERDADE):
  1. Gestor consulta logs de decisões e histórico de alterações com filtros (período, usuário, entidade, ação).
  2. Para qualquer decisão, as regras aplicadas e suas versões são consultáveis — com **visualização explicável** passo a passo para decisões automáticas (HU-099 RN-004).
  3. Trilha de auditoria completa é consultável e exportável.
  4. Conformidade LGPD é monitorada (consentimentos, acessos a dados pessoais, retenção).
  5. Padrões de abuso/fraude detectados por regras parametrizáveis geram alertas e envio à malha fina — nunca punição automática (HU-149; regras simples podem antecipar para a Fase 9 se a SEDUR priorizar).
**Plans**: TBD

### Phase 13: Integrações
**Goal**: Serviços externos integrados de verdade — cada adaptador implementado atrás de contrato e validado contra o ambiente de homologação real, com evidência de chamada registrada.
**Depends on**: Phases 3, 8, 9 e 10
**Requirements**: HU-103, HU-104, HU-105, HU-106, HU-107, HU-108, HU-109, HU-110, HU-111, HU-133, HU-146
**UI hint**: yes (painel de saúde das integrações — HU-146; telas de credenciais/teste de conexão seguem HU-014)
**Success Criteria** (o que deve ser VERDADE):
  1. Solicitações entram via **Regin** (formulário embed + webservice) com **recepção durável** (fila com ack, dead-letter e replay — HU-103 RN-009) e endpoint público protegido (token/rate limit/anti-bot — RN-010); protocolo **BAP** vinculado ao processo (HU-133) e parecer devolvido ao integrador — validado em homologação real.
  2. Deferimento de viabilidade é enviado à SEFAZ municipal via API com evidência de chamada real em homologação.
  3. Receita Federal, Cadastro Imobiliário, **SIGIS/CA 2000**, Protocolo e Portal do Contribuinte integrados atrás de contratos, cada um validado contra ambiente real.
  4. Dados do legado **SAPS/Simplifica** migrados/em convivência conforme estratégia confirmada (HU-111).
  5. Painel de saúde das integrações operacional: status real por serviço, fila de retentativas, reprocessamento idempotente e alerta de degradação (HU-146).
**Plans**: TBD

Nota (recursos do framework — levantamento 2026-06-12): adaptadores herdam retry/backoff e timeout parametrizados da Fase 3.1; verificações de saúde consultam serviços em paralelo (`Concurrency`/`Http::pool` — HU-146); fila dedicada por integração com `Queue::route()` (novo no Laravel 13); avaliar Horizon para visibilidade operacional das filas Redis nesta fase.

**Bloqueios conhecidos da fase** (detalhes em `docs/ANALISE-HUs-REUNIAO-SEDUR.md` seções 5 e 7.8):
- Contrato **Regin**↔SAPS (webservice em produção; spec não pública) — aguardando documentação (HU-103, HU-104, HU-133).
- Base **SIGIS / CA 2000** (substituir S69) — aguardando acesso (HU-107).
- HU-110 (SEFAZ): **confirmada** — API existente via SIGVISA; **endpoint de envio de viabilidade e credenciais SenhaWeb pendentes**.
- HU-111 (migração legado SAPS): **pendente** export parametrizações e estratégia com SEDUR.
- Acesso a ambiente de homologação (Regin/SEFAZ/GIS) — solicitado.

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
**Requirements**: HU-122, HU-123, HU-124, HU-125, HU-126, HU-127, HU-128, HU-129, HU-130, HU-131, HU-145
**UI hint**: yes (dashboard, relatórios com filtros, exportação)
**Success Criteria** (o que deve ser VERDADE):
  1. Gestor acompanha dashboard executivo com indicadores reais da operação.
  2. Relatórios por período, zona, CNAE e risco refletem os dados reais das solicitações.
  3. Taxas de deferimento/indeferimento e tempo médio de análise são calculados sobre os processos reais — **por etapa da timeline** e com regras de prazo corretas (úteis/feriados via HU-137), eliminando a distorção do legado (19 dias reportados vs 42h medidos).
  4. Produtividade por analista é consultável e todos os relatórios são exportáveis; relatórios equivalentes aos administrativos do SAPS (Tempo de Emissão de TVL, Sedes de Escritório Virtual).
  5. Relatório de quedas por gatilho com taxa de resposta expressa em série temporal e drill-down até as divergências analista × motor (HU-145) — fecha o ciclo medir → parametrizar (HU-143) → medir.
**Plans**: TBD

## Pendências de confirmação (SEDUR)

| Item | HUs afetadas | Fase | Status |
|------|--------------|------|--------|
| DAM viabilidade Regin (escopo revisado: consulta SEFAZ) | HU-071, HU-072 | 8 | **Escopo definido** — geração fica na SEFAZ; confirmar renovação portal |
| Endpoint e credenciais SEFAZ (envio de deferimento) | HU-110 | 13 | API confirmada; contrato e credenciais pendentes |
| Estratégia de migração/convivência legado SAPS/Simplifica | HU-111 | 13 | Aguardando export parametrizações + definição |
| Contrato Regin/webservice (spec não pública) | HU-103, HU-104, HU-133 | 13 | Aguardando documentação JUCEB/SEDUR |
| Base SIGIS / CA 2000 (substituir S69) | HU-107, EP04 | 4, 13 | Aguardando acesso |
| Regra semi-expresso vs gatilho CNAE | HU-049, HU-135 | 6, 10 | Aguardando lista completa gatilhos |
| Formato PDF/assinatura TVL backoffice | HU-132 | 10 | Aguardando Anderson |
| Vistoria de viabilidade (aba do SAPS; vagas vistoria) | HU-135, HU-082 | 10 | Aguardando confirmação de escopo (pergunta 10) |
| Tipos de serviço cobertos (revisão TVL, renovação, MEI, AOP) | HU-061 | 8 | Aguardando lista oficial (pergunta 11) |
| Semântica edifício comercial/complemento | HU-139 | 8 | Aguardando confirmação (pergunta 6) |
| Recurso administrativo contra indeferimento | (sem HU — não inventar rito) | 10–11 | Aguardando confirmação (pergunta 12) |
| Painel público de transparência | (decisão política) | 15 | Aguardando aval (pergunta 13) |
| Padrões reais de fraude para calibrar detecção | HU-149 | 12 | Aguardando casos da equipe (pergunta 14) |
| Procedimento de ciência presencial no balcão | HU-150 | 8 | Aguardando definição (pergunta 15) |
| Quadros parametrizados vigentes da LOUOS; "Quadro 11" ↔ 11B | HU-015 a HU-018 | 5 | Aguardando planilhas |
| Acesso a ambiente de homologação | EP13 | 13 | Solicitado |
| Credenciamento no Login Único GOV.BR (Termo de Adesão — SGD) | HU-151 | 3.2 | **Pendente** — feature pronta e desligada; validação contra staging real bloqueada até a credencial |

Pauta completa: `docs/ANALISE-HUs-REUNIAO-SEDUR.md` seções 5 e 7. Refinamento HUs aplicado em 2026-06-12. Nenhum bloqueio externo impede Fases 1–3; motores (5–6) nascem parametrizáveis.

## Progress

**Execution Order:**
As fases executam em ordem numérica: 1 → 2 → 3 → … → 15. Pares paralelizáveis: 3 ‖ 4 e 5 ‖ 6.

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Identidade, Acesso e Auditoria Transversal | 9/9 | Complete | 2026-06-10 |
| 2. Administração Base | 0/8 | Planned | - |
| 3. Cadastro Empresarial | 9/9 | Complete | 2026-06-13 |
| 3.1. Fundação assíncrona — scheduler, jobs e retenção (INSERTED) | 5/5 | Complete | 2026-06-13 |
| 3.2. Autenticação GOV.BR no portal (INSERTED) | 1/1 | Implemented — aguardando credenciamento p/ validar staging | - |
| 4. Georreferenciamento e Território | 8/8 | Complete (zona/lote bloqueados — pendente SEDUR) | 2026-06-13 |
| 5. Motor de Regras da LOUOS | 9/9 | Complete (Quadro 10/zona pendente SEDUR) | 2026-06-14 |
| 6. Classificação de Risco | 9/9 | Complete | 2026-06-14 |
| 7. Consulta Prévia de Viabilidade | 10/10 | Complete (veredito locacional/inscrição pendentes SEDUR) | 2026-06-14 |
| 8. Solicitação de Viabilidade | 16/16 | Complete (guardião APROVADO; resta só o smoke navegável humano); DAM HU-071/072 + Regin bloqueados → Fase 13; HU-139 texto livre | 2026-06-14 |
| 9. Fluxo Expresso | 12/12 | Complete (resta só o smoke navegável humano); Regin/SEFAZ HU-104/110 e HU-134 ativa → Fase 13; TVL PDF HU-132 → Fase 10; zona oficial Quadro 10 pendente SEDUR | 2026-06-14 |
| 10. Análise Técnica SEDUR | 18/18 | Complete (resta só o smoke navegável humano); Regin/SEFAZ HU-104/110 → Fase 13; HU-083/084 pleno → EP11; export HU-131 → Fase 15; zona oficial Quadro 10 + roteamento automático ao setor pendentes SEDUR | 2026-06-15 |
| 11. Pendências e Comunicação | 0/TBD | Not started | - |
| 12. Auditoria e Compliance | 0/TBD | Not started | - |
| 13. Integrações | 0/TBD | Not started | - |
| 14. Inteligência Artificial | 0/TBD | Not started | - |
| 15. Relatórios e Indicadores | 0/TBD | Not started | - |

**Cobertura de requisitos:** 151 HUs catalogadas — HU-001 a HU-131 (catálogo original), HU-132 a HU-139 (reunião SEDUR + mapeamento do SAPS legado), HU-140 a HU-147 (melhorias além do legado: pré-análise pelo motor, simulação no formulário, precedentes, sandbox de parametrização, fila com SLA, feedback loop do expresso, painel de integrações, escalonamento), HU-148 a HU-150 (segunda rodada: contingência, antifraude, atendimento presencial) e HU-151 (autenticação GOV.BR — pedido do produto em 2026-06-12, Fase 3.2). Rastreabilidade em `.planning/REQUIREMENTS.md` *(atualizar REQUIREMENTS na próxima revisão de milestone)*.

---
*Roadmap criado: 2026-06-09 | Atualizado: 2026-06-12 (reavaliação integral + melhorias além do legado, rodadas 1 e 2 — `docs/ANALISE-HUs-REUNIAO-SEDUR.md` seção 7.9; inserida Fase 3.1 e anotadas Fases 8–13 a partir do levantamento de recursos prontos do Laravel 13)*

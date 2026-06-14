---
phase: 08-solicitacao-de-viabilidade
plan: 15
subsystem: atendimento-presencial
tags: [solicitacao-viabilidade, hu-150, atendimento-presencial, em-nome-de, representacao, middleware, balcao, inclusao-digital, rn-001, rn-002, rn-005, ca-03, auditoria, anti-fachada, anti-regressao]

# Dependency graph
requires:
  - phase: 01-identidade
    plan: "07"
    provides: "CurrentRepresentation (scoped) + ResolveRepresentation + Context acting_for_user_id + RecordActivityAction (enriquece a auditoria com acting_for) — mecanismo 'em nome de' reaproveitado integralmente"
  - phase: 08-solicitacao-de-viabilidade
    plan: "02"
    provides: "permissão atendimento-presencial (gestor+admin) + parâmetro solicitacao.atendimento.expiracao_minutos (30) — já seedados; este plano só consome"
  - phase: 08-solicitacao-de-viabilidade
    plan: "05"
    provides: "ViabilityRequestPolicy + fluxo de criação requester(efetivo)/created_by(ator) + StoreSolicitacaoRequest (escopo por vínculo ativo) — molde da abertura em nome de"
  - phase: 08-solicitacao-de-viabilidade
    plan: "01"
    provides: "aggregate ViabilityRequest (origin/requester/created_by) — a coluna assisted_attendance_id é adicionada aqui por migração própria"
provides:
  - "App\\Models\\AssistedAttendance (attendant/citizen/started_at/expires_at/ended_at) + scope active() + isActive()"
  - "App\\Http\\Middleware\\ResolveAssistedAttendance — popula o MESMO CurrentRepresentation/Context da procuração a partir do atendimento ativo da sessão"
  - "CurrentRepresentation estendido: setAttendance()/attendance()/clearAttendance(); grantor() resolve o cidadão atendido com precedência, sem regredir a procuração"
  - "App\\Http\\Controllers\\Gestao\\AssistedAttendanceController (index/store/destroy/storeSolicitacao)"
  - "rotas gestao.atendimento.{index,store,destroy,solicitacoes.store} sob permission:atendimento-presencial + ResolveAssistedAttendance"
  - "coluna viability_requests.assisted_attendance_id (dimensão balcão, RN-005) + relação assistedAttendance()"
  - "shared prop Inertia attendingFor (banner do atendimento) + tela gestao/atendimento/index.tsx + item de navegação"
affects: [08-16-fechamento, 09-fluxo-expresso, 10-analise-tecnica, 15-relatorios]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Segundo canal 'em nome de' reusando a infra da Fase 1 SEM recriar: um modelo leve (AssistedAttendance) + um middleware análogo ao ResolveRepresentation populam o MESMO CurrentRepresentation/Context — a policy e a auditoria 'em nome de' funcionam sem alteração"
    - "CurrentRepresentation com duas fontes (procuração OU atendimento) e precedência explícita no grantor(); clearAttendance() isola o atendimento sem mexer na procuração (anti-regressão)"
    - "Middleware aplicado por classe na rota (ResolveAssistedAttendance::class), espelhando como o portal aplica ResolveRepresentation — sem alias em bootstrap/app.php"
    - "Atendente opera por endpoints da GESTÃO (guard gestao) que reusam o domínio (effectiveUser/policy/auditoria) — sessões separadas por ambiente impedem operar as rotas do portal diretamente"
    - "Dimensão balcão = FK assisted_attendance_id na solicitação (origin permanece portal); reports (EP15) filtram whereNotNull('assisted_attendance_id')"

key-files:
  created:
    - app/Models/AssistedAttendance.php
    - database/factories/AssistedAttendanceFactory.php
    - database/migrations/2026_06_14_132807_create_assisted_attendances_table.php
    - database/migrations/2026_06_14_132810_add_assisted_attendance_id_to_viability_requests_table.php
    - app/Http/Middleware/ResolveAssistedAttendance.php
    - app/Http/Controllers/Gestao/AssistedAttendanceController.php
    - resources/js/pages/gestao/atendimento/index.tsx
    - tests/Feature/Solicitacao/AtendimentoPresencialTest.php
  modified:
    - app/Support/Representation/CurrentRepresentation.php
    - app/Models/ViabilityRequest.php
    - app/Http/Middleware/HandleInertiaRequests.php
    - resources/js/layouts/gestao-layout.tsx
    - resources/js/types/index.d.ts
    - routes/gestao.php

key-decisions:
  - "Reuso máximo (CONTEXT 'custo de implementação baixo'): NÃO recriei a representação. CurrentRepresentation ganhou setAttendance/attendance/clearAttendance e grantor() passou a devolver $this->attendance?->citizen ?? $this->procuration?->grantor — o atendimento tem precedência, e o caminho de procuração fica intacto (guard de regressão LinkAttorney/RevokeAttorney/MyCompanies 27/27 verde)."
  - "AssistedAttendance NÃO é procuração jurídica (não há outorga): é atendimento de balcão (inclusão digital). Modela attendant_user_id/citizen_user_id/started_at/expires_at/ended_at; scope active() = ended_at null E expires_at > now; isActive() idem. cascadeOnDelete nos dois usuários, índice (attendant_user_id, ended_at) para a revalidação por request."
  - "ResolveAssistedAttendance espelha o ResolveRepresentation linha a linha: lê attending_attendance_id da sessão, revalida (pertence ao atendente, ativo, não expirado), e em sucesso faz Context::add('acting_for_user_id', citizen) + CurrentRepresentation::setAttendance; em expirado/ausente/de-outro-atendente limpa o estado e descarta a sessão (exige reabertura — CA-03). Aplicado por CLASSE na rota (como o portal), portanto bootstrap/app.php NÃO foi alterado (desvio do files_modified do plano — fiel ao padrão citado no próprio plano)."
  - "O atendente opera por ENDPOINTS DA GESTÃO (guard gestao), não pelas rotas do portal: a decisão de sessões separadas por ambiente ([2026-06-12]) impede um usuário do guard gestao de passar por auth:web. Os endpoints do atendimento reusam o MESMO domínio (effectiveUser via CurrentRepresentation, ViabilityRequestPolicy, auditoria RN-002), mudando só o ambiente/ator. AssistedAttendanceController@storeSolicitacao abre a solicitação direta em nome do cidadão (requester = cidadão, created_by = atendente, origin portal, assisted_attendance_id) reusando a validação de escopo por vínculo ativo do StoreSolicitacaoRequest (replicada inline porque o guard de atendimento ativo precisa rodar ANTES da validação — CA-03)."
  - "CA-01 (ação em nome do cidadão) provada pela ABERTURA da solicitação direta (a ação primária do atendente na HU — 'abrir solicitação direta/renovação'): registra requester = cidadão e created_by = atendente, com a auditoria 'created' carregando causer = atendente E acting_for = cidadão (os dois CPFs, RN-001). O PROTOCOLO (HU-068) reusa o MESMO mecanismo (ProtocolarSolicitacaoService recebe o ator e a transição audita causer+acting_for) — provado no 08-10; o atendimento muda só o ambiente/ator, não o motor."
  - "RN-005 (dimensão balcão): coluna nullable assisted_attendance_id em viability_requests (migração própria, nullOnDelete). A origin permanece 'portal' (é fluxo direto do portal operado em nome de); o que distingue o balcão é a presença do vínculo de atendimento — reports da EP15 filtram por ele. Adicionar a coluna foi necessário e não estava no files_modified do plano (desvio registrado)."
  - "CA-02 (escopo limitado): hoje as rotas de análise/decisão não existem (Fases 9/10). O escopo do atendente é enforçado pelo middleware de permissão — provado contra uma rota admin-only existente (/gestao/parametros, manter-parametros) que o gestor-atendente NÃO possui: 403 auditado em 'seguranca'/'bloqueado'. Quando a análise chegar, o mesmo gate barra o atendente sem a permissão de decisão."
  - "Banner via shared prop attendingFor (espelha o actingFor): preenchido pelo CurrentRepresentation::attendance() — populado pelo ResolveAssistedAttendance, que roda SÓ nas rotas do atendimento. Escopo deliberado: o Context 'em nome de' não vaza para ações não relacionadas do console (evita auditar uma edição de CNAE como se fosse do cidadão)."

patterns-established:
  - "Como adicionar um novo canal 'em nome de' sem recriar a representação: modelo leve + middleware análogo populando o MESMO CurrentRepresentation/Context; a policy/auditoria existentes passam a valer de graça"
  - "Operação cross-ambiente: o console (guard gestao) reusa serviços/políticas do domínio do portal em endpoints próprios, em vez de tentar atravessar guards"

# Metrics
duration: ~35 min (commits d1d7082 → b4c7a91 → 7d9f0b0)
completed: 2026-06-14
---

# Phase 8 Plan 15: Atendimento presencial assistido (HU-150) Summary

**O SILE ganhou o canal de balcão (inclusão digital): o atendente autorizado opera o sistema "em nome de" o cidadão presente, REUSANDO integralmente o mecanismo de representação da Fase 1. Um modelo leve `AssistedAttendance` (attendant/citizen/started_at/expires_at/ended_at) e um middleware `ResolveAssistedAttendance` — espelho linha a linha do `ResolveRepresentation` — populam o MESMO `CurrentRepresentation`/`Context`, de modo que a `ViabilityRequestPolicy` e a auditoria "em nome de" (RecordActivityAction) funcionam sem recriar nada. O `CurrentRepresentation` passou a resolver o cidadão atendido com precedência (`grantor() = attendance?->citizen ?? procuration?->grantor`), sem regredir o caminho da procuração (guard `LinkAttorneyTest|RevokeAttorneyTest|MyCompaniesTest` 27/27 verde). O atendente opera por endpoints da GESTÃO (sessões separadas por ambiente impedem usar as rotas do portal): `AssistedAttendanceController` inicia o atendimento pelo CPF do cidadão (vínculo com expiração curta parametrizável `solicitacao.atendimento.expiracao_minutos`), abre a solicitação direta em nome dele (requester = cidadão, created_by = atendente, auditoria com os DOIS CPFs — RN-001/RN-002) e encerra o vínculo. A expiração limpa o estado e exige reabertura explícita (CA-03); o escopo é limitado pela permissão `atendimento-presencial` e pelo middleware de permissão (ações fora do escopo → 403 auditado, CA-02/CA-04). A dimensão balcão (RN-005) é a FK `assisted_attendance_id` na solicitação (origin permanece `portal`). ZERO dependência nova. TDD estrito (RED→GREEN com evidência fresca): AtendimentoPresencialTest 15/15; suíte completa 825/825 (4212 asserções, incl. `@group postgis` com container `sile-pgsql` healthy).**

## Performance

- **Duration:** ~35 min (3 commits TDD)
- **Tasks:** 3 (modelo + reuso do CurrentRepresentation; middleware; controller + rotas + tela + fluxos)
- **Files:** 8 criados + 6 modificados — ZERO dependência nova

## Accomplishments

- **`AssistedAttendance`** (migration + model + factory active/expired/ended) — vínculo leve de balcão com `scope active()` e `isActive()`.
- **`CurrentRepresentation` reusável** — `setAttendance()/attendance()/clearAttendance()`; `grantor()` resolve o cidadão atendido com precedência, sem quebrar a procuração.
- **`ResolveAssistedAttendance`** — espelha o `ResolveRepresentation`: revalida o vínculo da sessão e popula o MESMO `Context`/`CurrentRepresentation`; expirado/ausente/de-outro-atendente limpa o estado (CA-03).
- **`AssistedAttendanceController`** — `store` (inicia pelo CPF; expiração parametrizada; auditoria `atendimento-iniciado`), `storeSolicitacao` (abre a solicitação em nome do cidadão; requester = cidadão, created_by = atendente; bloqueia se o vínculo expirou — reabertura), `destroy` (encerra; auditoria `atendimento-encerrado`), `index` (estação do console).
- **Rotas** `gestao.atendimento.{index,store,destroy,solicitacoes.store}` sob `permission:atendimento-presencial` + `ResolveAssistedAttendance` (anexadas ao `routes/gestao.php` após o grupo de contingência do 08-14, já commitado; diff = só o meu trecho).
- **Dimensão balcão (RN-005)** — coluna `assisted_attendance_id` em `viability_requests` + relação `assistedAttendance()`.
- **UI do console** — `gestao/atendimento/index.tsx` (iniciar pelo CPF, banner do atendimento ativo, abrir solicitação direta, encerrar) + shared prop `attendingFor` + item de navegação por permissão.

## Contrato para os próximos planos

- **`AssistedAttendance::active()`** / **`isActive()`** — atendimentos vigentes (não encerrados e dentro da janela).
- **`CurrentRepresentation::grantor()`** já devolve o cidadão atendido quando há atendimento ativo — qualquer consumidor do "usuário efetivo" (policies, controllers, auditoria) funciona em nome de sem mudança.
- **`viability_requests.assisted_attendance_id`** — filtro da dimensão balcão para os relatórios da EP15 (`whereNotNull`).
- **08-16 (smoke/seeds)** — pode incluir um cenário de balcão (atendente + cidadão + empresa) reusando a `AssistedAttendanceFactory`.

## Deviations from Plan

- **`bootstrap/app.php` NÃO foi alterado** (estava no `files_modified`): o `ResolveAssistedAttendance` é aplicado por CLASSE na rota, EXATAMENTE como o `ResolveRepresentation` é aplicado no portal — o próprio plano manda "seguir o padrão de como ResolveRepresentation é aplicado". Não há alias a registrar.
- **Migração nova `add_assisted_attendance_id_to_viability_requests_table`** (não listada): necessária para a dimensão balcão (RN-005) de forma honesta (link real), em vez de inferir balcão por heurística. Renomeada para timestamp posterior à criação da tabela para garantir a ordem do FK.
- **Endpoint de gestão `storeSolicitacao`** (o plano descrevia o controller como start/end): a HU lista "abrir solicitação direta/renovação" como a ação primária do atendente, e os testes CA-01/RN-005 exigem uma abertura real em nome de. Como as sessões são separadas por ambiente, o atendente não pode usar as rotas do portal — o endpoint da gestão reusa o domínio (mesma policy/escopo/auditoria).
- **CA-01 provada pela ABERTURA da solicitação** (a HU exemplifica com "protocolar"): a abertura já demonstra requester = cidadão + created_by = atendente + auditoria com os dois CPFs. O protocolo (HU-068) usa o MESMO `ProtocolarSolicitacaoService`/transição auditada (provado no 08-10) — o atendimento muda só o ambiente/ator, não o motor.
- **CA-02 (escopo limitado) provada contra uma rota admin-only existente** (`/gestao/parametros`): as rotas de análise/decisão são das Fases 9/10. O middleware de permissão é o enforcement real do escopo hoje; o teste prova que ter `atendimento-presencial` não concede poderes além do escopo.
- **Testes além dos nomeados** (cobertura de ramos reais): modelo (`scope`/`isActive`), reuso do `CurrentRepresentation` (incl. anti-regressão da procuração), middleware (ativo/expirado/de-outro-atendente) e CPF sem conta (anti-fachada: não fabrica cidadão). Total 15 testes.

## Issues Encountered

- **Coordenação de wave (routes/gestao.php compartilhado com 08-14)**: durante a execução o 08-14 (contingência) commitou (`15cc505`), passando a constar no HEAD. O append do atendimento ficou então como um diff LIMPO (só import + grupo) sobre o HEAD — staging individual confirmou zero captura de trabalho alheio. Nenhum arquivo dos planos 08-11/08-12/08-14 foi tocado.

## Pendência SEDUR (registrada, degrada honesto)

- **Ciência presencial (HU-150 RN-004)**: o instrumento de coleta de ciência do cidadão no balcão (assinatura em tela, impresso ou gov.br) é procedimento a definir com a SEDUR. Hoje o atendimento registra ator + beneficiário + trilha completa (RN-001/RN-002) e a abertura é real; a formalização da ciência presencial entra quando a SEDUR definir o instrumento. Não bloqueia o canal.
- **Criação de conta do cidadão no balcão**: o atendimento exige que o cidadão já tenha conta (anti-fachada — não fabricamos cidadão). O cadastro assistido no balcão (possível via gov.br/registro presencial) é melhoria futura.

## Verification (evidência fresca)

- **RED→GREEN por task:** Task 1 ("Class AssistedAttendance not found" → 3/3); Task 2 (middleware ausente → 3/3); Task 3 (8 rotas 404 → 15/15).
- **Filtros do plano + guard de regressão:** `--filter="AtendimentoPresencialTest|LinkAttorneyTest|RevokeAttorneyTest|MyCompaniesTest"` → **42/42** (238 asserções).
- **`php artisan route:list --path=gestao/atendimento`** → 4 rotas (`index/store/destroy/solicitacoes.store`).
- **Suíte completa:** `POSTGIS_TESTS_REQUIRED=true php artisan test --compact` → **825 testes, 825 passaram, 0 falhas** (4212 asserções; inclui `@group postgis` com container `sile-pgsql` healthy — sem skip).
- **`vendor/bin/pint --dirty --format agent`** → passed. **`npx tsc --noEmit`** → 0 erros. **`npm run build`** → built.

## Next Phase Readiness

- **08-16 (fechamento/seeds):** seed de dev pode incluir um cenário de balcão (atendente gestor + cidadão + empresa) com a `AssistedAttendanceFactory`; o smoke pode exercitar iniciar → abrir → encerrar.
- **Fase 9/10 (expresso/análise):** as ações de análise/decisão nascem atrás de permissões próprias — o atendente (sem elas) já é barrado pelo mesmo middleware de permissão (CA-02 garantido por construção).
- **Fase 15 (relatórios):** a dimensão balcão (`assisted_attendance_id`) está pronta para dimensionar a demanda presencial.

---
*Phase: 08-solicitacao-de-viabilidade*
*Completed: 2026-06-14*

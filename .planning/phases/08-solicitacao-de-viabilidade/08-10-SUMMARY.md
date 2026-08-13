---
phase: 08-solicitacao-de-viabilidade
plan: 10
subsystem: protocolo-solicitacao
tags: [solicitacao-viabilidade, hu-068, hu-141, protocolo, numero-unico, lockForUpdate, state-machine, evento-de-dominio, after-commit, listener, trilha, ciencia, bloqueio-documental, rn-002, rn-003, anti-fachada]

# Dependency graph
requires:
  - phase: 08-solicitacao-de-viabilidade
    plan: "01"
    provides: "ProtocolNumberGenerator (lockForUpdate + unique), ViabilityRequestStateMachine (transition rascunho→protocolada + timeline + auditoria), InvalidStatusTransitionException, protocol_sequences, ViabilityRequest (protocol_number/protocoled_at/applicant_proceeded_despite)"
  - phase: 08-solicitacao-de-viabilidade
    plan: "05"
    provides: "ViabilityRequestPolicy::protocol (dono + rascunho, CA-04)"
  - phase: 08-solicitacao-de-viabilidade
    plan: "08"
    provides: "DocumentRequirementResolver::missing() — base do bloqueio documental (HU-067)"
  - phase: 08-solicitacao-de-viabilidade
    plan: "09"
    provides: "simulation_snapshot/_resultado/_rules_versions/simulated_at — congelados no protocolo (RN-003)"
  - phase: 08-solicitacao-de-viabilidade
    plan: "02"
    provides: "toggle features.solicitacao_viabilidade (catálogo + fallback config/sile.php)"
provides:
  - "App\\Services\\Solicitacao\\ProtocolarSolicitacaoService::protocol(ViabilityRequest, User $actor, bool $proceedDespite = false): ViabilityRequest — coração do protocolo (valida → número único → transição → ciência → evento após commit)"
  - "App\\Events\\SolicitacaoProtocolada (ShouldDispatchAfterCommit) — PRIMEIRO evento de domínio do sistema (carrega public ViabilityRequest $request)"
  - "App\\Listeners\\RegistrarTrilhaProtocolo — marco amigável da timeline (HU-069) + auditoria de alto nível 'protocolada'"
  - "App\\Http\\Controllers\\Portal\\ProtocoloController@store + ProtocolarSolicitacaoRequest (proceed_despite)"
  - "Exceções de domínio DocumentacaoIncompletaException (->requisitos) / SolicitacaoIncompletaException (->campos)"
  - "Rota POST portal/solicitacoes/{solicitacao}/protocolar (name portal.solicitacoes.protocolar)"
affects: [08-11-consulta-protocolo, 08-12-cancelar, 08-13-ui-wizard, 08-14-contingencia, 08-15-atendimento-presencial]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "PRIMEIRO evento de domínio do SILE: SolicitacaoProtocolada implements ShouldDispatchAfterCommit, despachado APÓS o commit da transação (só protocolos efetivados geram efeitos)"
    - "Auditoria do protocolo INDEPENDE do evento: a transição síncrona rascunho→protocolada (StateMachine, na transação) garante RN-002 mesmo se um listener falhar; o evento só pendura efeitos desacoplados"
    - "Pré-condições (status/dados mínimos/documentos) ANTES da transação — bloqueio NÃO consome número de protocolo (anti-fachada)"
    - "Listener síncrono registrado por Event::listen no AppServiceProvider::boot (convenção do projeto, não auto-discovery); fases futuras só ADICIONAM listeners ao mesmo evento"

key-files:
  created:
    - app/Services/Solicitacao/ProtocolarSolicitacaoService.php
    - app/Events/SolicitacaoProtocolada.php
    - app/Listeners/RegistrarTrilhaProtocolo.php
    - app/Http/Controllers/Portal/ProtocoloController.php
    - app/Http/Requests/Portal/ProtocolarSolicitacaoRequest.php
    - app/Services/Solicitacao/DocumentacaoIncompletaException.php
    - app/Services/Solicitacao/SolicitacaoIncompletaException.php
    - tests/Feature/Solicitacao/ProtocolarSolicitacaoTest.php
    - tests/Feature/Solicitacao/ProtocolarConcorrenciaPostgisTest.php
  modified:
    - app/Providers/AppServiceProvider.php
    - routes/portal.php

key-decisions:
  - "Evento despachado APÓS o DB::transaction() retornar (fora de qualquer transação) — garante 'não dispara no rollback' de forma testável com Event::fake (a linha de dispatch não é alcançada na exceção). SolicitacaoProtocolada implementa ShouldDispatchAfterCommit como contrato/defesa adicional (semântica + intenção documentada + protege contra disparo precoce se algum dia for despachado dentro de transação)."
  - "Listener registrado explicitamente (Event::listen no AppServiceProvider::boot) seguindo a convenção do projeto (LogNotificationSent/AuditModelsPruned) — NÃO auto-discovery."
  - "Marco amigável da timeline é IDEMPOTENTE no listener: a transição síncrona já grava o public_label; o listener só cria o marco 'se ainda não houver' (nunca duplica). O ganho novo do listener é a auditoria de NEGÓCIO de alto nível 'protocolada' (distinta do 'transicao' técnico da StateMachine)."
  - "Ciência (applicant_proceeded_despite) só é registrada quando proceed_despite=true E a tendência simulada é DESFAVORÁVEL (simulation_resultado === 'nao_permitido' = indeferimento); 'pendente' é indeterminado, não desfavorável. Sem simulação desfavorável, mesmo com proceed_despite=true, não há ciência a registrar."
  - "Pré-condições (status rascunho; empresa/imóvel-polígono/área/CNAE principal; documentos obrigatórios) validadas ANTES da transação — bloqueio NÃO gera/consome número (anti-fachada). Exceções de domínio carregam a lista (->requisitos/->campos) traduzida em flash.error pelo controller."
  - "Snapshot da simulação CONGELADO no protocolo (RN-003): o service NÃO chama markSimulationStale nem reprocessa — apenas preserva o que o 08-09 persistiu."
  - "Sucesso → redirect para portal.solicitacoes.index com flash 'status' carregando o número (a rota de detalhe/consulta é o 08-11; o 08-13 refina a navegação)."
  - "Guard de status no service (rascunho) lança InvalidStatusTransitionException (reuso do 08-01); no controller a policy::protocol já barra terceiro/não-rascunho com 403 auditado (CA-04) antes de chegar ao service."

patterns-established:
  - "Serviço de protocolo como orquestrador transacional: injeta ProtocolNumberGenerator + ViabilityRequestStateMachine + DocumentRequirementResolver; valida fora da transação, efetiva dentro, dispara o evento de domínio depois do commit"
  - "Primeiro app/Events/ + listener de domínio do projeto — gabarito para os ganchos EP09/EP11/EP13 (só ADICIONAR listeners ao mesmo evento, sem tocar o protocolo)"

# Metrics
duration: ~25 min (commits ef8b6f7→92acb4d→d7e704a, com leitura de contexto)
completed: 2026-06-14
---

# Phase 8 Plan 10: Protocolar solicitação (HU-068) Summary

**O protocolo (HU-068) é o coração da Fase 8 e o PRIMEIRO processo formal com evento de domínio próprio. O `ProtocolarSolicitacaoService::protocol(ViabilityRequest, User $actor, bool $proceedDespite = false)` valida as pré-condições FORA da transação (status rascunho; dados mínimos — empresa, imóvel/polígono, área, CNAE principal; documentos obrigatórios via `DocumentRequirementResolver::missing()`) — bloqueando com aviso e SEM consumir número quando algo falta (anti-fachada). Dentro de UMA `DB::transaction`, gera o número único pelo `ProtocolNumberGenerator` (`protocol_sequences->lockForUpdate()` + `unique` como defesa final), grava `protocol_number`/`protocoled_at`/`applicant_proceeded_despite` e transiciona rascunho→protocolada pela `ViabilityRequestStateMachine` (timeline + auditoria RN-002). A simulação NÃO bloqueia: quando a tendência é de indeferimento (`simulation_resultado === 'nao_permitido'`) e o requerente prossegue, registra a ciência `applicant_proceeded_despite` (HU-141 RN-002, direito de petição) com o snapshot CONGELADO (RN-003 — não reprocessa). APÓS o commit dispara `SolicitacaoProtocolada` — primeiro evento de domínio (`ShouldDispatchAfterCommit`) — cujo listener síncrono `RegistrarTrilhaProtocolo` garante o marco amigável da timeline (idempotente) e grava a auditoria de alto nível `protocolada`. CRÍTICO: a auditoria do protocolo NÃO depende do evento — a transição síncrona já garante a trilha mesmo se um listener falhar. O `ProtocoloController@store` autoriza pela `ViabilityRequestPolicy::protocol` (dono em rascunho, CA-04), traduz os bloqueios de domínio em `flash.error` (aviso, nunca silencioso) e respeita o toggle `features.solicitacao_viabilidade`. Concorrência do número provada em `@group postgis` (lock real). ZERO dependência nova. TDD estrito: ProtocolarSolicitacaoTest 13/13 (SQLite) + ProtocolarConcorrenciaPostgisTest 1/1 (@group postgis); suíte completa 784/784.**

## Performance

- **Duration:** ~25 min (3 commits TDD), com leitura de contexto
- **Tasks:** 3 (service + evento; listener + controller + request + rota + registro; postgis concorrência)
- **Files:** 9 criados + 2 modificados — ZERO dependência nova

## Accomplishments

- **`ProtocolarSolicitacaoService::protocol(ViabilityRequest, User $actor, bool $proceedDespite = false): ViabilityRequest`** — valida (status/dados mínimos/documentos) fora da transação; gera número único + transiciona + grava ciência dentro de `DB::transaction`; dispara o evento após o commit.
- **`SolicitacaoProtocolada`** (primeiro `app/Events/` do projeto): `implements ShouldDispatchAfterCommit`, carrega `public ViabilityRequest $request`; PHPDoc documenta os ganchos FUTUROS (notificação EP11, elegibilidade EP09, resposta Regin EP13) — NÃO implementados.
- **`RegistrarTrilhaProtocolo`** (listener síncrono): marco amigável da timeline idempotente (HU-069) + auditoria de alto nível `solicitacoes/protocolada`; registrado por `Event::listen` no `AppServiceProvider::boot`.
- **`ProtocoloController@store`** + **`ProtocolarSolicitacaoRequest`** (`proceed_despite` boolean) + rota `POST portal/solicitacoes/{solicitacao}/protocolar` (name `portal.solicitacoes.protocolar`).
- **Exceções de domínio** `DocumentacaoIncompletaException` (`->requisitos`) e `SolicitacaoIncompletaException` (`->campos`) — carregam a lista, traduzidas em `flash.error`.
- **Anti-fachada**: protocola de verdade (status protocolada, `protocoled_at`, número real); bloqueio documental nunca silencioso; auditoria RN-002 independente do evento; número único provado sob concorrência real (`@group postgis`).

## Contrato para os próximos planos

### Service

```
App\Services\Solicitacao\ProtocolarSolicitacaoService::protocol(
    ViabilityRequest $request,
    App\Models\User $actor,
    bool $proceedDespite = false,
): ViabilityRequest
```

- Pré-condições (lançam ANTES da transação, sem consumir número):
  - status ≠ rascunho → `InvalidStatusTransitionException` (08-01).
  - faltam dados mínimos (empresa / imóvel-polígono / área / CNAE principal) → `SolicitacaoIncompletaException` (`->campos`).
  - faltam documentos obrigatórios → `DocumentacaoIncompletaException` (`->requisitos`).
- Efeito: `protocol_number` (`VIA-AAAA-NNNNNN`), `protocoled_at`, status protocolada, 1 transição rascunho→protocolada (timeline + auditoria `solicitacoes/transicao`), `applicant_proceeded_despite` quando aplicável.
- Pós-commit: `SolicitacaoProtocolada::dispatch($request)`.

### Evento + listener (gancho para EP09/EP11/EP13)

- **`App\Events\SolicitacaoProtocolada`** (`ShouldDispatchAfterCommit`, `Dispatchable`) — `public ViabilityRequest $request`. As próximas fases penduram listeners NESTE evento (registro por `Event::listen` no `AppServiceProvider::boot`), sem tocar o protocolo.
- **`App\Listeners\RegistrarTrilhaProtocolo`** — já registrado; grava auditoria `solicitacoes/protocolada` e garante o marco `public_label` ("Recebida — em processamento").

### Rota + controller

- **`POST portal/solicitacoes/{solicitacao}/protocolar`** (name `portal.solicitacoes.protocolar`), body opcional `proceed_despite` (boolean — ciência HU-141).
- Autorização `ViabilityRequestPolicy::protocol` (dono + rascunho; terceiro/não-rascunho → 403 auditado, CA-04).
- Sucesso → redirect `portal.solicitacoes.index` + flash `status` com o número. Bloqueio de domínio → `back()` + flash `error` com a lista. Toggle `features.solicitacao_viabilidade` off → `back()` + flash `status` comunicado.

## Task Commits

TDD estrito (RED→GREEN verificado com evidência fresca antes de cada commit):

1. **Task 1: ProtocolarSolicitacaoService + evento + exceções** — `ef8b6f7` (feat) — RED: "Target class [ProtocolarSolicitacaoService] does not exist" → GREEN: `--filter=ProtocolarSolicitacaoTest` 9/9 (36 asserções).
2. **Task 2: listener + controller + request + rota + registro** — `92acb4d` (feat) — RED: 4 falhas ("Route [portal.solicitacoes.protocolar] not defined") → GREEN: `--filter=ProtocolarSolicitacaoTest` 13/13 (62 asserções).
3. **Task 3: ProtocolarConcorrenciaPostgisTest** — `d7e704a` (test) — `@group postgis` 1/1 (8 asserções) contra Postgres real (lockForUpdate efetivo).

## Decisions Made

- **Dispatch após o `DB::transaction()` retornar** + evento `ShouldDispatchAfterCommit`: combina testabilidade do "não dispara no rollback" (Event::fake não alcança a linha de dispatch na exceção) com a semântica/defesa do contrato after-commit.
- **Listener registrado explicitamente** (`Event::listen` no `AppServiceProvider::boot`) — convenção do projeto, não auto-discovery.
- **Marco amigável idempotente no listener**: a StateMachine já grava o `public_label`; o listener só o cria "se ainda não houver" (nunca duplica). O valor novo do listener é a auditoria de negócio `protocolada`.
- **Ciência condicionada à tendência desfavorável** (`nao_permitido`): "pendente" é indeterminado, não desfavorável — não dispara ciência.
- **Pré-condições antes da transação**: bloqueio não consome número de protocolo (anti-fachada).
- **Snapshot congelado** (RN-003): o protocolo lê/preserva o snapshot do 08-09, nunca reprocessa.

## Deviations from Plan

- **Evento criado na Task 1** (o plano agrupa o evento na Task 2): o `ProtocolarSolicitacaoService` (Task 1) DEPENDE do evento para despachá-lo — a classe precisa existir para o GREEN da Task 1. O listener/controller/request/rota/registro ficaram na Task 2, como planejado.
- **Testes extras além dos nomeados no plano** (cobertura de ramos reais): `test_bloqueia_sem_dados_minimos` (FA-01 dados mínimos), `test_ciencia_nao_registrada_quando_simulacao_favoravel` (ciência só com tendência desfavorável) e `test_nao_dispara_evento_no_rollback` (separado do happy-path do evento). Total 13 testes SQLite (o plano sugeria ~8).
- **`test_so_dono_em_rascunho_protocola` na Task 2** (controller existe): a parte "terceiro 403" exige a rota/controller; o guard de status do service ("protocolada de novo") é coberto em `test_nao_protocola_fora_de_rascunho` (Task 1, nível de service).
- **Redirect de sucesso para `portal.solicitacoes.index`** (não há rota de detalhe/consulta ainda — é o 08-11): o número vai no flash `status`. O 08-13 refina o destino.

## Issues Encountered

- **Nenhum bloqueio.** O protocolo roda 100% real com os componentes do 08-01/05/08/09. As pendências SEDUR herdadas (zona oficial → veredito pendente na simulação) não afetam o protocolo (direito de petição — a simulação é orientativa e não bloqueia).

## Verification (evidência fresca)

- **RED Task 1:** "Target class [App\\Services\\Solicitacao\\ProtocolarSolicitacaoService] does not exist". **GREEN:** `--filter=ProtocolarSolicitacaoTest` → 9/9.
- **RED Task 2:** 4 falhas ("Route [portal.solicitacoes.protocolar] not defined"). **GREEN:** `--filter=ProtocolarSolicitacaoTest` → 13/13 (62 asserções).
- **Filtros do plano:** `--filter=ProtocolarSolicitacaoTest` → **13/13** (SQLite); `POSTGIS_TESTS_REQUIRED=true --group=postgis --filter=ProtocolarConcorrenciaPostgisTest` → **1/1** (8 asserções, `pgsql` real, sem skip).
- **Suíte completa:** `POSTGIS_TESTS_REQUIRED=true php artisan test --compact` → **784 testes, 784 passaram, 0 falhas** (3966 asserções; inclui 19 `@group postgis` com container `sile-pgsql` healthy — todos executados de verdade, sem skip).
- **`vendor/bin/pint --dirty --format agent`** → passed (após cada task).

## Next Phase Readiness

- **08-11 (consulta de protocolo / timeline pública)**: a timeline já tem o marco `public_label` da transição protocolada; consulta consome `viability_request_transitions`. A rota de detalhe/consulta substitui o redirect provisório para o index.
- **08-12 (cancelar)**: reusa a `ViabilityRequestStateMachine` (protocolada→cancelada já no mapa do 08-01) e a `ViabilityRequestPolicy::cancel`.
- **08-13 (UI revisar/protocolar)**: consome `portal.solicitacoes.protocolar` (com `proceed_despite` quando a simulação tende a indeferimento) e exibe os bloqueios documentais (`flash.error`) e o número no sucesso (`flash.status`).
- **08-14/15 (contingência / atendimento presencial)**: o mesmo `ProtocolarSolicitacaoService` protocola (muda só a origem auditada e o ator); o evento de domínio dispara igual.
- **Ganchos EP09/EP11/EP13**: pendurar listeners em `SolicitacaoProtocolada` (notificação, elegibilidade expresso, resposta Regin) — sem tocar o protocolo.

---
*Phase: 08-solicitacao-de-viabilidade*
*Completed: 2026-06-14*

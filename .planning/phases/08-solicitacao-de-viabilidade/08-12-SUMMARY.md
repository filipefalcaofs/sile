---
phase: 08-solicitacao-de-viabilidade
plan: 12
subsystem: cancelamento-solicitacao
tags: [solicitacao-viabilidade, hu-070, cancelar, state-machine, estados-cancelaveis, parametrizacao, hu-014, rn-002, ca-03, ca-04, anti-fachada, catalogo-49]

# Dependency graph
requires:
  - phase: 08-solicitacao-de-viabilidade
    plan: "01"
    provides: "ViabilityRequestStateMachine (transition rascunho/protocolada→cancelada já no mapa + timeline + auditoria RN-002), ViabilityRequest (cancelled_at/cancelled_reason/cancelled_by_user_id fora do fillable)"
  - phase: 08-solicitacao-de-viabilidade
    plan: "05"
    provides: "ViabilityRequestPolicy::cancel (dono + status em {rascunho, protocolada}, CA-04)"
  - phase: 08-solicitacao-de-viabilidade
    plan: "02"
    provides: "grupo de parâmetros 'solicitacao' + fallback config/sile.php (padrão HU-014); contagem do catálogo"
provides:
  - "App\\Services\\Solicitacao\\CancelarSolicitacaoService::cancel(ViabilityRequest, User $actor, string $reason): void — cancelamento via StateMachine com estados canceláveis parametrizáveis"
  - "App\\Services\\Solicitacao\\CancelamentoNaoPermitidoException (->status, ->estadosCancelaveis) — bloqueio fora dos estados (CA-03)"
  - "App\\Http\\Controllers\\Portal\\CancelamentoSolicitacaoController@destroy + CancelarSolicitacaoRequest (reason obrigatório)"
  - "Parâmetro solicitacao.cancelamento.estados_cancelaveis (json, default rascunho+protocolada) — catálogo 48→49 + fallback config/sile.php"
  - "Rota DELETE portal/solicitacoes/{solicitacao} (name portal.solicitacoes.cancelar)"
affects: [08-13-ui-wizard, 08-16-fechamento]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Estados canceláveis como PARÂMETRO de negócio (solicitacao.cancelamento.estados_cancelaveis, json) — efeito sem deploy, default honesto, definição fina pendente SEDUR"
    - "Policy = guarda grossa (dono + não-decidido); parâmetro = regra fina dos estados — o service compara o status atual contra a lista parametrizada e bloqueia auditando se fora dela"
    - "Único dono da mudança de catálogo na Wave 7 (ParameterSeeder/config/sile.php/seeder-tests) — contagem 48→49 atualizada em ParameterSeederTest e DatabaseSeederTest"

key-files:
  created:
    - app/Services/Solicitacao/CancelarSolicitacaoService.php
    - app/Services/Solicitacao/CancelamentoNaoPermitidoException.php
    - app/Http/Controllers/Portal/CancelamentoSolicitacaoController.php
    - app/Http/Requests/Portal/CancelarSolicitacaoRequest.php
    - tests/Feature/Solicitacao/CancelarSolicitacaoTest.php
  modified:
    - database/seeders/ParameterSeeder.php
    - config/sile.php
    - routes/portal.php
    - tests/Feature/Seeders/ParameterSeederTest.php
    - tests/Feature/Seeders/DatabaseSeederTest.php

key-decisions:
  - "Estados canceláveis em parâmetro json (solicitacao.cancelamento.estados_cancelaveis), default ['rascunho','protocolada'] — antes da decisão. Definição fina é pendência SEDUR; o default é honesto e ajustável sem deploy (HU-014). Lido por Settings::get com fallback espelhado em config/sile.php (banco→cache→config)."
  - "Camadas de autorização vs. regra de estado: a ViabilityRequestPolicy::cancel (08-05) garante dono + não-decidido (rascunho/protocolada) e barra terceiro com 403 auditado (CA-04); a regra FINA de quais estados são canceláveis fica no service via parâmetro. Assim, com o parâmetro restrito (ex.: só ['rascunho']), uma protocolada passa pela policy mas é bloqueada pelo service (CA-03)."
  - "Bloqueio fora dos estados auditado com AuditService::log('solicitacoes','cancelamento-bloqueado', subject=solicitação, result='bloqueado') em vez de logBlocked() — REFINAMENTO sobre o plano (que sugeria logBlocked): preserva o SUBJECT (qual solicitação) e usa um evento semântico próprio (CA-03 não é 'acesso-negado'/CA-04). RN-002 mais completo. result='bloqueado' mantido."
  - "Cancelar grava cancelled_at/cancelled_reason/cancelled_by_user_id via forceFill (fora do #[Fillable], como o protocolo grava protocol_number) e transiciona pela StateMachine reason=$reason, publicLabel='Cancelada a pedido do requerente' — timeline + auditoria 'transicao' RN-002, tudo em UMA DB::transaction."
  - "Motivo do cancelamento OBRIGATÓRIO (CancelarSolicitacaoRequest: reason required|string|max:1000, mensagens pt-BR) — registra o porquê na trilha (reason da transição)."
  - "SEM toggle de feature no cancelamento: cancelar é direito do requerente sobre o próprio processo e deve permanecer disponível mesmo se o módulo de novas solicitações for desativado (degradação correta — não bloquear a retirada)."
  - "Rota DELETE solicitacoes/{solicitacao} (sem subsegmento) ANEXADA após as literais e demais {solicitacao} — não conflita (método/​caminho distintos das rotas de atividades/imóvel/documentos/protocolar)."

patterns-established:
  - "Parametrização de uma máquina de estados: a lista de estados de origem permitidos vira parâmetro json administrável; o service lê via Settings::get e bloqueia+audita quando o estado atual está fora — gabarito para futuros cancelamentos/transições parametrizáveis"

# Metrics
duration: ~18 min (commits bebca1d→20faf21, com leitura de contexto)
completed: 2026-06-14
---

# Phase 8 Plan 12: Cancelar solicitação (HU-070) Summary

**O cancelamento (HU-070) deixa o requerente interromper um processo indevido ou duplicado enquanto NÃO decidido — de rascunho e de protocolada (antes da decisão). O `CancelarSolicitacaoService::cancel(ViabilityRequest, User $actor, string $reason)` lê os estados canceláveis do parâmetro `solicitacao.cancelamento.estados_cancelaveis` (json, default `["rascunho","protocolada"]`, fallback em `config/sile.php` — efeito sem deploy, HU-014); se o status atual está fora da lista, AUDITA o bloqueio (`AuditService::log('solicitacoes','cancelamento-bloqueado', result='bloqueado')`) e lança `CancelamentoNaoPermitidoException` (CA-03) — sem cancelar nada (anti-fachada). Dentro dos estados, grava `cancelled_at`/`cancelled_reason`/`cancelled_by_user_id` (via `forceFill`, fora do fillable) e transiciona para cancelada pela `ViabilityRequestStateMachine` (`publicLabel` "Cancelada a pedido do requerente", `reason` do requerente) — timeline + auditoria RN-002 — tudo em UMA `DB::transaction`. O `CancelamentoSolicitacaoController@destroy` autoriza pela `ViabilityRequestPolicy::cancel` (dono + não-decidido; terceiro → 403 auditado, CA-04), exige o motivo (`CancelarSolicitacaoRequest`: `reason` obrigatório) e traduz o bloqueio de estado em `flash.error` (aviso, nunca silencioso). A definição fina dos estados canceláveis é pendência SEDUR — entregue como default honesto + parâmetro. ZERO dependência nova. TDD estrito: CancelarSolicitacaoTest 6/6 + as contagens de seeder atualizadas de 48 para 49; filtros do plano 25/25.**

## Performance

- **Duration:** ~18 min (2 commits TDD), com leitura de contexto
- **Tasks:** 2 (parâmetro estados canceláveis + contagens; service + exceção + controller + request + rota + testes)
- **Files:** 5 criados + 5 modificados — ZERO dependência nova

## Accomplishments

- **Parâmetro `solicitacao.cancelamento.estados_cancelaveis`** (json, default `["rascunho","protocolada"]`, grupo `solicitacao`) no `ParameterSeeder` + fallback em `config/sile.php`; catálogo **48→49** com as contagens travadas em `ParameterSeederTest` e `DatabaseSeederTest`.
- **`CancelarSolicitacaoService::cancel(ViabilityRequest, User $actor, string $reason): void`** — estados canceláveis parametrizáveis; bloqueio auditado fora deles; cancelamento real (`cancelled_*`) + transição auditada na mesma transação.
- **`CancelamentoNaoPermitidoException`** (`->status`, `->estadosCancelaveis`) — bloqueio comunicado (CA-03), traduzido em `flash.error`.
- **`CancelamentoSolicitacaoController@destroy`** + **`CancelarSolicitacaoRequest`** (`reason` obrigatório, pt-BR) + rota `DELETE portal/solicitacoes/{solicitacao}` (name `portal.solicitacoes.cancelar`).
- **Anti-fachada**: cancela de verdade (status cancelada, `cancelled_at`/`cancelled_reason`/`cancelled_by_user_id`, marco na timeline) via `ViabilityRequestStateMachine`; fora dos estados não cancela e audita o bloqueio; autorização por policy (só o dono/representado).

## Contrato para os próximos planos

### Service

```
App\Services\Solicitacao\CancelarSolicitacaoService::cancel(
    ViabilityRequest $request,
    App\Models\User $actor,
    string $reason,
): void
```

- Lê `solicitacao.cancelamento.estados_cancelaveis` (Settings::get → fallback config). Status fora da lista → `CancelamentoNaoPermitidoException` (`->status`, `->estadosCancelaveis`) + auditoria `solicitacoes/cancelamento-bloqueado` (result `bloqueado`).
- Dentro dos estados: grava `cancelled_at`/`cancelled_reason`/`cancelled_by_user_id` + transição para cancelada (timeline `public_label` "Cancelada a pedido do requerente" + auditoria `solicitacoes/transicao` RN-002), em `DB::transaction`.

### Rota + controller

- **`DELETE portal/solicitacoes/{solicitacao}`** (name `portal.solicitacoes.cancelar`), body `reason` (string, obrigatório).
- Autorização `ViabilityRequestPolicy::cancel` (dono + status em {rascunho, protocolada}; terceiro → 403 auditado, CA-04).
- Sucesso → redirect `portal.solicitacoes.index` + flash `status`. Bloqueio de estado → `back()` + flash `error`.

### Parâmetro (HU-014)

- **`solicitacao.cancelamento.estados_cancelaveis`** (json) — administrável; muda quais estados o requerente pode cancelar sem deploy. Default honesto `["rascunho","protocolada"]`; definição fina pendente SEDUR.

## Task Commits

TDD estrito (RED→GREEN verificado com evidência fresca antes de cada commit):

1. **Task 1: parâmetro estados canceláveis + contagens 48→49** — `bebca1d` (feat) — RED: 5 falhas (contagens 48≠49 + parâmetro ausente "null is not null") → GREEN: `--filter="ParameterSeederTest|DatabaseSeederTest"` 19/19 (235 asserções).
2. **Task 2: service + exceção + controller + request + rota + testes** — `20faf21` (feat) — RED: 6 erros ("Route [portal.solicitacoes.cancelar] not defined" / "Target class [CancelarSolicitacaoService] does not exist") → GREEN: `--filter=CancelarSolicitacaoTest` 6/6 (45 asserções).

## Decisions Made

- **Estados canceláveis em parâmetro json** (default `["rascunho","protocolada"]`, fallback config) — efeito sem deploy; definição fina pendente SEDUR.
- **Policy grossa + parâmetro fino**: a policy garante dono + não-decidido; o parâmetro define quais estados; com `['rascunho']` uma protocolada passa na policy mas é bloqueada+auditada pelo service (CA-03).
- **Bloqueio auditado com `log(... subject, result:'bloqueado')`** (não `logBlocked()`): preserva o subject e usa evento próprio `cancelamento-bloqueado` (CA-03 ≠ 'acesso-negado'/CA-04) — RN-002 mais completo.
- **Motivo obrigatório** (`reason`): registra o porquê na trilha (reason da transição).
- **Sem toggle de feature** no cancelar: retirar o próprio processo é direito do requerente e não deve depender do toggle de novas solicitações.

## Deviations from Plan

- **Auditoria do bloqueio via `AuditService::log(...)` com subject + result `bloqueado` em vez de `logBlocked()`** (que o plano sugeria). Motivo: `logBlocked()` fixa `event='acesso-negado'` e NÃO grava subject; o bloqueio aqui é por estado (CA-03), distinto da negação de acesso (CA-04). Usar `log` com `event='cancelamento-bloqueado'`, `subject=$request` e `result='bloqueado'` torna a trilha RN-002 mais completa (registra QUAL solicitação) sem perder o `result` bloqueado. Os critérios de aceite do plano não exigiam `logBlocked`.
- **6 testes (plano sugeria 5)**: além dos 5 nomeados, acrescentei `test_controller_traduz_bloqueio_em_aviso` (prova a tradução do bloqueio de estado em `flash.error` no controller + auditoria com o usuário) — espelha o `test_controller_traduz_bloqueio_documental` do 08-10. O `test_bloqueia_fora_dos_estados_cancelaveis` ficou no nível de service (assere a exceção nomeada diretamente).

## Issues Encountered

- **Execução concorrente na MESMA working dir (Wave 7)**: o plano 08-11 (consulta de protocolo) está em andamento e commitou `ca32767` (FortifyServiceProvider + TimelineSolicitacao + ConsultarProtocoloTest) entre a minha Task 1 e Task 2, além de manter arquivos não commitados (`ConsultaProtocoloController.php` untracked). Verificado: `ca32767` NÃO tocou `routes/portal.php` (sem clobber); reli o arquivo imediatamente antes de editar e ANEXEI só meu import + rota (git diff = só meu trecho); staging individual (não toquei arquivos do 08-11). Os commits ficaram lineares (`93e86e5`→`bebca1d`→`ca32767`→`20faf21`).
- **4 falhas na suíte completa são do 08-11 em andamento, NÃO regressão minha**: todas em `ConsultarProtocoloTest` com `RouteNotFoundException: Route [portal.protocolo.publico] not defined` (rota/controller do 08-11 ainda não registrados). Regressão focada dos fluxos que tocam meus arquivos (criar/protocolar/cancelar + seeders) = 46/46 verde.

## Verification (evidência fresca)

- **RED Task 1:** 5 falhas (`Failed asserting that 48 is identical to 49.` ×4 + parâmetro ausente). **GREEN:** `--filter="ParameterSeederTest|DatabaseSeederTest"` → 19/19 (235 asserções).
- **RED Task 2:** 6 erros (`Route [portal.solicitacoes.cancelar] not defined` / `Target class [CancelarSolicitacaoService] does not exist`). **GREEN:** `--filter=CancelarSolicitacaoTest` → 6/6 (45 asserções).
- **Filtros do plano:** `--filter="CancelarSolicitacaoTest|ParameterSeederTest|DatabaseSeederTest"` → **25/25** (280 asserções).
- **Regressão focada** (fluxos que tocam `routes/portal.php` + `ParameterSeeder`): Cancelar + Protocolar + Criar + ParameterSeeder + DatabaseSeeder → **46/46** (383 asserções).
- **Suíte completa:** `php artisan test --compact` → **798 testes, 794 passaram, 4 falhas** — as 4 falhas são EXCLUSIVAMENTE do 08-11 em andamento (`ConsultarProtocoloTest`, `portal.protocolo.publico` ainda não registrada), não regressão do 08-12.
- **`vendor/bin/pint --dirty --format agent`** → passed (após cada task).
- **Catálogo de parâmetros:** 49 (era 48) — confirmado em `ParameterSeederTest` e `DatabaseSeederTest`.

## Next Phase Readiness

- **08-13 (UI revisar/protocolar)**: expõe a ação de cancelar na lista "Minhas solicitações"/detalhe via `confirm-dialog` consumindo `DELETE portal.solicitacoes.cancelar` (com o motivo) e exibe o bloqueio de estado (`flash.error`) e o sucesso (`flash.status`).
- **08-16 (fechamento/seeds)**: a contagem do catálogo é 49; o smoke navegável pode exercitar o cancelamento de um rascunho/protocolada de seed.
- **Pendência SEDUR**: definição fina dos estados canceláveis após o protocolo — parametrizada em `solicitacao.cancelamento.estados_cancelaveis` (ajustável sem deploy quando a SEDUR decidir).

---
*Phase: 08-solicitacao-de-viabilidade*
*Completed: 2026-06-14*

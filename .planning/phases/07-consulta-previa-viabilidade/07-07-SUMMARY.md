---
phase: 07-consulta-previa-viabilidade
plan: 07
subsystem: backend
tags: [consulta-viabilidade, historico, snapshot-imutavel, escopo-dono, for-user, auditoria, rn-002, paginacao, inertia, anti-fachada, hu-060]

# Dependency graph
requires:
  - phase: 07-consulta-previa-viabilidade
    plan: 03
    provides: "Model imutável ViabilityQuery (casts array, $timestamps=false, scopeForUser) + ViabilityQueryFactory (snapshot padrão / estado anonima)"
  - phase: 07-consulta-previa-viabilidade
    plan: 05
    provides: "ConsultaViabilidadeResult::toArray (snapshot completo: entrada/veredito_locacional/versoes) — payload persistido"
  - phase: 07-consulta-previa-viabilidade
    plan: 06
    provides: "ConsultaViabilidadeController + 3 endpoints públicos (endereço/CNAE/inscrição) onde a persistência é acoplada no caminho de sucesso"
  - phase: 03-cadastro-empresarial
    provides: "CompanyController@index — precedente de listagem server-driven escopada ao dono + redirect do visitante a /portal/login"
provides:
  - "Persistência só-quando-autenticado: registrarHistorico grava snapshot imutável (input+result+rules_versions+user_id+ip) só com $request->user() !== null, no caminho de SUCESSO dos 3 endpoints"
  - "HistoricoConsultaController@__invoke: listagem paginada/ordenada (mais recente) das consultas do PRÓPRIO usuário (forUser), auditada (consulta-historico)"
  - "Rota autenticada portal.viabilidade.historico (auth:web + verified + lgpd.accepted) — sem conflito com a pública viabilidade.index"
  - "Contrato do item da listagem (insumo da UI 07-09): id, entry_type, resultado, resultado_label, input, created_at (ISO-8601), result (snapshot completo)"
affects: [07-09-ui-historico]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Persistência acoplada ao controller (não ao service): o ConsultaViabilidadeService permanece reutilizável (HU-141, Fase 8); gravar histórico é decisão do controller, guardada por auth()"
    - "Histórico só-quando-autenticado no caminho de sucesso: erros honestos (404/503/422/toggle) nunca viram histórico; anônima segue auditada sem histórico pessoal (anti-fachada)"
    - "Listagem escopada ao dono via scopeForUser($request->user()) — espelha CompanyController@index ('Minhas empresas'); acesso a histórico de terceiro bloqueado por escopo real (testado)"
    - "Consulta a dado do próprio usuário auditada explicitamente (event consulta-historico) — precedente do histórico de acessos"

key-files:
  created:
    - app/Http/Controllers/Portal/HistoricoConsultaController.php
    - tests/Feature/Viabilidade/HistoricoConsultaTest.php
  modified:
    - app/Http/Controllers/Portal/ConsultaViabilidadeController.php
    - routes/portal.php

key-decisions:
  - "Persistência é decisão do CONTROLLER, não do service: registrarHistorico grava só quando $request->user() !== null; o service segue puro/reutilizável (HU-141)"
  - "Snapshot gravado apenas no caminho de sucesso (após obter o $result, antes do return); erros retornam antes de chamar registrarHistorico — nenhum erro vira histórico"
  - "Escopo do dono usa $request->user() (não o usuário efetivo da representação): a consulta de viabilidade é pessoal de quem a executou; forUser garante isolamento real"
  - "page-size REUTILIZA ui.companies.per_page (precedente de listagens do portal) — decisão do plano para NÃO crescer o catálogo de parâmetros nesta fase"
  - "Rota literal viabilidade/historico no grupo autenticado (lgpd.accepted + ResolveRepresentation), ao lado de acessos/empresas — distinta da pública viabilidade.index (07-06), sem conflito de match"

requirements-completed: [HU-060]

# Metrics
duration: ~9 min
completed: 2026-06-14
---

# Phase 7 Plan 07: Histórico Autenticado da Consulta de Viabilidade (HU-060) Summary

**O critério 3 do EP07 fechado de ponta a ponta: a consulta grava um SNAPSHOT imutável em `viability_queries` SOMENTE quando o usuário está autenticado (`registrarHistorico` nos 3 endpoints públicos do 07-06, no caminho de sucesso — input + result completo + rules_versions da época + user_id + ip), e o `HistoricoConsultaController` lista, paginado e ordenado da mais recente, APENAS as consultas do próprio usuário (`scopeForUser` — precedente "Minhas empresas"), sob rota autenticada (`auth:web + verified + lgpd.accepted`) e com a consulta auditada (`consulta-historico`, RN-002). A consulta anônima segue auditada mas NÃO cria histórico pessoal; nenhum caminho de erro vira histórico — sem fachada. Provado por 7 feature tests (gravação autenticada / não-gravação anônima e em erro / escopo do dono / autenticação exigida / auditoria / ordenação).**

## Performance

- **Duration:** ~9 min (07:53 → 07:58 -03)
- **Tasks:** 2 (ambas em TDD estrito RED → GREEN → pint)
- **Files:** 4 (2 criados, 2 modificados)

## Regra de gravação do histórico (HU-060 — anti-fachada)

`registrarHistorico(Request $request, ConsultaViabilidadeResult $result)` no `ConsultaViabilidadeController`, chamado nos 3 métodos (endereco/cnae/inscricao) **apenas no caminho de sucesso**, após obter o `$result`:

- **Autenticado** (`$request->user() !== null`): grava `ViabilityQuery` com `user_id`, `entry_type` (= `entrada.tipo`), `input` (= `entrada`), `result` (= `toArray()` completo, para reprodução), `rules_versions` (= `versoes`), `resultado` desnormalizado (= `veredito_locacional.resultado`), `ip_address` e `created_at` (imutável — `$timestamps=false`).
- **Anônimo** (`$request->user() === null`): retorna sem gravar — a consulta segue **auditada** no service (RN-002), mas não há histórico pessoal.
- **Erro** (endereço não localizado 404 / serviço indisponível 503 / toggle off 422): o método retorna ANTES de `registrarHistorico` — **nenhum erro vira histórico**.

## Contrato do item da listagem (insumo direto da UI 07-09)

`portal.viabilidade.historico` → `Inertia::render('portal/viabilidade/historico', ['consultas' => <paginator>])`. O paginator expõe `data`, `total`, `per_page`, `current_page`, `from`, `to`. Cada item:

```
{
  id: number,
  entry_type: 'endereco' | 'cnae' | 'inscricao',
  resultado: 'permitido' | 'permitido_com_condicoes' | 'nao_permitido' | 'pendente' | null,
  resultado_label: string | null,   // ResultadoViabilidade::label(); null quando resultado é null
  input: object,                     // entrada da consulta (tipo, cnae, cnae_formatado, area, endereco?, inscricao?)
  created_at: string,                // ISO-8601
  result: object                     // snapshot COMPLETO (toArray) — alimenta a visualização/reprodução no 07-09
}
```

## Escopo, rota e auditoria

- **Escopo do dono:** `ViabilityQuery::forUser($request->user())->latest('created_at')->latest('id')` — o usuário vê só as próprias consultas; acesso a histórico de terceiro é bloqueado pelo escopo (testado: A com 2, B com 1 → A vê só 2).
- **Rota AUTENTICADA:** `Route::get('viabilidade/historico', HistoricoConsultaController::class)->name('viabilidade.historico')` no grupo `auth:web + verified + lgpd.accepted` (ao lado de painel/empresas/acessos). Visitante → redirect `/portal/login`. Rota literal distinta da pública `viabilidade.index` (07-06) — `route:list` confirma 5 rotas sem conflito.
- **page-size:** `Settings::get('ui.companies.per_page', 15)` — reutiliza o parâmetro de listagens do portal (decisão do plano: não crescer o catálogo do 07-01).
- **Auditoria (RN-002):** `audit->log('viabilidade','consulta-historico','Consulta do histórico de viabilidade', ['itens' => $paginator->total()], result:'sucesso')` — causer = usuário autenticado (precedente do histórico de acessos).

## Task Commits

Cada task foi commitada atomicamente (TDD: teste RED → implementação GREEN → pint):

1. **Task 1: persistência do snapshot quando autenticado (3 endpoints)** — `7938c7b` (feat)
2. **Task 2: HistoricoConsultaController@__invoke (escopo do dono, paginado, auditado) + rota autenticada** — `cc91f2e` (feat)

**Plan metadata:** este SUMMARY (docs).

_Commit `c1550b7` (07-08, componente .tsx) ficou intercalado entre os dois commits de 07-07 — executor paralelo da fase, sem colisão de arquivos (07-08 só tocou UI)._

## Files Created/Modified

- `app/Http/Controllers/Portal/ConsultaViabilidadeController.php` — método `registrarHistorico` + chamada nos 3 endpoints (caminho de sucesso); imports de `ViabilityQuery`, `ConsultaViabilidadeResult`, `Request`.
- `app/Http/Controllers/Portal/HistoricoConsultaController.php` — single-action `__invoke`: listagem `forUser` paginada/ordenada + transformação do item + auditoria `consulta-historico`.
- `routes/portal.php` — rota autenticada `viabilidade/historico` (grupo lgpd.accepted) + import do controller.
- `tests/Feature/Viabilidade/HistoricoConsultaTest.php` — 7 feature tests (gravação autenticada / anônima não grava mas é auditada / erro não grava / escopo do dono / exige autenticação / auditoria / ordenação).

## Decisions Made

- **Persistência no controller, não no service:** gravar histórico é responsabilidade do controller (guardada por `auth()`); o `ConsultaViabilidadeService` permanece puro e reutilizável (HU-141, Fase 8). Duplicar a gravação no service amarraria o motor à sessão HTTP.
- **Só-quando-autenticado + só no sucesso:** alinha com o 07-06 (anônima auditada sem histórico) e com o anti-fachada (erro honesto nunca vira registro). O `$result` só existe no caminho de sucesso; os erros retornam antes.
- **Escopo por `$request->user()` (não usuário efetivo):** a consulta de viabilidade é pessoal de quem a fez; o histórico não é "da empresa representada". `forUser` isola por dono real.
- **`ui.companies.per_page` reutilizado:** decisão explícita do plano para não inflar o catálogo de parâmetros nesta fase — page-size compartilhado das listagens do portal.

## Deviations from Plan

Sem bug, correção crítica, bloqueio ou mudança arquitetural. Ajustes menores de teste (mesmos `files_modified` do plano, sem scope creep):

**1. [Setup de teste] `RolesAndPermissionsSeeder` adicionado ao `setUp`**
- **Found during:** Task 2.
- **Motivo:** os testes do histórico autenticado usam `User::factory()->cidadao()->withAcceptedLgpdTerm()` (precedente "Minhas empresas") — o estado `cidadao()` exige o papel seedado. Os seeders de motor (Louos/Risco) já estavam no `setUp` para os testes de gravação via endpoint (Task 1).
- **Files modified:** `tests/Feature/Viabilidade/HistoricoConsultaTest.php` (apenas teste).

**2. [Asserção Inertia] Listagem provada por props, sem `->component()`**
- **Motivo:** a página `portal/viabilidade/historico` (.tsx) nasce no 07-09; seguindo o precedente do 07-06, o teste assere o escopo/ordem via props (`consultas.data`/`consultas.total`) e o shape do item, sem `->component()`. Reativar `assertInertia()->component('portal/viabilidade/historico')` no 07-09.
- **Files modified:** idem (apenas teste).

**Total:** 0 correções de produção; 2 ajustes de teste. Sem mudança de escopo.

## Issues Encountered

- **Executor paralelo da fase (07-08):** o commit `c1550b7` (componente `.tsx` do 07-08) ficou intercalado entre os dois commits de 07-07. Sem colisão — 07-08 só tocou a UI; os arquivos de backend/teste do 07-07 estão íntegros (recorrência do alerta do STATE sobre sessões GSD simultâneas na mesma fase).

## User Setup Required

None - nenhuma configuração de serviço externo é necessária.

## Verification

- `vendor/bin/pint --dirty --format agent` → passed (sem pendências; ajuste de `ordered_imports` em `routes/portal.php`).
- `php artisan test --compact --filter="HistoricoConsultaTest|ConsultaViabilidadePublicaTest"` → **17/17 verde** (Task 1 não regrediu o 07-06).
- `php artisan test --compact --filter=HistoricoConsultaTest` → **7/7 verde** (49 asserções).
- `php artisan test --compact --filter=Viabilidade` → **46/46 verde** (226 asserções).
- `php artisan test --compact --exclude-group postgis` → **646/646 verde** (3276 asserções) = 639 baseline (07-06) + 7 novos, **zero regressão**.
- `php artisan route:list --path=portal/viabilidade` → 5 rotas (4 públicas do 07-06 + `portal.viabilidade.historico` autenticada).
- **Greps de aceitação:** `ViabilityQuery::create` (1) e `registrarHistorico` (4) no controller público; `class HistoricoConsultaController` (1), `forUser` (2), `consulta-historico` (1) no controller do histórico; `viabilidade.historico` (1) em `routes/portal.php`.
- **Evidência anti-fachada:** consulta autenticada grava 1 snapshot com user_id/entry_type/result/rules_versions; anônima → `ViabilityQuery::count() === 0` mas auditada; endereço não localizado autenticado → 404 e `count() === 0`; escopo (A=2 / B=1 → A vê só 2); visitante → redirect login; ordenação mais-recente-primeiro.

## Next Phase Readiness

- **07-09 (UI do histórico):** consome a prop `consultas` (paginator) de `portal/viabilidade/historico` — `data` com o item documentado acima (`result` completo para a reprodução do snapshot), `total`/`from`/`to` para a paginação. Ao criar `resources/js/pages/portal/viabilidade/historico.tsx`, reativar `assertInertia()->component('portal/viabilidade/historico')`.
- **Bloqueios herdados (não introduzidos aqui):** o veredito `pendente` gravado no snapshot reflete a degradação honesta do motor LOUOS (zona pendente SEDUR, Fase 13) — o histórico apenas armazena o resultado real da época; quando a base de zona chegar, novas consultas gravam o veredito definitivo, sem mudar a lógica.

---
*Phase: 07-consulta-previa-viabilidade*
*Completed: 2026-06-14*

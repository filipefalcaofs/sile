---
phase: 15-relatorios-e-indicadores
plan: 11
subsystem: relatorios/export
tags: [hu-131, export, csv, xlsx, pdf, report-source, lgpd, cpf-masked, rn-005, rn-007, rn-009]

# Dependency graph
requires:
  - phase: 15-02
    provides: contrato único de exportação (ReportExporter/ReportSource/ReportDefinition/ReportFilters + drivers CSV/PDF)
  - phase: 15-08
    provides: driver XLSX (openspout) — terceiro formato do contrato
  - phase: 15-09
    provides: padrão de branch ?formato= no controller delegando ao ReportExporter
provides:
  - 5 ReportSources das listagens das Fases 1–2 (cnaes/parâmetros/usuários/perfis/acessos)
  - branch ?formato= (csv/xlsx/pdf) nos 5 índices, sem rota nova (RN-009)
  - minimização de PII de usuários (cpf_masked por default — RN-007) com gate de PII reconstrutível pelo bag
affects: [15-13, 15-14]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Listagem das Fases 1–2 ganha export adicionando um ReportSource + branch ?formato= (~3 linhas) que delega ao ReportExporter — nenhuma tela reimplementa export (RN-009)"
    - "Filtros da listagem (search/active/sort/tab/user/pii) viajam no BAG do ReportFilters (NÃO no construtor) — source reconstrutível via fromArray, RN-005 no síncrono E no assíncrono"
    - "Gate de PII no bag: cpf_masked por default; CPF completo só quando o controller libera (pii) a partir de uma permissão — capacidade testada na fonte, desligada no controller até o DPO definir a permissão"

key-files:
  created:
    - app/Services/Relatorios/Export/Sources/CnaesReportSource.php
    - app/Services/Relatorios/Export/Sources/ParametrosReportSource.php
    - app/Services/Relatorios/Export/Sources/UsuariosReportSource.php
    - app/Services/Relatorios/Export/Sources/PerfisReportSource.php
    - app/Services/Relatorios/Export/Sources/AcessosUsuarioReportSource.php
    - tests/Feature/Relatorios/RetrofitListagensTest.php
  modified:
    - app/Http/Controllers/Gestao/CnaeController.php
    - app/Http/Controllers/Gestao/ParameterController.php
    - app/Http/Controllers/Gestao/UserManagementController.php
    - app/Http/Controllers/Gestao/RoleController.php
    - app/Http/Controllers/Gestao/AccessHistoryController.php

key-decisions:
  - "Sem permissão de PII no projeto → o branch de usuários NUNCA seta pii (CPF sempre mascarado); a permissão de PII fica como pendência DPO (RN-007)"
  - "AcessosUsuarioReportSource espelha as colunas REAIS da tela (evento/ip/canal/data-hora) e não user_agent — RN-005 = export reflete a listagem"
  - "Branch ?formato= segue a idiom estabelecida em ProcessoController/15-09 (request->string('formato')->lower() + in_array), não o snippet ilustrativo do plano (com erro de tipo)"

patterns-established:
  - "ReportSource de listagem lê o BAG e reproduz a MESMA query do index (when/whereLike caseSensitive:false/orderBy) com as colunas visíveis da tela"

# Metrics
duration: 27 min
completed: 2026-06-16
---

# Phase 15 Plano 11: Retrofit de exportação (HU-131) nas listagens das Fases 1–2 Summary

**As 5 listagens administrativas (CNAEs, parâmetros, usuários, perfis e histórico de acessos) ganharam exportação CSV/XLSX/PDF do conjunto filtrado pelo contrato único, com CPF mascarado por default e auditoria — sem nenhuma rota nova.**

## Performance

- **Duration:** ~27 min
- **Started:** 2026-06-16T14:45:00-03:00
- **Completed:** 2026-06-16T15:12:00-03:00
- **Tasks:** 3 (TDD estrito: RED → GREEN por task)
- **Files modified:** 11 (5 ReportSources novos + 5 controllers + 1 teste)

## Accomplishments

- Cumprido o critério de pronto transversal 8 do ROADMAP: paridade de export em toda a gestão (Fases 1–2), herdando o contrato único da HU-131.
- 5 `ReportSource` concretos que reproduzem EXATAMENTE a query filtrada de cada `index` lendo os filtros do BAG do `ReportFilters` (reconstrutíveis via `fromArray` — RN-005 no síncrono E no assíncrono).
- `?formato=` (csv/xlsx/pdf) em cada `index` delegando ao `ReportExporter` (RN-009 — nenhuma listagem reimplementa export); auditoria por exportação herdada do contrato (RN-008).
- Minimização de PII (RN-007): usuários exportam `cpf_masked` por default; o gate de liberação de PII viaja no bag e está provado em teste na fonte, mas desligado no controller (sem permissão de PII no projeto — pendência DPO).

## Colunas de cada listagem (RN-005 — espelham a tela)

- **CNAEs** (`cnaes`): Código (formatado), Denominação, Situação (Ativo/Inativo), Classe. Filtros do bag: `search` (prefixo de dígitos no código OU denominação case-insensitive), `active`, `sort`/`direction`.
- **Parâmetros** (`parametros`): Grupo, Chave, Tipo, Descrição, Valor, Padrão, Atualizado em. Catálogo sem filtros (ordem grupo/chave). Valor de parâmetro **sensível** sai como `[sensível]` — nunca em claro (RN-009).
- **Usuários** (`usuarios`): Nome, E-mail, Papel, CPF, Situação, Criado em. Filtros do bag: `search` (nome/e-mail case-insensitive), `tab` (equipe SEDUR × portal). CPF = `cpf_masked` por default (`***.***.***-NN`); completo só com `pii` liberado no bag.
- **Perfis** (`perfis`): Perfil, Permissões (total), Usuários (total). Sem filtros (ordem por nome).
- **Histórico de acessos do usuário** (`acessos-usuario`): Evento, IP, Canal, Data/hora. O `user`-alvo viaja no bag; a query reproduz `user_id` do alvo OU e-mail do alvo (inclui falhas/bloqueios pré-login).

## Permissão de PII usada

**Nenhuma** — o projeto não possui permissão de PII/CPF (as 30 permissões do `RolesAndPermissionsSeeder` não incluem nenhuma de liberação de dado pessoal). Por isso o branch de usuários **nunca seta `pii`** e o CPF sempre sai mascarado. A capacidade de emitir o CPF completo existe e está testada na fonte (`UsuariosReportSource` lê `pii` do bag), pronta para ser ligada quando a permissão for definida. **Pendência DPO registrada:** definir a permissão de liberação de PII (e quais colunas sensíveis ela libera) — até lá, CPF mascarado.

## Confirmação: nenhuma rota nova

Os 5 índices ganharam apenas o branch `?formato=` (delegando ao `ReportExporter`); nenhuma rota foi adicionada em `routes/gestao.php` (arquivo não tocado — as rotas `cnaes.index`, `parametros.index`, `usuarios.index`, `perfis.index` e `acessos.show` já existiam). `ParameterController::index`, `RoleController::index` e `AccessHistoryController::__invoke` passaram a receber `Request` (os dois primeiros não recebiam) e tiveram o tipo de retorno alargado para `Inertia\Response|Symfony\...\Response` — necessário para o branch retornar o streaming do driver.

## Task Commits

1. **Task 1 (CNAEs + Parâmetros)** — `07b3686` (test) → `5604f9e` (feat)
2. **Task 2 (Usuários cpf_masked + Perfis)** — `35f643c` (test) → `06c4982` (feat)
3. **Task 3 (Histórico de acessos do usuário)** — `82de476` (test) → `139299c` (feat)

_TDD estrito: cada task commitou o teste falhando (RED, verificado) antes da implementação mínima (GREEN, verificado)._

## Files Created/Modified

- `app/Services/Relatorios/Export/Sources/CnaesReportSource.php` — fonte da listagem de CNAEs (busca/situação/ordenação do bag).
- `app/Services/Relatorios/Export/Sources/ParametrosReportSource.php` — catálogo de parâmetros; valor sensível mascarado (RN-009).
- `app/Services/Relatorios/Export/Sources/UsuariosReportSource.php` — usuários com `cpf_masked` por default + gate de PII no bag (RN-007).
- `app/Services/Relatorios/Export/Sources/PerfisReportSource.php` — perfis com totais de permissões/usuários.
- `app/Services/Relatorios/Export/Sources/AcessosUsuarioReportSource.php` — histórico de acessos do usuário-alvo (user do bag, e-mail derivado do id).
- `app/Http/Controllers/Gestao/CnaeController.php` — branch `?formato=`.
- `app/Http/Controllers/Gestao/ParameterController.php` — branch `?formato=` (+ `Request` no index).
- `app/Http/Controllers/Gestao/UserManagementController.php` — branch `?formato=` (sem setar `pii`).
- `app/Http/Controllers/Gestao/RoleController.php` — branch `?formato=` (+ `Request` no index).
- `app/Http/Controllers/Gestao/AccessHistoryController.php` — branch `?formato=` (+ tipo de retorno alargado).
- `tests/Feature/Relatorios/RetrofitListagensTest.php` — 10 testes (RN-005/RN-007/RN-009 + formatos + reconstrução pelo bag + gate de PII na fonte + auditoria).

## Decisions Made

- **Sem permissão de PII → CPF sempre mascarado no controller.** O plano previa usar uma permissão de PII existente; não há nenhuma no projeto. Decisão (anti-fachada): nunca setar `pii` no branch (CPF mascarado de verdade), manter a capacidade testada na fonte e escalar a definição da permissão como pendência DPO.
- **Colunas do histórico de acessos = as da tela (evento/ip/canal/data-hora).** O plano sugeria `user_agent`, mas a listagem `gestao/acessos` exibe `channel` (canal), não `user_agent`. RN-005 (export reflete a listagem) prevalece; a fonte irmã `AcessosReportSource` (trilha) também usa `channel`.
- **Idiom do branch `?formato=`.** Seguida a convenção real do `ProcessoController`/15-09 (`$request->string('formato')->lower()->toString()` + `in_array(['csv','xlsx','pdf'])`), não o snippet ilustrativo do plano (`$request->query('formato')->toString()`, que tem erro de tipo).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] `index()` sem `Request` em ParameterController e RoleController**
- **Found during:** Tasks 1 e 2
- **Issue:** `ParameterController::index()` e `RoleController::index()` não recebiam `Request`, impossibilitando ler `?formato=`.
- **Fix:** Adicionado `Request $request` e alargado o tipo de retorno para `Inertia\Response|Symfony\Component\HttpFoundation\Response` (padrão do `ProcessoController`).
- **Files modified:** `ParameterController.php`, `RoleController.php`
- **Verification:** `--filter=RetrofitListagens` verde; `--filter=Relatorios` 114/114.
- **Committed in:** `5604f9e` (Task 1), `06c4982` (Task 2)

**2. [Rule 1 - Correctness] Coluna `user_agent` do plano não existe na listagem de acessos**
- **Found during:** Task 3
- **Issue:** O plano listava `user_agent`; a tela e a fonte irmã usam `channel` (canal).
- **Fix:** Colunas do `AcessosUsuarioReportSource` espelham a listagem (Evento, IP, Canal, Data/hora) — RN-005.
- **Files modified:** `AcessosUsuarioReportSource.php`
- **Verification:** `test_export_csv_de_acessos_traz_so_os_acessos_do_usuario_rn005` verde.
- **Committed in:** `139299c` (Task 3)

---

**Total deviations:** 2 auto-fixed (1 blocking, 1 correctness/RN-005). **Impact:** nenhuma mudança de escopo; ambos necessários para o branch funcionar e refletir a tela.

## Issues Encountered

None — as 3 tasks executaram em RED → GREEN sem bloqueios.

## User Setup Required

None — nenhuma configuração de serviço externo. **Pendência DPO (não bloqueia):** definição da permissão de liberação de PII para o CPF completo na exportação de usuários (até lá, CPF mascarado por default).

## Next Phase Readiness

- Critério de pronto transversal 8 (export nas listagens das Fases 1–2) cumprido; as telas React podem acoplar o `<ExportMenu>` de 15-12 apontando para `{index}?formato=`.
- Pendência DPO registrada (permissão de PII) — não bloqueia; degrada honesto (CPF mascarado).
- Verificação fresca: `--filter=RetrofitListagens` 10/10 (37 asserções); `--filter=Relatorios` 114/114 (465 asserções); `vendor/bin/pint` (arquivos do plano) passed.

---
*Phase: 15-relatorios-e-indicadores*
*Completed: 2026-06-16*

---
phase: 12-auditoria-e-compliance
plan: 04
subsystem: backend
tags: [auditoria, activity-log, access-logs, hu-100, hu-098, hu-101, lgpd, csv-streaming, server-driven, inertia]

# Dependency graph
requires:
  - phase: 12-auditoria-e-compliance
    provides: "12-01 — índices de consulta (created_at; (log_name,created_at); event) + coluna personal_data + AuditService::log(..., personalData)"
  - phase: 12-auditoria-e-compliance
    provides: "12-03 — permissão consultar-auditoria (gestor/admin) + parâmetro ui.auditoria.per_page"
  - phase: 01-identidade-acesso-e-auditoria-transversal
    provides: "activity_log (RN-002) + HasAuditoria (attribute_changes) + AccessLog (HU-010) + 403 auditado no ponto único"
  - phase: 10-analise-tecnica-sedur
    provides: "Padrão server-driven + CSV streamDownload/chunk(200) (ProcessoQueryService/ProcessoController)"
provides:
  - "AuditTrailQueryService: filtered(filtros) / apenasAlteracoes(filtros) / acessos(filtros) sobre activity_log + access_logs (server-driven, sem materializar)"
  - "ActivityResource: payload minimizado da trilha (causer/acting_for/subject/result/rules_version/ip/channel/personal_data + attribute_changes), sem properties cruas"
  - "Gestao\\AuditoriaController: index (roteia fonte, pagina, AUDITA) + export CSV streaming (mesmos filtros, guarda de volume, AUDITA)"
  - "Rotas gestao.auditoria.index e gestao.auditoria.export sob permission:consultar-auditoria"
  - "config sile.auditoria.export.max_linhas (constante técnica de volume = 50000)"
affects:
  - "12-10 (página da trilha): consome props de gestao/auditoria/index + rotas gestao.auditoria.*"
  - "12-12 (smoke/verificação integral da auditoria)"

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Query service server-driven espelhando o ProcessoQueryService (when()/whereLike caseSensitive:false/orderByDesc/eager-load), agora sobre activity_log"
    - "Busca textual por causer via whereHasMorph('causer', [User::class]) — constraint correta sobre relação morphTo"
    - "Fonte secundária (access_logs) por Builder próprio (merge na aplicação, não UNION SQL)"
    - "Meta-auditoria: o próprio controller de leitura/exportação chama AuditService com personalData:true (CA-02)"
    - "Guarda de volume no CSV streaming via contador + chunk(200) retornando false ao atingir max_linhas (config, não catálogo HU-014)"

key-files:
  created:
    - "app/Services/Auditoria/AuditTrailQueryService.php"
    - "app/Http/Resources/ActivityResource.php"
    - "app/Http/Controllers/Gestao/AuditoriaController.php"
    - "tests/Feature/Auditoria/AuditoriaConsultaTest.php"
    - "tests/Feature/Auditoria/AuditoriaExportTest.php"
  modified:
    - "config/sile.php"
    - "routes/gestao.php"

key-decisions:
  - "activity_log É a trilha (decisão do CONTEXT): consulta direto, sem tabela/materialized view; access_logs é fonte secundária por merge na aplicação"
  - "Busca por nome do causer com whereHasMorph([User::class]) (não whereHas) — causer é morphTo; evita erro de relação polimórfica"
  - "entidade_tipo filtra subject_type com whereLike %valor% (aceita basename E classe completa, já que não há morph map no projeto)"
  - "max_linhas é constante TÉCNICA em config/sile.php (precedente [02-02]); per_page é o parâmetro de negócio ui.auditoria.per_page (HU-014, 12-03)"
  - "ActivityResource NÃO expõe properties cruas (minimização LGPD); o attribute_changes (diff estruturado) É exposto para a HU-098"
  - "Export pleno XLSX/PDF permanece bloqueado honesto (HU-131/Fase 15); só CSV agora"
  - "Commit atômico único por task (test+impl, padrão da fase) por causa do working tree COMPARTILHADO com 12-05/12-06; pathspec no git add e no git commit, nunca git add -A"

patterns-established:
  - "AuditTrailQueryService como ponte de leitura da trilha — base direta da página 12-10 e de futuros recortes (LGPD/abuso)"
  - "CSV de auditoria com colunas distintas por fonte (atividade vs acessos), ambos com guarda de volume"

# Metrics
duration: ~12min
completed: 2026-06-15
---

# Phase 12 Plan 04: Superfície de consulta e exportação da trilha de auditoria Summary

**Consulta unificada server-driven da trilha (HU-100) + histórico de alterações (HU-098) + fonte global de acessos sobre `activity_log`/`access_logs`, com export CSV em streaming (HU-101) — tudo gated por `consultar-auditoria` e auto-auditado (meta-auditoria CA-02, `personal_data`), sem materializar nada e sem features de fachada.**

## Performance

- **Duration:** ~12 min
- **Started:** 2026-06-15T08:44:00Z (aprox.)
- **Completed:** 2026-06-15T08:56:00Z
- **Tasks:** 2 (TDD estrito RED→GREEN em cada)
- **Files:** 5 criados, 2 modificados

## Accomplishments

- **HU-100 (consulta filtrável):** `AuditTrailQueryService::filtered()` aplica filtros REAIS por período (`created_at`), usuário (`causer_id` ou nome via `whereHasMorph`), entidade (`subject_type`/`subject_id`), ação (`log_name`/`event`) e resultado (`result`), do mais recente ao mais antigo, com eager-load `causer`/`actingFor`/`subject` (sem N+1).
- **HU-098 (alterações):** `apenasAlteracoes()` = `filtered()->whereNotNull('attribute_changes')` — recorta só os registros com diff de dados; comprovado contra diff REAL gravado por `HasAuditoria` num update de `Cnae`.
- **Fonte de acessos:** `acessos()` lista o histórico GLOBAL de `access_logs` (login/logout/falha/bloqueio) por Builder próprio (merge na aplicação, ≠ `AccessHistoryController` por usuário).
- **HU-101 (export CSV):** `AuditoriaController::export` reusa `streamDownload` + `fputcsv` + `chunk(200)` (padrão Fase 10), respeitando os mesmos filtros e a fonte, com guarda de volume técnica (`auditoria.export.max_linhas`). Export pleno XLSX/PDF segue bloqueado honesto (HU-131/Fase 15).
- **Meta-auditoria CA-02:** index e export auditam a própria operação (`log_name='auditoria'`, `event` `consulta-trilha`/`exporta-trilha-csv`, `personalData: true`). O 403 sem `consultar-auditoria` é auditado no ponto único (`seguranca`/`acesso-negado`/`bloqueado`).
- **Minimização LGPD:** `ActivityResource` não expõe `properties` cruas; expõe o `attribute_changes` (diff) e os metadados da RN-002.

## API do AuditTrailQueryService

```php
filtered(array $filtros): Builder<Activity>          // HU-100
apenasAlteracoes(array $filtros): Builder<Activity>  // HU-098 (whereNotNull attribute_changes)
acessos(array $filtros): Builder<AccessLog>          // fonte secundária global
```

Chaves de filtro reconhecidas (vazias são ignoradas, normalizadas como no `ProcessoQueryService`):

| Chave | Aplica em | Fonte |
|---|---|---|
| `data_de` / `data_ate` | `whereDate('created_at', >=/<=)` | atividade + acessos |
| `usuario_id` | `causer_id` (trilha) / `user_id` (acessos) | ambas |
| `usuario` | nome do causer via `whereHasMorph([User::class])` / `whereHas('user')` (acessos) | ambas |
| `entidade_tipo` | `whereLike('subject_type', %v%)` (aceita basename) | atividade |
| `entidade_id` | `subject_id` | atividade |
| `log_name` | `log_name` (exato) | atividade |
| `event` | `event` (exato) | atividade + acessos |
| `resultado` | `result` (exato) | atividade |

## Shape do ActivityResource (->resolve(), sem wrapper "data")

```
id, created_at (ISO8601), log_name, event, description,
causer: { id, nome } | null (null = sistema),
acting_for: { id, nome } | null,
subject: { type (basename), id, label } | null,
result, rules_version, ip_address, channel,
personal_data (bool),
attribute_changes: { attributes, old } | null   // diff HU-098
```

`properties` cruas NÃO são expostas (minimização LGPD).

## Rotas (sob `permission:consultar-auditoria`)

| Método | URI | Name |
|---|---|---|
| GET | `gestao/auditoria/export` | `gestao.auditoria.export` |
| GET | `gestao/auditoria` | `gestao.auditoria.index` |

A rota estática `export` vem ANTES do index (proteção contra wildcards futuros do grupo). `?formato=csv` no index delega ao `export`.

## Props do Inertia `gestao/auditoria/index` (insumo do 12-10)

```
registros      // paginator server-side (ActivityResource OU linha de acesso), withQueryString
fonte          // 'atividade' | 'alteracoes' | 'acessos'
filtros        // filtros crus + per_page
fonteOptions   // [{ value, label }] das 3 fontes
perPageOptions // [10, 20, 50, 100]
```

Linha da fonte `acessos`: `{ id, created_at, event, usuario, email, ip_address, channel }`.

## Config (constante técnica)

```php
// config/sile.php
'auditoria' => ['export' => ['max_linhas' => 50000]],
```

Guarda de volume do CSV (fora do catálogo HU-014 — precedente [02-02]). O `per_page` da consulta é o parâmetro de negócio `ui.auditoria.per_page` (default 20, HU-014/12-03).

## Task Commits

1. **Task 1: AuditTrailQueryService + ActivityResource** - `2095d8d` (feat, TDD)
2. **Task 2: AuditoriaController + config + rotas** - `264595d` (feat, TDD)

## Deviations from Plan

- **Commits atômicos test+impl unificados por task** (em vez de RED/GREEN separados): o working tree é COMPARTILHADO com os executores 12-05/12-06 em paralelo, então deixar um teste RED commitado correria o risco de quebrar a suíte de um irmão. A disciplina TDD foi seguida no processo (teste escrito, RED verificado pelo motivo certo, GREEN verificado); só a granularidade do commit foi consolidada — mesmo padrão dos SUMMARYs 12-01/12-03 ("feat, TDD"). Uso de `pathspec` no `git add` e no `git commit` para tocar apenas os meus arquivos.
- Nada além do escopo: nenhum toque em arquivos de 12-05/12-06; único editor de `routes/gestao.php` e `config/sile.php` na Wave 2.

## Issues Encountered

- Nenhum bloqueio. `attribute_changes` é coluna REAL populada por `HasAuditoria` (confirmado por testes existentes e por teste novo com `Cnae`), então `whereNotNull('attribute_changes')` funciona sobre dado real — não foi preciso mecanismo adicional.

## Evidência de verificação (output real)

- RED Task 1: `--filter=AuditoriaConsultaTest` → **11 errors** (classes ausentes), RED pelo motivo certo.
- GREEN Task 1: `--filter=AuditoriaConsultaTest` → **11 passed, 38 assertions**.
- RED Task 2: `--filter=AuditoriaExportTest` → **9 failed** (404 — rota/controller inexistentes), RED pelo motivo certo.
- GREEN Task 2: `--filter=AuditoriaExportTest` → **9 passed, 47 assertions**.
- Combinado do plano: `--filter="AuditoriaConsultaTest|AuditoriaExportTest"` → **20 passed, 85 assertions**.
- Rotas: `php artisan route:list --path=gestao/auditoria` → `gestao.auditoria.index` + `gestao.auditoria.export`.
- Anti-regressão: `php artisan test --compact --exclude-group=postgis` → **1262 passed, 6534 assertions, 0 falhas** (inclui 12-05/12-06 já concluídos + Fases 1–11). Grupo `postgis` excluído por design (gotcha do `RefreshDatabaseState` em processo único; este plano não toca o espacial).
- `vendor/bin/pint --format agent` nos meus arquivos → passed em ambas as tasks. `ReadLints` limpo.

## Next Phase Readiness

- **12-10** (página da trilha): props de `gestao/auditoria/index` e rotas `gestao.auditoria.*` prontas; `fonteOptions`/`perPageOptions` já no payload. A UI consome o `ActivityResource` (e a linha de acesso) sem novo backend.
- **12-12** (smoke/verificação): a consulta/exportação reais e a meta-auditoria estão cobertas por feature tests; o smoke pode exercitar as 3 fontes + CSV + 403 auditado.
- Gotcha mantido (12-01/12-03): na verificação integral, rodar a suíte como `composer test` (2 processos: `--exclude-group postgis` e depois `--group postgis`) para o `sile_testing` migrar corretamente.

---
*Phase: 12-auditoria-e-compliance*
*Completed: 2026-06-15*

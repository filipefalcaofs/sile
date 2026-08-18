---
phase: 12-auditoria-e-compliance
plan: 09
subsystem: api
tags: [hu-149, abuse-alerts, painel, efetividade, malha-fina, server-driven, inertia, gerenciar-alertas-abuso, anti-fachada, tdd, sqlite]

# Dependency graph
requires:
  - phase: 12-auditoria-e-compliance
    provides: "12-03 — ledger abuse_alerts (model AbuseAlert + HasAuditoria, enums AbuseSeverity/AbuseAlertStatus, factory com states), permissão gerenciar-alertas-abuso (gestor+admin), campos de resolução (status/resolved_by/resolved_at/justification/fine_mesh_referral_id)"
  - phase: 12-auditoria-e-compliance
    provides: "12-06/12-08 — detectores geram os alertas e encaminham à malha fina acima do limiar (fine_mesh_referral_id)"
  - phase: 10-analise-tecnica-sedur
    provides: "MalhaFinaService + fine_mesh_referrals (HU-136) — padrão de ação auditada com justificativa obrigatória, ortogonal ao status (RN-001)"
provides:
  - "Gestao\\AbusoController@index — lista filtrável (rule_key/severity/status/período em detected_at) paginada server-side + indicador de efetividade (confirmados ÷ gerados, geral e por regra — RN-005), auditada (RN-002)"
  - "Gestao\\AbusoController@confirmar/@descartar — resolução humana com justificativa OBRIGATÓRIA, grava status+resolved_by+resolved_at+justification e audita (RN-003); CA-02 anti-fachada (NUNCA toca o processo nem a malha fina)"
  - "AbuseAlertResource — shape de leitura do alerta (severity/status value+label, janela, evidence, processo, encaminhado_malha_fina, resolução)"
  - "ResolverAbuseAlertRequest — justificativa obrigatória (só espaços também é vazio, espelha o motivo da malha fina)"
  - "Rotas gestao.abuso.index/confirmar/descartar sob permission:gerenciar-alertas-abuso"
affects:
  - "12-11 (tela do painel de abuso): consome 'gestao/abuso/index' (props alertas/efetividade/filtros/opções) e as rotas gestao.abuso.*"
  - "12-11 (app-sidebar): item Alertas de abuso gated por can(gerenciar-alertas-abuso) → gestao.abuso.index"
  - "12-12 (smoke/seed dev de alertas): alimenta o painel com abuse_alerts variados (aberto/confirmado/descartado) para validar lista+efetividade"

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Controller server-driven espelhando ResultadoExpressoController/AuditoriaController: filtros via when(), paginação server-side, Resource->resolve() em through(), consulta auditada"
    - "Efetividade calculada por agregação groupBy(rule_key,status) em UMA query, agregada em PHP — taxa null sem gerados (RN-005 anti-fachada: nunca número inventado)"
    - "Ação de resolução com justificativa obrigatória via Form Request com trim no prepareForValidation (espelha o motivoObrigatorio do MalhaFinaService)"
    - "Anti-fachada CA-02 provada por teste: resolver o ALERTA via update() de campos do próprio alerta — nunca transiciona o processo nem mexe na malha fina (ortogonal)"
    - "Filtro rule_key data-driven (distinct do ledger), severity/status validados contra os enum cases"

key-files:
  created:
    - "app/Http/Controllers/Gestao/AbusoController.php"
    - "app/Http/Resources/AbuseAlertResource.php"
    - "app/Http/Requests/Gestao/ResolverAbuseAlertRequest.php"
    - "tests/Feature/Abuso/AbusoPainelTest.php"
  modified:
    - "routes/gestao.php"

key-decisions:
  - "Efetividade é GLOBAL (todos os alertas reais), independente dos filtros da lista — é indicador de calibração de regras (RN-005), não recorte da página"
  - "per_page reusa ui.auditoria.per_page (default 20) com PER_PAGE_OPTIONS [10,20,50,100] — painel de compliance, mesmo estilo da trilha"
  - "rule_key filtra por whereLike caseSensitive:false; severity/status por igualdade validada contra os enum cases; ruleKeyOptions vêm do distinct real do ledger"
  - "confirmar/descartar são dois endpoints finos sobre um resolver() privado; permite re-triagem (sem guarda de já-resolvido) — fora do escopo do plano"
  - "Props inspecionadas via viewData('page') nos testes (a tela .tsx é 12-11) — padrão LgpdMonitorTest/ProcessoConsultaTest"

patterns-established:
  - "Painel humano de revisão (HU-149): lista server-driven + efetividade agregada + ação auditada com justificativa, gated e anti-fachada — base para a tela 12-11"

# Metrics
duration: ~16min
completed: 2026-06-15
---

# Phase 12 Plan 09: Painel humano de alertas de abuso (HU-149) Summary

**Superfície humana de revisão dos alertas de abuso (HU-149): `Gestao\AbusoController` server-driven com lista filtrável (rule_key/severity/status/período), indicador de efetividade confirmados÷gerados (geral e por regra — RN-005) e confirmar/descartar com justificativa obrigatória auditada (RN-003), tudo gated por `gerenciar-alertas-abuso` — provando por teste a defesa CA-02: resolver o alerta NUNCA toca o status do processo nem a malha fina já criada.**

## Performance

- **Duration:** ~16 min
- **Started:** 2026-06-15T09:24:00Z
- **Completed:** 2026-06-15T09:39:47Z
- **Tasks:** 2 (TDD estrito RED→GREEN em cada)
- **Files:** 4 criados, 1 modificado

## Accomplishments
- Painel de alertas real (lista + filtros + efetividade), gated por `gerenciar-alertas-abuso` e auditado — a superfície de revisão humana que faltava sobre o motor (12-06/12-08).
- Confirmar/descartar com justificativa obrigatória, gravando resolução completa e auditando (RN-003) — espelha o motivo obrigatório da malha fina.
- CA-02 anti-fachada garantida por teste: a resolução do alerta muda SÓ o status do alerta; o processo deferido continua deferido e a malha fina existente segue intacta.

## Rotas (gestao.abuso.*)

Sob `permission:gerenciar-alertas-abuso` (dentro do grupo `auth:gestao` + `permission:acessar-gestao` + `lgpd.accepted`); a estática index vem ANTES das ações com `{abuseAlert}` (POST de dois segmentos — não colidem):

| Método | URI | Name | Ação |
|---|---|---|---|
| GET | `gestao/abuso` | `gestao.abuso.index` | lista + filtros + efetividade |
| POST | `gestao/abuso/{abuseAlert}/confirmar` | `gestao.abuso.confirmar` | confirma o alerta (justificativa obrigatória) |
| POST | `gestao/abuso/{abuseAlert}/descartar` | `gestao.abuso.descartar` | descarta o alerta (justificativa obrigatória) |

403 sem `gerenciar-alertas-abuso` é auditado no ponto único (`bootstrap/app.php`: `log_name='seguranca'`, `event='acesso-negado'`).

## Shape do AbuseAlertResource

`->resolve()` (sem wrapper "data"), consumido como prop do Inertia:

```text
{
  id: int,
  rule_key: string,
  severity: { value: 'baixa'|'media'|'alta', label: string },
  status:   { value: 'aberto'|'confirmado'|'descartado', label: string },
  detected_at: iso8601|null,
  window: { start: iso8601|null, end: iso8601|null },
  evidence: array|null,                 // recorte mínimo do detector (total/limite/ids/identificador)
  processo: { id: int, protocol_number: string|null } | null,   // quando há viability_request_id
  encaminhado_malha_fina: bool,         // via fine_mesh_referral_id !== null (ortogonal ao status)
  resolucao: {                          // null enquanto status = aberto
    resolved_by: { id: int, nome: string|null } | null,
    resolved_at: iso8601|null,
    justification: string|null
  } | null
}
```

## Cálculo da efetividade (RN-005)

Uma query `selectRaw('rule_key, status, count(*)')->groupBy('rule_key','status')` sobre TODOS os alertas reais (global — calibração de regras, independente dos filtros da lista), agregada em PHP:

- `taxa = round(confirmados / gerados * 100, 1)`; `gerados == 0 ⇒ taxa = null` (nunca número inventado).
- `gerados` = total de alertas da regra (qualquer status); `confirmados`/`descartados`/`abertos` por status.
- `por_regra` ordenado por `rule_key` (ksort).

```text
efetividade: {
  geral:    { gerados, confirmados, descartados, abertos, taxa: float|null },
  por_regra: [ { rule_key, gerados, confirmados, descartados, abertos, taxa: float|null }, ... ]
}
```

Exemplo coberto por teste: volume_cnpj 4 gerados/1 confirmado → 25.0; volume_contador 2/1 → 50.0; geral 6/2 → 33.3.

## Props do Inertia 'gestao/abuso/index' (insumo de 12-11)

| Prop | Tipo | Descrição |
|---|---|---|
| `alertas` | LengthAwarePaginator → `{ data: AbuseAlertResource[], ... }` | lista paginada server-side (withQueryString) |
| `efetividade` | `{ geral, por_regra }` | indicador confirmados÷gerados (RN-005) |
| `filtros` | `{ rule_key, severity, status, data_de, data_ate, per_page }` | estado atual dos filtros (eco para a UI) |
| `perPageOptions` | `int[]` | `[10, 20, 50, 100]` |
| `ruleKeyOptions` | `string[]` | rule_keys REAIS presentes no ledger (distinct) |
| `severityOptions` | `{ value, label }[]` | cases de AbuseSeverity |
| `statusOptions` | `{ value, label }[]` | cases de AbuseAlertStatus |

## Mapa CA → teste (HU-149)

| HU / RN | Teste | Status |
|---|---|---|
| CA-03 — lista + efetividade (confirmados÷gerados, geral e por regra) | `test_lista_alertas_...`, `test_efetividade_calcula_...`, `test_efetividade_sem_alertas_nao_inventa_taxa` | verde |
| CA-03 — filtros (rule_key/severity/status/período) | `test_filtra_por_rule_key`, `test_filtra_por_severity_e_status`, `test_filtra_por_periodo_em_detected_at` | verde |
| RN-003 — justificativa obrigatória + auditoria | `test_confirmar_exige_justificativa...`, `test_descartar_rejeita_justificativa_so_de_espacos`, `test_confirmar_grava_resolucao_e_audita`, `test_descartar_grava_resolucao_e_audita` | verde |
| CA-02 — confirmar/descartar NUNCA muda status do processo nem a malha fina | `test_confirmar_nunca_altera_status_do_processo_nem_a_malha_fina` | verde |
| Segurança — 403 sem gerenciar-alertas-abuso (auditado) | `test_sem_permissao_...recebe_403_auditado` (index e confirmar) | verde |
| Shape do resource | `test_resource_expoe_shape_completo...`, `test_resource_de_alerta_aberto_sem_processo_minimiza_campos` | verde |

## Task Commits

1. **Task 1: AbusoController@index + AbuseAlertResource + rota** — `c69c1bc` (feat, TDD)
2. **Task 2: confirmar/descartar + ResolverAbuseAlertRequest + rotas** — `87828c0` (feat, TDD)

## Files Created/Modified
- `app/Http/Controllers/Gestao/AbusoController.php` — index (lista+filtros+efetividade) + confirmar/descartar (resolver auditado, anti-fachada)
- `app/Http/Resources/AbuseAlertResource.php` — shape de leitura do alerta
- `app/Http/Requests/Gestao/ResolverAbuseAlertRequest.php` — justificativa obrigatória (trim → required)
- `routes/gestao.php` — grupo gestao.abuso.* (único editor da Wave 4)
- `tests/Feature/Abuso/AbusoPainelTest.php` — 15 testes (lista/filtros/efetividade/auditoria/resolução/CA-02/403)

## Decisions Made
- Efetividade GLOBAL (não recorta pelos filtros): RN-005 é calibração de regras, não da página.
- per_page reusa `ui.auditoria.per_page` (20) com PER_PAGE_OPTIONS [10,20,50,100] — painel de compliance.
- `confirmar`/`descartar` são endpoints finos sobre um `resolver()` privado; re-triagem permitida (sem guarda de já-resolvido — fora do escopo).
- Testes inspecionam props via `viewData('page')` (a tela .tsx é de 12-11) — padrão LgpdMonitorTest/ProcessoConsultaTest.

## Deviations from Plan

None — plano executado exatamente como escrito. Escopo respeitado: nenhum toque no motor (12-06/12-08) nem nos detectores; só leitura + resolução de alertas. ÚNICO editor de `routes/gestao.php` na Wave 4 (staging seletivo por pathspec; os `.tsx` do par 12-10 ficaram intactos).

## Issues Encountered
- **`assertInertia(->component('gestao/abuso/index'))` exigia o arquivo .tsx (ainda não existe — é 12-11).** Causa-raiz: a verificação de existência do componente do `AssertableInertia`. Correção: inspecionar as props via `viewData('page')` (não valida o arquivo), exatamente como o `LgpdMonitorTest`/`ProcessoConsultaTest` fazem para endpoints que precedem a UI. De quebra, `viewData` entrega os props PHP crus, então o `taxa` (float 25.0) é comparável com `assertSame(25.0, ...)` sem o arredondamento do JSON (25.0 → 25).

## User Setup Required
None — sem configuração de serviço externo.

## Next Phase Readiness
- **12-11** (tela do painel de abuso): consumir `gestao/abuso/index` com as props acima e as rotas `gestao.abuso.*`; adicionar o item de menu no `app-sidebar` gated por `can(gerenciar-alertas-abuso)`. Com deferred props, incluir skeleton.
- **12-12** (smoke/seed dev de alertas): semear `abuse_alerts` variados (aberto/confirmado/descartado por regra) para exercitar lista + efetividade ponta a ponta. O motor processa dados reais; o seed só muda a carga.
- Verificação fresca: `AbusoPainelTest` 15 passed / 93 assertions; suíte completa `--exclude-group=postgis` 1308 passed / 6720 assertions (sem regressão); `route:list --path=gestao/abuso` lista index/confirmar/descartar; pint limpo.

---
*Phase: 12-auditoria-e-compliance*
*Completed: 2026-06-15*

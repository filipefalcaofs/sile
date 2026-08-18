---
phase: 10-analise-tecnica-sedur
plan: 04
subsystem: backend
tags: [hu-138, hu-085, setores, textos-padrao, crud-console, inertia, permissoes, auditoria, versionamento]

# Dependency graph
requires:
  - phase: 10-analise-tecnica-sedur/10-01
    provides: "permissão manter-setores; reuso de manter-parametros para a biblioteca de textos-padrão"
  - phase: 10-analise-tecnica-sedur/10-02
    provides: "models Sector (+pivot sector_user, User::sectors) e StandardText; ViabilityRequest.sector_id/status; enum ViabilityRequestStatus"
provides:
  - "SectorController (HU-138): CRUD de setores + vínculo analista↔setor N:N (syncAnalysts) + inativação não-destrutiva (toggleActivation), sem destroy"
  - "StandardTextController (HU-085): CRUD versionado da biblioteca de textos-padrão (version++ por mudança de conteúdo), filtro por categoria, inativação preservando histórico"
  - "Rotas gestao.setores.* (manter-setores) e gestao.textos-padrao.* (manter-parametros) + SectorRequest/StandardTextRequest + SectorResource/StandardTextResource"
affects: [10-07, 10-09, 10-17]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "CRUD console server-driven espelhando ViabilityServiceTypeController (Form Request + Eloquent API Resource via ->resolve() no through())"
    - "Auditoria explícita via AuditService (log_name por domínio, evento por ação) — inclusive do vínculo N:N, que não entra no diff de model"
    - "Versionamento de dado por mudança de conteúdo (version++ apenas quando content muda; metadados não evoluem a versão)"
    - "Teste backend de página Inertia ainda inexistente: inspeção via response->viewData('page') (sem assertInertia, que exige o .tsx em disco)"

key-files:
  created:
    - app/Http/Controllers/Gestao/SectorController.php
    - app/Http/Requests/Gestao/SectorRequest.php
    - app/Http/Resources/SectorResource.php
    - app/Http/Controllers/Gestao/StandardTextController.php
    - app/Http/Requests/Gestao/StandardTextRequest.php
    - app/Http/Resources/StandardTextResource.php
    - tests/Feature/Analise/SectorCrudTest.php
    - tests/Feature/Analise/StandardTextCrudTest.php
  modified:
    - routes/gestao.php

key-decisions:
  - "Permissão de setores = manter-setores (gestor/admin); biblioteca de textos-padrão sob manter-parametros (admin) — reuso da permissão de admin de configuração, sem 6ª permissão (decisão 10-01). Pendência SEDUR: a coordenação pode exigir permissão própria; a leitura da lista ativa pelo parecer fica sob analisar-processos em 10-09"
  - "RN-004: inativar setor com processos em aberto é PERMITIDO e devolve aviso (flash 'warning'), nunca bloqueia nem exclui — a redistribuição é operação separada (10-07)"
  - "RN-005: syncAnalysts usa sync() do pivot do setor; não remove o analista de outros setores (um analista cobre vários setores)"
  - "HU-085 RN-005: version só incrementa quando o content muda; editar categoria/situação preserva a versão. version inicia em 1 no store"
  - "Auditoria explícita (AuditService) em vez de HasAuditoria nos models — os eventos enumerados pelo plano (criar/atualizar/ativacao/vincular-analistas) ficam autocontidos no controller, sem alterar os models de 10-02 (fora do escopo files_modified deste plano)"

patterns-established:
  - "ÚNICO editor de routes/gestao.php na Wave 2 — 10-05/10-06 são serviços sem rota"
  - "Tela de console adiada para 10-17: o controller já entrega as props (component + filters + perPageOptions); o teste inspeciona viewData('page')"

# Metrics
duration: ~13min
completed: 2026-06-14
---

# Phase 10 Plan 04: Manter setores (HU-138) + biblioteca de textos-padrão (HU-085) — Summary

**Backend completo dos dois cadastros de apoio da análise técnica: SectorController (CRUD + vínculo analista↔setor N:N + inativação protegida, gated por manter-setores) e StandardTextController (CRUD versionado da biblioteca de textos-padrão, gated por manter-parametros), com rotas, Form Requests, Eloquent Resources e auditoria explícita. TDD estrito: SectorCrudTest 10/10 + StandardTextCrudTest 7/7 (17/17, 65 asserções). Suíte completa (exceto postgis) 994/994. Zero dependência nova; ÚNICO editor de routes/gestao.php na Wave 2; telas de console em 10-17.**

## Performance

- **Started:** 2026-06-14T22:18:16Z
- **Completed:** 2026-06-14T22:31:01Z
- **Duration:** ~13 min
- **Tasks:** 2 (commit atômico por task)
- **Files:** 9 (8 criados, 1 modificado)

## Accomplishments

### HU-138 — Manter setores (Task 1)
- `SectorController` server-driven (espelha `ViabilityServiceTypeController`) atrás de `permission:manter-setores`.
- Vínculo analista↔setor N:N administrável (RN-005): `syncAnalysts` sincroniza o conjunto exato do setor sem remover o analista de outros setores.
- Inativação não-destrutiva (RN-004): `toggleActivation` inverte a situação; ao inativar um setor com processos em aberto (status `em_analise`/`em_pendencia`), devolve aviso (`flash 'warning'`) mas NÃO bloqueia nem exclui — preserva histórico e vínculo. Não há rota `destroy`.
- Auditoria explícita (RN-002) via `AuditService` em todas as ações, inclusive no vínculo (antes/depois), que não entraria no diff de model.

### HU-085 — Biblioteca de textos-padrão (Task 2)
- `StandardTextController` server-driven atrás de `permission:manter-parametros`.
- Versionamento (RN-005): editar o `content` incrementa a `version`; editar só categoria/situação preserva a versão vigente; `version` inicia em 1 no `store`.
- Inativação preserva o histórico (não exclui); filtro por categoria na listagem.
- Auditoria explícita em criar/atualizar/ativacao (com `content_changed`/`version` nas propriedades).

## Contrato para os planos seguintes (nomes EXATOS)

### Rotas (todas dentro do grupo `auth:gestao` + `permission:acessar-gestao` + `lgpd.accepted`)

| Método | URI | Nome | Permissão | Controller@ação |
|---|---|---|---|---|
| GET | `gestao/setores` | `gestao.setores.index` | manter-setores | `SectorController@index` |
| POST | `gestao/setores` | `gestao.setores.store` | manter-setores | `SectorController@store` |
| PUT | `gestao/setores/{sector}` | `gestao.setores.update` | manter-setores | `SectorController@update` |
| PUT | `gestao/setores/{sector}/ativacao` | `gestao.setores.ativacao.update` | manter-setores | `SectorController@toggleActivation` |
| PUT | `gestao/setores/{sector}/analistas` | `gestao.setores.analistas.update` | manter-setores | `SectorController@syncAnalysts` |
| GET | `gestao/textos-padrao` | `gestao.textos-padrao.index` | manter-parametros | `StandardTextController@index` |
| POST | `gestao/textos-padrao` | `gestao.textos-padrao.store` | manter-parametros | `StandardTextController@store` |
| PUT | `gestao/textos-padrao/{standardText}` | `gestao.textos-padrao.update` | manter-parametros | `StandardTextController@update` |
| PUT | `gestao/textos-padrao/{standardText}/ativacao` | `gestao.textos-padrao.ativacao.update` | manter-parametros | `StandardTextController@toggleActivation` |

Sem `destroy` em nenhum dos dois (RN-004/HU-085 — inativar, nunca excluir).

### Assinaturas dos controllers
- `SectorController`: `index(Request): Response`; `store(SectorRequest): RedirectResponse`; `update(SectorRequest, Sector): RedirectResponse`; `toggleActivation(Sector): RedirectResponse`; `syncAnalysts(Request, Sector): RedirectResponse` (valida `analyst_ids` array de `exists:users,id`; `sync()` em transação + auditoria antes/depois com `users.id` qualificado).
- `StandardTextController`: `index(Request): Response`; `store(StandardTextRequest): RedirectResponse` (`version=1`); `update(StandardTextRequest, StandardText): RedirectResponse` (`version++` só se `content` mudou); `toggleActivation(StandardText): RedirectResponse`.

### Props do Inertia (consumidas pelas telas de 10-17)
- `gestao/setores/index`: `sectors` (paginado; cada item via `SectorResource`: `id`, `name`, `active`, `analysts_count`, `requests_count`, `analysts[]{id,name}`), `filters` (`search`, `sort`, `direction`, `per_page`, `active`), `perPageOptions`.
- `gestao/textos-padrao/index`: `standardTexts` (paginado; cada item via `StandardTextResource`: `id`, `category`, `content`, `active`, `version`, `updated_at`), `filters` (`search`, `category`, `sort`, `direction`, `per_page`, `active`), `perPageOptions`.

### Form Requests
- `SectorRequest` (store+update): `name` required/unique em `sectors` (ignora o próprio no update), `active` boolean (default true); trim do nome.
- `StandardTextRequest` (store+update): `category` required string max:100, `content` required string, `active` boolean (default true).

### Auditoria (AuditService)
- `log_name='setores'`, eventos `criar`/`atualizar`/`ativacao`/`vincular-analistas`.
- `log_name='textos-padrao'`, eventos `criar`/`atualizar`/`ativacao`.
- Acesso sem permissão → `log_name='seguranca'`, `event='acesso-negado'`, `result='bloqueado'` (infra existente em `bootstrap/app.php`; CA-04).

## Mapa CA → teste (verde)

| HU / RN | Teste |
|---|---|
| HU-138 CA-01 — setor como caixa | `SectorCrudTest::test_gestor_lista_setores` / `test_cria_setor_auditado` / `test_edita_nome_e_situacao` |
| HU-138 RN-005 — analista↔setor N:N | `test_vincula_e_desvincula_analistas_auditado` / `test_analista_pode_pertencer_a_dois_setores` |
| HU-138 RN-004 — não exclui (inativa) | `test_inativar_preserva_setor_e_nao_ha_rota_destrutiva` / `test_inativar_setor_com_processo_em_aberto_avisa_mas_nao_bloqueia` |
| HU-138 CA-04 / HU-085 — acesso 403 auditado | `test_lista_exige_permissao_manter_setores` / `StandardTextCrudTest::test_lista_exige_permissao_manter_parametros` |
| HU-085 RN-005 — versionamento | `test_cria_texto_padrao_versao_inicial_1_auditado` / `test_editar_conteudo_incrementa_versao` / `test_editar_apenas_metadados_nao_incrementa_versao` |

## Task Commits

1. **Task 1: CRUD de setores (HU-138)** — `0ae0e98` (feat)
2. **Task 2: biblioteca de textos-padrão versionada (HU-085)** — `e97ed20` (feat)

_TDD estrito em ambas: RED confirmado (404/sessão sem status) antes do GREEN; commit único por task (teste + implementação coesos)._

## Decisions Made
- **Permissão da biblioteca = manter-parametros** (admin), reusando a permissão de admin de configuração — sem 6ª permissão (decisão 10-01). Setores sob manter-setores (gestor/admin). **Pendência SEDUR:** a coordenação pode exigir permissão própria para textos-padrão; a leitura da lista ATIVA pelo parecer fica sob analisar-processos em 10-09.
- **Auditoria explícita (AuditService) em vez de HasAuditoria nos models** — o plano enumera eventos por ação (`criar`/`atualizar`/`ativacao`/`vincular-analistas`); manter os eventos no controller mantém os models de 10-02 intactos (fora do `files_modified` deste plano) e cobre o vínculo N:N, que o diff de model não captaria.
- **RN-004 inativação com aviso, nunca bloqueio** — inativar um setor com processos em aberto é permitido e retorna `flash 'warning'`; a redistribuição é a operação separada de 10-07.

## Deviations from Plan

### Auto-fixed Issues
**1. [Rule 1 — Bug] Coluna `id` ambígua no vínculo N:N**
- **Encontrado em:** Task 1 (ciclo GREEN, `syncAnalysts`).
- **Problema:** `$sector->analysts()->pluck('id')` junta `users` e `sector_user` (ambas têm `id`) → `SQLSTATE[HY000] ambiguous column name: id`.
- **Correção:** qualificar `users.id` no `orderBy`/`pluck`. Coberto por `test_vincula_e_desvincula_analistas_auditado`.
- **Commit:** `0ae0e98`.

### Ajuste de abordagem de teste (não é desvio de produção)
- O plano sugeria `assertInertia(->component(...))`, mas `inertia.testing.ensure_pages_exist` é `true` e as telas de console são de 10-17. Para manter o escopo **backend-only** (sem criar `.tsx`, que pertence a 10-17 e poderia colidir), os testes de listagem inspecionam `response->viewData('page')` (component + props) sem exigir o arquivo em disco. Decisão de teste, sem impacto no código de produção.

## Issues Encountered (cross-plan — FORA do escopo do 10-04)
- Na 1ª execução da suíte completa, 3 erros em `tests/Feature/Analise/PrecedentRepositoryPostgisTest` (`#[Group('postgis')]`, conexão `pgsql_testing`): `duplicate key ... viability_decisions_tvl_product_number_unique (TVL-2026-000001)`. É a armadilha de factory documentada em 10-02 (`ViabilityDecisionFactory` com `tvl_product_number` fixo ao criar várias decisões). O arquivo é da **wave paralela 10-06** (precedentes), ainda **não commitado** no momento do run (race na árvore compartilhada) e tagueado `postgis`. **Não é regressão deste plano** — nenhum arquivo do 10-04 toca `viability_decisions`/TVL. Reexecutando com `--exclude-group postgis` (com o grupo já presente): **994/994 verde**. Pendência do 10-06/orquestrador.

## Verification (evidência fresca)
- `vendor/bin/pint --dirty --format agent` → **passed** (em ambas as tasks).
- `php artisan test --compact --filter="SectorCrudTest|StandardTextCrudTest"` → **17/17** (65 asserções).
- `php artisan test --compact --exclude-group postgis` → **994/994** (5047 asserções) — sem regressão; inclui os testes de contagem (parâmetros 67, **permissões 24**: nenhuma permissão nova).
- `php artisan route:list --path=gestao` → `gestao.setores.*` (5 rotas) e `gestao.textos-padrao.*` (4 rotas) registradas.
- `npm run typecheck` NÃO executado: este plano não toca TSX (telas em 10-17).

## Next Phase Readiness
- **10-07** (distribuir/assumir): usa o setor como caixa e o vínculo `sector->analysts()` (RN-005) para a fila por setor; a inativação preserva o vínculo para redistribuição.
- **10-09** (parecer): lê a biblioteca de textos-padrão ATIVOS para inserção na fundamentação (sob analisar-processos — leitura, não manutenção).
- **10-17** (telas de console + nav): consome as props já entregues (`gestao/setores/index`, `gestao/textos-padrao/index`) e os nomes de rota `gestao.setores.*` / `gestao.textos-padrao.*`.
- **Pendência SEDUR:** confirmar se textos-padrão exige permissão própria (hoje reusa manter-parametros).

---
*Phase: 10-analise-tecnica-sedur*
*Completed: 2026-06-14*

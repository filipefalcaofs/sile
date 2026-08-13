---
phase: 06-classificacao-de-risco
plan: 06
subsystem: backend
tags: [mantenedores-risco, consulta-vigente, publicacao-versionada, quatro-olhos, condicionante-pergunta, permissoes, inertia, auditoria, sqlite]

# Dependency graph
requires:
  - phase: 06-01-fundacao-regras-versionadas
    provides: "RuleVersionService.openDraft/publish (quatro olhos, fecha vigente anterior, auditoria 'regras') + scopes vigente/naData + enum RuleDomain (RiscoMunicipal/RiscoSanitario isSensitive)"
  - phase: 06-02-classificacao-risco-municipal
    provides: "RiskClassification (FK rule_version_id, cnae_code, enum RiscoMunicipal) — tabela consultada e copiada na publicação versionada"
  - phase: 06-03-risco-sanitario
    provides: "RiskCondicionante (regra_reclassificacao jsonb) + enum TipoRespostaCondicionante — alvo do CRUD HU-019; versão sanitária vigente exibida na consulta"
  - phase: 02-administracao-base
    provides: "Padrão de CRUD do console (CnaeController server-driven, FormRequest, flash status) + RolesAndPermissionsSeeder aditivo + Settings::get (ui.cnaes.per_page)"
  - phase: 04-georreferenciamento
    provides: "Padrão cross-guard de permissão (TerritoryController/TerritoryPageTest: gate permission:, 403 auditado)"
  - phase: 01-identidade
    provides: "AuditService.log (RN-002) + HasAuditoria"
provides:
  - "Permissões consultar-risco (analista/gestor/admin) e manter-risco (admin) — seedadas aditivas (givePermissionTo)"
  - "Rotas gestao.risco.* (index + condicionantes.index sob consultar-risco; publicar + CRUD de condicionantes sob manter-risco)"
  - "RiscoController@index: consulta da tabela MUNICIPAL vigente por CNAE (busca/filtro/paginação/auditoria) — HU-052"
  - "RiscoController@publish + RiscoMaintenanceService.publishNewVersion: atualização VERSIONADA por quatro olhos (copia a vigente + aplica alterações + publica) — HU-053/HU-020, sem edição destrutiva"
  - "RiscoCondicionanteController CRUD (index/store/update/destroy) escopado à versão sanitária vigente, auditado via HasAuditoria — HU-019"
  - "FormRequests PublishRiscoVersionRequest + Store/UpdateRiscoCondicionanteRequest (validação dinâmica)"
affects: [06-08-ui-risco, 07-consulta-previa]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Mantenedor de regra como dado: atualizar a classificação publica NOVA versão (RuleVersionService.publish) herdando a vigente + alterações — nunca UPDATE destrutivo da tabela vigente"
    - "Quatro olhos na UI: autor (author_id no payload) distinto do publicador (usuário autenticado); mesmo usuário => bloqueio comunicado (flash.error), nunca silencioso"
    - "Cópia da versão via insert em lote (sem model events, condicionantes json_encode UNESCAPED_UNICODE) — a auditoria é o evento único de publicação, não uma activity por linha (precedente do import 06-02/03)"
    - "Consulta server-driven espelhando CnaeController: leftJoin a cnaes para denominação, busca por código (prefixo dígitos) OU denominação (whereLike caseSensitive:false), filtro por nível, page size reusando ui.cnaes.per_page"
    - "CRUD de condicionantes escopado por rule_version_id da versão sanitária vigente (RuleVersion::vigente) — edição não vaza para versões substituídas"

key-files:
  created:
    - app/Http/Controllers/Gestao/RiscoController.php
    - app/Http/Controllers/Gestao/RiscoCondicionanteController.php
    - app/Http/Requests/Gestao/PublishRiscoVersionRequest.php
    - app/Http/Requests/Gestao/StoreRiscoCondicionanteRequest.php
    - app/Http/Requests/Gestao/UpdateRiscoCondicionanteRequest.php
    - app/Services/Risco/RiscoMaintenanceService.php
    - tests/Feature/Risco/RiscoConsultaTest.php
    - tests/Feature/Risco/RiscoCondicionanteMaintenanceTest.php
  modified:
    - database/seeders/RolesAndPermissionsSeeder.php
    - routes/gestao.php
    - tests/Feature/Authorization/RolesAndPermissionsSeederTest.php
    - tests/Feature/Roles/ManageRolesTest.php

key-decisions:
  - "route:list faz ReflectionClass na classe do controller (RouteListCommand) e falha se ela não existir: os controllers de risco foram criados como esqueleto na Task 1 (HTTP 501) para o verify da Task 1 passar; a lógica real entrou nas Tasks 2/3. O plano assumia que route:list resolveria sem as classes — não resolve."
  - "Atualizar a classificação publica NOVA versão (publishNewVersion): copia as classificações da vigente para o rascunho, aplica as alterações por CNAE (insert em lote, sem events) e publica via RuleVersionService (fecha a anterior, quatro olhos, auditoria 'regras'). A versão anterior é preservada — provado por teste (nível original intacto na versão fechada)."
  - "Quatro olhos pela UI: o payload traz author_id; o publicador é o usuário autenticado. author_id == publisher em domínio sensível => back()->with('error', ...) ANTES de abrir rascunho (sem rascunho órfão). O RuleVersionService.publish reforça a regra (FourEyesViolationException) como defesa em profundidade."
  - "Page size reusa ui.cnaes.per_page (fallback técnico) — NÃO criado ui.risco.per_page (evita inflar o catálogo e tocar ParameterSeeder, fora do files_modified; o plano pede preferir reuso)."
  - "Consulta auditada uma vez por carregamento (log 'risco', event 'consulta-tabela', rules_version = versão municipal vigente) — consulta relevante a dado de risco (espelha o TerritoryService/CA-02)."
  - "Verificação SQLite via 'php artisan test --compact --exclude-group postgis' (precedente 06-01..05); o backend é tabular e roda em :memory:."

patterns-established:
  - "Contrato Inertia 'gestao/risco/index' e 'gestao/risco/condicionantes' (props abaixo) — insumo direto da UI 06-08; component assertado por nome sem exigir arquivo em disco (component(..., false), precedente 03-04)"
  - "Permissão nova de fase altera a contagem global: o teste de contagem fora do plano (ManageRolesTest ->has('permissions', N)) é atualizado junto, sem deixar regressão"

# Metrics
duration: ~30min
completed: 2026-06-14
---

# Phase 6 Plan 06: Mantenedores de Risco e Condicionantes Summary

**O console SEDUR ganha a consulta e a manutenção da tabela de risco (backend): analista/gestor/admin consultam a classificação MUNICIPAL vigente por CNAE (busca, filtro por nível, resumo, auditoria — HU-052); o administrador atualiza a classificação publicando uma NOVA versão por quatro olhos (herda a vigente + aplica alterações + fecha a anterior sem apagar — HU-053/HU-020); e mantém as condicionantes-pergunta da versão sanitária vigente via CRUD auditado (HU-019). Permissões consultar-risco/manter-risco seedadas aditivas; bloqueio por permissão auditado (CA-04). Tudo sem fachada: atualizar nunca sobrescreve — versiona.**

## Performance

- **Duration:** ~30 min
- **Tasks:** 3 (todas TDD RED→GREEN→pint)
- **Files created:** 8 | **modified:** 4

## Accomplishments

- Permissões `consultar-risco` (analista/gestor/admin) e `manter-risco` (admin) no `RolesAndPermissionsSeeder` (aditivo, `givePermissionTo` — re-seed não desfaz ajustes da HU-013).
- 6 rotas `gestao.risco.*`: consulta (`index`, `condicionantes.index`) sob `permission:consultar-risco`; manutenção (`publicar`, `condicionantes.store/update/destroy`) sob `permission:manter-risco`.
- `RiscoController@index` (HU-052): consulta server-driven da tabela municipal vigente, busca por código/denominação, filtro por nível, resumo por nível, versões vigentes (municipal/sanitária), auditoria da consulta.
- `RiscoMaintenanceService.publishNewVersion` + `RiscoController@publish` (HU-053/HU-020): atualização versionada por quatro olhos — copia a vigente, aplica alterações e publica; anterior preservada.
- `RiscoCondicionanteController` CRUD (HU-019): mantém as condicionantes-pergunta da versão sanitária vigente com auditoria automática.
- Suíte SQLite: **515/515 verde** (495 baseline 06-05 + 7 golden do 06-07 paralelo + 13 deste plano), sem regressão. `--filter=Risco` 56/56; `--filter=Authorization|Roles` 19/19.

## Task Commits

Cada task foi commitada atomicamente (TDD RED→GREEN→pint):

1. **Task 1: Permissões + rotas + esqueleto dos controllers** - `69fe9c6` (feat)
2. **Task 2: Consulta vigente + publicação versionada 4-olhos** - `6c512eb` (feat)
3. **Task 3: CRUD de condicionantes-pergunta** - `485d83a` (feat)

**Plan metadata:** este SUMMARY (docs).

> Coexistência com o 06-07 (executor paralelo): os commits do golden (`347ca69`, `e6a7a2f`, `2cb9a16`, `59b9370`) intercalaram com os do 06-06 — arquivos distintos, sem conflito. Nenhuma fixture/teste golden do 06-07 foi tocado.

## Contrato dos artefatos (insumo da UI 06-08)

### Permissões e papéis (catálogo 10 → 12)
| permissão | cidadão | analista | gestor | administrador |
|---|---|---|---|---|
| `consultar-risco` | — | ✓ | ✓ | ✓ |
| `manter-risco` | — | — | — | ✓ |

### Rotas (`gestao.risco.*`)
| método | uri | name | permissão | controller |
|---|---|---|---|---|
| GET | `gestao/risco` | `gestao.risco.index` | consultar-risco | `RiscoController@index` |
| GET | `gestao/risco/condicionantes` | `gestao.risco.condicionantes.index` | consultar-risco | `RiscoCondicionanteController@index` |
| PUT | `gestao/risco/publicar` | `gestao.risco.publicar` | manter-risco | `RiscoController@publish` |
| POST | `gestao/risco/condicionantes` | `gestao.risco.condicionantes.store` | manter-risco | `RiscoCondicionanteController@store` |
| PUT | `gestao/risco/condicionantes/{condicionante}` | `gestao.risco.condicionantes.update` | manter-risco | `RiscoCondicionanteController@update` |
| DELETE | `gestao/risco/condicionantes/{condicionante}` | `gestao.risco.condicionantes.destroy` | manter-risco | `RiscoCondicionanteController@destroy` |

### Props Inertia `gestao/risco/index` (consulta — HU-052)
```
classificacoes: paginator { data: [{ id, cnae_code, formatted_code, cnae_description, risco_municipal, risco_municipal_label, condicionantes[], observacao }], ... }
versaoMunicipal: { version, valid_from } | null
versaoSanitaria: { version, valid_from } | null
resumoNiveis: { baixo_a: int, baixo_b: int, alto: int }   // distribuição da versão municipal vigente
filtros: { search, nivel ('' | baixo_a | baixo_b | alto), per_page }
perPageOptions: [10, 15, 25, 50]
```
Query params aceitos: `search` (código por prefixo de dígitos OU denominação), `nivel`, `per_page` (whitelist), `page`.

### Props Inertia `gestao/risco/condicionantes` (CRUD — HU-019)
```
condicionantes: paginator { data: [{ id, cnae_code, formatted_code|null, pergunta, tipo_resposta, regra_reclassificacao, texto_parecer }], ... }
versaoSanitaria: { version, valid_from } | null
filtros: { search, per_page }
perPageOptions: [10, 15, 25, 50]
niveisReclassificacao: [{ value: 'baixo'|'medio'|'alto', label }]   // opções da regra de reclassificação
```

### Payload `PUT gestao/risco/publicar` (HU-053/HU-020)
```
version: string (única no domínio risco_municipal)
author_id: int (usuário autor da nova versão; DEVE ser ≠ do publicador autenticado — quatro olhos)
alteracoes: [{ cnae_code, risco_municipal (baixo_a|baixo_b|alto), condicionantes?: string[], observacao?: string|null }]   // opcional
```
- Sucesso: `flash.status` com a nova versão; a vigente anterior vira `Substituida` (com `valid_to`), a nova é `Vigente`.
- author_id == publicador: `flash.error` (degradação controlada), nada publicado.

### Payload condicionantes (`store`/`update`)
```
cnae_code?: string (DDDD-D/SS ou dígitos — geral se omitido)
pergunta: string (obrigatória)
tipo_resposta: 'booleano_sim_nao'   // selecao reservado, não aceito ainda
regra_reclassificacao?: { resposta_gatilho: bool, reclassifica_para?: 'baixo'|'medio'|'alto'|null, fundamento?: string }
texto_parecer?: string
```

### Assinatura `RiscoMaintenanceService`
```
App\Services\Risco\RiscoMaintenanceService (injeta RuleVersionService)

publishNewVersion(RuleDomain $domain, string $version, array $alteracoes, int $authorId, int $publisherId): RuleVersion
  - DB::transaction: openDraft(domain, version, source, authorId)
  - copia as classificações da vigente para o rascunho (insert em lote, sem events)
  - aplica $alteracoes por cnae_code (sobrescreve/adiciona)
  - publish(draft, publisherId) — quatro olhos + fecha a anterior + auditoria 'regras'
  - Implementado para RiscoMunicipal (dimensão da publicação pela UI). NÃO simula outras dimensões.
```

### Decisão sobre os quatro olhos na publicação pela UI
A regra "publicador ≠ autor" é verificada no `RiscoController@publish` (author_id do payload vs. usuário autenticado) ANTES de abrir o rascunho, devolvendo `flash.error` quando coincidem — bloqueio comunicado, sem rascunho órfão. O `RuleVersionService.publish` reforça a regra (lança `FourEyesViolationException`) como defesa em profundidade. A UI 06-08 deve coletar o autor da alteração e impedir que o próprio publicador seja o autor (a tela comunica o motivo).

## Files Created/Modified
- `app/Http/Controllers/Gestao/RiscoController.php` - Consulta vigente (HU-052) + publicação versionada (HU-053/020).
- `app/Http/Controllers/Gestao/RiscoCondicionanteController.php` - CRUD de condicionantes-pergunta (HU-019).
- `app/Services/Risco/RiscoMaintenanceService.php` - publishNewVersion (copia vigente + aplica alterações + publica 4-olhos).
- `app/Http/Requests/Gestao/PublishRiscoVersionRequest.php` - Versão única no domínio + validação das alterações.
- `app/Http/Requests/Gestao/StoreRiscoCondicionanteRequest.php` - Pergunta/tipo/regra de reclassificação (in baixo,medio,alto) + normalização do CNAE.
- `app/Http/Requests/Gestao/UpdateRiscoCondicionanteRequest.php` - Estende o Store (mesmas regras).
- `routes/gestao.php` - Bloco `risco` (consultar-risco e manter-risco), espelhando CNAEs/território.
- `database/seeders/RolesAndPermissionsSeeder.php` - +consultar-risco/manter-risco (aditivo).
- `tests/Feature/Risco/RiscoConsultaTest.php` - Consulta, busca/filtro, auditoria, 403 auditado, publicação versionada, quatro olhos, bloqueio por permissão (7 testes).
- `tests/Feature/Risco/RiscoCondicionanteMaintenanceTest.php` - Consulta da vigente, criar, editar/remover, validação, bloqueio por permissão (5 testes).
- `tests/Feature/Authorization/RolesAndPermissionsSeederTest.php` - 10→12 permissões + teste das permissões de risco.
- `tests/Feature/Roles/ManageRolesTest.php` - `->has('permissions', 10)` → 12 (ajuste de contagem — ver Deviations).

## Decisions Made
- **Atualizar = versionar (anti-fachada):** `publish` herda a vigente, aplica alterações e fecha a anterior. A versão substituída mantém o nível ORIGINAL (provado por teste) — reprodução por época continua válida (RN-005, motor 06-05).
- **Quatro olhos pela UI via author_id no payload:** verificado no controller antes de abrir rascunho (mensagem clara, sem rascunho órfão) e reforçado no service.
- **Cópia em lote sem events:** evita uma activity por classificação copiada (até ~1.331 linhas); a auditoria da operação é o evento único de publicação ('regras'). `condicionantes` com `json_encode(JSON_UNESCAPED_UNICODE)` (o insert não aplica o cast array — precedente 06-02).
- **Reuso de `ui.cnaes.per_page`:** não inflar o catálogo de parâmetros (sem `ui.risco.per_page`); o `ParameterSeeder` e a contagem (31) permaneceram intocados.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] route:list exige a classe do controller (esqueleto na Task 1)**
- **Found during:** Task 1 (verify `php artisan route:list --path=gestao/risco`).
- **Issue:** O plano diz "criar rotas referenciando as classes; o teste de rota roda após as classes existirem", assumindo que `route:list` resolveria sem os controllers. Não resolve: `RouteListCommand` faz `new ReflectionClass($route->getControllerClass())` e lança `ReflectionException` (controller inexistente), quebrando o verify da Task 1.
- **Fix:** Controllers `RiscoController`/`RiscoCondicionanteController` criados como esqueleto (métodos `abort(501)`) na Task 1 para o `route:list` resolver; a lógica real entrou nas Tasks 2/3 (TDD). Sem mudança de comportamento — só ordem de criação dos arquivos.
- **Verification:** `route:list --path=gestao/risco` lista 6 rotas; testes das Tasks 2/3 verdes.
- **Committed in:** `69fe9c6` (esqueleto), `6c512eb`/`485d83a` (lógica real).

**2. [Ajuste de teste fora do files_modified] Contagem de permissão em ManageRolesTest (10 → 12)**
- **Found during:** Task 1 (GREEN do seeder).
- **Issue:** Adicionar 2 permissões altera a contagem global. `tests/Feature/Roles/ManageRolesTest.php` asserta `->has('permissions', 10)` (prop `permissions` = todas as permissões no RoleController@index). Sem ajuste, o teste quebraria — regressão fora do escopo do plano.
- **Fix:** `->has('permissions', 12)` no ManageRolesTest (sancionado explicitamente pelas constraints do plano). Também `assertSame(10 → 12)` no RolesAndPermissionsSeederTest (dentro do files_modified). Nenhum outro teste de contagem de permissão encontrado (grep abrangente).
- **Verification:** `--filter='Authorization|Roles'` 19/19 verde.
- **Committed in:** `69fe9c6`.

**3. [Decisão de teste] Component Inertia assertado por nome sem arquivo em disco**
- **Found during:** Task 2/3 (GREEN da consulta).
- **Issue:** O plano sugere `Inertia component 'gestao/risco/index'`. As páginas React de risco são entregues no 06-08; `->component('gestao/risco/index')` (padrão) exige o arquivo em disco e falha.
- **Fix:** `->component('gestao/risco/index', false)` / `->component('gestao/risco/condicionantes', false)` — asserta o NOME do componente sem exigir o arquivo (precedente 03-04). O contrato das props é validado normalmente.
- **Verification:** RiscoConsultaTest 7/7 e RiscoCondicionanteMaintenanceTest 5/5.
- **Committed in:** `6c512eb`/`485d83a`.

**Enriquecimentos aditivos (sem scope creep):**
- Testes além dos nomeados no plano, como evidência anti-fachada: `test_publicacao_pelo_mesmo_autor_e_bloqueada_por_quatro_olhos`, `test_sem_permissao_manter_risco_nao_publica` e `test_analista_consulta_condicionantes_da_versao_vigente` (prova o escopo por versão vigente — condicionante de versão antiga não aparece).

**4. [Verificação] `--exclude-group postgis` em vez de `migrate:fresh --env=testing`**
- Mesma natureza e racional do 06-01..05 (sem `.env.testing`; o grupo postgis exige container). O backend deste plano é tabular e provado pelo `RefreshDatabase` em SQLite.

---

**Total deviations:** 1 blocking (esqueleto p/ route:list), 1 ajuste de contagem de permissão fora do files_modified (sancionado), 1 decisão de teste (component sem arquivo), 1 de verificação (padrão da fase).
**Impact on plan:** Sem scope creep nos domínios. Todos os arquivos dentro do `files_modified`, exceto `ManageRolesTest.php` (ajuste de contagem obrigatório, sancionado pelas constraints). Nada das fixtures/golden do 06-07 nem da UI do 06-08 foi tocado.

## Testes de contagem de permissão ajustados (registro explícito)
- `tests/Feature/Authorization/RolesAndPermissionsSeederTest.php`: `assertSame(10 → 12, Permission::count())` + novo teste `test_papeis_recebem_permissoes_de_risco`.
- `tests/Feature/Roles/ManageRolesTest.php`: `->has('permissions', 10)` → `->has('permissions', 12)`.
- Busca abrangente (`rg "has\('permissions'|Permission::query\(\)->count\(\)"`) confirmou que não há outros pontos de contagem a corrigir.

## Issues Encountered
- **route:list reflete o controller** (deviation 1) — resolvido com esqueleto na Task 1.
- **Executor 06-07 paralelo no mesmo working directory:** commits intercalados (golden cases), arquivos distintos dos do 06-06, sem conflito. Baseline reconciliado: 495 (06-05) + 7 (06-07) + 13 (06-06) = 515.

## User Setup Required
None - sem configuração de serviço externo. Sem dependência nova.

## Next Phase Readiness
- **06-08 (UI de risco):** consome os contratos Inertia acima (`gestao/risco/index` e `gestao/risco/condicionantes`), o payload de publicação (com `author_id` para os quatro olhos) e o de condicionantes; deve criar as páginas React (component(..., false) vira component() quando o arquivo existir) e coletar o autor da alteração impedindo author == publicador.
- **EP07 (consulta prévia):** o motor (06-05) reflete automaticamente as alterações publicadas pelos mantenedores (dado versionado, sem mudança de código).
- Sem blockers introduzidos por este plano. A publicação versionada está implementada para a dimensão MUNICIPAL (a usada pela UI de atualização); a manutenção da dimensão sanitária se dá via CRUD de condicionantes (HU-019) — versionamento sanitário pela UI, se necessário, espelha publishNewVersion sem retrabalho.

---
*Phase: 06-classificacao-de-risco*
*Completed: 2026-06-14*

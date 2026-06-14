---
phase: 08-solicitacao-de-viabilidade
plan: 02
subsystem: infra
tags: [parametrizacao, hu-014, settings, feature-toggle, throttle, spatie-permission, seeder, config, anti-fachada, hu-061, hu-148, hu-150]

# Dependency graph
requires:
  - phase: 02-administracao-base
    plan: 02
    provides: "Settings::get (banco→cache→config/sile.php) + Parameter (sensitive fora do fillable, typedValue json→array) + ParameterSeeder upsert só de metadados (value administrado preservado)"
  - phase: 02-administracao-base
    plan: 01
    provides: "RolesAndPermissionsSeeder aditivo (givePermissionTo, nunca sync) + papéis cidadao/analista/gestor/administrador no guard web"
provides:
  - "13 parâmetros novos no catálogo (catálogo 35→48), novo grupo de negócio 'solicitacao'"
  - "Fallbacks espelhados em config/sile.php (blocos features/solicitacao/storage/seguranca.throttle) — Settings::get lê config('sile.{key}') sem banco"
  - "2 feature toggles administráveis (features.solicitacao_viabilidade, features.simulacao_solicitacao)"
  - "Throttle parametrizado da consulta pública de protocolo (seguranca.throttle.consulta_protocolo.por_minuto)"
  - "5 permissões aditivas (14→19) com atribuição por papel (admin/gestor com as 5; analista só consultar-solicitacoes; cidadão por policy)"
affects: [08-01-protocolo (ProtocolNumberGenerator lê prefixo/padding), 08-06-imovel (tolerância área×polígono), 08-08-anexos (max_mb/mime/disk), 08-09-simulacao (toggle), 08-11-consulta-protocolo (ttl/throttle/prazo), 08-15-atendimento (expiracao_minutos), 08-03/08-04/08-14 (permissões)]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Novo grupo de negócio 'solicitacao' no catálogo (ordem alfabética: depois de 'seguranca', antes de 'ui')"
    - "key ≠ group mantido: storage.documentos.disk pertence ao grupo 'solicitacao' (como security.* → grupo 'seguranca')"
    - "Toda funcionalidade acoplável nasce com feature toggle; toda rota pública nasce com throttle parametrizado (precedente Fase 3.1/7)"

key-files:
  created:
    - .planning/phases/08-solicitacao-de-viabilidade/08-02-SUMMARY.md
  modified:
    - database/seeders/ParameterSeeder.php
    - config/sile.php
    - database/seeders/RolesAndPermissionsSeeder.php
    - tests/Feature/Seeders/ParameterSeederTest.php
    - tests/Feature/Seeders/DatabaseSeederTest.php
    - tests/Feature/Authorization/RolesAndPermissionsSeederTest.php
    - tests/Feature/Roles/ManageRolesTest.php

key-decisions:
  - "Grupo 'solicitacao' único para todos os solicitacao.* E para storage.documentos.disk (key ≠ group é a norma do catálogo); lista de grupos cresce só com 'solicitacao'"
  - "duplicidade.janela_dias e estados_cancelaveis NÃO entram aqui (constantes/parâmetros dos planos 08-05/08-12) — escopo restrito à lista do plano"
  - "gestor recebe as 5 permissões (opera E parametriza os cadastros da fase); analista só consultar-solicitacoes (consulta backoffice); cidadão NENHUMA (opera as próprias solicitações por policy)"
  - "Defaults honestos com ressalvas SEDUR no description (protocolo.prefixo formato a confirmar; prazo_estimado_dias é estimativa até a medição HU-129/Fase 15)"

patterns-established:
  - "Parâmetro json (mime_permitidos) com typedValue() → array: o consumidor (08-08) sempre recebe array, fallback de config também é array"
  - "disk de documentos NUNCA público (default 'local'), parametrizável para s3 sem deploy"

# Metrics
duration: ~9 min
completed: 2026-06-14
---

# Phase 8 Plan 02: Fundação de Parametrização e Permissões Summary

**A Fase 8 ganhou sua fundação de parametrização HU-014 (irmã paralela do 08-01, zero sobreposição de arquivos): 13 parâmetros administráveis novos elevam o catálogo de 35→48 sob o novo grupo de negócio `solicitacao` — toggles `features.solicitacao_viabilidade`/`features.simulacao_solicitacao`, formato de protocolo (`solicitacao.protocolo.prefixo='VIA'`/`.padding=6`), anexos (`max_mb`/`mime_permitidos` json/`storage.documentos.disk='local'` nunca público), tolerância área×polígono, prazo estimado, expiração do atendimento presencial e o throttle da consulta pública de protocolo — todos com fallback espelhado em `config/sile.php` (efeito sem deploy via `Settings::get` banco→cache→config). Em paralelo, 5 permissões aditivas (14→19) entram com atribuição por papel (admin/gestor com as 5; analista só `consultar-solicitacoes`; cidadão por policy). ZERO dependência nova. Provado por TDD estrito (RED→GREEN com evidência fresca) nos 3 testes de seeder/permissão (27/27) e suíte verde no meu escopo.**

## Performance

- **Duration:** ~9 min
- **Started:** 2026-06-14T10:47:42Z
- **Completed:** 2026-06-14T10:57:03Z
- **Tasks:** 3 (Task 1 parâmetros+config; Task 2 testes de parâmetro; Task 3 permissões+testes)
- **Files modified:** 7 (6 do plano + 1 colateral ManageRolesTest) — ZERO dependência nova

## Accomplishments

- **13 parâmetros novos** no `ParameterSeeder` (catálogo 35→48), grupo `solicitacao` nascendo na lista (alfabética, entre `seguranca` e `ui`).
- **Fallbacks em `config/sile.php`**: blocos `features` (2 toggles), novo `solicitacao` (7 sub-chaves), novo `storage.documentos.disk`, `seguranca.throttle.consulta_protocolo` — `Settings::get` resolve sem banco.
- **5 permissões aditivas** (14→19) com atribuição por papel, espelhando o padrão risco/louos (givePermissionTo, nunca sync).
- **Contagens travadas** nos dois seeders (48 em ParameterSeeder/DatabaseSeeder) e nas permissões (19), com caso dedicado para cada domínio.
- **Anti-fachada**: defaults reais e administráveis sem deploy; ressalvas SEDUR registradas no `description` (formato de protocolo; prazo estimado é estimativa até HU-129/Fase 15).

## Os 13 parâmetros (chave · grupo · tipo · default)

| # | Chave | Grupo | Tipo | Default |
|---|-------|-------|------|---------|
| 1 | `features.solicitacao_viabilidade` | features | boolean | `1` |
| 2 | `features.simulacao_solicitacao` | features | boolean | `1` |
| 3 | `solicitacao.cnaes_complementares.max` | solicitacao | integer | `99` |
| 4 | `solicitacao.protocolo.prefixo` | solicitacao | string | `VIA` |
| 5 | `solicitacao.protocolo.padding` | solicitacao | integer | `6` |
| 6 | `solicitacao.consulta_publica.assinatura_ttl_dias` | solicitacao | integer | `30` |
| 7 | `solicitacao.anexos.max_mb` | solicitacao | integer | `10` |
| 8 | `solicitacao.anexos.mime_permitidos` | solicitacao | json | `["application/pdf","image/jpeg","image/png"]` |
| 9 | `storage.documentos.disk` | solicitacao | string | `local` |
| 10 | `solicitacao.area_poligono.tolerancia_percentual` | solicitacao | integer | `10` |
| 11 | `solicitacao.prazo_estimado_dias` | solicitacao | integer | `30` |
| 12 | `solicitacao.atendimento.expiracao_minutos` | solicitacao | integer | `30` |
| 13 | `seguranca.throttle.consulta_protocolo.por_minuto` | seguranca | integer | `30` |

Catálogo: **35 → 48**. Lista de grupos: `['features','geo','integracoes','louos','retencao','risco','seguranca','solicitacao','ui']` (acrescenta só `solicitacao`).

## As 5 permissões (atribuição por papel)

| Permissão | administrador | gestor | analista | cidadão |
|-----------|:---:|:---:|:---:|:---:|
| `registrar-contingencia` | ✓ | ✓ | — | — |
| `atendimento-presencial` | ✓ | ✓ | — | — |
| `consultar-solicitacoes` | ✓ | ✓ | ✓ | — |
| `manter-tipos-servico` | ✓ | ✓ | — | — |
| `manter-requisitos-documentais` | ✓ | ✓ | — | — |

Permissões: **14 → 19**. Cidadão opera as próprias solicitações por policy (sem permissão nomeada).

## Task Commits

TDD estrito (RED→GREEN por domínio):

1. **Task 2 (RED parâmetros): trava catálogo em 48 + grupo solicitacao** — `cee3161` (test)
2. **Task 1 (GREEN parâmetros): 13 parâmetros + fallbacks em config** — `0d4a872` (feat)
3. **Task 3 (RED permissões): trava permissões em 19** — `94dc51b` (test)
4. **Task 3 (GREEN permissões): 5 permissões aditivas por papel** — `a98f80a` (feat)
5. **Colateral: ManageRolesTest contagem 14→19** — `fe6d42d` (test)

**Plan metadata:** `docs(08-02)` (este SUMMARY + STATE).

## Files Created/Modified

- `database/seeders/ParameterSeeder.php` — 13 entradas novas no `catalog()` (grupo solicitacao + features + seguranca), nada removido.
- `config/sile.php` — fallbacks espelhados (features +2, novo bloco solicitacao, novo bloco storage, throttle +1).
- `database/seeders/RolesAndPermissionsSeeder.php` — 5 permissões + atribuições (analista/gestor/administrador), aditivo.
- `tests/Feature/Seeders/ParameterSeederTest.php` — contagem 48 (2 pontos), grupo solicitacao, `test_seeder_registra_parametros_da_solicitacao_de_viabilidade`.
- `tests/Feature/Seeders/DatabaseSeederTest.php` — contagem 48 (2 pontos).
- `tests/Feature/Authorization/RolesAndPermissionsSeederTest.php` — 5 permissões na lista, `test_papeis_recebem_permissoes_de_solicitacao`, idempotência 19.
- `tests/Feature/Roles/ManageRolesTest.php` — `has('permissions', 19)` (colateral da contagem total exposta na listagem de perfis).

## Decisions Made

- **Grupo `solicitacao` único** para todos os `solicitacao.*` e para `storage.documentos.disk` (key ≠ group, norma do catálogo). A lista de grupos cresce apenas com `solicitacao`.
- **Escopo restrito à lista do plano**: NÃO foram adicionados `solicitacao.duplicidade.janela_dias` (constante de config do 08-05) nem `estados_cancelaveis` (08-12).
- **Atribuição por papel**: gestor opera E parametriza os cadastros da fase (as 5); analista só consulta o backoffice; cidadão por policy.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] `ManageRolesTest` fixava o total de permissões em 14**
- **Found during:** verificação da suíte completa (Task 3 / permissões)
- **Issue:** `tests/Feature/Roles/ManageRolesTest.php` asserta `->has('permissions', 14)` na listagem de perfis; a adição das 5 permissões subiu o total para 19, quebrando o teste (a suíte completa precisa seguir verde — exigência do plano).
- **Fix:** ajustada a asserção para `->has('permissions', 19)` (única ocorrência hardcoded no repositório, confirmada por grep).
- **Files modified:** `tests/Feature/Roles/ManageRolesTest.php`
- **Verification:** `php artisan test --compact --filter="ManageRolesTest|ParameterSeederTest|DatabaseSeederTest|RolesAndPermissionsSeederTest"` → 39/39 verde.
- **Committed in:** `fe6d42d`

---

**Total deviations:** 1 auto-fixed (Rule 1 — teste colateral). **Impacto:** nenhum scope creep; correção necessária para a contagem total exposta na UI de perfis bater com o seeder.

## Issues Encountered

- **08-01 executando em paralelo na MESMA working dir:** durante a execução, o agente do 08-01 criou models/enums/factories da solicitação (`app/Models/ViabilityRequest.php`, `app/Enums/ViabilityRequestStatus.php`, etc.), migrations (commit `ad88011`) e o `tests/Feature/Solicitacao/SolicitacaoSchemaTest.php`, commitando intercalado com os meus commits. **Boundary respeitado:** não toquei em nenhum arquivo de domínio do 08-01; staging sempre individual (nunca `git add -A`).
- **`pint --dirty` formatou `SolicitacaoSchemaTest.php` (08-01):** o `--dirty` opera em todos os arquivos não commitados. O fix foi apenas `no_unused_imports` (cosmético, sem remover imports necessários — os 5 `use` do domínio permaneceram). Não commitei esse arquivo; ficou para o 08-01.
- **8 erros remanescentes na suíte completa são do `SolicitacaoSchemaTest` (08-01), NÃO do meu escopo:** mudaram entre execuções (de "class not found" para "factory state undefined"/"NOT NULL requester_user_id") — sinal do ciclo RED→GREEN em andamento do 08-01. Meu escopo (seeders/config/testes de seeder + colateral ManageRoles) está 100% verde.

## Verification

- **Baseline (antes):** `--filter="ParameterSeederTest|DatabaseSeederTest|RolesAndPermissionsSeederTest"` → 25/25 verde (35 params, 14 permissões).
- **RED parâmetros:** 5 falhas pelos motivos certos (`35 is identical to 48` ×4 + `null is not null` para a chave nova).
- **GREEN parâmetros:** `--filter="ParameterSeederTest|DatabaseSeederTest"` → 18/18 verde (228 asserções).
- **RED permissões:** 3 falhas (`14 is identical to 19` + `no permission named registrar-contingencia` ×2).
- **GREEN permissões:** `--filter=RolesAndPermissionsSeederTest` → 9/9 verde (85 asserções).
- **Os 3 testes-alvo juntos:** 27/27 verde (313 asserções).
- **ManageRoles + 3 seeders:** 39/39 verde (371 asserções).
- **Suíte completa (`--exclude-group postgis`):** 675 testes, **667 passaram, 0 falhas**, 8 erros — todos no `SolicitacaoSchemaTest` (08-01, fora do escopo). Zero falha atribuível a este plano.
- `php artisan config:show sile.solicitacao.protocolo.prefixo` → `VIA`; `sile.storage.documentos.disk` → `local`.
- `vendor/bin/pint --dirty --format agent` → passed.

## Next Phase Readiness

- **Insumo direto dos planos que leem `Settings`:** 08-01 (`ProtocolNumberGenerator` → prefixo/padding), 08-06 (tolerância área×polígono), 08-08 (anexos max_mb/mime/disk), 08-09 (toggle simulação), 08-11 (ttl/throttle/prazo), 08-15 (expiração do atendimento).
- **Insumo das permissões:** 08-03/08-04/08-14/08-15 (canais de backoffice/cadastros + atendimento presencial).
- **Bloqueios herdados (não introduzidos aqui):** formato oficial de protocolo e prazo estimado real pendentes SEDUR/HU-129 — registrados como ressalva no `description` do parâmetro (degrada honesto, parametrizável quando a SEDUR confirmar).

---
*Phase: 08-solicitacao-de-viabilidade*
*Completed: 2026-06-14*

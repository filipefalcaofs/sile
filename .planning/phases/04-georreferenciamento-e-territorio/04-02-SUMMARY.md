---
phase: 04-georreferenciamento-e-territorio
plan: "04-02"
subsystem: database
tags: [parametros, hu-014, geocoding, nominatim, permissoes, spatie-permission, config, territorio]

# Dependency graph
requires:
  - phase: 02-administracao-base
    provides: catálogo de parâmetros HU-014 (ParameterSeeder), papéis/permissões (RolesAndPermissionsSeeder), Settings::get com fallback de config
  - phase: 03.1-cnpj-lookup
    provides: padrão de parâmetro de integração (base_url parametrizável + constantes técnicas em config) e throttle por minuto
provides:
  - "features.geocoding (boolean, default true) — toggle da geocodificação"
  - "integrations.geocoding.base_url (string url, default Nominatim, requires_connection_test) — trocável sem deploy"
  - "seguranca.throttle.geocoding.por_minuto (integer, default 60) — limite por usuário"
  - "geo.validacao.sobreposicao_minima (integer %, default 50, faixa 1-100) — limiar HU-037"
  - "geo.via_max_metros (50) — constante técnica em config/sile.php (NÃO-catálogo)"
  - "integrations.geocoding.* (user_agent, timeout, retries, backoff_ms, cache_ttl) — constantes técnicas em config"
  - "permissão consultar-territorio concedida a analista, gestor e administrador (CA-04)"
affects:
  - "04-03 (geocodificação: features.geocoding, integrations.geocoding.base_url, throttle, permissão)"
  - "04-05 (TerritoryService: geo.via_max_metros para a via mais próxima)"
  - "04-06 (validação de localização: geo.validacao.sobreposicao_minima)"
  - "04-07 (telas de território: permissão consultar-territorio)"

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Parâmetro de integração: base_url no catálogo (requires_connection_test); demais constantes técnicas só em config/sile.php (precedente 02-02/03-01)"
    - "Chave pt-BR no catálogo (geo.*) espelha config('sile.{chave}') para fallback sem banco"
    - "Permissão de domínio concedida de forma aditiva (givePermissionTo) aos papéis de gestão; cidadão fica de fora até as Fases 7/8"

key-files:
  created: []
  modified:
    - database/seeders/ParameterSeeder.php
    - config/sile.php
    - database/seeders/RolesAndPermissionsSeeder.php
    - tests/Feature/Seeders/ParameterSeederTest.php
    - tests/Feature/Seeders/DatabaseSeederTest.php
    - tests/Feature/Authorization/RolesAndPermissionsSeederTest.php
    - tests/Feature/Roles/ManageRolesTest.php

key-decisions:
  - "geo.via_max_metros entra como constante técnica em config/sile.php, fora do catálogo: o catálogo cresce 25 -> 29 (apenas 4 parâmetros de negócio)"
  - "throttle de geocodificação default 60/min (Nominatim recomenda ~1 req/s); faixa 1-300 espelha o throttle de cnpj_lookup"
  - "consultar-territorio concedida a analista/gestor/administrador; cidadão não recebe (uso do cidadão entra nas Fases 7/8 com permissões próprias)"

patterns-established:
  - "base_url parametrizável + constantes técnicas em config: reusado da Fase 3.1 para a geocodificação"
  - "novo grupo de parâmetros 'geo' para limiares territoriais de negócio"

# Metrics
duration: 6 min
completed: 2026-06-13
---

# Phase 4 Plan 02: Parâmetros e permissão do território Summary

**Catálogo HU-014 cresce de 25 para 29 parâmetros (toggle/base_url Nominatim/throttle/limiar de sobreposição) com fallbacks em config/sile.php, a constante técnica geo.via_max_metros e a permissão consultar-territorio concedida aos papéis de gestão.**

## Performance

- **Duration:** 6 min
- **Started:** 2026-06-13T21:36:00Z
- **Completed:** 2026-06-13T21:42:31Z
- **Tasks:** 2
- **Files modified:** 7

## Accomplishments

- 4 parâmetros novos de negócio no catálogo HU-014 (25 -> 29), no novo grupo `geo` e nos grupos `features`/`integracoes`/`seguranca` existentes, cada um com tipo, default e regras de validação.
- Fallbacks em `config/sile.php` para as 4 chaves (chaves pt-BR espelhando `config('sile.{chave}')`) + bloco `integrations.geocoding` de constantes técnicas (user_agent, timeout, retries, backoff_ms, cache_ttl), garantindo `Settings::get` sem banco em build/CI.
- `geo.via_max_metros` (50) registrada como constante técnica em config — fora do catálogo, coerente com o precedente [02-02].
- Permissão `consultar-territorio` (CA-04) seedada e concedida de forma aditiva a `analista`, `gestor` e `administrador`; `cidadao` não a recebe.

## Task Commits

Cada task foi commitada atomicamente:

1. **Task 1: 4 parâmetros novos do EP04 + fallbacks em config/sile.php** - `0d638ec` (feat)
2. **Task 2: Permissão consultar-territorio (CA-04)** - `c7430fc` (feat)

_Ciclo TDD por task: RED (testes de contagem/grupo/permissão falhando pelo motivo certo) -> GREEN (catálogo/config/seeder) -> REFACTOR (pint). Test e implementação consolidados num commit feat por task, conforme "commitar cada task atomicamente"._

## Contrato entregue (para fases consumidoras)

| Chave / permissão | Tipo | Default | Validação | Grupo / local |
|---|---|---|---|---|
| `features.geocoding` | boolean | `1` (true) | required, boolean | catálogo (features) |
| `integrations.geocoding.base_url` | string | `https://nominatim.openstreetmap.org` | required, url (`requires_connection_test`) | catálogo (integracoes) |
| `seguranca.throttle.geocoding.por_minuto` | integer | `60` | required, integer, min:1, max:300 | catálogo (seguranca) |
| `geo.validacao.sobreposicao_minima` | integer (%) | `50` | required, integer, min:1, max:100 | catálogo (geo) |
| `geo.via_max_metros` | int (config) | `50` | — | **config (não-catálogo)** |
| `consultar-territorio` | permissão | — | — | analista, gestor, administrador |

Catálogo HU-014: **29 parâmetros**. Total de permissões: **10**.

## Files Created/Modified

- `database/seeders/ParameterSeeder.php` - 4 chaves novas no `catalog()` (features.geocoding, integrations.geocoding.base_url, seguranca.throttle.geocoding.por_minuto, geo.validacao.sobreposicao_minima)
- `config/sile.php` - fallbacks `features.geocoding`, bloco `geo` (validacao.sobreposicao_minima + via_max_metros técnico), `seguranca.throttle.geocoding`, bloco `integrations.geocoding` (constantes técnicas)
- `database/seeders/RolesAndPermissionsSeeder.php` - permissão `consultar-territorio` na lista + concessão aditiva a analista/gestor/administrador
- `tests/Feature/Seeders/ParameterSeederTest.php` - contagem 29, grupo `geo` na lista ordenada, novo `test_seeder_registra_parametros_do_georreferenciamento`
- `tests/Feature/Seeders/DatabaseSeederTest.php` - contagem de parâmetros 25 -> 29 (2 ocorrências)
- `tests/Feature/Authorization/RolesAndPermissionsSeederTest.php` - novo `test_papeis_recebem_permissao_consultar_territorio`; total de permissões 9 -> 10
- `tests/Feature/Roles/ManageRolesTest.php` - listagem de perfis passa a esperar 10 permissões

## Decisions Made

- **geo.via_max_metros como constante técnica (config), fora do catálogo:** raio em metros da "via mais próxima" é detalhe de implementação do TerritoryService (04-05), sem decisão de negócio para o admin alterar; segue o precedente [02-02] de constantes técnicas só em config. O catálogo cresce só 4 (25 -> 29).
- **throttle de geocodificação default 60/min, faixa 1-300:** Nominatim recomenda ~1 req/s; o teto e a faixa espelham o throttle de `cnpj_lookup` da Fase 3.1 para consistência.
- **consultar-territorio aditiva a analista/gestor/administrador, sem cidadão:** alinhado a HU-036 (analista consulta camadas) e às HUs do EP04; o uso pelo cidadão entra nas Fases 7/8 com permissões próprias. Concessão por `givePermissionTo` (nunca `sync`) preserva ajustes do administrador via interface (HU-013).

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Base parametrizável e de autorização pronta para 04-03 (geocodificação: toggle/base_url/throttle/permissão), 04-05 (geo.via_max_metros), 04-06 (sobreposicao_minima) e 04-07 (permissão nas telas).
- Verificação escopada `tests/Feature/Seeders tests/Feature/Authorization tests/Feature/Roles` verde (32 testes, 231 asserções); `config:show sile.integrations.geocoding.base_url` e `sile.geo.via_max_metros` retornam os defaults.
- Sem bloqueios. Observação: esta foi a wave 1 paralela; o STATE.md é consolidado pelo orquestrador (não editado por este plano).

---
*Phase: 04-georreferenciamento-e-territorio*
*Completed: 2026-06-13*

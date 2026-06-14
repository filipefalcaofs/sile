---
phase: 07-consulta-previa-viabilidade
plan: 01
subsystem: infra
tags: [parametrizacao, hu-014, rate-limiting, fortify, seeder, throttle, feature-toggle]

# Dependency graph
requires:
  - phase: 03.1-cnpj-lookup
    provides: "padrão de throttle parametrizado (RateLimiter::for cnpj-lookup/geocoding) + Settings::get com fallback em config/sile.php"
  - phase: 02-parametrizacao
    provides: "catálogo ParameterSeeder + tabela parameters + App\\Support\\Settings"
provides:
  - "Parâmetro features.consulta_viabilidade (toggle, default ligado) — liga/desliga a consulta pública sem deploy"
  - "Parâmetro seguranca.throttle.consulta_viabilidade.por_minuto (default 20) — limite administrável sem deploy"
  - "Fallbacks espelhados em config/sile.php (Settings funciona com banco indisponível)"
  - "RateLimiter nomeado 'consulta-viabilidade' parametrizado por usuário autenticado/IP anônimo"
  - "Catálogo de parâmetros 33→35 travado nos dois testes de seeder"
affects: [07-06 (rotas públicas /portal/viabilidade com throttle + toggle), 07-09/07-10 (degradação honesta do toggle)]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Throttle de rota pública nasce parametrizado (HU-014): RateLimiter::for lê o parâmetro via Settings::get, com fallback em config"
    - "Funcionalidade acoplável nasce com feature toggle administrável (features.*)"

key-files:
  created: []
  modified:
    - database/seeders/ParameterSeeder.php
    - config/sile.php
    - app/Providers/FortifyServiceProvider.php
    - tests/Feature/Seeders/ParameterSeederTest.php
    - tests/Feature/Seeders/DatabaseSeederTest.php

key-decisions:
  - "Throttle default da consulta de viabilidade em 20/min (mais restritivo que cnpj-lookup 30 e geocoding 60: a consulta orquestra 3 motores, é a rota pública mais cara)"
  - "Toggle nasce ligado (default '1'): a consulta é a entrega central da fase; a degradação ao desligar é responsabilidade do 07-06, sem fachada"
  - "Reuso integral do padrão de throttle da Fase 3.1, sem nenhuma dependência nova"

patterns-established:
  - "Parâmetro + fallback em config + RateLimiter parametrizado: bloco copiado de cnpj-lookup/geocoding para consulta-viabilidade"

# Metrics
duration: 5 min
completed: 2026-06-14
---

# Phase 7 Plan 01: Parâmetros e RateLimiter da Consulta de Viabilidade Summary

**Fundação de parametrização da consulta pública: toggle `features.consulta_viabilidade` + throttle `seguranca.throttle.consulta_viabilidade.por_minuto` (default 20) no catálogo (33→35), com fallbacks em config e o RateLimiter nomeado `consulta-viabilidade` pronto para as rotas públicas do 07-06.**

## Performance

- **Duration:** ~5 min
- **Started:** 2026-06-14T08:00:30Z
- **Completed:** 2026-06-14T08:05:17Z
- **Tasks:** 2
- **Files modified:** 5

## Accomplishments
- 2 parâmetros administráveis novos no catálogo (HU-014), em grupos já existentes (`features`, `seguranca`) — nenhum grupo novo
- `RateLimiter::for('consulta-viabilidade')` parametrizado por usuário autenticado (ou IP quando anônimo), espelhando exatamente `cnpj-lookup`/`geocoding`
- Fallbacks espelhados em `config/sile.php` (`features.consulta_viabilidade => true`, `seguranca.throttle.consulta_viabilidade => ['por_minuto' => 20]`) — Settings resolve sem banco
- Catálogo 33→35 travado nos DOIS testes de seeder + caso dedicado validando tipo/default/regras/grupo dos 2 parâmetros
- Zero dependência nova

## Task Commits

Cada task foi commitada atomicamente, seguindo o ciclo RED→GREEN:

1. **Task 2 (RED): travar contagem 33→35 + caso dedicado nos dois seeders** — escrito primeiro e verificado vermelho (5 falhas: contagem e parâmetros inexistentes)
2. **Task 1 (GREEN): 2 parâmetros + fallbacks + RateLimiter** - `b4ffc4a` (feat)
3. **Task 2 (testes): contagem 35 + teste dedicado verde** - `25c9447` (test)

**Plan metadata:** `docs(07-01)` (este SUMMARY)

_Nota: a Task 1 (implementação) e a Task 2 (testes) foram commitadas em ordem de plano; a disciplina RED→GREEN foi confirmada antes de cada commit (testes vermelhos sem a Task 1, verdes com ela)._

## Files Created/Modified
- `database/seeders/ParameterSeeder.php` - +2 entradas no `catalog()`: `features.consulta_viabilidade` (boolean, default '1') e `seguranca.throttle.consulta_viabilidade.por_minuto` (integer, default '20', min:1/max:300)
- `config/sile.php` - fallbacks: `features.consulta_viabilidade => true` e `seguranca.throttle.consulta_viabilidade => ['por_minuto' => 20]`
- `app/Providers/FortifyServiceProvider.php` - `RateLimiter::for('consulta-viabilidade', ...)` lendo o parâmetro via `Settings::get(..., 20)`, chaveado por `user()?->id ?: ip()`
- `tests/Feature/Seeders/ParameterSeederTest.php` - contagem 35 (2 pontos) + `test_seeder_registra_parametros_da_consulta_de_viabilidade`
- `tests/Feature/Seeders/DatabaseSeederTest.php` - contagem 35 (2 pontos)

## Decisions Made
- **Default do throttle = 20/min**: a consulta de viabilidade orquestra geocoding + território + LOUOS + risco (a rota pública mais cara da fase), então nasce mais restritiva que `cnpj-lookup` (30) e `geocoding` (60). Continua administrável sem deploy.
- **Toggle nasce ligado**: a consulta é a entrega central do EP07; a degradação controlada ao desligar (sem fachada) é implementada nas rotas (07-06).
- **Posição no catálogo**: as 2 entradas foram acrescentadas ao final do `catalog()` (convenção de "params da fase nova ao final"), sem remover/reordenar nada.

## Deviations from Plan

None - plano executado exatamente como escrito.

## Issues Encountered
- A suíte completa (`php artisan test --compact --exclude-group postgis`) retorna **604 testes, 603 passando, 1 erro**. O único erro — `Tests\Feature\Viabilidade\PropertyRegistryLookupTest::test_provider_real_esta_indisponivel_e_nunca_inventa_ponto` ("Target [App\\Services\\Realty\\PropertyRegistryLookup] is not instantiable") — é o estado **RED intencional do plano paralelo 07-02** (commit `11d68fc test(07-02): teste do contrato PropertyRegistryLookup (RED)`): a interface existe, mas o binding concreto é registrado no `AppServiceProvider` por uma etapa GREEN posterior do 07-02. **Não é regressão do 07-01** — fora do escopo (`AppServiceProvider` é domínio do 07-02, explicitamente vedado a este plano). Verificação escopada do 07-01: `ParameterSeederTest|DatabaseSeederTest` = 17/17 verde, e os 603 demais testes da suíte passam.

## User Setup Required
None - nenhuma configuração de serviço externo necessária.

## Next Phase Readiness
- **Pronto para 07-06**: o RateLimiter `consulta-viabilidade` e o toggle `features.consulta_viabilidade` estão disponíveis para as rotas públicas (`->middleware('throttle:consulta-viabilidade')` + checagem do toggle com degradação honesta).
- Parâmetros administráveis pela UI de parametrização existente (HU-014); efeito sem deploy (limitado ao cache de 300s do Settings).
- Sem bloqueios introduzidos por este plano.

---
*Phase: 07-consulta-previa-viabilidade*
*Completed: 2026-06-14*

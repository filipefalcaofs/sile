---
phase: 10-analise-tecnica-sedur
plan: 01
subsystem: infra
tags: [hu-014, parametros, settings, permissoes, spatie-permission, seeders, config-fallback]

# Dependency graph
requires:
  - phase: 09-fluxo-expresso
    provides: "padrão do catálogo HU-014, fallback config/sile.php, risco.mapa_encaminhamento, expresso.* (reusados, não recriados)"
  - phase: 02-administracao
    provides: "Parameter (registry HU-014), Settings::get, RolesAndPermissionsSeeder, padrão de permissões aditivas (givePermissionTo)"
provides:
  - "11 parâmetros administráveis novos: grupo 'analise' (10) + toggle features.analise_tecnica (catálogo 56→67)"
  - "5 permissões aditivas (19→24): analisar-processos, distribuir-processos, emitir-tvl, encaminhar-malha-fina, manter-setores, atribuídas por papel"
  - "Fallback config/sile.php do bloco 'analise' (negócio) + constantes técnicas (autosave debounce, cache de precedentes)"
affects: [10-04-manter-setores, 10-05-sla, 10-06-precedentes, 10-07-distribuir-assumir, 10-09, 10-10, 10-11-pendencia, 10-12-malha-fina, 10-13-tvl]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Parâmetro de negócio no ParameterSeeder + espelho de fallback em config/sile.php (Settings::get lê config sem banco)"
    - "Constante técnica fica SÓ no config, fora do catálogo HU-014 (precedente 02-02)"
    - "Permissão aditiva por papel via givePermissionTo (nunca sync — preserva ajustes do admin)"

key-files:
  created: []
  modified:
    - database/seeders/ParameterSeeder.php
    - config/sile.php
    - database/seeders/RolesAndPermissionsSeeder.php
    - tests/Feature/Seeders/ParameterSeederTest.php
    - tests/Feature/Seeders/DatabaseSeederTest.php
    - tests/Feature/Authorization/RolesAndPermissionsSeederTest.php

key-decisions:
  - "A consulta de processos (HU-082) reusa a permissão consultar-solicitacoes — NÃO cria permissão de consulta nova (a validar com a SEDUR); espelha o resultado expresso (09-11)"
  - "imagem_path do TVL nasce com default '' (nullable) — sem imagem configurada até a SEDUR entregar a assinatura do diretor"
  - "Constantes técnicas (autosave debounce_ms, precedentes cache_ttl_segundos) ficam fora do catálogo — não são decisão de negócio"

patterns-established:
  - "Grupo 'analise' inserido na lista ordenada (alfabética) dos grupos de parâmetros"
  - "Plano único dono do catálogo/permissões da Fase 10 — evita colisão nos testes de contagem entre waves paralelas"

# Metrics
duration: 7min
completed: 2026-06-14
---

# Phase 10 Plan 01: Fundação de parametrização e segurança da análise técnica — Summary

**11 parâmetros administráveis do grupo `analise` (catálogo HU-014 56→67) + 5 permissões aditivas por papel (19→24) + fallback em config/sile.php, com TDD estrito e zero dependência nova.**

## Performance

- **Duration:** ~7 min
- **Started:** 2026-06-14T21:47:00Z
- **Completed:** 2026-06-14T21:53:00Z
- **Tasks:** 2
- **Files modified:** 6

## Accomplishments
- Registrou no catálogo HU-014 os 11 parâmetros da análise técnica (10 do grupo novo `analise` + o toggle `features.analise_tecnica`), todos administráveis com tipo, default, validação e `value` null preservável.
- Espelhou os valores de negócio em `config/sile.php` (bloco `analise` + `features.analise_tecnica`) para o fallback do `Settings::get` sem banco, e guardou as constantes técnicas (autosave/cache) fora do catálogo.
- Adicionou 5 permissões aditivas atribuídas por papel via `givePermissionTo` (nunca `sync`), total 19→24.
- Os três testes de seeder seguem verdes com as contagens atualizadas (67 parâmetros / 24 permissões).

## Catálogo — 11 chaves novas

| Chave | group | type | default | validation_rules |
|---|---|---|---|---|
| `features.analise_tecnica` | features | boolean | `1` | required, boolean |
| `analise.sla.distribuicao_dias` | analise | integer | `2` | required, integer, min:1, max:60 |
| `analise.sla.analise_dias` | analise | integer | `10` | required, integer, min:1, max:180 |
| `analise.sla.semaforo.amarelo_percentual` | analise | integer | `80` | required, integer, min:1, max:99 |
| `analise.pendencia.prazo_resposta_dias` | analise | integer | `15` | required, integer, min:1, max:180 |
| `analise.precedentes.janela_meses` | analise | integer | `12` | required, integer, min:1, max:120 |
| `analise.precedentes.max_itens` | analise | integer | `10` | required, integer, min:1, max:50 |
| `analise.tvl.disk` | analise | string | `local` | required, string, max:50 |
| `analise.tvl.assinatura.modo` | analise | string | `imagem` | required, in:imagem,nenhuma |
| `analise.tvl.assinatura.imagem_path` | analise | string | `''` | nullable, string, max:255 |
| `analise.tvl.download.ttl_minutos` | analise | integer | `5` | required, integer, min:1, max:1440 |

## Fallback config/sile.php — bloco `analise`

```
sile.features.analise_tecnica = true
sile.analise.sla.distribuicao_dias = 2
sile.analise.sla.analise_dias = 10
sile.analise.sla.semaforo.amarelo_percentual = 80
sile.analise.pendencia.prazo_resposta_dias = 15
sile.analise.precedentes.janela_meses = 12
sile.analise.precedentes.max_itens = 10
sile.analise.precedentes.cache_ttl_segundos = 300   # CONSTANTE TÉCNICA (fora do catálogo)
sile.analise.tvl.disk = local
sile.analise.tvl.assinatura.modo = imagem
sile.analise.tvl.assinatura.imagem_path = '' (vazio)
sile.analise.tvl.download.ttl_minutos = 5
sile.analise.autosave.debounce_ms = 1500            # CONSTANTE TÉCNICA (fora do catálogo)
```

Consumidores das waves seguintes leem via `Settings::get('analise....', config('sile.analise....'))`. As constantes técnicas (`precedentes.cache_ttl_segundos`, `autosave.debounce_ms`) NÃO têm parâmetro no catálogo: leia-as direto de `config('sile.analise....')`.

## Permissões — 5 novas (19→24) e atribuição por papel

| Permissão | analista | gestor | administrador |
|---|:--:|:--:|:--:|
| `analisar-processos` | ✓ | ✓ | ✓ |
| `distribuir-processos` | — | ✓ | ✓ |
| `emitir-tvl` | ✓ | ✓ | ✓ |
| `encaminhar-malha-fina` | ✓ | ✓ | ✓ |
| `manter-setores` | — | ✓ | ✓ |

- `cidadao` não recebe nenhuma das cinco.
- Atribuição aditiva (`givePermissionTo`, nunca `sync`) — re-seed preserva ajustes do admin via interface.
- A **consulta** de processos (HU-082) reusa `consultar-solicitacoes` (não há permissão de consulta nova).

## Task Commits

1. **Task 1: 11 parâmetros novos (grupo analise) + fallback config + asserts** - `dbd8ad0` (feat)
2. **Task 2: 5 permissões aditivas + atribuição por papel** - `1ceb70c` (feat)

_TDD estrito em ambas (RED confirmado antes do GREEN); commit único por task pois teste e implementação são coesos no mesmo arquivo de domínio._

## Files Created/Modified
- `database/seeders/ParameterSeeder.php` - 11 parâmetros novos no catálogo (56→67)
- `config/sile.php` - bloco `analise` (negócio + constantes técnicas) + `features.analise_tecnica`
- `database/seeders/RolesAndPermissionsSeeder.php` - 5 permissões aditivas + atribuição por papel
- `tests/Feature/Seeders/ParameterSeederTest.php` - contagem 67, grupo `analise`, teste dos 11 parâmetros
- `tests/Feature/Seeders/DatabaseSeederTest.php` - contagem de parâmetros 56→67 (2 asserções)
- `tests/Feature/Authorization/RolesAndPermissionsSeederTest.php` - contagem 24, teste das 5 permissões + atribuição

## Decisions Made
- **Reuso de `consultar-solicitacoes` para a consulta (HU-082):** não se cria permissão de consulta nova — decisão a validar com a SEDUR, espelha o expresso (09-11).
- **`imagem_path` default `''`:** sem assinatura configurada até a SEDUR entregar a imagem do diretor; modo `imagem` é o default, `nenhuma` é a alternativa; gov.br/ICP é gancho futuro.
- **Constantes técnicas só no config:** `precedentes.cache_ttl_segundos` (300) e `autosave.debounce_ms` (1500) não entram no catálogo — não são valores de negócio (precedente 02-02).

## Deviations from Plan
None - plan executed exactly as written.

## Issues Encountered
Waves paralelas (ex.: 10-03) commitaram no mesmo branch durante a execução. O `STATE.md` NÃO foi alterado por este plano para evitar clobber entre executores concorrentes — a consolidação do STATE.md/progresso fica a cargo do orquestrador da fase. Apenas os arquivos do escopo deste plano foram staged (commits atômicos, nunca `git add -A`).

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Catálogo, fallback e permissões prontos: as waves seguintes podem ler parâmetros via `Settings::get('analise....', config('sile.analise....'))` e proteger rotas via `permission:` sem acoplar ao seeder.
- Insumo direto: 10-04 (manter-setores), 10-05 (SLA), 10-06 (precedentes — janela/itens + cache_ttl técnico), 10-07 (distribuir/analisar), 10-09/10-10 (analisar), 10-11 (pendência), 10-12 (encaminhar-malha-fina), 10-13 (TVL/emitir-tvl).
- Pendência SEDUR a registrar: confirmar se a consulta de processos deve ter permissão própria (hoje reusa `consultar-solicitacoes`) e a entrega da imagem de assinatura do TVL.

---
*Phase: 10-analise-tecnica-sedur*
*Completed: 2026-06-14*

---
phase: 09-fluxo-expresso
plan: 01
subsystem: parametrizacao
tags: [hu-014, fluxo-expresso, parametros, feature-toggle, config-fallback, seeder, anti-fachada, expresso, hu-073, hu-076, hu-077, hu-134]

# Dependency graph
requires:
  - phase: 02-administracao-base
    plan: "02"
    provides: "App\\Support\\Settings::get (banco→cache→config/sile.php) + Parameter (typedValue) + ParameterSeeder (upsert por key SÓ de metadados, value preservado) + padrão de testes de contagem"
  - phase: 06-classificacao-de-risco
    plan: "(risco)"
    provides: "risco.mapa_encaminhamento (parâmetro json baixo_a/baixo_b→expresso, alto→analise) REUSADO para elegibilidade — NÃO recriado"
provides:
  - "7 parâmetros administráveis novos do fluxo expresso (HU-014): features.fluxo_expresso, features.notificacao_resultado_expresso (grupo features) + expresso.bap.prazo_horas, expresso.notificacao.assunto_deferida/_indeferida, expresso.tvl.prefixo/.padding (grupo novo expresso)"
  - "Catálogo HU-014 de 49 → 56 parâmetros; grupo novo 'expresso' (10 grupos no total)"
  - "config/sile.php: bloco 'expresso' (fallback dos parâmetros de negócio) + 2 toggles em features.* + constantes TÉCNICAS fora do catálogo (lock.ttl_segundos, fila, job.tries/timeout/backoff)"
affects: [09-05, 09-06, 09-07, 09-10]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Parametrização HU-014: cada valor de negócio (prazo BAP, assuntos de e-mail, formato do número TVL, toggles) nasce administrável com tipo/default/validação/histórico + espelho de fallback em config/sile.php (Settings::get lê config quando o banco está indisponível)"
    - "Constantes técnicas (TTL do Cache::lock, fila, tries/timeout/backoff do job) ficam SÓ no config/sile.php, FORA do catálogo HU-014 — precedente [02-02]: parametrizar isso no painel seria ruído sem valor de negócio"
    - "Reuso de parâmetro existente (risco.mapa_encaminhamento) em vez de criar um novo para elegibilidade — evita duplicação de fonte de verdade"

key-files:
  created:
    - .planning/phases/09-fluxo-expresso/09-01-SUMMARY.md
  modified:
    - database/seeders/ParameterSeeder.php
    - config/sile.php
    - tests/Feature/Seeders/ParameterSeederTest.php
    - tests/Feature/Seeders/DatabaseSeederTest.php

key-decisions:
  - "7 parâmetros novos (catálogo 49→56). Os 2 toggles ficam no grupo 'features' existente (features.fluxo_expresso, features.notificacao_resultado_expresso); os 5 de negócio do expresso vão para o grupo NOVO 'expresso' (bap.prazo_horas=48, notificacao.assunto_deferida/_indeferida, tvl.prefixo=TVL, tvl.padding=6). Lista ordenada de grupos passou a ['expresso','features','geo','integracoes','louos','retencao','risco','seguranca','solicitacao','ui']."
  - "Constantes técnicas SÓ no config (fora do catálogo): expresso.lock.ttl_segundos=10 (idempotência da emissão via Cache::lock), expresso.fila='default' e expresso.job (tries=3, timeout=120, backoff=[30,60,120]) — resiliência do DecidirFluxoExpressoJob. Precedente [02-02]: técnico ≠ negócio."
  - "REUSA risco.mapa_encaminhamento (Fase 6) para a elegibilidade do expresso — NÃO foi criado parâmetro novo de encaminhamento."
  - "ZERO permissão nova nesta fase: o fluxo expresso reusa 'consultar-solicitacoes' (permissões permanecem 19). Por isso o DatabaseSeederTest mexeu SÓ na contagem de parâmetros (49→56), preservando as demais asserções da Fase 8."
  - "value NUNCA entra no update do seeder (mantido o padrão de upsert só de metadados): re-seed em deploy preserva o que o admin gravou."

patterns-established:
  - "Toggle de funcionalidade acoplável nasce com chave administrável + degradação comunicada: features.fluxo_expresso off ⇒ toda protocolada vai para análise técnica (não falha silenciosa); features.notificacao_resultado_expresso off ⇒ não envia e audita."

# Metrics
duration: ~7 min
completed: 2026-06-14
---

# Phase 9 Plan 01: Fundação de Parametrização do Fluxo Expresso Summary

**A Fase 9 ganhou sua fundação de parametrização (Track A da Wave 1, dono exclusivo do catálogo): os 7 parâmetros administráveis do fluxo expresso entraram no catálogo HU-014 (de 49 para 56) sem nenhum valor de negócio hardcoded. Dois toggles no grupo `features` existente — `features.fluxo_expresso` (deferimento/indeferimento automático; off ⇒ tudo vai para análise técnica, degradação comunicada) e `features.notificacao_resultado_expresso` (e-mail de resultado ao requerente, sem anexo de TVL) — e cinco no grupo NOVO `expresso`: `expresso.bap.prazo_horas` (48), `expresso.notificacao.assunto_deferida`/`_indeferida` e `expresso.tvl.prefixo` (TVL)/`.padding` (6). Cada um com tipo, descrição pt-BR, valor padrão, regras de validação e `value` null (histórico/auditoria pela infra da HU-014). O `config/sile.php` espelha tudo como fallback do `Settings::get` (banco indisponível) e guarda, FORA do catálogo, as constantes TÉCNICAS do fluxo (TTL do `Cache::lock`, fila e tries/timeout/backoff do job de decisão — precedente [02-02]). A elegibilidade REUSA `risco.mapa_encaminhamento` (Fase 6), sem parâmetro novo; nenhuma permissão criada (reuso de `consultar-solicitacoes`, permanecem 19). TDD estrito (RED→GREEN com evidência fresca): `ParameterSeederTest` 15/15 e `DatabaseSeederTest` + `ParameterSeederTest` 21/21; suíte completa 857/857 (4489 asserções, inclui @group postgis com `sile-pgsql` healthy). ZERO dependência nova.**

## Performance

- **Duration:** ~7 min
- **Started:** 2026-06-14T16:31:40Z
- **Completed:** 2026-06-14T16:36:00Z (último commit de código c963d71)
- **Tasks:** 2 (catálogo + asserts no ParameterSeederTest; fallback/constantes técnicas no config + contagem no DatabaseSeederTest)
- **Files modified:** 4 (ParameterSeeder, config/sile.php, ParameterSeederTest, DatabaseSeederTest) + este SUMMARY — ZERO dependência nova

## Os 7 parâmetros novos (contrato dos consumidores da Fase 9)

| Key | Grupo | Tipo | Default | Validação |
|-----|-------|------|---------|-----------|
| `features.fluxo_expresso` | features | boolean | `1` | `required, boolean` |
| `features.notificacao_resultado_expresso` | features | boolean | `1` | `required, boolean` |
| `expresso.bap.prazo_horas` | expresso | integer | `48` | `required, integer, min:1, max:720` |
| `expresso.notificacao.assunto_deferida` | expresso | string | `Resultado da sua solicitação de viabilidade: deferida` | `required, string, max:150` |
| `expresso.notificacao.assunto_indeferida` | expresso | string | `Resultado da sua solicitação de viabilidade: indeferida` | `required, string, max:150` |
| `expresso.tvl.prefixo` | expresso | string | `TVL` | `required, string, max:10` |
| `expresso.tvl.padding` | expresso | integer | `6` | `required, integer, min:4, max:10` |

## Estrutura do bloco `expresso` em config/sile.php

```php
'expresso' => [
    'bap' => ['prazo_horas' => 48],
    'notificacao' => [
        'assunto_deferida' => 'Resultado da sua solicitação de viabilidade: deferida',
        'assunto_indeferida' => 'Resultado da sua solicitação de viabilidade: indeferida',
    ],
    'tvl' => ['prefixo' => 'TVL', 'padding' => 6],
    // Constantes TÉCNICAS (fora do catálogo HU-014 — precedente [02-02])
    'lock' => ['ttl_segundos' => 10],
    'fila' => 'default',
    'job' => ['tries' => 3, 'timeout' => 120, 'backoff' => [30, 60, 120]],
],
```

Mais os 2 toggles `features.fluxo_expresso => true` / `features.notificacao_resultado_expresso => true` no bloco `features`.

## Task Commits

TDD estrito (RED→GREEN verificado com evidência fresca antes de cada commit):

1. **Task 1: 7 parâmetros no catálogo + asserts no ParameterSeederTest** — `88145aa` (feat) — RED: 3 falhas (`49 is identical to 56` em catálogo/idempotência + `null is not null` no parâmetro inexistente) → GREEN: 15/15 (221 asserções).
2. **Task 2: fallback + constantes técnicas em config/sile.php + contagem no DatabaseSeederTest** — `c963d71` (feat) — RED: 2 falhas (`56 is identical to 49` em test_seed_completo/test_seed_e_idempotente, provando que o seeder da Task 1 já produz 56 via DatabaseSeeder) → GREEN: 21/21 (300 asserções) + `config:show sile.expresso.tvl.prefixo` = TVL.

**Plan metadata:** `docs(09-01)` (este SUMMARY + STATE).

## Decisions Made

- **Catálogo 49→56, grupo novo `expresso`**: toggles no grupo `features` existente; valores de negócio do fluxo no grupo `expresso`. Lista ordenada de grupos atualizada com `expresso` em primeiro (ordem alfabética).
- **Constantes técnicas SÓ no config**: TTL do lock, fila e tries/timeout/backoff do job não viram parâmetro de painel (não são valores de negócio — precedente [02-02]). Ficam disponíveis para o job de decisão (09-06) lê via `config('sile.expresso....')`.
- **Reuso de `risco.mapa_encaminhamento`**: a elegibilidade do expresso (HU-073) usa o mapa de encaminhamento já parametrizado na Fase 6; não criei parâmetro redundante.
- **Sem permissão nova**: o fluxo expresso reusa `consultar-solicitacoes`; o `DatabaseSeederTest` só teve a contagem de parâmetros ajustada (49→56), mantendo intactas as asserções de permissões (19) e da Fase 8.
- **`value` preservado**: o seeder continua fazendo upsert apenas dos metadados — re-seed em deploy não sobrescreve o valor que o admin gravou.

## Deviations from Plan

None — plano executado exatamente como escrito (2 tasks, mesmos 7 parâmetros/grupos/defaults/validações e mesmo bloco de config especificados).

## Issues Encountered

- **Wave 1 em paralelo na MESMA working dir:** o plano 09-02 commitou intercalado (`6a85838`, schema da decisão) entre minhas duas tasks. **Boundary respeitado**: toquei SOMENTE `ParameterSeeder`/`config/sile.php`/`ParameterSeederTest`/`DatabaseSeederTest` (escopo exclusivo do 09-01); staging sempre individual (nunca `git add -A`). Meus commits `88145aa`/`c963d71` permanecem íntegros na história e a suíte completa (857/857) inclui o trabalho já commitado dos planos paralelos sem conflito.

## Verification (evidência fresca)

- **RED Task 1:** `--filter=ParameterSeederTest` → 3 falhas (`49 is identical to 56` ×2 + `null is not null` no fluxo expresso). **GREEN:** 15/15 (221 asserções).
- **RED Task 2:** `--filter=DatabaseSeederTest` (teste em 49 via stash) → 2 falhas (`56 is identical to 49`). **GREEN:** `--filter="DatabaseSeederTest|ParameterSeederTest"` → 21/21 (300 asserções).
- **Fallback HU-014:** `php artisan config:show sile.expresso.tvl.prefixo` → `TVL`; `php artisan config:show sile.expresso` mostra o bloco completo (negócio + lock/fila/job técnicos).
- **`vendor/bin/pint --dirty --format agent`** → passed (após cada task).
- **Suíte completa:** `php artisan test --compact` → **857 testes, 857 passaram, 0 falhas** (4489 asserções; inclui @group postgis com `sile-pgsql` healthy e o trabalho commitado dos planos paralelos).

## Next Phase Readiness

- **Insumo direto da Wave 2+**: 09-05 (toggle `features.fluxo_expresso` + número TVL via `expresso.tvl.prefixo`/`.padding` + assuntos), 09-07 (notificação HU-077: `expresso.notificacao.assunto_*` + toggle `features.notificacao_resultado_expresso`), 09-10 (HU-134: `expresso.bap.prazo_horas`) e 09-06 (resiliência do job: `expresso.fila`/`expresso.job.*`/`expresso.lock.ttl_segundos`).
- **Consumo padronizado**: os serviços leem `Settings::get('<chave>', config('sile.<chave>', <default inline>))` — o catálogo + fallback já estão prontos ANTES dos consumidores, mantendo cada serviço testável com default inline (independe do seeder).
- **Bloqueios herdados (não introduzidos aqui):** `expresso.bap.prazo_horas` é parâmetro de uma HU-134 DORMENTE (nada entra em `aguardando_bap` até o Regin/Fase 13); o toggle do fluxo degrada honesto (off ⇒ análise técnica). Nenhuma fachada: os parâmetros existem e são reais; quem os consome chega nas waves seguintes.

---
*Phase: 09-fluxo-expresso*
*Completed: 2026-06-14*

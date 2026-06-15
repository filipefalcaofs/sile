---
phase: 11-pendencias-e-comunicacao
plan: 02
subsystem: infra
tags: [hu-014, parametros, settings, notificacoes, whatsapp, seeders, config-fallback]

# Dependency graph
requires:
  - phase: 10-analise-tecnica-sedur
    provides: "padrão do catálogo HU-014 (10-01), fallback config/sile.php, analise.pendencia.prazo_resposta_dias (reusado, não recriado)"
  - phase: 02-administracao
    provides: "Parameter (registry HU-014), Settings::get, criptografia condicional de sensitive, requires_connection_test"
provides:
  - "11 parâmetros administráveis novos: 3 toggles em features + grupo novo 'notificacoes' (6) + integrations.whatsapp.* (2) (catálogo 67→78)"
  - "Fallback config/sile.php: features.notificacao_*, bloco 'notificacoes' (negócio) e integrations.whatsapp (base_url/token + constantes técnicas)"
  - "WhatsApp OFF por default (degradação honesta); credenciais como integração com teste de conexão e segredo criptografado"
affects: [11-03-whatsapp-gateway, 11-04-dispatcher-canais, 11-05-pendencia-templates, 11-06-resposta-pendencia, 11-07-vencimento-escalonamento]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Toggle de canal (boolean) em features + mapa de canais (json) em 'notificacoes' — intersecção resolve canais no disparo (11-04)"
    - "Credencial de integração: base_url (requires_connection_test) + token (sensitive, criptografado) — espelha govbr (02)"
    - "Constante técnica do adaptador (timeout/tries/backoff) fica SÓ no config, fora do catálogo HU-014 (precedente 02-02)"

key-files:
  created: []
  modified:
    - database/seeders/ParameterSeeder.php
    - config/sile.php
    - tests/Feature/Seeders/ParameterSeederTest.php
    - tests/Feature/Seeders/DatabaseSeederTest.php

key-decisions:
  - "WhatsApp nasce OFF (features.notificacao_whatsapp = 0): provedor real bloqueado até a Fase 13; degradação honesta, nunca finge envio"
  - "Destinatário do escalonamento é parametrizável (notificacoes.escalonamento.gestor_role = 'gestor') — não há 'gestor do setor' no schema (pendência SEDUR)"
  - "Tratamento do escalonamento default só NOTIFICA (amarelo→analista, vencido→gestor): sem decisão automática (HU-147)"
  - "Templates de pendência (assunto/corpo) com placeholders {protocolo}/{pendencia} — texto pt-BR honesto, oficial pendente SEDUR"
  - "Constantes técnicas do WhatsApp (timeout/tries/backoff_ms) só em config — não são valor de negócio (precedente 02-02)"
  - "NÃO cria permissão nova: central in-app é por dono e o histórico (HU-096) reusa consultar-solicitacoes — permissões seguem 24"

patterns-established:
  - "Grupo 'notificacoes' inserido na lista ordenada (alfabética: entre 'louos' e 'retencao')"
  - "ParameterSeeder é o ÚNICO dono do catálogo/config na Fase 11 — evita colisão de contagem entre waves paralelas (11-01 schema roda junto)"

# Metrics
duration: 6min
completed: 2026-06-15
---

# Phase 11 Plan 02: Catálogo de parâmetros HU-014 da comunicação — Summary

**11 parâmetros administráveis da comunicação multicanal (catálogo HU-014 67→78): 3 toggles de canal, grupo novo `notificacoes` e credenciais do WhatsApp (teste de conexão + segredo criptografado), com espelho de fallback em `config/sile.php`, WhatsApp OFF por default, TDD estrito e zero dependência nova.**

## Performance

- **Duration:** ~6 min
- **Started:** 2026-06-15T03:47:30Z
- **Completed:** 2026-06-15T03:54:00Z
- **Tasks:** 2 (executados como ciclo TDD: RED → GREEN)
- **Files modified:** 4

## Accomplishments
- Registrou no catálogo HU-014 os 11 parâmetros da comunicação (3 toggles em `features` + 6 no grupo novo `notificacoes` + 2 em `integrations.whatsapp`), todos administráveis com tipo, default, validação e `value` null preservável.
- Espelhou os valores de negócio em `config/sile.php` (toggles em `features`, bloco `notificacoes`, `integrations.whatsapp`) para o fallback do `Settings::get` sem banco, e guardou as constantes técnicas (timeout/tries/backoff) fora do catálogo.
- WhatsApp nasce DESLIGADO e suas credenciais já entram como integração com teste de conexão (`base_url`) e segredo criptografado (`token` sensitive) — prontas para a Fase 13 ligar só o binding.
- Os dois seeder-tests seguem verdes com a contagem atualizada (67→78) e o grupo `notificacoes` na lista ordenada; permissões inalteradas (24).

## Catálogo — 11 chaves novas

| Chave | group | type | default | validation_rules | flags |
|---|---|---|---|---|---|
| `features.notificacao_email` | features | boolean | `1` | required, boolean | — |
| `features.notificacao_in_app` | features | boolean | `1` | required, boolean | — |
| `features.notificacao_whatsapp` | features | boolean | `0` | required, boolean | OFF (Fase 13) |
| `notificacoes.mapa_canais` | notificacoes | json | ver abaixo | required, json | — |
| `notificacoes.vencimento.antecedencia_dias` | notificacoes | integer | `3` | required, integer, min:1, max:60 | — |
| `notificacoes.escalonamento.tratamento` | notificacoes | json | ver abaixo | required, json | — |
| `notificacoes.escalonamento.gestor_role` | notificacoes | string | `gestor` | required, string, max:50 | — |
| `notificacoes.pendencia.assunto` | notificacoes | string | `Pendência na sua solicitação de viabilidade {protocolo}` | required, string, max:150 | — |
| `notificacoes.pendencia.corpo` | notificacoes | string | template pt-BR ({protocolo}/{pendencia}) | required, string, max:2000 | — |
| `integrations.whatsapp.base_url` | integracoes | string | `''` | nullable, url | requires_connection_test |
| `integrations.whatsapp.token` | integracoes | string | `null` | nullable, string, max:255 | sensitive (criptografado) |

### JSON defaults

`notificacoes.mapa_canais`:

```json
{"pendencia_aberta":["email","in_app"],"pendencia_respondida":["in_app"],"prazo_vencendo":["email","in_app"],"escalonamento_sla":["email","in_app"],"resultado":["email","in_app"]}
```

`notificacoes.escalonamento.tratamento`:

```json
{"amarelo":"notificar_analista","vencido":"notificar_gestor"}
```

`notificacoes.pendencia.corpo` (default):

```
Olá! Identificamos uma pendência na sua solicitação de viabilidade {protocolo}. Pendência: {pendencia}. Acesse o portal do SILE para responder dentro do prazo informado.
```

## Fallback config/sile.php

```
sile.features.notificacao_email = true
sile.features.notificacao_in_app = true
sile.features.notificacao_whatsapp = false
sile.notificacoes.mapa_canais = [array decodificado]
sile.notificacoes.vencimento.antecedencia_dias = 3
sile.notificacoes.escalonamento.tratamento = ['amarelo'=>'notificar_analista','vencido'=>'notificar_gestor']
sile.notificacoes.escalonamento.gestor_role = 'gestor'
sile.notificacoes.pendencia.assunto = 'Pendência na sua solicitação de viabilidade {protocolo}'
sile.notificacoes.pendencia.corpo = '...{protocolo}...{pendencia}...'
sile.integrations.whatsapp.base_url = ''
sile.integrations.whatsapp.token = ''
sile.integrations.whatsapp.timeout = 8        # CONSTANTE TÉCNICA (fora do catálogo)
sile.integrations.whatsapp.tries = 3          # CONSTANTE TÉCNICA (fora do catálogo)
sile.integrations.whatsapp.backoff_ms = 1000  # CONSTANTE TÉCNICA (fora do catálogo)
```

Consumidores das waves seguintes leem via `Settings::get('notificacoes....', config('sile.notificacoes....'))`. Os parâmetros `json` (`mapa_canais`, `escalonamento.tratamento`) voltam como ARRAY tanto pelo `typedValue()` quanto pelo espelho de config — o dispatcher (11-04) sempre recebe array, nunca string. As constantes técnicas do WhatsApp (`timeout`/`tries`/`backoff_ms`) NÃO têm parâmetro no catálogo: leia-as direto de `config('sile.integrations.whatsapp....')` (11-03).

## Pontos de leitura por plano consumidor
- **11-03 (WhatsApp gateway):** `integrations.whatsapp.base_url` + `.token` (sensitive) + constantes técnicas `config('sile.integrations.whatsapp.{timeout,tries,backoff_ms}')`; toggle `features.notificacao_whatsapp`.
- **11-04 (dispatcher/canais):** `features.notificacao_email/_in_app/_whatsapp` + `notificacoes.mapa_canais` (intersecção toggles × mapa por tipo).
- **11-05/06 (templates de pendência):** `notificacoes.pendencia.assunto` + `.corpo` (placeholders {protocolo}/{pendencia}).
- **11-07 (vencimento/escalonamento):** `notificacoes.vencimento.antecedencia_dias` + `notificacoes.escalonamento.tratamento` + `.gestor_role`.

## Task Commits

1. **Task 2 (RED): seeder-tests da comunicação (67→78, grupo notificacoes, novo método)** - `50b20ca` (test)
2. **Task 1 (GREEN): catálogo + fallback config (11 parâmetros)** - `955fafa` (feat)

_TDD estrito: o commit `test` (RED, 5 falhas confirmadas pelo motivo certo) precede o commit `feat` (GREEN). Dois commits atômicos — um por concern — em vez de um por task na ordem do plano, para deixar a evidência RED→GREEN explícita no histórico._

## Files Created/Modified
- `database/seeders/ParameterSeeder.php` - 11 parâmetros novos no catálogo (67→78); `token` com `sensitive => true`, `base_url` com `requires_connection_test => true`
- `config/sile.php` - toggles em `features`, bloco novo `notificacoes` (negócio) e `integrations.whatsapp` (base_url/token + constantes técnicas)
- `tests/Feature/Seeders/ParameterSeederTest.php` - contagem 78, grupo `notificacoes` na lista ordenada, `test_seeder_registra_parametros_de_notificacoes` (11 parâmetros, typedValue dos json)
- `tests/Feature/Seeders/DatabaseSeederTest.php` - contagem de parâmetros 67→78 (2 asserções); permissões (24) e demais contagens inalteradas

## Decisions Made
- **WhatsApp OFF por default:** `features.notificacao_whatsapp = 0` — provedor real bloqueado até a Fase 13; o sistema degrada de forma comunicada e nunca finge envio.
- **Destinatário do escalonamento parametrizável:** `notificacoes.escalonamento.gestor_role = 'gestor'` — não há "gestor do setor" no schema (roteamento ao setor é pendência SEDUR); default honesto e ajustável sem deploy.
- **Tratamento do escalonamento só notifica:** default `amarelo→notificar_analista`, `vencido→notificar_gestor` — sem decisão automática (HU-147).
- **Templates de pendência pt-BR com placeholders:** `{protocolo}` e `{pendencia}` — texto oficial (identidade/base legal) é pendência SEDUR; default honesto agora.
- **Constantes técnicas só no config:** `integrations.whatsapp.timeout/tries/backoff_ms` não entram no catálogo — não são valores de negócio (precedente 02-02).
- **Sem permissão nova:** a central in-app é por dono e o histórico (HU-096) reusa `consultar-solicitacoes` — permissões seguem 24.

## Deviations from Plan
None - plan executed exactly as written.

## Issues Encountered
Wave paralela 11-01 (schema de `communications`) cria arquivos não commitados no working tree durante a execução. Apenas os 4 arquivos do escopo deste plano foram staged (commits atômicos, nunca `git add -A`); os arquivos do 11-01 (migration/model/enums/factory/test de `Communication`) NÃO foram tocados nem commitados. O `vendor/bin/pint --dirty` reformatou `tests/Feature/Comunicacao/CommunicationModelTest.php` (do 11-01) — mudança cosmética de imports, deixada para o 11-01 commitar. O `STATE.md` NÃO foi alterado por este plano (consolidação a cargo do orquestrador da fase).

## User Setup Required
None - no external service configuration required.

A credencial real do WhatsApp (`integrations.whatsapp.base_url` + `.token`) e o provedor (API comercial) são pendência externa: a feature fica explicitamente bloqueada (toggle off) até a Fase 13, sem adaptador falso.

## Next Phase Readiness
- Catálogo e fallback prontos: as waves seguintes leem parâmetros via `Settings::get('notificacoes....', config('sile.notificacoes....'))` e os toggles via `config('sile.features.notificacao_*')` sem acoplar ao seeder.
- Insumo direto: 11-03 (gateway WhatsApp), 11-04 (dispatcher/canais), 11-05/06 (templates de pendência), 11-07 (vencimento/escalonamento).
- Pendências SEDUR a registrar: texto oficial dos templates de pendência; roteamento do escalonamento ao "gestor do setor" (hoje role `gestor`); credencial/provedor real de WhatsApp (Fase 13).

---
*Phase: 11-pendencias-e-comunicacao*
*Completed: 2026-06-15*

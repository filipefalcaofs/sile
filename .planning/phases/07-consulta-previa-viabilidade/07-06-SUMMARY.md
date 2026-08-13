---
phase: 07-consulta-previa-viabilidade
plan: 06
subsystem: backend
tags: [consulta-viabilidade, endpoint-publico, anonimo, throttle, feature-toggle, auditoria, rn-002, degradacao-honesta, anti-fachada, render-json, hu-054, hu-055, hu-056]

# Dependency graph
requires:
  - phase: 07-consulta-previa-viabilidade
    plan: 01
    provides: "RateLimiter nomeado consulta-viabilidade + toggle features.consulta_viabilidade (parametrizados, fallback em config)"
  - phase: 07-consulta-previa-viabilidade
    plan: 03
    provides: "Model imutável ViabilityQuery (prova de que a consulta anônima NÃO grava histórico pessoal)"
  - phase: 07-consulta-previa-viabilidade
    plan: 05
    provides: "ConsultaViabilidadeService::consultarPorEndereco/Cnae/Inscricao + ConsultaViabilidadeResult::toArray (contrato JSON) + auditoria de sucesso interna"
  - phase: 04-georreferenciamento
    provides: "AddressNotFoundException / GeocoderException (traduzidas em 404/503 honestos pelo controller)"
provides:
  - "ConsultaViabilidadeController: página pública (index) + 3 endpoints JSON (endereco/cnae/inscricao) delegando ao service"
  - "3 FormRequests públicos (authorize true): ConsultaViabilidadeEnderecoRequest/CnaeRequest/InscricaoRequest"
  - "Rotas públicas /portal/viabilidade (page sem throttle + 3 endpoints com throttle:consulta-viabilidade), FORA de auth:web"
  - "Guarda do toggle (422 comunicado + auditado) e tradução das exceções do geocoder (404 não localizado / 503 indisponível) — sem resultado falso"
  - "shouldRenderJsonWhen estendido para portal/viabilidade/* quando expectsJson (render JSON do portal, padrão 03-02)"
affects: [07-07-historico (mesmos endpoints persistem quando autenticado), 07-08-ui-consulta (consome os 3 endpoints)]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Endpoint público anônimo: rota fora de auth:web, throttle parametrizado no grupo, guarda de toggle no controller, auditoria RN-002 com causer null (espelha CnpjLookupController + TerritoryController)"
    - "Degradação honesta por código HTTP: endereço não localizado = 404, serviço indisponível = 503, toggle off = 422 (antes de executar); inscrição indisponível = 200 com aviso (a consulta funcionou, só não resolveu o ponto — nunca inventa coordenada)"
    - "Controller fino: só orquestra (delega ao service) e comunica; a auditoria de sucesso é interna ao ConsultaViabilidadeService"

key-files:
  created:
    - app/Http/Controllers/Portal/ConsultaViabilidadeController.php
    - app/Http/Requests/Portal/ConsultaViabilidadeEnderecoRequest.php
    - app/Http/Requests/Portal/ConsultaViabilidadeCnaeRequest.php
    - app/Http/Requests/Portal/ConsultaViabilidadeInscricaoRequest.php
    - tests/Feature/Viabilidade/ConsultaViabilidadePublicaTest.php
  modified:
    - routes/portal.php
    - bootstrap/app.php

key-decisions:
  - "Rotas /portal/viabilidade PÚBLICAS (fora de auth:web), no estilo do grupo guest do GOV.BR; página sem throttle, endpoints JSON com throttle:consulta-viabilidade"
  - "Degradação por inscrição é comunicada via avisos no resultado (HTTP 200), não por erro HTTP: o service já captura PropertyRegistryUnavailableException e degrada para a via CNAE — o controller não trata exceção de inscrição (nunca inventa ponto)"
  - "Auditoria de sucesso permanece no service (causer null quando anônimo); o controller só audita o bloqueio por toggle (event 'viabilidade'/'consulta', result 'bloqueado', motivo 'toggle-desativado')"
  - "Asserção do componente Inertia da página adiada para 07-08 (assertInertia exige o .tsx em disco); o teste prova que a página é pública via assertOk + não-redirect"

requirements-completed: [HU-054, HU-055, HU-056]

# Metrics
duration: ~12 min
completed: 2026-06-14
---

# Phase 7 Plan 06: Controller Público + Endpoints da Consulta de Viabilidade Summary

**A consulta prévia de viabilidade ganhou sua porta HTTP PÚBLICA: a página `portal.viabilidade.index` e os 3 endpoints JSON anônimos (endereço HU-054, CNAE HU-056, inscrição HU-055), todos sob `/portal/viabilidade` fora de `auth:web`, com throttle parametrizado (`throttle:consulta-viabilidade`, 07-01), guarda do toggle `features.consulta_viabilidade` (422 comunicado + auditado antes de executar) e auditoria RN-002 (causer null quando anônimo). O controller é fino: delega ao `ConsultaViabilidadeService` (07-05) e comunica a degradação honesta — endereço não localizado vira 404, serviço de geocodificação indisponível vira 503, e a inscrição indisponível degrada para a via CNAE com aviso (HTTP 200, sem ponto inventado). Exceções renderizam JSON (não redirect) via `shouldRenderJsonWhen` estendido para `portal/viabilidade/*`. Provado por 14 feature tests com motores REAIS e fakes de Geocoder/SpatialRepository.**

## Performance

- **Duration:** ~12 min (commits 05:42 → 05:45 -03)
- **Completed:** 2026-06-14
- **Tasks:** 3 (todas em TDD estrito RED → GREEN → pint)
- **Files:** 7 (5 criados, 2 modificados)

## Contrato dos endpoints (insumo direto de 07-07 e 07-08)

Todos sob o prefixo/nome `portal.` — rotas PÚBLICAS (cidadão anônimo). A página não tem throttle; os 3 endpoints estão no grupo `throttle:consulta-viabilidade`.

| Método | URI | Nome | Body | Sucesso |
|---|---|---|---|---|
| GET | `/portal/viabilidade` | `portal.viabilidade.index` | — | 200 (Inertia `portal/viabilidade/consulta`, prop `consultaEnabled`) |
| POST | `/portal/viabilidade/endereco` | `portal.viabilidade.endereco` | `{ endereco: string(3..255), cnae: string(≤14), area?: number(0..9999999) }` | 200 `ConsultaViabilidadeResult::toArray()` |
| POST | `/portal/viabilidade/cnae` | `portal.viabilidade.cnae` | `{ cnae: string(≤14), area?: number }` | 200 `toArray()` (sem território; aviso "não avalia o local") |
| POST | `/portal/viabilidade/inscricao` | `portal.viabilidade.inscricao` | `{ inscricao: string(≤60), cnae: string(≤14), area?: number }` | 200 `toArray()` (degradado p/ via CNAE + aviso enquanto base pendente SEDUR) |

### Códigos HTTP por caminho (honestos, sem fachada)

| Caminho | Código | Corpo |
|---|---|---|
| Sucesso (qualquer entrada) | **200** | `ConsultaViabilidadeResult::toArray()` (entrada, geocode, territorio, enquadramento, risco, restricoes, veredito_locacional, fundamentacao, avisos, versoes) |
| Endereço não localizado (`AddressNotFoundException`) | **404** | `{ "message": "Endereço não localizado. Revise o endereço ou posicione a consulta por CNAE." }` |
| Geocodificação indisponível (`GeocoderException`) | **503** | `{ "message": "Serviço de geocodificação indisponível no momento. Tente novamente em instantes." }` |
| Toggle `features.consulta_viabilidade` off | **422** | `{ "message": "A consulta de viabilidade está temporariamente desativada. Tente novamente mais tarde." }` (auditado `bloqueado`, ANTES de executar) |
| Validação do FormRequest | **422** | `{ "message", "errors": { ... } }` |
| Throttle excedido | **429** | resposta padrão do `ThrottleRequests` |

**Decisão central de degradação (HU-055):** a inscrição imobiliária indisponível **NÃO** é erro HTTP. O `ConsultaViabilidadeService` captura `PropertyRegistryUnavailableException` e degrada para a análise por CNAE + área; o controller devolve **200** com `avisos[0] = "Resolução por inscrição imobiliária indisponível (base de lotes pendente SEDUR)…"`, `geocode = null` e `territorio = null` — a consulta funcionou, apenas não resolveu o ponto, e **nenhuma coordenada é inventada**.

## Auditoria (RN-002)

- **Sucesso:** auditado **dentro do service** (07-05) — `log_name 'viabilidade'`, `event 'consulta'`, `result 'sucesso'`, com tipo/cnae/veredito/avisos/versões. `causer` **null** quando anônimo (resolvido no `AuditService`); origem/IP enriquecidos pela `RecordActivityAction`.
- **Bloqueio por toggle:** auditado **no controller** (`guardToggle`) — mesmo `log_name`/`event`, `result 'bloqueado'`, `properties { motivo: 'toggle-desativado' }`, antes de qualquer orquestração.
- **Consulta anônima não gera histórico pessoal:** `ViabilityQuery::count() === 0` após uma consulta anônima (a persistência só-quando-autenticado é do 07-07) — porém FOI auditada.

## Render JSON do portal (padrão 03-02)

`bootstrap/app.php` → `shouldRenderJsonWhen` agora inclui `($request->is('portal/viabilidade/*') && $request->expectsJson())`, ao lado de `portal/empresas/consultar-cnpj` e `gestao/territorio/*`. Sem isso, os erros (404/503/422) virariam redirect 302 no portal. A página (`portal/viabilidade`, sem barra final) não é coberta pelo glob — ela renderiza HTML/Inertia normalmente.

## Task Commits

1. **Task 1 (TDD): página + endereço + toggle + throttle + render JSON + auditoria** — `ed35bae` (feat, HU-054)
2. **Task 2 (TDD): endpoint CNAE** — `f521eb7` (feat, HU-056)
3. **Task 3 (TDD): endpoint inscrição (degradado) + throttle + anônima sem histórico** — `ff316db` (feat, HU-055)

**Plan metadata:** `docs(07-06)` (este SUMMARY).

_Cada task em TDD estrito: testes primeiro (RED verificado pelo motivo certo — rota inexistente → 404), implementação mínima (GREEN), `pint` limpo._

## Files Created/Modified

- `app/Http/Controllers/Portal/ConsultaViabilidadeController.php` — index (página) + endereco/cnae/inscricao (delegam ao service) + `guardToggle` privado.
- `app/Http/Requests/Portal/ConsultaViabilidadeEnderecoRequest.php` — endereco/cnae/area, `prepareForValidation` (trim do endereço), mensagens pt-BR.
- `app/Http/Requests/Portal/ConsultaViabilidadeCnaeRequest.php` — cnae/area.
- `app/Http/Requests/Portal/ConsultaViabilidadeInscricaoRequest.php` — inscricao/cnae/area.
- `routes/portal.php` — grupo público `/portal/viabilidade` (página + 3 endpoints throttled).
- `bootstrap/app.php` — `shouldRenderJsonWhen` estendido para `portal/viabilidade/*`.
- `tests/Feature/Viabilidade/ConsultaViabilidadePublicaTest.php` — 14 feature tests (HU-054/055/056, toggle, throttle, auditoria anônima, validação, erros honestos).

## Decisions Made

- **Rotas públicas fora de `auth:web`:** declaradas no estilo do grupo guest do GOV.BR; nenhum binding `{}` → sem risco de colisão com o histórico autenticado (07-07, rota literal `viabilidade/historico`).
- **Degradação por inscrição via `avisos` (200), não erro HTTP:** alinhado ao service (07-05) que já captura a indisponibilidade — o controller não duplica o try/catch da inscrição (anti-fachada: nunca inventa ponto, mas também não trata como erro o que "funcionou").
- **Controller fino, auditoria de sucesso no service:** evita auditoria dupla; o controller só registra o bloqueio por toggle (caminho que não chega ao service), espelhando o `CnpjLookupController`.
- **Asserção do componente Inertia adiada para 07-08:** `assertInertia()->component()` exige o `.tsx` em disco (que nasce no 07-08); o teste da página prova publicidade via `assertOk()` + não-redirect, conforme orientação do plano.

## Deviations from Plan

Sem bug, correção crítica, bloqueio ou mudança arquitetural. Enriquecimentos de teste além dos casos nomeados no plano (mesmos `files_modified`, sem scope creep):

- **[Aditivo] `test_servico_de_geocodificacao_indisponivel_retorna_mensagem_honesta`** — cobre explicitamente o caminho 503 (`GeocoderException`) do endereço, além do 404 pedido pelo plano.
- **[Aditivo] testes de validação (422) por endpoint** — `test_endereco_invalido…`, `test_cnae_invalido…`, `test_inscricao_invalida…` provam que o FormRequest rejeita antes de orquestrar.
- **[Aditivo] `test_inscricao_respeita_toggle`** — paridade do toggle nas 3 vias (o plano nomeou endereço e CNAE).
- **[Variação de asserção da página]** o `test_pagina_de_consulta_e_publica` usa `assertOk()` + `assertFalse(isRedirect())` em vez de `assertInertia()->component()`, porque o componente da UI (07-08) ainda não existe em disco — exatamente como o plano orientou ("por ora asserir status 200 e que NÃO redireciona para login").

**Total:** 0 correções; 5 testes de enriquecimento + 1 variação de asserção (orientada pelo plano). 14 testes no total (o plano nomeou 7 casos).

## Verification

- `php artisan test --compact --filter=ConsultaViabilidadePublicaTest` → **14/14 verde** (58 asserções).
- `php artisan test --compact --exclude-group postgis` → **639/639 verde** (3227 asserções) = 625 baseline (07-05) + 14 novos, **zero regressão**.
- `vendor/bin/pint --dirty --format agent` → passed (a cada task).
- `php artisan route:list --path=portal/viabilidade -v` → 4 rotas: `portal.viabilidade.index` (GET, sem throttle) + `.endereco`/`.cnae`/`.inscricao` (POST, cada uma com `⇂ throttle:consulta-viabilidade`).
- **Greps de aceitação:** `class ConsultaViabilidadeController` (1), `consultarPorEndereco`/`consultarPorCnae`/`consultarPorInscricao` no controller (1 cada), `viabilidade.endereco`/`.cnae`/`.inscricao` em `routes/portal.php`, `throttle:consulta-viabilidade` (1), `portal/viabilidade` em `bootstrap/app.php` (1).
- **Evidência anti-fachada:** endereço não localizado → 404 (`assertJsonMissingPath('veredito_locacional')`); toggle off → 422 com geocoder que falharia o teste se chamado (bloqueio ANTES de executar); inscrição indisponível → 200 com aviso + geocode/territorio null (nenhum ponto inventado); throttle 2/min → 3ª consulta 429; anônima → `ViabilityQuery::count() === 0` mas auditada.

## Next Phase Readiness

- **07-07 (histórico):** os mesmos 3 endpoints persistem `ConsultaViabilidadeResult::toArray()`/`->versoes()` em `viability_queries` **quando autenticado** (gravação só com `auth()->check()`; a rota autenticada `viabilidade/historico` não colide com o grupo público). A consulta anônima continua só auditada.
- **07-08 (UI):** consome os 3 endpoints JSON (useHttp/fetch) e a prop `consultaEnabled` da página; deve exibir `veredito_locacional` (pendente com motivo, sem mostrar Permitido/Não permitido sem zona), `avisos` (degradações honestas) e tratar 404/503/422/429 com mensagens. Ao criar `resources/js/pages/portal/viabilidade/consulta.tsx`, reativar a asserção `assertInertia()->component('portal/viabilidade/consulta')`.
- **Bloqueios herdados (não introduzidos aqui):** veredito permitido/não permitido depende da base de zona (SIGIS/CA 2000, Fase 13); resolução por inscrição depende da base de lotes (contrato 07-02, Fase 13 troca só o binding). Ambos degradam honestamente via `avisos` + veredito `pendente`.

---
*Phase: 07-consulta-previa-viabilidade*
*Completed: 2026-06-14*

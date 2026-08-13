---
phase: 03-cadastro-empresarial
plan: 02
subsystem: integrations
tags: [cnpj, http-client, cache, audit, feature-toggle, laravel, brasilapi]

requires:
  - phase: 03-cadastro-empresarial
    plan: 01
    provides: Rule ValidCnpj, parâmetros features.cnpj_lookup + integrations.cnpj_lookup.base_url, config/sile.php (timeout/retries/cache_ttl)
  - phase: 01-fundacao
    provides: Settings (banco+cache+fallback), AuditService.log/result, ResolveRepresentation, portal auth+verified+lgpd.accepted
provides:
  - Contrato App\Services\Cnpj\CnpjLookup (lookup(string): CnpjData) — binding trocável na Fase 13
  - DTO readonly CnpjData (fromBrasilApi / toArray snake_case) — shape do JSON do endpoint e do formulário React
  - Provider real BrasilApiCnpjLookup com base_url parametrizada, cache 24h só de sucesso
  - Exceções tipadas CnpjNotFoundException (404) e CnpjLookupException (indisponibilidade)
  - Endpoint POST /portal/empresas/consultar-cnpj (portal.empresas.consultar-cnpj) auditado e com toggle
affects: [03-06-portal-telas (cadastrar.tsx — botão Buscar CNPJ via useHttp), 13-integracoes (HU-105 troca o binding)]

tech-stack:
  added: []
  patterns:
    - "Provider HTTP atrás de contrato (interface + DTO + binding) — troca de fonte por parâmetro ou binding sem tocar call sites"
    - "Cache::remember só de sucesso: exceção dentro do closure impede gravação (falha nunca cacheada)"
    - "Auditoria em TODAS as saídas (sucesso/falha/bloqueio) via AuditService com result correspondente"
    - "shouldRenderJsonWhen estendido por rota para endpoints JSON do portal (401/422/404 em JSON, não redirect)"

key-files:
  created:
    - app/Services/Cnpj/CnpjLookup.php
    - app/Services/Cnpj/CnpjData.php
    - app/Services/Cnpj/BrasilApiCnpjLookup.php
    - app/Services/Cnpj/CnpjLookupException.php
    - app/Services/Cnpj/CnpjNotFoundException.php
    - app/Http/Requests/Portal/LookupCnpjRequest.php
    - app/Http/Controllers/Portal/CnpjLookupController.php
    - tests/Fixtures/cnpj/brasilapi-banco-do-brasil.json
    - tests/Feature/Companies/CnpjLookupTest.php
  modified:
    - app/Providers/AppServiceProvider.php
    - routes/portal.php
    - bootstrap/app.php

key-decisions:
  - "ConnectionException da falha de conexão é capturada no provider e relançada como CnpjLookupException — o contrato expõe só exceções do namespace Cnpj (o controller ainda captura ConnectionException por segurança)"
  - "shouldRenderJsonWhen (bootstrap/app.php) estendido para portal/empresas/consultar-cnpj quando expectsJson() — sem essa extensão o portal redirecionava (302) em vez de 401/422/404 JSON"
  - "Teste de não-cache de falha usa Http::sequence()->pushFailedConnection() x3 (cobre retries=2 → 3 tentativas) seguido de sucesso — Http::fake() chamado duas vezes MERGE stubs e o primeiro vence, então não serve para sobrescrever"

requirements-completed: [HU-021]

duration: 18min
completed: 2026-06-12
---

# Phase 3 Plan 02: Consulta de CNPJ (HU-021) Summary

**HU-021 de ponta a ponta no backend: contrato `CnpjLookup` + DTO `CnpjData` + provider REAL `BrasilApiCnpjLookup` (dados abertos RFB) com URL administrável por parâmetro, cache 24h só de sucesso, toggle `features.cnpj_lookup` e endpoint JSON `POST /portal/empresas/consultar-cnpj` auditado em sucesso, falha e bloqueio.**

## Performance

- **Duration:** ~18 min
- **Completed:** 2026-06-12
- **Tasks:** 2
- **Files modified:** 12 (9 criados, 3 modificados)

## Accomplishments
- Consulta real de CNPJ atrás de contrato: `app(CnpjLookup::class)->lookup($cnpj)` resolve para `BrasilApiCnpjLookup`, que lê `integrations.cnpj_lookup.base_url` via `Settings` (banco→cache→config) — alternar BrasilAPI ↔ minhareceita é mudança de parâmetro, sem deploy (provado por `test_lookup_usa_url_parametrizada`).
- Cache de 24h apenas de SUCESSO: a exceção dentro do `Cache::remember` impede a gravação; `test_lookup_cacheia_somente_sucesso` confirma 1 único request em consulta repetida e `test_falha_de_conexao_nao_e_cacheada` confirma que após uma indisponibilidade a próxima chamada volta à rede.
- Exceções tipadas: `CnpjNotFoundException` (provider 404) e `CnpjLookupException` (5xx / falha de conexão) — expressivas nos testes e no controller.
- Endpoint `POST /portal/empresas/consultar-cnpj` auditado em todas as saídas (`result` sucesso/falha/bloqueado), com toggle desligado bloqueando ANTES de qualquer request (`Http::assertNothingSent`) e mensagem pt-BR de degradação.
- CNPJ inválido rejeitado por `ValidCnpj` sem efeito colateral (422, sem request); visitante recebe 401; provider host registrado em `properties.provider`.

## Shape exato do JSON do endpoint (CnpjData::toArray)

Em sucesso (200), o endpoint retorna o DTO serializado em snake_case — contrato direto do formulário React (03-06):

```json
{
  "cnpj": "00000000000191",
  "legal_name": "BANCO DO BRASIL SA",
  "trade_name": "DIRECAO GERAL",
  "legal_nature_code": "2038",
  "legal_nature": "Sociedade de Economia Mista",
  "size_code": "05",
  "size": "DEMAIS",
  "primary_cnae_code": "6422100",
  "secondary_cnae_codes": ["6499999"],
  "street": "SAUN QUADRA 5 BLOCO B TORRE I, II, III",
  "number": "SN",
  "complement": null,
  "neighborhood": "ASA NORTE",
  "city": "BRASILIA",
  "state": "DF",
  "zip_code": "70040912",
  "phone": "6134939002",
  "email": null,
  "registration_status": "ATIVA"
}
```

Normalizações no `fromBrasilApi`: `size_code` com `str_pad(.., 2, '0', STR_PAD_LEFT)` (5 → "05"); `zip_code`/`phone` apenas dígitos; `secondary_cnae_codes` como `array<string>` filtrando códigos vazios/zero; `trade_name`/`complement`/`email` viram `null` quando vazios.

## Exceções tipadas e saídas HTTP

| Situação | Exceção do service | Resposta do endpoint | Auditoria (result) |
|---|---|---|---|
| Sucesso | — | 200 + `CnpjData::toArray()` | `consulta-cnpj` / sucesso (properties: cnpj, provider) |
| CNPJ inexistente | `CnpjNotFoundException` (404 do provider) | 404 `{message: "CNPJ não encontrado na base da Receita Federal."}` | `consulta-cnpj` / falha (motivo nao-encontrado) |
| Indisponibilidade (5xx / conexão) | `CnpjLookupException` (e `ConnectionException` no controller) | 503 `{message: "Serviço de consulta indisponível no momento. Preencha os dados manualmente."}` | `consulta-cnpj` / falha (motivo indisponibilidade) |
| Toggle desligado | — (bloqueado antes do service) | 422 `{message: "A consulta automática de CNPJ está desativada. Preencha os dados manualmente."}` | `consulta-cnpj` / bloqueado (motivo toggle-desativado) |
| CNPJ inválido | — (validação) | 422 erros de validação em `cnpj` | — (sem consulta) |
| Visitante | — | 401 | — |

## Como trocar o provider por parâmetro (evidência)

`BrasilApiCnpjLookup` lê a base via `Settings::get('integrations.cnpj_lookup.base_url', config('sile.integrations.cnpj_lookup.base_url'))`. O parâmetro é administrável (HU-014, criado no 03-01) e a gravação do `Parameter` invalida o cache do `Settings` (`booted` → `Cache::forget`). O teste `test_lookup_usa_url_parametrizada` grava `https://minhareceita.org` no parâmetro e assevera via `Http::assertSent` que o request saiu para `minhareceita.org` — troca de fonte sem deploy nem código novo. A substituição definitiva pelo convênio RFB (Fase 13 / HU-105) é uma troca de binding no `AppServiceProvider` (`bind(CnpjLookup::class, ...)`), sem tocar controller/telas.

## Task Commits

1. **Task 1: Contrato + DTO + provider BrasilAPI (TDD)** - `a6f2d00` (feat)
2. **Task 2: Endpoint com toggle e auditoria (TDD)** - `f177d68` (feat)

_TDD por tarefa: RED (testes do service / do endpoint primeiro, verificados falhando) → GREEN → REFACTOR (pint)._

## Files Created/Modified
- `app/Services/Cnpj/CnpjLookup.php` - Interface `lookup(string): CnpjData`
- `app/Services/Cnpj/CnpjData.php` - DTO readonly com `fromBrasilApi` e `toArray` snake_case
- `app/Services/Cnpj/BrasilApiCnpjLookup.php` - Provider real (base_url parametrizada, retry/timeout, cache 24h só de sucesso)
- `app/Services/Cnpj/CnpjNotFoundException.php`, `CnpjLookupException.php` - Exceções tipadas pt-BR
- `app/Http/Requests/Portal/LookupCnpjRequest.php` - Normaliza CNPJ (alfanumérico) + ValidCnpj
- `app/Http/Controllers/Portal/CnpjLookupController.php` - Invokable auditado com toggle e degradação
- `app/Providers/AppServiceProvider.php` - `bind(CnpjLookup::class, BrasilApiCnpjLookup::class)`
- `routes/portal.php` - Rota literal `empresas/consultar-cnpj` no grupo lgpd.accepted + ResolveRepresentation
- `bootstrap/app.php` - `shouldRenderJsonWhen` estendido para o endpoint JSON do portal
- `tests/Fixtures/cnpj/brasilapi-banco-do-brasil.json` - Payload real BrasilAPI (Banco do Brasil)
- `tests/Feature/Companies/CnpjLookupTest.php` - 12 testes (5 do service + 7 do endpoint), herméticos

## Decisions Made
- **`shouldRenderJsonWhen` por rota:** o projeto restringia o render JSON de exceções a `api/*` (bootstrap/app.php), o que fazia o portal redirecionar (302) em vez de devolver 401/422/404 JSON. Estendi a condição para `portal/empresas/consultar-cnpj` quando `expectsJson()`, preservando o comportamento das demais rotas do portal.
- **Captura de `ConnectionException`:** o provider já a converte em `CnpjLookupException` (contrato limpo), mas o controller captura `CnpjLookupException|ConnectionException` por defesa — qualquer fuga de conexão vira 503 auditado.
- **Teste de não-cache de falha:** `Http::fake()` chamado duas vezes faz MERGE de stubs e o primeiro padrão vence; usei `Http::sequence()->pushFailedConnection()` (x3, cobrindo retries=2) seguido de sucesso na mesma URL para provar que a falha não foi cacheada.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] `shouldRenderJsonWhen` impedia respostas JSON no endpoint do portal**
- **Found during:** Task 2 (testes de visitante e CNPJ inválido)
- **Issue:** `bootstrap/app.php` só renderizava exceções como JSON para `api/*`. Sem isso, `ValidationException` virava redirect (erro "Call to a member function all() on array" no teste) e `AuthenticationException` virava 302 — quebrando os CAs que exigem 422/401 JSON.
- **Fix:** Estendido `shouldRenderJsonWhen` para incluir `portal/empresas/consultar-cnpj` quando `$request->expectsJson()`.
- **Files modified:** bootstrap/app.php
- **Verification:** CnpjLookupTest verde (12 testes); suítes Procuration + Companies verdes (36 testes) — sem regressão nas demais rotas do portal.
- **Committed in:** f177d68 (Task 2 commit)

**2. [Rule 1 - Bug] Sobrescrita de fake não funciona por merge de stubs**
- **Found during:** Task 1 (test_falha_de_conexao_nao_e_cacheada)
- **Issue:** O roteiro do plano refazia `Http::fake(['*' => success])` após `Http::fake(['*' => failedConnection()])`; o Laravel MERGE os stubs e o primeiro padrão (`*`) continua vencendo — a 2ª chamada nunca via o sucesso.
- **Fix:** Substituído por `Http::sequence()->pushFailedConnection()` (x3, cobrindo as 3 tentativas com retries=2) seguido de `push(fixture, 200)` na mesma URL — prova fiel de que a falha não foi cacheada.
- **Files modified:** tests/Feature/Companies/CnpjLookupTest.php
- **Verification:** test_falha_de_conexao_nao_e_cacheada verde.
- **Committed in:** a6f2d00 (Task 1 commit)

---

**Total deviations:** 2 auto-fixed (1 blocking, 1 bug). Sem scope creep — ambos necessários para os CAs do plano.

## Issues Encountered
- Nenhum além das duas deviations acima.

## User Setup Required
None - o provider público (BrasilAPI) não exige credenciais. A suíte é hermética (`Http::preventStrayRequests()`); nenhum request real sai em testes.

## Next Phase Readiness
- 03-06 (telas do portal): o endpoint `POST /portal/empresas/consultar-cnpj` e o shape snake_case do `toArray` são o contrato direto do botão "Buscar CNPJ" (`useHttp`). Toggle OFF responde 422 com mensagem de degradação — a UI deve desabilitar o botão e exibir o aviso.
- Fase 13 (HU-105): o provider oficial RFB substitui apenas o binding `CnpjLookup::class` no `AppServiceProvider`.

---
*Phase: 03-cadastro-empresarial*
*Completed: 2026-06-12*

## Self-Check: PASSED

- Todos os 9 arquivos-chave criados existem no disco.
- Os 2 commits de tarefa (`a6f2d00`, `f177d68`) existem no histórico.

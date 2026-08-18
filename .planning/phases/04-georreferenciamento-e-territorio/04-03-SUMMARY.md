---
phase: 04-georreferenciamento-e-territorio
plan: "04-03"
subsystem: api
tags: [geocoding, nominatim, osm, hu-029, territorio, contrato, cache, throttle, ratelimiter, auditoria, cross-guard, http-client]

# Dependency graph
requires:
  - phase: 04-02
    provides: features.geocoding, integrations.geocoding.base_url, seguranca.throttle.geocoding.por_minuto, bloco config sile.integrations.geocoding (user_agent/timeout/retries/backoff_ms/cache_ttl), permissão consultar-territorio
  - phase: 03.1-cnpj-lookup
    provides: padrão contrato+provider+cache (CnpjLookup/BrasilApiCnpjLookup), retry/timeout/backoff parametrizados no Http client, throttle parametrizado (RateLimiter nomeado), shouldRenderJsonWhen para endpoint JSON
  - phase: 01-identidade
    provides: AuditService.log() (RN-002), permissões spatie no guard web, 403 auditado em bootstrap/app.php
provides:
  - "contrato App\\Services\\Geo\\Geocoder (geocode(string): GeocodeResult) com binding trocável"
  - "NominatimGeocoder real: cache só de sucesso, retry/timeout/backoff parametrizados, User-Agent obrigatório"
  - "DTO GeocodeResult (latitude/longitude/display_name/confidence/address) + toArray snake_case — contrato JSON do mapa (04-07)"
  - "endpoint POST gestao/territorio/geocodificar (gestao.territorio.geocodificar) auditado, protegido por consultar-territorio + throttle:geocoding"
  - "RateLimiter nomeado geocoding (seguranca.throttle.geocoding.por_minuto)"
  - "PADRÃO CROSS-GUARD de FormRequest do território (authorize()=true; gate é o middleware permission:)"
affects:
  - "04-05 (TerritoryService consome a coordenada do geocoder)"
  - "04-06 (validação de localização: reusa o padrão cross-guard nos seus FormRequests)"
  - "04-07 (mapa/telas: consome o contrato JSON latitude/longitude/display_name/confidence/address)"
  - "04-08 (fechamento: evidência da chamada REAL ao Nominatim)"

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Contrato+provider+cache de integração externa (Geocoder espelha CnpjLookup): Fase 13 troca o binding por self-host/base SEDUR sem tocar call sites"
    - "Cache de sucesso via Cache::remember com sha1(address); exceção dentro do closure impede gravar falha (precedente [03-02])"
    - "PADRÃO CROSS-GUARD: FormRequest authorize()=true e o gate é o middleware permission: da rota (permissão no guard web resolve para usuário do guard gestao, sem 403 falso)"
    - "Endpoint JSON da gestão: shouldRenderJsonWhen estendido para gestao/territorio/* quando expectsJson()"

key-files:
  created:
    - app/Services/Geo/Geocoder.php
    - app/Services/Geo/GeocodeResult.php
    - app/Services/Geo/GeocoderException.php
    - app/Services/Geo/AddressNotFoundException.php
    - app/Services/Geo/NominatimGeocoder.php
    - app/Http/Controllers/Gestao/GeocodeController.php
    - app/Http/Requests/Gestao/GeocodeRequest.php
    - tests/Unit/Geo/NominatimGeocoderTest.php
    - tests/Feature/Geo/GeocodeEndpointTest.php
  modified:
    - app/Providers/AppServiceProvider.php
    - app/Providers/FortifyServiceProvider.php
    - routes/gestao.php
    - bootstrap/app.php

key-decisions:
  - "GeocodeResult guarda latitude/longitude NOMEADAS (Pitfall 3): o consumidor converte para [lng,lat] de GeoJSON/ST_MakePoint ou [lat,lng] do Leaflet"
  - "Corpo vazio/HTML de bloqueio/objeto sem [0] → AddressNotFoundException (404); failed()/ConnectionException → GeocoderException (503), conforme o plano"
  - "GeocodeRequest::authorize() retorna true — o gate é o middleware permission:consultar-territorio; usar $this->user() sem guard cairia no web (null) e daria 403 falso na sessão só-gestao"
  - "Throttle e degradação reusam 1:1 o padrão do consultar-cnpj (HU-021): mesma forma de auditoria, toggle antes do request e RateLimiter parametrizado"

patterns-established:
  - "Contrato de geocodificação atrás de binding (Geocoder/NominatimGeocoder) — espelho fiel de CnpjLookup/BrasilApiCnpjLookup"
  - "PADRÃO CROSS-GUARD dos FormRequests de território (para o 04-06 reusar)"

# Metrics
duration: 7 min
completed: 2026-06-13
---

# Phase 4 Plan 03: Geocodificação atrás de contrato Summary

**HU-029 entregue: contrato Geocoder + NominatimGeocoder real (cache de sucesso, retry/timeout/backoff parametrizados, User-Agent obrigatório) e endpoint POST gestao/territorio/geocodificar auditado em toda saída, protegido por consultar-territorio + throttle:geocoding, com toggle features.geocoding degradando antes de qualquer request — sem fachada.**

## Performance

- **Duration:** 7 min
- **Started:** 2026-06-13T22:05:02Z
- **Completed:** 2026-06-13T22:11:42Z
- **Tasks:** 2 (TDD)
- **Files modified:** 13 (9 criados, 4 modificados)

## Accomplishments

- Contrato `Geocoder` + provider real `NominatimGeocoder` espelhando `CnpjLookup`: `base_url` via `Settings::get` (banco→cache→config), cache de 24h só de sucesso (`Cache::remember` com `sha1(address)`), `retry/timeout/backoff` parametrizados e **User-Agent obrigatório** (política do Nominatim). Binding trocável no `AppServiceProvider` (Fase 13 → self-host/base SEDUR sem tocar call sites).
- DTO `GeocodeResult` com `toArray()` snake_case (`latitude`, `longitude`, `display_name`, `confidence`, `address`) — contrato JSON consumido pelo mapa (04-07). Latitude/longitude **nomeadas** documentando o Pitfall 3 de ordem de coordenadas.
- Endpoint `POST gestao/territorio/geocodificar` auditado em **toda** saída (sucesso/não-encontrado/indisponível/bloqueado) no log `territorio`/`geocodificacao`, protegido por `permission:consultar-territorio` + `throttle:geocoding` parametrizado; toggle `features.geocoding` desliga **antes** de qualquer request HTTP (422 comunicado).
- `RateLimiter::for('geocoding')` lendo `seguranca.throttle.geocoding.por_minuto`; `shouldRenderJsonWhen` estendido para `gestao/territorio/*`.
- Provado o **PADRÃO CROSS-GUARD**: analista no guard `gestao` com a permissão (que vive no guard `web`) recebe **200** — sem 403 falso.

## Task Commits

Cada task foi commitada atomicamente (ciclo TDD RED → GREEN → REFACTOR/pint consolidado num commit `feat` por task, conforme "commitar cada task atomicamente" e o precedente 04-02):

1. **Task 1: Contrato Geocoder + GeocodeResult + NominatimGeocoder (cache/throttle/retry)** - `827e86d` (feat)
2. **Task 2: RateLimiter geocoding + GeocodeController auditado + rota protegida** - `b04e96b` (feat)

**Plan metadata:** `docs(04-03)` (este SUMMARY)

## Contrato entregue (para fases consumidoras)

| Item | Valor |
|---|---|
| Contrato | `App\Services\Geo\Geocoder::geocode(string $address): GeocodeResult` |
| DTO `toArray()` (JSON do mapa 04-07) | `latitude` (float), `longitude` (float), `display_name` (string), `confidence` (?float, de `importance`), `address` (array) |
| Exceções | `AddressNotFoundException` (não localizado → 404), `GeocoderException` (indisponível → 503) |
| Endpoint | `POST /gestao/territorio/geocodificar` — name `gestao.territorio.geocodificar` |
| Entrada | `{ "address": string (required, min:3, max:255) }` |
| Middleware | `auth:gestao` › `permission:acessar-gestao` › `lgpd.accepted` › `permission:consultar-territorio` › `throttle:geocoding` |
| Auditoria | log_name `territorio`, event `geocodificacao`, result `sucesso`/`falha`/`bloqueado`, properties `endereco` + `provider` (host) ou `motivo` |
| Binding | `Geocoder::class → NominatimGeocoder::class` (AppServiceProvider) |
| Cache | `sile.geocoding.{sha1(address)}`, TTL `config('sile.integrations.geocoding.cache_ttl')` — só sucesso |

### Respostas do endpoint

| Situação | Status | Corpo |
|---|---|---|
| Sucesso | 200 | `GeocodeResult::toArray()` |
| Endereço não localizado | 404 | `{ "message": "Endereço não localizado. Ajuste o texto ou posicione o ponto no mapa." }` |
| Serviço indisponível | 503 | `{ "message": "Serviço de geocodificação indisponível. Posicione o ponto manualmente no mapa." }` |
| Toggle desligado | 422 | `{ "message": "A geocodificação está desativada. Informe a localização manualmente no mapa." }` |
| Sem permissão | 403 | auditado em `seguranca`/`acesso-negado` (bootstrap) |
| Excedeu o throttle | 429 | resposta padrão do `throttle:geocoding` |

## PADRÃO CROSS-GUARD (para o 04-06 reusar)

Os FormRequests de território (e quaisquer endpoints da gestão protegidos por permissão de domínio) seguem:

- **`authorize(): bool { return true; }`** — o gate **é o middleware `permission:<...>` da rota**, não o FormRequest. As permissões vivem no guard `web` (decisão travada `User::$guard_name = 'web'`), mas o usuário autenticado da gestão está no guard `gestao`. O middleware `permission:` do spatie resolve a permissão de forma guard-agnóstica (via o model do usuário), então o 200 acontece corretamente.
- **NÃO** chamar `$this->user()` sem argumento no `authorize()`: cairia no guard default `web`, retornando `null` numa sessão só-gestao → **403 falso**.
- Em testes: `actingAs($usuario, 'gestao')`. O caso CROSS-GUARD (analista com `consultar-territorio` no guard `gestao` → 200) está em `tests/Feature/Geo/GeocodeEndpointTest.php` e PROVA que `authorize()=true` + middleware `permission:` resolvem a permissão sem 403 falso. O RED foi confirmado falhando por **404 (rota inexistente)**, nunca por 403 falso.

## Files Created/Modified

- `app/Services/Geo/Geocoder.php` - contrato `geocode(string): GeocodeResult` (PHPDoc das exceções)
- `app/Services/Geo/GeocodeResult.php` - DTO `final readonly` + `fromNominatim()` + `toArray()` snake_case (Pitfall 3 documentado)
- `app/Services/Geo/GeocoderException.php` / `AddressNotFoundException.php` - exceções tipadas (espelham CnpjLookupException/CnpjNotFoundException)
- `app/Services/Geo/NominatimGeocoder.php` - provider real (cache só sucesso; `/search?format=jsonv2&addressdetails=1&countrycodes=br&limit=1`; User-Agent; ConnectionException/failed()→GeocoderException; vazio/HTML→AddressNotFoundException)
- `app/Providers/AppServiceProvider.php` - binding `Geocoder → NominatimGeocoder`
- `app/Providers/FortifyServiceProvider.php` - `RateLimiter::for('geocoding')` parametrizado
- `app/Http/Requests/Gestao/GeocodeRequest.php` - `authorize()=true` (cross-guard) + rules `address` required|string|min:3|max:255
- `app/Http/Controllers/Gestao/GeocodeController.php` - `__invoke`, toggle-aware, auditoria em toda saída
- `routes/gestao.php` - grupo `territorio` sob `permission:consultar-territorio`, rota com `throttle:geocoding`
- `bootstrap/app.php` - `shouldRenderJsonWhen` estendido para `gestao/territorio/*`
- `tests/Unit/Geo/NominatimGeocoderTest.php` - 11 testes (sucesso, não-encontrado, HTML de bloqueio, 5xx, ConnectionException, User-Agent, params oficiais, retry parametrizado, cache de sucesso, falha não cacheada)
- `tests/Feature/Geo/GeocodeEndpointTest.php` - 8 testes (visitante 401, sem-permissão 403 auditado, CROSS-GUARD 200 auditado, validação 422, não-encontrado 404, indisponível 503, toggle 422, throttle 429)

## Decisions Made

- **GeocodeResult com coordenadas nomeadas (Pitfall 3):** ponto único de conversão; o consumidor (PostGIS `[lng,lat]` vs Leaflet `[lat,lng]`) respeita a ordem do alvo. Evita o ponto cair no oceano.
- **Classificação das falhas conforme o plano:** corpo vazio (`[]`), HTML de bloqueio (não-array) e objeto de erro sem `[0]` → `AddressNotFoundException` (404); `failed()`/`ConnectionException` → `GeocoderException` (503). O HTML de bloqueio do Nominatim (User-Agent recusado) é tratado como "não localizado".
- **`authorize()=true` no FormRequest (cross-guard):** ver seção PADRÃO CROSS-GUARD. Decisão central do plano, documentada para reuso no 04-06.
- **Reuso 1:1 do padrão consultar-cnpj:** auditoria em toda saída, toggle antes de qualquer request HTTP, cache só de sucesso e throttle parametrizado — consistência com a Fase 3.1.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required. A geocodificação usa o Nominatim público por padrão (`integrations.geocoding.base_url`); para produção em volume, trocar por self-host via parâmetro (sem deploy).

## Next Phase Readiness

- Contrato `Geocoder` e endpoint prontos para o 04-05 (TerritoryService consome a coordenada) e o 04-07 (mapa consome o JSON `latitude/longitude/display_name/confidence/address`).
- PADRÃO CROSS-GUARD documentado e provado por teste para o 04-06 reusar nos seus FormRequests de território.
- **Evidência pendente (fechamento 04-08):** chamada REAL ao Nominatim em dev (endereço de Salvador → coordenada) registrada como evidência — aqui tudo é provado com `Http::fake` (regra "sem fachada": integração externa validada contra o serviço real no fechamento).
- Verificação escopada fresca: `php artisan test --compact tests/Unit/Geo/NominatimGeocoderTest.php tests/Feature/Geo/GeocodeEndpointTest.php` → **19/19 verde (52 asserções)**; `pint --dirty` limpo; `route:list --path=territorio` lista `gestao.territorio.geocodificar` com `throttle:geocoding`.
- Observação: wave 2 paralela ao 04-04 (import de camadas) — STATE.md é consolidado pelo orquestrador (não editado por este plano); nenhum arquivo do 04-04 foi tocado.

---
*Phase: 04-georreferenciamento-e-territorio*
*Completed: 2026-06-13*

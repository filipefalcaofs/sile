---
phase: 04-georreferenciamento-e-territorio
plan: "04-06"
subsystem: api
tags: [territorio, mapa, postgis, st_area, st_intersection, sobreposicao, hu-030, hu-036, hu-037, rn-004, cross-guard, auditoria, parametrizacao, sem-fachada]

# Dependency graph
requires:
  - phase: 04-05
    provides: "TerritoryService.identify(lat,lng,?date) + TerritoryResult (contrato snake_case por dimensao); SpatialRepository/FakeSpatialRepository; zona/lote como indisponivel pendente SEDUR"
  - phase: 04-03
    provides: "PADRAO CROSS-GUARD (FormRequest authorize()=true; gate no middleware permission:consultar-territorio); grupo de rotas territorio; shouldRenderJsonWhen para gestao/territorio/*; endpoint auditado modelo (GeocodeController)"
  - phase: 04-02
    provides: "parametro geo.validacao.sobreposicao_minima (catalogo + fallback config/sile.php); permissao consultar-territorio"
  - phase: 01-identidade
    provides: "AuditService.log (RN-002); permissoes spatie no guard web; 403 auditado em bootstrap/app.php"
provides:
  - "LocationValidationService.validate(polygonGeoJson,?date): sobreposicao ST_Area(ST_Intersection)/ST_Area com limiar parametrizado; lote pendente => indisponivel (sem fachada)"
  - "LocationValidationResult (DTO readonly) + toArray() snake_case (status/sobreposicao_percentual/limiar/motivo/alerta) — contrato JSON da validacao (04-07)"
  - "TerritoryController: index (pagina de consulta territorial) + identificar (TerritoryService) + validar-localizacao (auditado)"
  - "rotas gestao.territorio.index/identificar/validar-localizacao sob permission:consultar-territorio"
  - "IdentifyTerritoryRequest/ValidateLocationRequest (cross-guard) com validacao de lat/lng e GeoJSON Polygon"
affects: [04-07, 05-motor-de-enquadramento]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Sobreposicao topologica via PostGIS (ST_Area(ST_Intersection)/ST_Area) isolada no service com DB::selectOne — sem cálculo geométrico em PHP, sem novo binding (precedente Don't Hand-Roll)"
    - "Degradacao comunicada dirigida pelo DADO: lote pendente_fonte/inexistente => status indisponivel sem consulta espacial; quando a SEDUR entregar o lote vigente, a MESMA validate calcula (a carga muda, a logica nao)"
    - "decide(percent,limiar) puro isolado para teste unitario sem PostGIS; SQL espacial provado em @group postgis com fixture (teste hibrido sem fachada, precedente 04-05)"
    - "Reuso do PADRAO CROSS-GUARD do 04-03 nos FormRequests de territorio (authorize()=true; gate no middleware permission:)"

key-files:
  created:
    - app/Services/Geo/LocationValidationService.php
    - app/Services/Geo/LocationValidationResult.php
    - app/Http/Controllers/Gestao/TerritoryController.php
    - app/Http/Requests/Gestao/IdentifyTerritoryRequest.php
    - app/Http/Requests/Gestao/ValidateLocationRequest.php
    - tests/Unit/Geo/LocationValidationServiceTest.php
    - tests/Feature/Geo/PostgisLocationValidationTest.php
    - tests/Feature/Geo/TerritoryPageTest.php
  modified:
    - routes/gestao.php

key-decisions:
  - "LocationValidationService usa DB::selectOne direto (sem novo metodo no SpatialRepository): files_modified do 04-06 nao inclui o repositorio e o plano especifica DB; a SQL roda so no caminho com lote vigente (provado em @group postgis)"
  - "Bloqueio do lote pelo STATUS (pendente_fonte) ou ausencia da camada — espelha TerritoryService.isBlocked (04-05); RN-005 (divergencia por inscricao) bloqueada pelo mesmo motivo"
  - "validar-localizacao audita result 'sucesso' sempre (a validacao foi executada); o desfecho (indisponivel/validado/alerta_sobreposicao) e o alerta vao em properties (RN-004 — alerta registrado para o analista)"
  - "identificar nao re-audita no controller: a auditoria (territorio/identificacao) ja e feita dentro do TerritoryService (04-05); o controller so repassa o JSON"
  - "bootstrap/app.php NAO foi alterado: o shouldRenderJsonWhen do 04-03 ja cobre gestao/territorio/* (glob), entao identificar/validar-localizacao ja respondem JSON em erros sem mudanca"

patterns-established:
  - "Validacao de localizacao por sobreposicao com lote (HU-037 RN-004): infra pronta e testada para 'ligar' com a base oficial sem reescrita"
  - "Backend do mapa territorial (pagina + endpoints) como contrato de props/JSON para a UI Leaflet do 04-07"

# Metrics
duration: ~10 min
completed: 2026-06-13
---

# Fase 4 Plano 06: Backend do mapa territorial Summary

**Backend do mapa (HU-030/HU-036/HU-037): pagina de consulta territorial da gestao (lista de camadas com zona/lote explicitos como pendentes + limiar de sobreposicao), endpoint `identificar` (lat/lng -> TerritoryService) e endpoint `validar-localizacao` (LocationValidationService: sobreposicao poligono x lote com `ST_Area(ST_Intersection)/ST_Area` e limiar parametrizado `geo.validacao.sobreposicao_minima`). Como o lote e `pendente_fonte`, a validacao comunica "indisponivel — base de lotes pendente SEDUR" sem fabricar lote (sem fachada); a infraestrutura de sobreposicao entra pronta e provada contra PostGIS real com fixture, ligando sozinha quando a SEDUR entregar a base. Tudo auditado (RN-002) e protegido por `consultar-territorio` (PADRAO CROSS-GUARD do 04-03).**

## Performance

- **Duration:** ~10 min
- **Started:** 2026-06-13T22:53:00Z
- **Completed:** 2026-06-13T23:03:31Z
- **Tasks:** 2 (ambas TDD)
- **Files modified/created:** 9 (8 criados, 1 modificado)

## Accomplishments

- `LocationValidationService.validate(polygon, ?date)` calcula a maior sobreposicao do poligono informado com as feicoes do lote vigente via SQL espacial REAL (`ST_Area(ST_Intersection(informado, lote)) / NULLIF(ST_Area(informado),0)`) e alerta abaixo do limiar administravel `geo.validacao.sobreposicao_minima` (HU-014). Com o lote `pendente_fonte` (estado real), comunica `indisponivel` com motivo "base de lotes pendente SEDUR" SEM consulta espacial e SEM lote fabricado (sem fachada). RN-005 (divergencia por inscricao) bloqueada pelo mesmo motivo.
- `LocationValidationResult` (DTO readonly) com `toArray()` snake_case (`status`, `sobreposicao_percentual`, `limiar`, `motivo`, `alerta`) — contrato JSON consumido pela UI (04-07).
- `TerritoryController`: `index()` renderiza a pagina com a lista de camadas vigentes (zona/lote explicitos como `pendente_fonte` — HU-036), o limiar e o toggle de geocodificacao; `identify()` repassa o `TerritoryService` (auditado nele); `validateLocation()` chama o service e audita explicitamente o resultado/alerta (RN-004).
- Rotas `gestao.territorio.index/identificar/validar-localizacao` adicionadas ao grupo `territorio` existente (sob `permission:consultar-territorio`); `IdentifyTerritoryRequest`/`ValidateLocationRequest` reusam o PADRAO CROSS-GUARD (authorize()=true; gate no middleware).
- Sobreposicao provada contra PostGIS REAL com fixture de lote vigente: poligono identico -> ~100% (sem alerta, validado); poligono com ~1/4 de interseccao -> abaixo do limiar (alerta_sobreposicao). A infra "liga" com a base oficial sem reescrita.

## Task Commits

Cada task foi commitada atomicamente (ciclo TDD RED -> GREEN -> REFACTOR/pint consolidado num commit `feat` por task, precedente 04-03/04-05):

1. **Task 1: LocationValidationService + LocationValidationResult (sobreposicao + limiar; lote pendente)** - `125b0da` (feat) — RED (unit 5 erros classe inexistente) -> GREEN (unit 5/5 SQLite + @group postgis 2/2) -> pint.
2. **Task 2: TerritoryController (index/identificar/validar) auditado + Requests + rotas** - `1d702d5` (feat) — RED (7 falhas por 404 rota inexistente) -> GREEN (7/7 feature) -> pint.

**Plan metadata:** este SUMMARY — `docs(04-06): completa backend do mapa territorial`.

_(STATE.md NAO foi editado — consolidacao e do orquestrador, conforme o objetivo do plano.)_

## Contrato entregue (para 04-07 / Fase 5)

### Rotas (grupo `territorio`, middleware `permission:consultar-territorio`)

| Metodo | URI | Name | Acao |
|---|---|---|---|
| GET | `/gestao/territorio` | `gestao.territorio.index` | Pagina de consulta territorial (Inertia `gestao/territorio/index`) |
| POST | `/gestao/territorio/identificar` | `gestao.territorio.identificar` | Identifica bairro/via/zona/lote/restricoes por ponto |
| POST | `/gestao/territorio/validar-localizacao` | `gestao.territorio.validar-localizacao` | Valida sobreposicao do poligono com o lote |
| POST | `/gestao/territorio/geocodificar` | `gestao.territorio.geocodificar` | (04-03) endereco -> coordenada |

Middleware completo das rotas novas: `auth:gestao` › `permission:acessar-gestao` › `lgpd.accepted` › `permission:consultar-territorio`.

### Props da pagina (`index`)

```
camadas: Array<{ type, type_label, status, status_label, version, feature_count }>  // camadas vigentes (valid_to nulo), inclui zona/lote como status 'pendente_fonte'
sobreposicaoMinima: int            // geo.validacao.sobreposicao_minima (default 50)
geocodingEnabled: bool             // toggle features.geocoding
mapa: { centro: { lat, lng }, zoom }  // centro Salvador (-12.97,-38.5), zoom 13
```

### JSON do `identificar` (entrada `{ lat: number(-90..90), lng: number(-180..180) }`)

Retorna `TerritoryResult::toArray()` (contrato do 04-05): chaves `bairro`, `via` (+`distancia_m`), `zona`, `lote`, `restricoes` (`itens[]`), cada uma com `status` ∈ {`identificado`,`nao_encontrado`,`indisponivel`}, `nome`, `propriedades`, `motivo`, `versao_camada`. Hoje `zona`/`lote` => `indisponivel` (pendente SEDUR).

### JSON do `validar-localizacao` (entrada `{ polygon: GeoJSON Polygon (type+coordinates) }`)

```
{
  "status": "validado" | "alerta_sobreposicao" | "indisponivel",
  "sobreposicao_percentual": float | null,   // null quando indisponivel
  "limiar": int,                              // geo.validacao.sobreposicao_minima
  "motivo": string | null,                    // preenchido quando indisponivel
  "alerta": bool                              // true quando sobreposicao < limiar
}
```

### Respostas (codigos)

| Situacao | Status |
|---|---|
| Sucesso (identificar/validar) | 200 + JSON do contrato |
| Validacao de entrada (lat/lng fora de faixa; polygon nao-Polygon) | 422 + erros |
| Visitante (sem sessao) | 401 |
| Sem `consultar-territorio` | 403 (auditado em `seguranca`/`acesso-negado`, bootstrap) |

### Auditoria (RN-002)

| Fluxo | log_name | event | result | properties |
|---|---|---|---|---|
| identificar | `territorio` | `identificacao` | `sucesso` | `lat`/`lng`/`versoes_por_camada`/`resumo_status` (gerado no TerritoryService — 04-05) |
| validar-localizacao | `territorio` | `validacao-localizacao` | `sucesso` | `resultado` (toArray) + `alerta` (RN-004 — alerta registrado) |

## Como o lote pendente e comunicado na validacao (sem fachada)

- `LocationValidationService::isBlocked()` trata o lote **inexistente** ou com status **`pendente_fonte`** como bloqueado: retorna `status='indisponivel'`, `sobreposicao_percentual=null`, `alerta=false` e `motivo='Base de lotes (inscricao imobiliaria) pendente SEDUR — validacao de sobreposicao nao disponivel'` — SEM consulta espacial e SEM lote fabricado.
- A logica e dirigida pelo DADO: quando a SEDUR/SEFAZ entregar o lote como camada **vigente com feicoes**, a MESMA `validate` passa a calcular a sobreposicao real (provado por `PostgisLocationValidationTest` com fixture vigente). A carga muda, a logica nao.

## Files Created/Modified

- `app/Services/Geo/LocationValidationService.php` - sobreposicao ST_Area(ST_Intersection)/ST_Area (DB::selectOne), limiar via Settings::get, lote pendente => indisponivel, `decide()` puro
- `app/Services/Geo/LocationValidationResult.php` - DTO `final readonly` + `toArray()` snake_case
- `app/Http/Controllers/Gestao/TerritoryController.php` - `index`/`identify`/`validateLocation` (validacao auditada)
- `app/Http/Requests/Gestao/IdentifyTerritoryRequest.php` - cross-guard; `lat` between -90..90, `lng` between -180..180
- `app/Http/Requests/Gestao/ValidateLocationRequest.php` - cross-guard; `polygon` array + `polygon.type` in:Polygon + `polygon.coordinates` array
- `routes/gestao.php` - 3 rotas novas no grupo `territorio` (index/identificar/validar-localizacao)
- `tests/Unit/Geo/LocationValidationServiceTest.php` - 5 testes (indisponivel sem consulta; sem camada; limiar do parametro; decide abaixo/acima)
- `tests/Feature/Geo/PostgisLocationValidationTest.php` - 2 testes @group postgis (sobreposicao ~100% sem alerta; ~25% com alerta)
- `tests/Feature/Geo/TerritoryPageTest.php` - 7 testes (403 auditado; pagina 200 com camadas/limiar; identificar JSON+auditoria; validacoes 422; validar indisponivel+auditoria; visitante 401)

## Decisions Made

- **DB::selectOne no service (sem novo metodo no SpatialRepository):** `files_modified` do 04-06 nao inclui o repositorio e o plano especifica `DB`; a SQL roda so no caminho com lote vigente. Mantem o repositorio do 04-05 intacto e evita editar arquivo fora do escopo.
- **Bloqueio do lote pelo STATUS (`pendente_fonte`) ou ausencia:** espelha `TerritoryService.isBlocked` (04-05) — honesto e generico; quando o lote virar vigente com feicoes, identifica/valida sem mudar codigo.
- **`validar-localizacao` audita `sucesso` sempre:** a validacao foi executada; o desfecho (incluindo `alerta`/`indisponivel`) vai em `properties` (RN-004 — alerta registrado para o analista).
- **`identificar` nao re-audita no controller:** a auditoria `territorio/identificacao` ja e feita no `TerritoryService` (04-05); o controller so repassa o JSON.
- **`bootstrap/app.php` inalterado:** o `shouldRenderJsonWhen` do 04-03 ja cobre `gestao/territorio/*` (glob) — identificar/validar ja respondem JSON em erros sem mudanca.

## Deviations from Plan

None - plan executed exactly as written. (O `bootstrap/app.php` consta em `files_modified` mas nao precisou de alteracao: o glob `gestao/territorio/*` do 04-03 ja cobre as rotas novas.)

## Issues Encountered

None. Container `sile-pgsql` de pe (porta 5433, healthy) e `sile_testing` com PostGIS durante toda a execucao.

## Evidencia fresca (sem fachada)

- **Verificacao escopada do plano:** `--exclude-group postgis tests/Unit/Geo/LocationValidationServiceTest.php tests/Feature/Geo/TerritoryPageTest.php` -> **12/12 (50 asserções)**; `--group postgis tests/Feature/Geo/PostgisLocationValidationTest.php` -> **2/2 (7 asserções)**.
- **SQL espacial REAL (@group postgis):** poligono identico ao lote -> ~100% (validado, sem alerta); poligono com ~1/4 de interseccao -> abaixo do limiar 50% (alerta_sobreposicao). Prova que `ST_Area(ST_Intersection)/ST_Area` funciona no PostGIS — pronto para a base oficial.
- **Limiar parametrizado:** unit prova que o limiar do resultado segue o `Parameter` administrado (70) em vez do fallback de config (50) — HU-014, efeito sem deploy.
- **Sem regressao:** grupo Geo SQLite **61/61 (198 asserções)**; grupo **@group postgis 13/13 (61 asserções)** (11 herdados + 2 novos); `pint --dirty` limpo; sem lints.
- **route:list:** `gestao.territorio.index` (GET), `identificar` (POST), `validar-localizacao` (POST) e `geocodificar` (POST) listadas.

## User Setup Required

None - no external service configuration required. A validacao de sobreposicao "liga" automaticamente quando a SEDUR/SEFAZ entregar a camada de lote (inscricao imobiliaria) como vigente — sem deploy, so carga de dado.

## Next Phase Readiness

- Contrato de props/JSON pronto para a UI Leaflet do **04-07** (pagina `gestao/territorio/index`, endpoints identificar/validar-localizacao/geocodificar).
- **Bloqueio mantido e visivel:** zona urbanistica (HU-031) e lote cadastral (HU-033/HU-037 RN-004/RN-005) retornam `indisponivel`/pendente ate a base oficial da SEDUR/SEFAZ — nunca simulados. O orquestrador deve manter o bloqueio no STATE/ROADMAP (ja registrado pelo 04-04/04-05).
- A infraestrutura de sobreposicao esta pronta e provada contra PostGIS — quando a base de lotes chegar, a validacao real passa a alertar sem reescrita.
- Verificacao full-suite e evidencia de integracao real (Nominatim) sao do fechamento da fase (04-08).

---
*Phase: 04-georreferenciamento-e-territorio*
*Completed: 2026-06-13*

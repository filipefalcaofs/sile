---
phase: 08-solicitacao-de-viabilidade
plan: 06
subsystem: portal
tags: [solicitacao-viabilidade, hu-062, hu-063, imovel, geometry, driver-aware, postgis, territorio, area-poligono, rn-004, rn-005, rn-002, policy, anti-fachada]

# Dependency graph
requires:
  - phase: 08-solicitacao-de-viabilidade
    plan: 01
    provides: "aggregate ViabilityRequest (property_polygon_geojson jsonb fonte + property_polygon geometry derivada só pgsql; markSimulationStale) + factory com polígono de 4 pontos"
  - phase: 08-solicitacao-de-viabilidade
    plan: 02
    provides: "parâmetro solicitacao.area_poligono.tolerancia_percentual (default 10) com fallback em config/sile.php"
  - phase: 08-solicitacao-de-viabilidade
    plan: 05
    provides: "ViabilityRequestPolicy::update (dono + status rascunho) + rotas portal.solicitacoes.* (literais)"
  - phase: 04-georreferenciamento
    plan: "(geo)"
    provides: "TerritoryService::identify (degrada honesto sem zona) + GeoJsonLayerImporter (padrão ST_MakeValid(ST_SetSRID(ST_GeomFromGeoJSON,4326)) só pgsql) + LocationValidationService (lote pendente SEDUR)"
  - phase: 01-identidade
    plan: "02"
    provides: "AuditService::log (RN-002) + 403 auditado globalmente (AccessDeniedHttpException → seguranca/acesso-negado/bloqueado)"
provides:
  - "App\\Services\\Solicitacao\\PropertyGeometryWriter::write(ViabilityRequest) — deriva property_polygon (geometry) só no pgsql, espelhando GeoJsonLayerImporter"
  - "App\\Services\\Solicitacao\\PropertyGeometryWriter::polygonAreaSquareMeters(array): ?float — área em m² driver-aware (ST_Area::geography no pgsql; shoelace planar em SQLite)"
  - "App\\Http\\Controllers\\Portal\\SolicitacaoImovelController@update — grava imóvel/área, identifica território (Fase 4), valida área×polígono (alerta), zera simulação"
  - "App\\Http\\Requests\\Portal\\UpdateSolicitacaoImovelRequest (polígono GeoJSON ≥4 pontos, referência obrigatória, área > 0, indicadores)"
  - "rota PUT portal/solicitacoes/{solicitacao}/imovel (portal.solicitacoes.imovel)"
affects: [08-08-anexos, 08-09-simulacao, 08-10-protocolo, 08-11-consulta-protocolo, 08-13-ui]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Geometria derivada do imóvel driver-aware reusando o padrão da Fase 4: jsonb é a FONTE (portável SQLite); a coluna geometry property_polygon é gravada via DB::update ST_MakeValid(ST_SetSRID(ST_GeomFromGeoJSON(?),4326)) só quando DB::getDriverName()==='pgsql' (no-op em SQLite)"
    - "Cálculo de área driver-aware: ST_Area(::geography) no pgsql (fonte de verdade geodésica); aproximação planar shoelace (graus→metros pelo cosseno da latitude) em SQLite só para o alerta orientativo"
    - "Centroide do polígono em PHP (média dos vértices únicos) — portável SQLite — para reusar TerritoryService::identify(lat,lng) sem recriar geo nem recomputar veredito"
    - "Inconsistência de negócio (área declarada ≫ polígono) registrada como ALERTA não-bloqueante (RN-004): flash areaAlert + nota auditada (AuditService event imovel-area-inconsistente, result alerta) — nunca 422"

key-files:
  created:
    - app/Services/Solicitacao/PropertyGeometryWriter.php
    - app/Http/Controllers/Portal/SolicitacaoImovelController.php
    - app/Http/Requests/Portal/UpdateSolicitacaoImovelRequest.php
    - tests/Feature/Solicitacao/InformarImovelTest.php
    - tests/Feature/Solicitacao/InformarImovelPostgisTest.php
  modified:
    - routes/portal.php

key-decisions:
  - "PropertyGeometryWriter concentra a geometria do imóvel driver-aware: write() grava a derivada SÓ no pgsql (idêntico ao GeoJsonLayerImporter; geometry NUNCA mass-assign), e polygonAreaSquareMeters() calcula a área (ST_Area::geography no pgsql; shoelace planar em SQLite). A FONTE de verdade é sempre o property_polygon_geojson (jsonb)."
  - "Validação área×polígono (HU-063 RN-004) é contra a ÁREA DO PRÓPRIO POLÍGONO desenhado (a validação contra o LOTE oficial degrada — lote pendente SEDUR via LocationValidationService). Alerta apenas quando a declarada EXCEDE a do polígono além da tolerância (usar menos área que o imóvel é legítimo); nunca bloqueia (direito de petição/orientação)."
  - "Território identificado pelo CENTROIDE do polígono (média dos vértices em PHP, portável) via TerritoryService::identify — reusa a Fase 4 e degrada honesto (zona pendente SEDUR → status 'indisponivel' com motivo, nome null — NUNCA inventa). NÃO recomputa veredito. O resumo (toArray) volta no flash 'territorio' para a UI (08-13); a identificação já é auditada pelo próprio TerritoryService (RN-002/RN-004 com versões)."
  - "Sem coluna de território no aggregate (schema fechado no 08-01, sem migração neste plano): o resumo é devolvido para exibição (flash) + auditado pelo TerritoryService. A persistência associada à solicitação chega via simulação (08-09, simulation_snapshot) quando aplicável — aqui não se inventa schema."
  - "markSimulationStale() é chamado em TODO update de imóvel/área (RN-005) dentro da transação, após o update e o write — zera snapshot/rules_versions/resultado/simulated_at; usa a API canônica do 08-01 (escrita única)."
  - "address_complement é TEXTO LIVRE (HU-139 adiado). Indicadores (is_virtual_office/is_public_area/has_independent_access) gravados via $request->boolean() (checkbox não enviado = false); a regra documental de concessão (área pública) fica no 08-08, aqui só o flag."
  - "Inconsistência de área fica REGISTRADA para o analista como nota auditada (AuditService log 'solicitacoes' event 'imovel-area-inconsistente' result 'alerta', subject = a solicitação) além do flash areaAlert — sem campo/flag novo no schema."

patterns-established:
  - "Edição de etapa do rascunho no portal: Gate::authorize('update') (dono+rascunho) → validated() → transação (update + write geometry + markSimulationStale + nota auditada condicional) → back() com flash (status/territorio/areaAlert). Molde para 08-08 (anexos) e demais etapas."
  - "Coordenação de rotas em arquivo compartilhado (paralelo com 08-07): reler routes/portal.php imediatamente antes, ANEXAR só o próprio trecho (âncoras estáveis), git diff HEAD confirmando zero clobber, staging individual."

# Metrics
duration: ~12 min
completed: 2026-06-14
---

# Phase 8 Plan 06: Informar Imóvel (HU-062) e Área Utilizada (HU-063) Summary

**O rascunho da solicitação ganhou o imóvel e a área: o requerente demarca o polígono de 4 pontos (GeoJSON é a FONTE portável), informa complemento (texto livre — HU-139 adiado), ponto de referência (obrigatório) e os indicadores (escritório virtual/área pública/acessos independentes), e declara a área utilizada. O `PropertyGeometryWriter` concentra a geometria do imóvel driver-aware espelhando o `GeoJsonLayerImporter` da Fase 4: `write()` deriva a coluna `property_polygon` (geometry) via `ST_MakeValid(ST_SetSRID(ST_GeomFromGeoJSON(?),4326))` SÓ no Postgres (no-op em SQLite, onde a coluna nem existe), e `polygonAreaSquareMeters()` calcula a área em m² (ST_Area::geography real no pgsql; aproximação planar shoelace em SQLite). O `SolicitacaoImovelController@update` identifica o território pelo CENTROIDE do polígono reusando o `TerritoryService::identify` (degrada honesto sem zona — status `indisponivel` com motivo, nunca um valor inventado; NÃO recomputa veredito), valida a área declarada contra a do próprio polígono com tolerância parametrizável (`solicitacao.area_poligono.tolerancia_percentual`, default 10) gerando um ALERTA que NÃO bloqueia (RN-004) e fica registrado para o analista (flash + nota auditada), e zera a simulação anterior (`markSimulationStale` — RN-005). Só o dono edita, e apenas em rascunho (`ViabilityRequestPolicy::update`, CA-04 com 403 auditado). ZERO dependência nova. TDD estrito (RED→GREEN com evidência fresca, incluindo `@group postgis` rodando de verdade): 6 testes SQLite + 2 `@group postgis`; suíte completa 750/750.**

## Performance

- **Duration:** ~12 min
- **Completed:** 2026-06-14
- **Tasks:** 2 (PropertyGeometryWriter + teste postgis; controller/request/rota + teste SQLite)
- **Files:** 5 criados + 1 modificado (routes/portal.php) — ZERO dependência nova

## Accomplishments

- **`PropertyGeometryWriter`** (driver-aware): `write()` grava a geometry derivada só no pgsql (idêntico ao `GeoJsonLayerImporter`; geometry nunca mass-assign); `polygonAreaSquareMeters()` calcula a área em m² (ST_Area real no pgsql; shoelace planar em SQLite). Fonte de verdade = `property_polygon_geojson` (jsonb).
- **`SolicitacaoImovelController@update`**: grava polígono/complemento/referência/indicadores/área em transação; identifica território (Fase 4, degrada honesto); deriva geometry; valida área×polígono (alerta); zera simulação (RN-005); autoriza por policy.
- **Território honesto**: zona indisponível → registrada como pendente (status/motivo), nome `null` — nunca inventa. A identificação é auditada pelo próprio `TerritoryService` (RN-002/RN-004 com versões das camadas).
- **Alerta área×polígono (RN-004)**: divergência acima da tolerância parametrizável → `areaAlert` (flash) + nota auditada `imovel-area-inconsistente`; nunca bloqueia (sem 422); a inconsistência mantida fica registrada.
- **`UpdateSolicitacaoImovelRequest`**: valida polígono GeoJSON (type Polygon, anel ≥ 4 pontos, pares [lng,lat] numéricos), `address_reference` obrigatório, `used_area_m2` > 0, indicadores boolean; mensagens pt-BR.
- **Rota** `PUT portal/solicitacoes/{solicitacao}/imovel` (`portal.solicitacoes.imovel`) anexada após as literais (coordenação com 08-07, zero clobber).

## Rota (nome exato — insumo de 08-08/08-09/08-13)

| Método | URI | Nome | Ação |
|---|---|---|---|
| PUT | `portal/solicitacoes/{solicitacao}/imovel` | `portal.solicitacoes.imovel` | grava imóvel+área, identifica território, valida área×polígono |

Grupo `auth:web` + `verified` + `lgpd.accepted` + `ResolveRepresentation`; RMB `{solicitacao}` → `ViabilityRequest`.

## Contratos (insumo dos planos seguintes)

- **`PropertyGeometryWriter::write(ViabilityRequest $request): void`** — deriva `property_polygon` só no pgsql a partir do jsonb fonte. No-op fora do Postgres.
- **`PropertyGeometryWriter::polygonAreaSquareMeters(array $geojson): ?float`** — área em m² driver-aware; `null` se o GeoJSON não tem anel com ≥ 3 vértices.
- **`SolicitacaoImovelController::update(UpdateSolicitacaoImovelRequest, ViabilityRequest $solicitacao): RedirectResponse`** — `back()` com flash `status`, `territorio` (resumo `TerritoryResult::toArray`) e, condicional, `areaAlert` (`{area_declarada_m2, area_poligono_m2, tolerancia_percentual, divergencia_percentual}`).
- **GeoJSON aceito**: `{ type: "Polygon", coordinates: [ [ [lng,lat] × ≥4 (fechado) ] ] }` — é a FONTE; a geometry derivada serve às consultas espaciais reversas (Fases 9/10).

## Task Commits

TDD estrito (RED→GREEN verificado com evidência fresca antes de cada commit):

1. **Task 1: PropertyGeometryWriter + InformarImovelPostgisTest** — `29fa6c9` (feat) — RED: 2 erros (classe inexistente, rodando contra Postgres real) → GREEN: 2/2 (8 asserções) `@group postgis`.
2. **Task 2: SolicitacaoImovelController + UpdateSolicitacaoImovelRequest + rota + InformarImovelTest** — `4cc6301` (feat) — RED: 6 falhas (404/sem território) → GREEN: 6/6 (32 asserções).

**Plan metadata:** `docs(08-06)` (este SUMMARY + STATE).

## Decisions Made

- **Geometria do imóvel concentrada no `PropertyGeometryWriter`** (escrita + área), driver-aware, espelhando a Fase 4. A fonte é sempre o jsonb; a derivada é provada em `@group postgis` (ST_AsText/ST_SRID/GeometryType reais).
- **Área×polígono contra o próprio polígono** (lote oficial pendente SEDUR via `LocationValidationService`). Alerta só quando a declarada excede o polígono além da tolerância; usar menos área é legítimo. Nunca bloqueia.
- **Território pelo centroide em PHP** (portável) reusando `TerritoryService`; degrada honesto sem zona; resumo no flash + auditoria do serviço. Sem coluna nova de território (schema fechado no 08-01).
- **Inconsistência registrada sem schema novo**: nota auditada `imovel-area-inconsistente` (result `alerta`) + flash, em vez de uma coluna/flag — fiel ao plano ("nota auditada") e ao escopo.

## Deviations from Plan

None — plano executado como escrito. Adições dentro do escopo: (1) `polygonAreaSquareMeters()` no `PropertyGeometryWriter` (centraliza a geometria driver-aware num só serviço, em vez de SQL espacial no controller); (2) `test_validacao_exige_poligono_referencia_e_area` no `InformarImovelTest` (cobre o FormRequest), além dos 5 testes pedidos.

## Issues Encountered

- **Filtro misto SQLite+`@group postgis` numa execução pequena falha** ("no such table" no `:memory:`): artefato GERAL do `RefreshDatabase` quando o teste postgis roda antes do SQLite e o estado `migrated` impede a migração do `:memory:` — REPRODUZIDO também no par já existente `ProtocolNumberGenerator` (não é do código novo). Os filtros do plano são rodados SEPARADAMENTE (SQLite sem postgis; postgis com `--group=postgis`) e a suíte completa (ordem natural) passa normalmente.
- **Execução paralela com 08-07** (mesmo `routes/portal.php`): o 08-07 já havia commitado (atividades) ao editar; reli o arquivo, anexei só a rota do imóvel após a de atividades (âncoras estáveis) e `git diff HEAD` confirmou apenas o meu trecho (import + rota). Staging individual.

## Verification (evidência fresca)

- **RED Task 1:** `--group=postgis --filter=InformarImovelPostgisTest` (POSTGIS_TESTS_REQUIRED=true) → 2 erros (classe inexistente, rodando no Postgres real, sem skip). **GREEN:** 2/2 (8 asserções).
- **RED Task 2:** `--filter=InformarImovelTest` → 6 falhas (404/sem território). **GREEN:** 6/6 (32 asserções).
- **Filtros do plano (separados):** `--filter=InformarImovelTest` → 6/6; `--group=postgis --filter=InformarImovelPostgisTest` (POSTGIS_TESTS_REQUIRED=true) → 2/2 (FOR REAL no pgsql).
- **`php artisan route:list --path=solicitacoes`** → `PUT portal/solicitacoes/{solicitacao}/imovel` (`portal.solicitacoes.imovel`).
- **`vendor/bin/pint --dirty --format agent`** → passed.
- **Suíte completa:** `POSTGIS_TESTS_REQUIRED=true php artisan test --compact` → **750 testes, 750 passaram, 0 falhas** (3806 asserções; inclui os `@group postgis` inline com o container `sile-pgsql` healthy e os testes do 08-07 já commitados).
- **`php artisan geo:preparar-banco-de-testes`** → banco `sile_testing` pronto / extensão postgis garantida.

## Next Phase Readiness

- **08-08 (anexos):** `is_public_area` já é gravado; a obrigatoriedade de concessão de uso quando área pública entra lá (resolver documental). O imóvel/área já alimentam a validação documental.
- **08-09 (simulação HU-141):** o ponto (centroide) e a área já estão na solicitação — a simulação por ponto+CNAE evita geocodificar de novo; `markSimulationStale` já zera o snapshot a cada mudança de imóvel/área (RN-005).
- **08-10 (protocolo):** o imóvel/área são pré-condições do protocolo; a geometry derivada (pgsql) deixa o gancho espacial pronto.
- **08-13 (UI mapa):** consome a rota `portal.solicitacoes.imovel`, o GeoJSON fonte, o flash `territorio` (resumo por dimensão) e `areaAlert` para o mapa Leaflet (MapImovel) e os avisos.
- **Bloqueios herdados (degradam honesto, registrados):** zona urbanística LOUOS e lote cadastral pendentes SEDUR (território/validação de lote comunicam "pendente", nunca inventam); HU-139 (complemento texto livre).

---
*Phase: 08-solicitacao-de-viabilidade*
*Completed: 2026-06-14*

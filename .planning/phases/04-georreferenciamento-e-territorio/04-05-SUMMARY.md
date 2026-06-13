---
phase: 04-georreferenciamento-e-territorio
plan: "04-05"
subsystem: geo
tags: [postgis, territoryservice, spatialrepository, st_contains, st_dwithin, st_distance, st_intersects, geography, fake-repository, auditoria, rn-004, sem-fachada]

# Dependency graph
requires:
  - phase: 04-01
    provides: "geo_layers/geo_features (geometry(Geometry,4326) + GiST), models GeoLayer (vigente()/naData()) e GeoFeature, enums GeoLayerType/GeoLayerStatus, conexão pgsql_testing + PostgisTestCase (guard fail-not-skip)"
  - phase: 04-02
    provides: "geo.via_max_metros (constante técnica em config/sile.php) — raio da via mais próxima"
  - phase: 04-04
    provides: "GeoJsonLayerImporter (carga real de FeatureCollection), camadas reais GeoSalvador (bairro/restrição/via) e zona/lote pendente_fonte; fixture tests/Fixtures/geo/bairros-amostra.geojson"
  - phase: 01-identidade
    provides: "AuditService.log (RN-002) com result/rules_version"
provides:
  - "SpatialRepository (interface): containingFeature/nearestFeature/intersectingFeatures recebendo a GeoLayer já resolvida pela vigência"
  - "PostgisSpatialRepository: SQL espacial real (ST_Contains; ST_DWithin/ST_Distance com ::geography em metros; ST_Intersects), properties jsonb decodificadas"
  - "FakeSpatialRepository (tests/Support/Geo): fake em memória que registra os tipos consultados — unit-testa consumidores sem PostGIS"
  - "TerritoryService.identify(lat,lng,?date): bairro/via/restrições reais; zona/lote como indisponível (pendente SEDUR) sem consulta espacial; auditoria territorio/identificacao com versão por camada (RN-004)"
  - "TerritoryResult (DTO readonly) com status/nome/propriedades/motivo/versao_camada por dimensão e toArray() snake_case (contrato 04-06/04-07)"
  - "binding SpatialRepository -> PostgisSpatialRepository"
affects: [04-06, 04-07, 05-motor-de-enquadramento]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Repositório espacial fino atrás de contrato (SpatialRepository): SQL PostGIS isolado; consumidores testáveis com fake em memória (mesma disciplina de CnpjLookup/Geocoder)"
    - "Distância em metros via cast ::geography (Pitfall 4); contenção/interseção em geometry(4326); ponto sempre (lng,lat) — ST_MakePoint (Pitfall 3)"
    - "Degradação comunicada dirigida pelo DADO: camada pendente_fonte/inexistente => indisponível sem consulta (a carga muda, a lógica não)"
    - "Versão da camada consultada registrada no resultado e na auditoria (RN-004 — reprodução por época via naData)"

key-files:
  created:
    - "app/Services/Geo/SpatialRepository.php"
    - "app/Services/Geo/PostgisSpatialRepository.php"
    - "app/Services/Geo/TerritoryService.php"
    - "app/Services/Geo/TerritoryResult.php"
    - "tests/Support/Geo/FakeSpatialRepository.php"
    - "tests/Feature/Geo/PostgisSpatialRepositoryTest.php"
    - "tests/Unit/Geo/TerritoryServiceTest.php"
  modified:
    - "app/Providers/AppServiceProvider.php"

key-decisions:
  - "Gate de indisponibilidade pelo STATUS da camada (pendente_fonte) e não por feature_count: honesto e genérico — quando a SEDUR entregar zona/lote como camada vigente com feições, a MESMA lógica identifica sem mudar código (sem fachada nos dois sentidos)"
  - "SpatialRepository recebe a GeoLayer já resolvida (vigente()/naData()) em vez de resolver a versão internamente: a decisão de vigência/reprodução fica no TerritoryService; o repositório só executa SQL sobre a carga indicada (RN-004)"
  - "TerritoryResult guarda cada dimensão como array de shape estável (status/nome/propriedades/motivo/versao_camada; via +distancia_m; restricoes com itens[]) — contrato snake_case direto para o endpoint 04-06 e o mapa 04-07, sem sub-DTO extra"
  - "nome legível derivado de chaves usuais (NOME_BAIRRO/NOME_LOGRADOURO/NOME_VIA/NOME/nome); quando ausente, propriedades carrega o dado bruto — não acopla o serviço a um schema rígido de feição"
  - "Auditoria territorio/identificacao sem rules_version único (múltiplas camadas): as versões vão em properties.versoes_por_camada + resumo_status (RN-002 + RN-004)"

patterns-established:
  - "Identificação territorial por ponto via repositório fino + DTO de resultado: insumo do motor da Fase 5 e das telas 04-06/04-07"
  - "Teste híbrido sem fachada: SQL espacial provado em @group postgis (PostGIS real) e consumidores provados em Unit com FakeSpatialRepository (SQLite)"

# Metrics
duration: ~14 min
completed: 2026-06-13
---

# Fase 4 Plano 05: TerritoryService + SpatialRepository PostGIS Summary

**Identificação territorial por ponto (HU-031 a HU-035): `TerritoryService.identify(lat,lng,?date)` devolve bairro (ST_Contains), via mais próxima em metros (ST_DWithin/ST_Distance::geography) e restrições incidentes (ST_Intersects) sobre as camadas REAIS do GeoSalvador, e devolve zona/lote como indisponível (base pendente SEDUR) SEM consulta espacial — degradação comunicada, com a versão de cada camada registrada (RN-004) e a identificação auditada (RN-002). O SQL fica isolado em `PostgisSpatialRepository` atrás da interface `SpatialRepository`, com `FakeSpatialRepository` em memória para os consumidores.**

## Performance

- **Duration:** ~14 min
- **Started:** 2026-06-13T22:34:32Z
- **Completed:** 2026-06-13T22:48:36Z
- **Tasks:** 2 (ambas TDD)
- **Files modified/created:** 8 (7 criados, 1 modificado)

## Accomplishments

- `PostgisSpatialRepository` executa o SQL espacial REAL e isolado: `ST_Contains` (bairro/zona/lote), `ST_DWithin`+`ST_Distance` com `::geography` para metros (via mais próxima, Pitfall 4) e `ST_Intersects` (restrições) — provado em `@group postgis` contra dado oficial real do GeoSalvador (golden point → "Colinas de Periperi").
- `TerritoryService.identify` orquestra as cinco dimensões: identifica bairro/via/restrições nas camadas vigentes e comunica zona/lote como **indisponível (pendente SEDUR)** sem disparar consulta espacial — provado por unit que o repositório NÃO é chamado para zona/lote.
- `TerritoryResult` (DTO readonly) carrega status/nome/propriedades/motivo/`versao_camada` por dimensão e expõe `toArray()` snake_case + `versoes()`/`resumoStatus()` — contrato pronto para o endpoint (04-06) e o mapa (04-07).
- `SpatialRepository` atrás de binding permite testar consumidores (motor da Fase 5, controllers) com `FakeSpatialRepository` em memória, sem PostGIS.

## Task Commits

1. **Task 1: SpatialRepository (interface) + PostgisSpatialRepository + FakeSpatialRepository + binding** — `3820860` (feat) — TDD: RED (`@group postgis`, classe inexistente) → GREEN (3 testes, 13 asserções) → pint.
2. **Task 2: TerritoryService.identify + TerritoryResult (bloqueio comunicado + auditoria por versão)** — `620f13c` (feat) — TDD: RED (7 erros, classe inexistente) → GREEN (7 testes Unit, 25 asserções) → pint.

**Plan metadata:** este SUMMARY — `docs(04-05): completa identificação territorial`.

_(STATE.md NÃO foi editado — consolidação é do orquestrador, conforme o objetivo do plano.)_

## Contrato entregue (para 04-06 / 04-07 / Fase 5)

```php
// app/Services/Geo/SpatialRepository.php  (interface; ponto sempre em (lng, lat))
public function containingFeature(GeoLayer $layer, float $lng, float $lat): ?array;       // {id, properties}|null
public function nearestFeature(GeoLayer $layer, float $lng, float $lat, int $maxMeters): ?array; // {id, properties, distancia_m}|null
public function intersectingFeatures(GeoLayer $layer, float $lng, float $lat): array;      // [{id, properties}, ...]

// app/Services/Geo/TerritoryService.php
public function identify(float $lat, float $lng, ?CarbonInterface $date = null): TerritoryResult;

// app/Services/Geo/TerritoryResult.php — toArray() (snake_case):
[
  'bairro'     => ['status' => 'identificado|nao_encontrado|indisponivel', 'nome' => ?string, 'propriedades' => ?array, 'motivo' => ?string, 'versao_camada' => ?string],
  'via'        => [...idem..., 'distancia_m' => ?float],
  'zona'       => [...idem...],   // tipicamente status 'indisponivel', motivo 'Base de zoneamento pendente SEDUR'
  'lote'       => [...idem...],   // tipicamente status 'indisponivel', motivo 'Base de lotes pendente SEDUR'
  'restricoes' => ['status' => 'identificado|nao_encontrado|indisponivel', 'itens' => [['nome' => ?string, 'propriedades' => array], ...], 'motivo' => ?string, 'versao_camada' => ?string],
]
// + versoes(): array<dimensao,?string>  e  resumoStatus(): array<dimensao,string>
```

## Como zona/lote são comunicados como indisponível (sem fachada)

- `TerritoryService::isBlocked()` trata camada **inexistente** ou com status **`pendente_fonte`** como bloqueada: retorna `status = 'indisponivel'` com `motivo` (`Base de zoneamento pendente SEDUR` / `Base de lotes pendente SEDUR`) e `versao_camada` da carga registrada — **sem chamar o repositório** (provado por `assertNotContains('zona'|'lote', $fake->containingCalls)`).
- A lógica é dirigida pelo DADO: quando a SEDUR entregar a base de zoneamento/lotes como camada **vigente com feições**, a MESMA `identify` passa a identificá-las — a carga muda, a lógica não.

## Binding adicionado

- `AppServiceProvider::register()`: `$this->app->bind(SpatialRepository::class, PostgisSpatialRepository::class);` — a Fase 13/SEDUR pode trocar a implementação sem tocar os consumidores.

## Decisions Made

- **Gate por STATUS (pendente_fonte), não por feature_count:** honesto e genérico — uma camada vigente com 0 feições consulta e devolve `nao_encontrado` (verdade), enquanto `pendente_fonte` é `indisponivel` sem consulta. Quando zona/lote virarem vigentes, identificam sem mudar código.
- **Repositório recebe a `GeoLayer` resolvida:** a vigência/reprodução (vigente()/naData()) fica no serviço; o repositório só executa SQL sobre a carga indicada (RN-004).
- **Dimensão como array de shape estável no DTO** (em vez de sub-DTO por dimensão): contrato snake_case direto para a UI/endpoint, menos superfície.
- **`nome` best-effort + `propriedades` bruto:** não acopla o serviço a um schema rígido de feição; a UI tem o nome usual e o dado completo.
- **Auditoria sem rules_version único:** múltiplas camadas; as versões consultadas vão em `properties.versoes_por_camada` (+ `resumo_status`).

## Deviations from Plan

None - plan executed exactly as written. (Ajuste interno menor: o ramo "indisponível" das restrições monta o shape `{status, itens:[], motivo, versao_camada}` explicitamente, para não carregar as chaves `nome/propriedades` da dimensão de lista — sem efeito no contrato.)

## Issues Encountered

None. Container `sile-pgsql` de pé (5433, healthy) e `sile_testing` com PostGIS durante toda a execução.

## Evidência fresca (sem fachada)

- **SQL espacial real (`@group postgis`):** `PostgisSpatialRepositoryTest` 3/3 — golden point (`ST_PointOnSurface` da geometria importada) → bairro **"Colinas de Periperi"**; ponto no oceano (0,0) → null; via a ~32 m → retornada com `distancia_m` ∈ (0, 50]; restrição por interseção retornada. Carga via `GeoJsonLayerImporter` real sobre a fixture oficial do GeoSalvador.
- **Consumidores sem PostGIS (Unit/SQLite):** `TerritoryServiceTest` 7/7 — bairro/via/restrições identificados; **zona e lote `indisponivel` com motivo "pendente SEDUR" e o repositório NÃO consultado** para elas; auditoria `territorio/identificacao` criada com `versoes_por_camada` e `resumo_status`.
- **Sem regressão:** suíte SQLite **436/436** (baseline 429 + 7 novos); grupo **@group postgis 11/11** (3 novos do 04-05 + 8 herdados); **pint** limpo; sem lints.

## Next Phase Readiness

- `TerritoryService` + `SpatialRepository` são o contrato consumido por 04-06 (endpoint de identificação/validação de localização) e 04-07 (mapa), e o insumo do motor de enquadramento da Fase 5.
- Bloqueio mantido e visível: **zona urbanística (HU-031)** e **lote cadastral (HU-033)** retornam indisponível-pendente até a base oficial da SEDUR/SEFAZ — nunca simulados. O orquestrador deve manter esse bloqueio no STATE/ROADMAP (já registrado pelo 04-04).
- A reprodução por data (`identify(..., $date)`) usa `GeoLayer::naData` — pronta para auditoria/recurso (RN-004) quando houver histórico de versões.

---
*Phase: 04-georreferenciamento-e-territorio*
*Completed: 2026-06-13*

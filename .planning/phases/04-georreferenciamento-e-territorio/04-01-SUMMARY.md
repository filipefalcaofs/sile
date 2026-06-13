---
phase: 04-georreferenciamento-e-territorio
plan: "01"
subsystem: database
tags: [postgis, geometry, gist, srid-4326, versionamento, testing, pgsql, sqlite, fail-not-skip]

# Dependency graph
requires:
  - phase: 03-cadastro-empresarial
    provides: "padrão de model (#[Fillable], casts(), HasAuditoria, HasFactory) e enum string com label()"
  - phase: 03.1-fundacao-assincrona
    provides: "infra de container PostGIS de dev (porta 5433) e disciplina de parametrização/testes"
provides:
  - "Esquema de camadas geográficas versionadas: geo_layers + geo_features (geometry(Geometry,4326) + índice GiST driver-aware)"
  - "Models GeoLayer (scopes vigente()/naData(), auditado) e GeoFeature (relação layer(), properties array)"
  - "Enums GeoLayerType (bairro/zona/via/lote/restricao, isBlockedSource) e GeoLayerStatus (vigente/substituida/pendente_fonte)"
  - "Conexão pgsql_testing + preflight geo:preparar-banco-de-testes (cria sile_testing + extensão postgis)"
  - "PostgisTestCase com guard de honestidade (fail-not-skip) e smoke @group postgis (ST_Contains/ST_Area reais)"
affects: [04-02, 04-03, 04-04, 04-05, 04-06, 04-07, 04-08, 05-motor-de-enquadramento]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Migração driver-aware: DDL PostGIS (índice GiST) só em pgsql; SQLite migra a coluna geometry como tipo genérico"
    - "Coluna geometry(Geometry,4326) GENÉRICA (aceita Polygon/MultiPolygon/LineString); SRID 4326 fixo"
    - "Camada como dado versionado com vigência (valid_to nulo = vigente; naData reproduz a versão da época)"
    - "Teste espacial honesto: conexão pgsql_testing dedicada + guard fail-not-skip (skip só sem servidor)"

key-files:
  created:
    - "app/Enums/GeoLayerType.php"
    - "app/Enums/GeoLayerStatus.php"
    - "app/Models/GeoLayer.php"
    - "app/Models/GeoFeature.php"
    - "database/factories/GeoLayerFactory.php"
    - "database/factories/GeoFeatureFactory.php"
    - "database/migrations/2026_06_13_214038_create_geo_layers_table.php"
    - "database/migrations/2026_06_13_214420_create_geo_features_table.php"
    - "app/Console/Commands/PrepareGeoTestDatabaseCommand.php"
    - "tests/PostgisTestCase.php"
    - "tests/Unit/Geo/GeoLayerTest.php"
    - "tests/Feature/Geo/GeoSchemaTest.php"
    - "tests/Feature/Geo/PostgisSmokeTest.php"
  modified:
    - "config/database.php"
    - "phpunit.xml"
    - ".env.example"
    - ".env (local, não versionado)"

key-decisions:
  - "Coluna geometry GENÉRICA (Geometry,4326) em vez de tipada — aceita polígono (bairro/zona/lote) e LineString (via) sem múltiplas colunas"
  - "Índice GiST e qualquer DDL PostGIS guardados por DB::getDriverName() === 'pgsql' — SQLite :memory: da suíte não quebra (Pitfall 1)"
  - "Banco de teste espacial sile_testing reutiliza o container de dev (5433) via conexão pgsql_testing; criado por preflight idempotente"
  - "Guard de honestidade fail-not-skip: servidor de pé + erro = FALHA; markTestSkipped só sem servidor; POSTGIS_TESTS_REQUIRED=true força falha no CI"

patterns-established:
  - "Migração driver-aware para recursos só-Postgres mantendo a suíte SQLite intacta"
  - "PostgisTestCase é a base de todo teste @group postgis das próximas fatias do EP04"

# Metrics
duration: ~20 min
completed: 2026-06-13
---

# Fase 4 Plano 01: Fundação PostGIS e Infra de Teste Espacial Summary

**Esquema de camadas geográficas versionadas (geo_layers/geo_features) com geometry(Geometry,4326) + índice GiST driver-aware, models/enums com vigência, conexão pgsql_testing + preflight, e PostgisTestCase com guard fail-not-skip provando ST_Contains/ST_Area reais sem quebrar os 385 testes SQLite.**

## Performance

- **Duration:** ~20 min
- **Started:** 2026-06-13T21:36:00Z
- **Completed:** 2026-06-13T21:56:00Z
- **Tasks:** 4
- **Files modified/created:** 17 (13 criados, 4 modificados)

## Accomplishments

- `geo_layers` + `geo_features` migram em SQLite (suíte) e PostgreSQL; o índice GiST existe SÓ no Postgres (driver-aware), sem derrubar a suíte de 385 testes.
- `GeoLayer` resolve a versão vigente (`vigente()`) e a versão de uma data passada (`naData()`) — vigência provada por teste puro em SQLite (HU-036 RN-004).
- Camadas sem fonte pública (zona/lote) são modeláveis como `status = pendente_fonte` com `feature_count = 0` — sem polígono inventado (HU-031/HU-033).
- Preflight executável e idempotente `geo:preparar-banco-de-testes` cria `sile_testing` + extensão `postgis` no Postgres de dev (porta 5433).
- `PostgisTestCase` com guard de honestidade: o grupo `postgis` EXECUTA SQL espacial real (ST_Contains, ST_Area) e nunca passa em silêncio — servidor de pé + erro vira FALHA.

## Task Commits

1. **Task 1: camada versionada (geo_layers + enums + model + factory)** — `7dcac55` (feat)
2. **Task 2: geo_features geometry(4326) driver-aware + GeoFeature** — `f20652c` (feat)
3. **Task 3: conexão pgsql_testing + DB_TEST_* + preflight** — `bf19001` (feat)
4. **Task 4: PostgisTestCase guard fail-not-skip + smoke @group postgis** — `2429e16` (feat)

_TDD em cada task: RED confirmado (classe/tabela inexistente) → GREEN → pint._

## Files Created/Modified

### Migrations (nomes exatos + colunas)

- `database/migrations/2026_06_13_214038_create_geo_layers_table.php` — colunas: `id, type, version, status (default 'vigente'), valid_from (date,null), valid_to (date,null = vigente), source, rules_version, feature_count (uint,0), timestamps`; `unique(type, version)` (carga idempotente); `index(type, valid_to)` (consulta da vigente).
- `database/migrations/2026_06_13_214420_create_geo_features_table.php` — colunas: `id, geo_layer_id (FK cascadeOnDelete), geometry (geometry(Geometry,4326)), properties (jsonb,null), timestamps`; índice `geo_features_geometry_gist ... USING GIST (geometry)` criado **só em pgsql**.

### Models / Enums / Factories

- `app/Enums/GeoLayerType.php` — `bairro|zona|via|lote|restricao`, `label()`, `isBlockedSource()` (true para zona/lote).
- `app/Enums/GeoLayerStatus.php` — `vigente|substituida|pendente_fonte`, `label()`.
- `app/Models/GeoLayer.php` — `HasAuditoria`, casts (type/status/datas), relação `features()`, scopes `scopeVigente`/`scopeNaData`.
- `app/Models/GeoFeature.php` — `geometry` FORA do fillable (gravado via DB::raw/ST_*), `properties` => array, relação `layer()`.
- `database/factories/GeoLayerFactory.php` — states `vigente()` e `pendenteFonte()`.
- `database/factories/GeoFeatureFactory.php` — gera só `geo_layer_id` + `properties` (geometria é dos testes @group postgis via SQL real).

### Infra de teste

- `config/database.php` — conexão `pgsql_testing` (espelha pgsql; `DB_TEST_*` com defaults para a 5433 / `sile_testing`).
- `app/Console/Commands/PrepareGeoTestDatabaseCommand.php` — `geo:preparar-banco-de-testes` (idempotente; cria sile_testing + `CREATE EXTENSION IF NOT EXISTS postgis`; não toca o banco `sile`).
- `tests/PostgisTestCase.php` — base `@group postgis`; aponta default para `pgsql_testing`; guard fail-not-skip.
- `tests/Unit/Geo/GeoLayerTest.php` (6 testes), `tests/Feature/Geo/GeoSchemaTest.php` (3), `tests/Feature/Geo/PostgisSmokeTest.php` (2, @group postgis).
- `phpunit.xml` — comentário de que o grupo postgis roda sob `pgsql_testing` (default global segue SQLite :memory:).
- `.env.example` (e `.env` local) — bloco `DB_TEST_*` + `POSTGIS_TESTS_REQUIRED`.

### Variáveis de ambiente novas

```dotenv
DB_TEST_HOST=127.0.0.1
DB_TEST_PORT=5433
DB_TEST_DATABASE=sile_testing
DB_TEST_USERNAME=sile
DB_TEST_PASSWORD=secret
POSTGIS_TESTS_REQUIRED=false   # CI define true → qualquer skip vira FALHA
```

## Decisions Made

- **Coluna geometry GENÉRICA `geometry(Geometry,4326)`**: aceita Polygon, MultiPolygon e LineString numa única coluna (bairro/zona/lote são polígonos; via é LineString), SRID 4326 fixo (Pitfall 2/7 do RESEARCH).
- **GiST e DDL PostGIS por driver**: `if (DB::getDriverName() === 'pgsql')` envolve o `CREATE INDEX ... USING GIST`. Em SQLite a coluna `geometry` compila como tipo genérico e as tabelas são criadas; o índice não é tentado (Pitfall 1) — os 385 testes seguem verdes.
- **Banco de teste dedicado `sile_testing`**: a conexão `pgsql_testing` reaproveita o container de dev (5433) num banco isolado, criado pelo preflight. RefreshDatabase migra com a default apontada para `pgsql_testing` (nunca `migrate:fresh` no banco `sile`).
- **Guard de honestidade (fail-not-skip)**: `markTestSkipped` SÓ quando o servidor Postgres não está alcançável (fsockopen). Com o servidor de pé, banco/extensão ausente vira `$this->fail()` com a orientação do preflight. `POSTGIS_TESTS_REQUIRED=true` transforma qualquer skip em falha (enforcement de CI). Sem fachada de teste.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] `createApplication()` deve ser `public`, não `protected`**
- **Found during:** Task 4 (PostgisTestCase).
- **Issue:** o snippet do plano declarava `protected function createApplication()`, mas a base `Illuminate\Foundation\Testing\TestCase::createApplication()` é `public`. Reduzir a visibilidade num override causa erro fatal ("Access level must be public").
- **Fix:** declarado `public function createApplication()` (visibilidade da base preservada).
- **Verification:** smoke `@group postgis` carrega e executa (2/2 passou).
- **Committed in:** `2429e16`.

**2. [Rule 3 - Blocking] Comentário no phpunit.xml não pode conter `--`**
- **Found during:** Task 4 (primeira execução do smoke).
- **Issue:** o comentário XML continha "`--group postgis`"; XML proíbe `--` em comentários — PHPUnit abortou com "Comment must not contain '--' (double-hyphen)", impedindo qualquer teste de rodar.
- **Fix:** comentário reescrito sem `--` (mantém a orientação de como rodar o grupo).
- **Verification:** suíte e grupo postgis voltaram a executar.
- **Committed in:** `2429e16`.

**3. [Rule 2 - Missing Critical] Validação do nome do banco antes do DDL interpolado**
- **Found during:** Task 3 (preflight).
- **Issue:** `CREATE DATABASE` não aceita prepared statement, então o nome é interpolado no SQL. Embora venha de config (confiável), interpolar sem validar é frágil.
- **Fix:** `preg_match('/^[A-Za-z0-9_]+$/', $testDb)` rejeita nomes inesperados antes do `unprepared`.
- **Verification:** preflight roda e cria `sile_testing`; nome inválido retornaria FAILURE.
- **Committed in:** `bf19001`.

**Nota (não-deviation):** a asserção `features()->count()` foi mantida no `GeoSchemaTest` (Task 2, quando `GeoFeature` já existe) em vez do `GeoLayerTest` (Task 1), para preservar commits atômicos. `GeoLayerTest` é um teste Unit que usa `RefreshDatabase` — consistente com a convenção do repo (testes Unit estendem `Tests\TestCase`).

---

**Total deviations:** 3 auto-fixed (1 bug de visibilidade, 1 bloqueio de parse XML, 1 hardening defensivo). **Impact:** todos necessários para o grupo postgis executar e para a robustez do preflight. Sem scope creep.

## Issues Encountered

Nenhum além das deviations acima. O container `sile-pgsql` estava de pé (5433, healthy) e PostGIS 3.5 disponível durante toda a execução.

## Evidência — o grupo @group postgis EXECUTA de verdade (não skip)

Container de dev de pé + preflight rodado. Linhas de resultado (reporter padrão do projeto):

- **Smoke PASSOU executando SQL espacial (não skip):**
  ```
  {"tool":"phpunit","result":"passed","tests":2,"passed":2,"assertions":4}
  ```
  (`php artisan test --compact --group postgis tests/Feature/Geo/PostgisSmokeTest.php`)

- **PROVA DO GUARD — servidor de pé + `sile_testing` derrubado → FALHA (nunca skip):**
  ```
  {"tool":"phpunit","result":"failed","tests":2,"passed":0,"failed":2, ...
   "Servidor Postgres de pé mas o banco de teste espacial não está pronto.
    Rode 'php artisan geo:preparar-banco-de-testes'. ... database \"sile_testing\" does not exist"}
  ```
  (banco restaurado com o preflight em seguida; smoke voltou a `passed:2`.)

- **Skip legítimo — sem servidor alcançável (porta morta):**
  ```
  {"tool":"phpunit","result":"passed","tests":2,"skipped":2}
  ```
- **Enforcement de CI — sem servidor + `POSTGIS_TESTS_REQUIRED=true` → FALHA:**
  ```
  "POSTGIS_TESTS_REQUIRED=true mas Servidor Postgres de teste inacessível em 127.0.0.1:59999
   (Connection refused). O grupo postgis NÃO pode ser pulado neste ambiente."
  ```

- **Sem regressão na suíte SQLite:**
  ```
  {"tool":"phpunit","result":"passed","tests":396,"passed":396,"assertions":2003}
  ```
  (`php artisan test --compact --exclude-group postgis` — inclui GeoLayerTest e GeoSchemaTest; eram 385, +9 deste plano, +2 do 04-02 paralelo.)

- **Pint:** `{"tool":"pint","result":"passed"}`.

## Como rodar o grupo postgis

**Local (uma vez por ambiente / após recriar o container):**
```bash
php artisan geo:preparar-banco-de-testes        # cria sile_testing + extensão postgis (idempotente)
php artisan test --group postgis                 # executa o SQL espacial real
php artisan test --exclude-group postgis         # suíte rápida SQLite (sem PostGIS)
```

**CI (GitHub Actions):** subir um service container PostGIS (ex.: `postgis/postgis:16-3.5`), apontar `DB_TEST_*` para ele, rodar `php artisan geo:preparar-banco-de-testes` e então `php artisan test --group postgis` com **`POSTGIS_TESTS_REQUIRED=true`** — assim qualquer indisponibilidade vira FALHA (o grupo nunca é pulado em silêncio no CI).

## Next Phase Readiness

- Fundação espacial pronta para as próximas fatias do EP04 (04-02 parâmetros/permissão já em paralelo; 04-03+ TerritoryService/SpatialRepository, import de GeoJSON, Geocoder, mapa Leaflet) e para o motor da Fase 5.
- `PostgisTestCase` é a base reutilizável de todo teste `@group postgis` seguinte.
- Camadas bloqueadas (zona/lote — sem fonte pública) já são representáveis como `pendente_fonte`; o bloqueio de dados permanece registrado para a SEDUR (base GIS), sem simulação.

---
*Phase: 04-georreferenciamento-e-territorio*
*Completed: 2026-06-13*

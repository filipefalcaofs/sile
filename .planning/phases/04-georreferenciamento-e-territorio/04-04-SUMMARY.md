---
phase: 04-georreferenciamento-e-territorio
plan: "04-04"
subsystem: geo
tags: [postgis, geojson, versionamento, vigencia, diff-auditado, geosalvador, arcgis, seed, pendente-fonte, sem-fachada]

# Dependency graph
requires:
  - phase: 04-01
    provides: "geo_layers/geo_features (geometry(Geometry,4326) + GiST driver-aware), models GeoLayer/GeoFeature, enums GeoLayerType/GeoLayerStatus, conexão pgsql_testing + PostgisTestCase (guard fail-not-skip)"
  - phase: 02-administracao-base
    provides: "padrão de import oficial versionado (CnaeImportService + database/data/) e AuditService.log"
provides:
  - "GeoLayerService: openVersion (fecha a vigente anterior sem apagar) + computeDiff (RN-005) + finalizeCount + auditLoad (territorio/carga-camada)"
  - "GeoJsonLayerImporter: carrega FeatureCollection em geo_features (ST_MakeValid(ST_SetSRID(ST_GeomFromGeoJSON))), idempotente por (type,version), diff auditado"
  - "Comando geo:importar {type} {arquivo} --versao --source (auto-descoberto, exit codes pt-BR)"
  - "Snapshots oficiais REAIS do GeoSalvador commitados em database/data/geo/ (bairros 171, restrições ZEIS 234, vias 800) em SRID 4326"
  - "GeoLayerSeeder driver-aware: carga real em pgsql; zona/lote como pendente_fonte (bloqueio comunicado, sem polígono inventado)"
affects: [04-05, 04-06, 04-07, 05-motor-de-enquadramento]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Versão de camada com vigência: openVersion fecha a anterior (substituida + valid_to) e abre a nova vigente; idempotente por (type,version)"
    - "Carga GeoJSON uma feição por insert (Pitfall 6) com ST_MakeValid (Pitfall 8); origem já 4326 (outSR=4326) dispensa ST_Transform (Pitfall 5)"
    - "Idempotência real do import: re-import da mesma versão apaga e reinsere as feições (carga reproduzível); nova versão preserva a anterior (RN-004)"
    - "Dado oficial público versionado/commitado em database/data/geo/ (precedente do CSV de CNAEs) — carga reproduzível, sem rede em teste/CI"
    - "Seeder driver-aware: PostGIS só em pgsql; zona/lote pendente_fonte em qualquer driver (sem geometria)"

key-files:
  created:
    - "app/Services/Geo/GeoLayerService.php"
    - "app/Services/Geo/GeoJsonLayerImporter.php"
    - "app/Console/Commands/ImportGeoLayerCommand.php"
    - "database/seeders/GeoLayerSeeder.php"
    - "database/data/geo/bairros.geojson"
    - "database/data/geo/restricoes-ambientais.geojson"
    - "database/data/geo/vias.geojson"
    - "tests/Fixtures/geo/bairros-amostra.geojson"
    - "tests/Unit/Geo/GeoLayerServiceTest.php"
    - "tests/Feature/Geo/ImportGeoLayerTest.php"
    - "tests/Feature/Geo/ImportGeoLayerCommandTest.php"
    - "tests/Feature/Geo/GeoLayerSeederTest.php"
    - "tests/Feature/Geo/GeoLayerSeederPostgisTest.php"
  modified:
    - "database/seeders/DatabaseSeeder.php"

key-decisions:
  - "computeDiff conta a carga de nova versão (e o re-import) como remove + add, NUNCA alteração in-place: a fonte (GeoJSON GeoSalvador) não traz chave estável de feição. RN-005 exige o resumo adicionadas/removidas — exatamente o que o diff entrega; 'alteradas' fica reservado (sempre 0) para evolução futura com identificador estável."
  - "Opção --version do comando renomeada para --versao: --version colide com a opção global do Symfony Console (LogicException 'An option named version already exists')."
  - "Idempotência do importer = apagar+reinserir as feições da versão (carga reproduzível), não pular — escolhido para o re-import refletir o snapshot atual."
  - "vias.geojson é um extrato REAL e limitado de logradouros (recorte central de Salvador, 800 de 1934 features no envelope) — dado oficial real, não inventado: 'muda a carga, nunca a lógica'."
  - "Cenário @group postgis do seeder vive em arquivo próprio (GeoLayerSeederPostgisTest) — PHPUnit casa classe↔arquivo pelo basename, e o filtro --group exige a classe no arquivo nomeado."

patterns-established:
  - "Importador espacial fino (GeoJsonLayerImporter) consumido pelo seeder e pelo comando — ponto único de carga auditada de camadas"
  - "Snapshot oficial commitado por camada em database/data/geo/ substituível pela base SEDUR sem reescrever lógica"

# Metrics
duration: ~30 min
completed: 2026-06-13
---

# Fase 4 Plano 04: Camadas Versionadas + Carga Real GeoSalvador Summary

**GeoLayerService (vigência + diff auditado RN-004/RN-005), GeoJsonLayerImporter + comando `geo:importar` idempotente e auditado, e carga de dados oficiais REAIS do GeoSalvador (171 bairros, 234 restrições ZEIS, 800 vias) commitada em `database/data/geo/`; zona e lote ficam `pendente_fonte` — bloqueio comunicado, sem polígono inventado.**

## O que foi entregue (HU-036, HU-034, HU-035, HU-032)

- **`GeoLayerService`** — versionamento com vigência: `openVersion` fecha a versão vigente anterior do mesmo tipo (`status = substituida`, `valid_to` preenchido) sem apagá-la e abre a nova vigente; idempotente por `(type, version)`. `computeDiff` resume adicionadas/removidas (RN-005). `auditLoad` registra a carga em `territorio/carga-camada` com diff + `rules_version`. `finalizeCount` fixa `feature_count`.
- **`GeoJsonLayerImporter`** — carrega `FeatureCollection` em `geo_features`, uma feição por insert (`ST_MakeValid(ST_SetSRID(ST_GeomFromGeoJSON(...), 4326))`), idempotente por `(type, version)` (apaga+reinsere a versão), preserva versões anteriores e audita o diff. Feição com geometria ausente entra no relatório (`invalidas`), nunca descartada em silêncio.
- **Comando `geo:importar {type} {arquivo} --versao= --source=`** — auto-descoberto; valida tipo/arquivo/JSON/versão com mensagens pt-BR e exit codes; relatório Lidas/Inseridas/Inválidas.
- **Carga real GeoSalvador** versionada em `database/data/geo/` e carregável pelo `GeoLayerSeeder` (driver-aware); zona/lote `pendente_fonte`.

## Task Commits

1. **Task 1 — GeoLayerService (vigência + diff auditado)** — `23e525b` (feat) — TDD: RED (classe inexistente) → GREEN (8 testes Unit em SQLite) → pint.
2. **Task 2 — GeoJsonLayerImporter + comando geo:importar** — `bed3b11` (feat) — TDD: RED → GREEN (5 testes @group postgis + 4 de validação SQLite) → pint.
3. **Task 3 — snapshots reais + GeoLayerSeeder driver-aware + pendentes** — `bd58707` (feat) — RED → GREEN (2 SQLite + 1 @group postgis) → pint.

_(Commits do 04-03 — geocodificação — intercalaram em paralelo, em arquivos distintos; sem conflito.)_

## Camadas: o que foi carregado vs bloqueado

Fonte: ArcGIS REST público do GeoSalvador (`access:public`), exportado `f=geojson&outSR=4326` em 2026-06-13.

| Camada | Veredito | Snapshot (database/data/geo/) | Features | URL da fonte |
|---|---|---|---|---|
| **Bairro** (HU-034) | CARREGADA (requisito rígido) | `bairros.geojson` | **171** | `.../Bairros/MapServer/0/query?where=1=1&outFields=*&f=geojson&outSR=4326` |
| **Restrição** (HU-035) | CARREGADA | `restricoes-ambientais.geojson` | **234** (ZEIS, PDDU 2016) | `.../PDDU_2016/MapServer/76/query?...&f=geojson&outSR=4326` (ZEIS_Emendas_region) |
| **Via** (HU-032) | CARREGADA (extrato real limitado) | `vias.geojson` | **800** (recorte central; envelope -38.53,-13.00,-38.49,-12.96) | `.../Logradouros/MapServer/0/query?...&f=geojson&outSR=4326` |
| **Zona urbanística** (HU-031) | BLOQUEADA — `pendente_fonte` | — (sem geometria) | 0 | Sem camada vetorial pública (pasta ArcGIS `LOUOS` vazia; Quadro 10 só em PDF) |
| **Lote cadastral** (HU-033) | BLOQUEADA — `pendente_fonte` | — (sem geometria) | 0 | Restrito (Cadastro Multifinalitário / SEFAZ) |

Snapshots commitados (~4,4 MB): a carga lê o arquivo, **nunca a rede** em teste/CI (reprodutível).

## Assinaturas (contrato para 04-05 e seguintes)

```php
// app/Services/Geo/GeoLayerService.php
public function openVersion(GeoLayerType $type, string $version, string $source, ?CarbonInterface $validFrom = null): GeoLayer;
public function finalizeCount(GeoLayer $layer): void;
public function computeDiff(?GeoLayer $previous, GeoLayer $current): array; // {adicionadas, alteradas, removidas}
public function auditLoad(GeoLayer $layer, array $diff): void;

// app/Services/Geo/GeoJsonLayerImporter.php
public function import(GeoLayerType $type, string $version, string $source, array $featureCollection): array;
// retorna {lidas, inseridas, invalidas[], version, feature_count}

// Comando: geo:importar {type} {arquivo} --versao= --source=
```

## Decisões (com fundamentação)

- **Diff = remove + add (RN-005):** sem chave estável por feição na fonte, a nova versão e o re-import são contabilizados como remoção + adição, não alteração in-place. `alteradas` é sempre 0 (reservado para quando houver identificador estável de feição). RN-005 pede o resumo de adicionadas/removidas — entregue.
- **`--versao` em vez de `--version`:** `--version` é opção global reservada do Symfony Console; usar como nome de opção própria lança `LogicException`. Renomeado para `--versao` (pt-BR, consistente com `arquivo`).
- **Idempotência = apagar+reinserir:** o re-import da mesma `(type, version)` limpa as feições da versão e reinsere — carga reproduzível que reflete o snapshot atual; nova versão NÃO apaga a anterior (RN-004).
- **`vias.geojson` é extrato real limitado:** o dataset completo de logradouros é grande demais para versionar; o recorte central (800 de 1934 no envelope) é dado oficial real — muda a carga, nunca a lógica.
- **`GeoLayerSeederPostgisTest` em arquivo próprio:** o cenário @group postgis foi separado do `GeoLayerSeederTest` (SQLite) porque o PHPUnit casa classe↔arquivo pelo basename ao filtrar por caminho + `--group`.

## Evidência fresca (sem fachada)

- **Comando contra Postgres+PostGIS real (dev `sile`):** `geo:importar` carregou os snapshots completos — **bairro 171, restrição 234, via 800** feições inseridas.
- **Geometria espacial de verdade:** `ST_IsValid` = true em 100% das feições de cada camada (171/171, 234/234, 800/800); `ST_SRID = 4326`.
- **Auditoria (RN-002/RN-005):** três `activity` `territorio/carga-camada` com `properties.diff.adicionadas` (171/234/800), `rules_version` por camada e `result = sucesso`.
- **Estado canônico do dev após `db:seed --class=GeoLayerSeeder`:** bairro/restrição/via `vigente` com features reais; **zona/lote `pendente_fonte`, feature_count 0** (bloqueio comunicado).
- **Testes:** suíte SQLite **429/429**; grupo **@group postgis 8/8** (smoke 04-01 + carga/idempotência/versionamento/diff + seeder real); pint limpo.

## Desvios do plano

1. **[Rule 3 — Bloqueio] `--version` → `--versao`** — colisão com a opção global do Symfony Console; renomeado e documentado. Commit `bed3b11`.
2. **[Organização de teste] Arquivo extra `GeoLayerSeederPostgisTest.php`** — o cenário @group postgis do seeder não pode coexistir com o cenário SQLite no mesmo arquivo sob filtro `--group` + caminho (basename). Separado em arquivo próprio (mais um teste que o plano listou). Sem scope creep.
3. **[Cobertura extra] `ImportGeoLayerCommandTest.php`** — validação de entrada do comando (tipo/arquivo/JSON/versão) em SQLite, sem exigir PostGIS para provas que fazem short-circuit antes da carga. Adição de cobertura, fora da lista do plano.

Nenhuma mudança arquitetural; nenhuma dependência nova; nenhum arquivo do 04-03 tocado.

## Para o orquestrador registrar no STATE/ROADMAP (bloqueio)

- **Zona urbanística LOUOS (HU-031)** e **lote cadastral (HU-033)**: sem fonte vetorial pública confirmada — seedadas como `pendente_fonte` (feature_count 0). Bloqueio precisa ficar explícito porque a Fase 5 depende da zona; pendência SEDUR (base GIS / Cadastro Multifinalitário SEFAZ). NUNCA polígono inventado.
- **Classificação viária LOUOS (Quadros 11/11A):** a geometria das vias entra (PDDU/logradouros); o atributo de classificação operacional fica pendente da confirmação SEDUR (PDDU ≟ LOUOS Mapa 04).
- **Licença/uso GeoSalvador:** endpoint `access:public`; confirmar termos de uso/atribuição com a SEDUR (provável trivial — órgão dono do dado).

## Prontidão para a próxima fatia

- `GeoLayerService` e `GeoJsonLayerImporter` são o contrato de carga consumido por 04-05 (identificação territorial / TerritoryService) e pelo motor da Fase 5.
- Camadas reais (bairro/restrição/via) disponíveis no Postgres de dev; zona/lote bloqueadas e visíveis para a UI (04-07).
- Snapshot substituível pela base oficial da SEDUR quando entregue, sem reescrever lógica.

---
*Phase: 04-georreferenciamento-e-territorio*
*Completed: 2026-06-13*

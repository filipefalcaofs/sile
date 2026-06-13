# Fase 4: Georreferenciamento e Território - Pesquisa

**Pesquisado em:** 2026-06-13
**Domínio:** Geoprocessamento (PostGIS) + geocodificação (Nominatim/OSM) + mapa web (Leaflet) sobre Laravel 13 / Inertia v3 / React 19
**Confiança geral:** ALTA (fontes oficiais consultadas e endpoints públicos verificados em chamada real)

## Resumo

Esta fase tem dois eixos de risco distintos. O **eixo técnico** (PostGIS no Laravel 13, geocodificação atrás de contrato, mapa Leaflet) é **baixo risco e totalmente entregável**: o Laravel 13 tem suporte nativo a colunas `geometry`/`geography` com SRID, o PostGIS 3.5 já está habilitado no banco, e os padrões de contrato+provider+cache+throttle da Fase 3/3.1 (`CnpjLookup`) se replicam quase 1:1 no `Geocoder`. O **eixo de dados** (quais camadas têm fonte pública real) é o que decide o escopo: foi verificado por chamada real ao ArcGIS REST público do **GeoSalvador** (`https://geo.salvador.ba.gov.br/arcgis/rest/services`, `access:public`, `Query` habilitado, exporta `f=geojson` com `outSR=4326`).

O resultado da verificação: **bairro, eixo viário (logradouros) e restrições ambientais (SAVAM/ZEIS/Mata Atlântica) são ENTREGÁVEIS** a partir de dados públicos reais hoje. **Zona urbanística da LOUOS (Quadro 10) e lote cadastral com inscrição imobiliária são BLOQUEADOS** — não há camada vetorial pública confirmada (a pasta `LOUOS` do ArcGIS está vazia; o zoneamento da Lei 9.148/2016 existe só em PDF; o lote cadastral chaveado por inscrição vive no Cadastro Multifinalitário/SEFAZ restrito). A **classificação viária da LOUOS** (Quadros 11/11A) é parcial: a hierarquia viária do PDDU 2016 é pública, mas a correspondência com o "Mapa 04 — Classificação Viária" da LOUOS precisa de confirmação da SEDUR.

O maior risco de execução é **testar PostGIS sem fachada**: a suíte hoje roda em SQLite `:memory:`, que não executa funções espaciais. A recomendação é uma conexão Postgres+PostGIS de teste dedicada para os testes espaciais (obrigatória no CI via service container, nunca pulada em silêncio), com as migrations de geo sendo driver-aware para não quebrar os 385 testes SQLite existentes.

**Recomendação primária:** colunas `geometry(...,4326)` nativas do Laravel + repositório espacial fino usando `DB::raw`/`whereRaw` para `ST_Contains`/`ST_DWithin`/`ST_Area`/`ST_GeomFromGeoJSON` (sem dependência nova no backend); carregar **bairro + restrições + eixo viário de fontes públicas reais** e deixar **zona LOUOS e lote cadastral explicitamente bloqueados** no STATE/ROADMAP e com aviso na UI; conexão Postgres+PostGIS dedicada para testes espaciais no CI.

---

## Veredito por Camada (decisão de escopo — entregável vs bloqueada)

Evidência: chamada real ao ArcGIS REST do GeoSalvador em 2026-06-13 (HTTP 200, `access:public`, `capabilities: Map,Query,Data`, `supportedQueryFormats: JSON, geoJSON, PBF`).

| Camada | Veredito | Fonte pública real | Acesso/Formato | Confiança |
|--------|----------|--------------------|----------------|-----------|
| **Bairro** | ✅ ENTREGÁVEL | GeoSalvador `Bairros/MapServer/0` (oficial, instituído por Dec. 38.776/2024) | ArcGIS REST `query?where=1=1&outFields=*&f=geojson&outSR=4326` | ALTA |
| **Bairro (alternativa nacional)** | ✅ ENTREGÁVEL | IBGE — Arquivo geoespacial de Bairros por UF (BA) | GeoFTP shp/gpkg (EPSG:4674) | ALTA |
| **Eixo viário (logradouro/geometria)** | ✅ ENTREGÁVEL | GeoSalvador `Logradouros/MapServer/0` (DBARCGIS.LOGRADOURO) | ArcGIS REST `f=geojson&outSR=4326` | ALTA |
| **Restrições ambientais (SAVAM, ZEIS, Mata Atlântica, APA, parques)** | ✅ ENTREGÁVEL (validar operacionalidade) | GeoSalvador `PDDU_2016/MapServer` (camadas `Savam_*`, `ZEIS_Emendas_region`, `Mata_Atlantica_Classificacao`) | ArcGIS REST `f=geojson&outSR=4326` | MÉDIA |
| **Classificação viária LOUOS (Quadros 11/11A)** | ⚠️ PARCIAL / CONFIRMAR | GeoSalvador `PDDU_2016` traz hierarquia (`Sist_Via_Via_Art_*` arterial, `Via_Col_*` coletora, `Via_Exp_*` expressa) — é classificação do PDDU 2016, não o Mapa 04 da LOUOS (só PDF) | ArcGIS REST (geometria) + PDF (LOUOS) | MÉDIA |
| **Zona urbanística LOUOS (Quadro 10)** | ⛔ BLOQUEADA | Nenhuma camada vetorial pública de zoneamento da Lei 9.148/2016. Pasta ArcGIS `LOUOS` vazia; Mapa 01A só em PDF. PDDU traz macrozonas/macroáreas (instrumento mais amplo, NÃO o zoneamento LOUOS) | — | ALTA (do bloqueio) |
| **Lote cadastral (com inscrição imobiliária)** | ⛔ BLOQUEADA | Consulta pública tem "Quadra 2017" e "Área de propriedade particular 2017", mas não o lote cadastral chaveado por inscrição (Cadastro Multifinalitário/SEFAZ restrito) | — | ALTA (do bloqueio) |

### URLs concretas confirmadas

- **GeoSalvador (raiz):** `https://geo.salvador.ba.gov.br/arcgis/rest/services?f=json` — pastas: `ANALISES, APA, FGM, FMLF, GEOPROCESSAMENTO, Hosted, LOUOS (vazia), MAPA_BASE, SAT_SIG, SECIS, SECULT, SEDUR, SEFAZ, SEINFRA, SEMOB, SEMPRE, SEMPS, SIG, SIGHML, SMS, Terreiros, Utilities`.
- **Bairros (vigente, Dec. 38.776/2024):** `https://geo.salvador.ba.gov.br/arcgis/rest/services/Bairros/MapServer/0` — campos `NOME_BAIRRO`, `GMLID`, `INSTITUIDO_POR`, `ALTERADO_POR`. Geometria polígono. **SRID nativo 31984 (SIRGAS 2000 / UTM 24S)**; exporta em 4326 com `outSR=4326`. `maxRecordCount: 2000`.
- **Bairros (alternativo, consulta agregada):** `https://geo.salvador.ba.gov.br/arcgis/rest/services/MAPA_BASE/MAPA_BASE_Consulta/MapServer/164` ("Bairros (Dec.32791_2020) Vigente").
- **Logradouros:** `https://geo.salvador.ba.gov.br/arcgis/rest/services/Logradouros/MapServer/0`.
- **Restrições/ambiental:** `https://geo.salvador.ba.gov.br/arcgis/rest/services/PDDU_2016/MapServer` (ex.: layer 76 `ZEIS_Emendas_region`, layer 63 `Mata_Atlantica_Classificacao`, layers 66–75 `Savam_*`).
- **IBGE bairros BA (shp):** `https://geoftp.ibge.gov.br/organizacao_do_territorio/malhas_territoriais/malhas_de_setores_censitarios__divisoes_intramunicipais/censo_2022/bairros/shp/UF/BA/` (gpkg em `.../gpkg/UF/BA/BA_bairros_CD2022.gpkg`). Campos `CD_BAIRRO`, `NM_BAIRRO`. EPSG:4674.
- **Catálogo SEDUR (WFS oficial):** `https://servicos.sedur.salvador.ba.gov.br/geoservicos` — expõe "Bairros oficiais", "Logradouros", "Revitalizar" via WFS/OGC (NÃO expõe zoneamento nem lote). Página web bloqueia user-agent genérico (403) mas o serviço OGC é público.
- **LOUOS — mapas oficiais (PDF, referência legal, não vetorial):** `https://sedur.salvador.ba.gov.br/louos-2016/18-legislacao/63-louos-mapas` (Mapa 01A Zoneamento, 01B ZEIS, 02A SAVAM, 04 Classificação Viária). **Texto da Lei 9.148/2016** para parametrização dos quadros: `https://sedur.salvador.ba.gov.br/louos-2016/18-legislacao/62-louos`.

### Implicação para o planner

1. **Carregar agora (seed dev com dado oficial real):** bairro (GeoSalvador, primário) + eixo viário (logradouros) + restrições ambientais (PDDU/SAVAM/ZEIS). Estas atendem HU-034 (bairro), HU-035 (restrições) e a geometria de HU-032 (via).
2. **Bloquear explicitamente (STATE.md + ROADMAP + feature flag/aviso na UI, nunca inventar polígono):**
   - **Zona urbanística (HU-031)** — sem fonte pública; é o insumo do Quadro 10 do motor da Fase 5. Bloqueio precisa ficar visível porque a Fase 5 depende dele.
   - **Lote cadastral com inscrição (HU-033, e HU-037 RN-004/RN-005)** — a infraestrutura espacial (`ST_Area`/`ST_Intersection`/sobreposição) entra pronta e testada com fixtures, mas a validação real fica bloqueada até a base de lotes da SEDUR/SEFAZ.
   - **Classificação viária LOUOS (atributo dos Quadros 11/11A)** — geometria do eixo viário entra; o atributo de classificação operacional fica pendente da confirmação SEDUR (PDDU ≟ LOUOS Mapa 04).
3. Reforça a decisão travada do CONTEXT: `integrations.geocoding.base_url` e a estrutura `geo_layers` versionada permitem **trocar dado público pela base SEDUR/SIGIS-CA2000 quando entregue, sem reescrever lógica** (a carga muda, o motor não).

---

## Standard Stack

### Backend — sem dependência nova (recomendado)

| Biblioteca | Versão | Propósito | Por que é o padrão |
|-----------|--------|-----------|--------------------|
| PostGIS | 3.5 (já habilitado) | Armazenamento + consulta espacial | Já no `docker/postgres/Dockerfile`; padrão de fato para SIG em Postgres |
| Laravel Schema nativo | 13.8 | `geometry()`/`geography()` com SRID | Suporte nativo desde a unificação de tipos espaciais (PR laravel/framework #49634); evita dependência |
| `DB::raw`/`whereRaw`/`selectRaw` | core | `ST_Contains`, `ST_DWithin`, `ST_Distance`, `ST_Intersection`, `ST_Area`, `ST_GeomFromGeoJSON` | Resolve 100% das consultas da fase sem novo pacote (preferência do projeto: evitar deps) |

### Backend — alternativa avaliada (somente se houver decisão explícita)

| Em vez de | Poderia usar | Trade-off |
|-----------|--------------|-----------|
| `DB::raw` + `ST_AsGeoJSON` | `matanyadaev/laravel-eloquent-spatial` **v4.7.0** (suporte Laravel 13 desde 2026-03-18; Postgres 12–16 + PostGIS 3.4; usa `brick/geo`) | Ganha objetos PHP tipados (`Point`/`Polygon`), casts automáticos e scopes `whereContains`/`whereWithin`/`whereDistance`; **custo:** nova dependência (+`brick/geo`) que o projeto prefere evitar. Justificável se a (de)serialização GeoJSON em PHP ficar dolorosa. |
| Schema nativo | `clickbar/laravel-magellan` (L11–13) | A maioria dos helpers de schema foi **deprecada** em favor dos métodos nativos do Laravel; só `magellanBox2D/3D` e `GeometryCollection` seguem úteis. Não recomendado só para esta fase. |

**Recomendação:** ficar no nativo + `DB::raw`. A superfície espacial da fase é pequena e estável (5 funções), e o padrão de repositório fino isola o SQL. Reavaliar `matanyadaev` só se surgir necessidade real de objetos geométricos tipados no domínio.

### Geocodificação

| Biblioteca | Versão | Propósito | Observação |
|-----------|--------|-----------|------------|
| Nominatim público (OSM) | API atual | Endereço → lat/lng + endereço normalizado | Atrás do contrato `Geocoder`; `base_url` parametrizável (trocável por self-host sem deploy). Política: ≤1 req/s, User-Agent obrigatório, cache obrigatório |
| `Illuminate\Http\Client` (Http) | core | Cliente HTTP com retry/timeout/backoff | Reusa exatamente o padrão de `BrasilApiCnpjLookup` (Fase 3.1) |

### Frontend — dependência nova aprovada (CONTEXT)

| Biblioteca | Versão | Propósito | Compatibilidade |
|-----------|--------|-----------|-----------------|
| `leaflet` | ^1.9.0 | Mapa + tiles OSM | Estável; v2 ainda em alpha — **não usar** |
| `react-leaflet` | ^5.0.0 | Componentes React para Leaflet | **Requer React 19** (peer dep) — casa com o projeto (React 19.2). v5 lançada 2024-12-14 |
| `@types/leaflet` | ^1.9.x (dev) | Tipos TS | Projeto usa TS 6 |

**Instalação:**

```bash
# Frontend (aprovado no CONTEXT)
npm install leaflet react-leaflet
npm install -D @types/leaflet

# Backend: NENHUMA dependência nova (PostGIS já habilitado; schema e ST_* são nativos)
```

---

## Architecture Patterns

### Estrutura sugerida (segue a organização atual do projeto)

```
app/
├── Services/
│   ├── Geo/
│   │   ├── Geocoder.php                 # interface (contrato) — espelha CnpjLookup
│   │   ├── NominatimGeocoder.php        # provider real + cache + retry/backoff
│   │   ├── GeocodeResult.php            # DTO readonly (lat/lng/display_name/confianca)
│   │   ├── GeocoderException.php
│   │   ├── GeoLayerService.php          # carga/versão/vigência + diff auditado (HU-036)
│   │   ├── TerritoryService.php         # identificação por ponto (consome o repositório)
│   │   ├── SpatialRepository.php        # interface — isola o SQL PostGIS
│   │   └── PostgisSpatialRepository.php # implementação real (DB::raw/ST_*)
│   └── Cnpj/                            # padrão existente a espelhar
├── Models/
│   ├── GeoLayer.php                     # type, version, valid_from, valid_to, source, rules_version, feature_count
│   └── GeoFeature.php                   # layer_id, geometry (4326), properties (jsonb)
├── Enums/
│   └── GeoLayerType.php                 # bairro|zona|via|lote|restricao
└── Console/Commands/
    └── ImportGeoLayerCommand.php        # import GeoJSON versionado/auditado (idempotente por type+version)
```

### Padrão 1: Contrato + provider + cache (geocodificação) — espelha `CnpjLookup`

**O que:** interface `Geocoder` com provider `NominatimGeocoder`, cache só de sucesso, retry/timeout/backoff via `Settings::get`, toggle `features.geocoding`, `integrations.geocoding.base_url` parametrizável.
**Quando usar:** toda a HU-029. A Fase 13 (self-host ou base SEDUR) troca só o binding.
**Exemplo:** ver "Code Examples → Geocoder".

### Padrão 2: Camadas como dados versionados (HU-036 RN-004/RN-005)

- `geo_layers`: uma linha por `(type, version)` com `valid_from`/`valid_to`, `source`, `rules_version`, `feature_count`. Carga de nova versão **não apaga** a anterior.
- `geo_features`: `layer_id`, `geometry` (SRID 4326, índice GiST), `properties` (jsonb).
- **Consulta operacional** usa a versão vigente (`valid_to IS NULL` ou data atual entre `valid_from`/`valid_to`); **reprodução/auditoria** usa a versão vigente na data da decisão (mesma disciplina do versionamento de regras — alinhado a HU-107 RN-009).
- Carga **idempotente** por `(type, version)`; o `GeoLayerService` calcula e audita o diff (features +/−/~) via `AuditService` (`log_name` 'territorio', `event` 'carga-camada', `rules_version` = versão da camada).

### Padrão 3: Identificação territorial via repositório fino (`TerritoryService`)

- `TerritoryService` recebe lat/lng e devolve bairro/zona/via/lote/restrições, **registrando a versão de cada camada consultada** no resultado e na auditoria.
- O SQL espacial fica em `PostgisSpatialRepository` atrás da interface `SpatialRepository` — isso permite **fake em memória** para unit-testar os consumidores (motor da Fase 5), enquanto o SQL real tem teste de integração PostGIS próprio (ver "Estratégia de testes").
- `ST_Contains`/`ST_Intersects` para bairro/zona/lote (ponto-em-polígono); `ST_DWithin` + `ST_Distance` para via mais próxima; interseção para restrições.

### Padrão 4: Import de GeoJSON oficial (comando auditado)

- Comando `geo:importar {type} {arquivo|url} {--version=}`: lê GeoJSON (FeatureCollection), itera features, insere via `ST_SetSRID(ST_GeomFromGeoJSON(:geom), 4326)`, em transação por lote.
- Para a fonte ArcGIS (GeoSalvador), exportar já em 4326 (`outSR=4326`) elimina reprojeção; para shapefile/gpkg (IBGE, EPSG:4674) ou WFS UTM (EPSG:31984), reprojetar com `ST_Transform(geom, 4326)` na carga.
- Dado oficial público versionado em `database/data/geo/` (precedente do CSV oficial de CNAEs em `database/data/`).

### Anti-padrões a evitar

- **Polígono inventado para "destravar" zona/lote:** proibido (regra de entrega funcional). Camada sem fonte pública = bloqueada e comunicada.
- **Geometria sem SRID / SRID misturado:** sempre 4326 em todas as colunas e literais.
- **Consulta espacial sem índice GiST:** vira full scan; sempre criar o índice.
- **Apagar a versão anterior na carga:** quebra a reprodução por data da decisão (HU-036 RN-004).

---

## Don't Hand-Roll

| Problema | Não construir | Usar | Por quê |
|----------|---------------|------|---------|
| Ponto-em-polígono | Algoritmo ray-casting em PHP | `ST_Contains`/`ST_Intersects` no PostGIS | Lida com MultiPolygon, buracos, borda e índice GiST |
| Distância em metros entre lat/lng | Haversine manual | `ST_DWithin`/`ST_Distance` com cast `::geography` | Precisão geodésica e uso de índice |
| Sobreposição de áreas (HU-037) | Cálculo de interseção próprio | `ST_Area(ST_Intersection(a,b)) / ST_Area(a)` | Robustez topológica |
| Parse de GeoJSON para geometria | Parser próprio | `ST_GeomFromGeoJSON` | Valida e converte no banco |
| Reprojeção de coordenadas | Fórmulas de projeção | `ST_Transform` (PostGIS) ou `outSR=4326` no ArcGIS | PROJ embutido, correto para SIRGAS 2000/UTM |
| Geocodificação | Heurística de endereço | Nominatim atrás de contrato | Base OSM mantida; self-host quando escalar |
| Throttle do provedor | Sleep manual | `RateLimiter::for('geocoding', ...)` parametrizado | Mesmo padrão de `cnpj-lookup` (Fase 3.1) |

---

## Common Pitfalls

### Pitfall 1: SQLite de teste não executa PostGIS (CRÍTICO)
**O que quebra:** qualquer `ST_*` na suíte atual (SQLite `:memory:`) — e, pior, as próprias migrations de geo (índice GiST) falham no SQLite, derrubando os 385 testes existentes.
**Como evitar:** migrations driver-aware (criar índice GiST só em `pgsql`); conexão Postgres+PostGIS de teste dedicada para os testes espaciais. Ver "Estratégia de testes".

### Pitfall 2: SRID misturado
**O que quebra:** `ST_Contains`/`ST_DWithin` lançam "Operation on mixed SRID geometries".
**Como evitar:** coluna `geometry(...,4326)`; todo literal com `ST_SetSRID(ST_MakePoint(:lng,:lat),4326)`; GeoJSON com `ST_SetSRID(ST_GeomFromGeoJSON(...),4326)`.

### Pitfall 3: Ordem de coordenadas lng/lat × lat/lng
**O que quebra:** ponto cai no oceano/fora de Salvador.
**Detalhe:** GeoJSON e `ST_MakePoint` usam **[longitude, latitude]**; o Leaflet usa **[latitude, longitude]**. `ST_X` = lng, `ST_Y` = lat. Salvador ≈ lat −12.97, lng −38.5.
**Como evitar:** ponto único de conversão no DTO; teste com coordenada conhecida.

### Pitfall 4: `geometry` × `geography` para distância em metros
**O que quebra:** `ST_Distance` em `geometry(4326)` retorna **graus**, não metros — "via mais próxima" fica errada.
**Como evitar:** para distância/raio em metros, usar `::geography`: `ST_DWithin(via.geom::geography, ponto::geography, :metros)` e `ST_Distance(...::geography)`. Armazenar como `geometry(...,4326)` (consultas de contenção) e castar para `geography` só onde precisar de metros.

### Pitfall 5: Reprojeção da fonte (SIRGAS 2000 / UTM 24S → WGS84)
**O que quebra:** dado nativo do GeoSalvador é **EPSG:31984** (UTM 24S) e o do IBGE é **EPSG:4674** (SIRGAS 2000 geográfico); inserir como 4326 sem transformar coloca tudo no lugar errado.
**Como evitar:** exportar do ArcGIS com `outSR=4326` (faz a reprojeção no servidor) **ou** importar com o SRID de origem e `ST_Transform(geom, 4326)` na carga.

### Pitfall 6: `ST_GeomFromGeoJSON` não aceita FeatureCollection
**O que quebra:** passar a coleção inteira gera erro — a função só aceita **uma geometria**.
**Como evitar:** iterar `features[]` e passar `feature.geometry`; gravar `feature.properties` no jsonb.

### Pitfall 7: Polígono × MultiPolígono na mesma coluna tipada
**O que quebra:** coluna `geometry(Polygon,4326)` rejeita MultiPolygon (bairros/zonas frequentemente são MultiPolygon).
**Como evitar:** normalizar com `ST_Multi(...)` e usar subtipo `multiPolygon`, **ou** usar coluna genérica `geometry(Geometry,4326)` para aceitar ambos.

### Pitfall 8: Geometria inválida no dado de origem
**O que quebra:** polígonos auto-interseccionados fazem `ST_Contains`/`ST_Intersection` falhar.
**Como evitar:** `ST_MakeValid` na carga e/ou checar `ST_IsValid`; registrar features inválidas no diff auditado (nunca descartar em silêncio).

### Pitfall 9: Leaflet quebra no SSR do Inertia v3
**O que quebra:** `ReferenceError: window is not defined` — o Leaflet acessa `window` na importação.
**Como evitar:** montar o mapa só no cliente (guarda `mounted` com `useEffect`, ou `React.lazy`/import dinâmico do componente do mapa). O Inertia v3 tem SSR ligado por padrão.

### Pitfall 10: Ícone do marcador some no build Vite
**O que quebra:** marcadores invisíveis em produção (caminho das imagens do Leaflet quebra no bundle).
**Como evitar:** importar `marker-icon.png`/`marker-icon-2x.png`/`marker-shadow.png` e `L.Icon.Default.mergeOptions({...})`; importar `leaflet/dist/leaflet.css`; dar **altura explícita** ao `MapContainer`.

### Pitfall 11: `maxRecordCount` do ArcGIS (paginação)
**O que quebra:** o GeoSalvador limita a 2000 features por resposta — camadas grandes (lotes/quadras) vêm truncadas.
**Como evitar:** paginar com `resultOffset`/`resultRecordCount`; para bairros (~160) uma página basta.

---

## Code Examples

### Migration — colunas geometry + índice GiST driver-aware

```php
// database/migrations/xxxx_create_geo_features_table.php
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

Schema::create('geo_features', function (Blueprint $table) {
    $table->id();
    $table->foreignId('geo_layer_id')->constrained()->cascadeOnDelete();
    // Genérico aceita Polygon e MultiPolygon; SRID 4326 fixo.
    $table->geometry('geometry', subtype: 'geometry', srid: 4326);
    $table->jsonb('properties')->default('{}');
    $table->timestamps();
});

// Índice espacial só no PostgreSQL — mantém a suíte SQLite verde (Pitfall 1).
if (DB::getDriverName() === 'pgsql') {
    DB::statement('CREATE INDEX geo_features_geometry_gist ON geo_features USING GIST (geometry)');
}
```

> Nota: `$table->spatialIndex('geometry')` também gera `USING GIST` no Postgres, mas o `DB::statement` guardado por driver é o caminho mais seguro para não quebrar o SQLite.

### Consultas espaciais via DB::raw (TerritoryService → PostgisSpatialRepository)

```php
// Bairro/zona/lote por ponto (contenção) — versão vigente da camada
$bairro = DB::table('geo_features as f')
    ->join('geo_layers as l', 'l.id', '=', 'f.geo_layer_id')
    ->where('l.type', 'bairro')
    ->whereNull('l.valid_to') // vigente; para reprodução, filtrar por data da decisão
    ->whereRaw('ST_Contains(f.geometry, ST_SetSRID(ST_MakePoint(?, ?), 4326))', [$lng, $lat])
    ->selectRaw("f.properties->>'NOME_BAIRRO' as nome, l.version")
    ->first();

// Via mais próxima dentro de N metros (distância em METROS via ::geography — Pitfall 4)
$via = DB::table('geo_features as f')
    ->join('geo_layers as l', 'l.id', '=', 'f.geo_layer_id')
    ->where('l.type', 'via')->whereNull('l.valid_to')
    ->whereRaw('ST_DWithin(f.geometry::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)', [$lng, $lat, 50])
    ->orderByRaw('ST_Distance(f.geometry::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography)', [$lng, $lat])
    ->selectRaw('f.properties, l.version')
    ->first();

// Sobreposição polígono informado × lote oficial (HU-037 RN-004)
$ratio = DB::selectOne(
    'SELECT ST_Area(ST_Intersection(:informado::geometry, f.geometry))
          / NULLIF(ST_Area(:informado2::geometry), 0) AS sobreposicao
       FROM geo_features f JOIN geo_layers l ON l.id = f.geo_layer_id
      WHERE l.type = :tipo AND l.valid_to IS NULL
        AND ST_Intersects(f.geometry, :informado3::geometry)
      ORDER BY sobreposicao DESC LIMIT 1',
    ['informado' => $wkt, 'informado2' => $wkt, 'informado3' => $wkt, 'tipo' => 'lote'],
);
```

### Import de GeoJSON (uma feature por vez — Pitfall 6)

```php
foreach ($featureCollection['features'] as $feature) {
    DB::table('geo_features')->insert([
        'geo_layer_id' => $layer->id,
        // outSR=4326 na origem ArcGIS dispensa ST_Transform; para 4674/31984 usar ST_Transform(...,4326)
        'geometry' => DB::raw('ST_Multi(ST_MakeValid(ST_SetSRID(ST_GeomFromGeoJSON('
            . DB::connection()->getPdo()->quote(json_encode($feature['geometry'])) . '), 4326)))'),
        'properties' => json_encode($feature['properties']),
        'created_at' => now(), 'updated_at' => now(),
    ]);
}
```

### Geocoder (contrato) — espelha `BrasilApiCnpjLookup`

```php
// Política Nominatim: User-Agent obrigatório, ≤1 req/s, cache só de sucesso.
final class NominatimGeocoder implements Geocoder
{
    public function geocode(string $address): GeocodeResult
    {
        $baseUrl = rtrim((string) Settings::get(
            'integrations.geocoding.base_url',
            config('sile.integrations.geocoding.base_url'),
        ), '/');

        return Cache::remember("sile.geocoding." . sha1($address),
            (int) config('sile.integrations.geocoding.cache_ttl', 86400),
            function () use ($baseUrl, $address): GeocodeResult {
                $response = Http::withHeaders(['User-Agent' => config('sile.integrations.geocoding.user_agent')])
                    ->timeout((int) Settings::get('integrations.geocoding.timeout', config('sile.integrations.geocoding.timeout', 8)))
                    ->retry(
                        (int) Settings::get('integrations.geocoding.retries', config('sile.integrations.geocoding.retries', 2)),
                        (int) Settings::get('integrations.geocoding.backoff_ms', config('sile.integrations.geocoding.backoff_ms', 1000)),
                        throw: false,
                    )
                    ->acceptJson()
                    ->get("{$baseUrl}/search", [
                        'q' => $address, 'format' => 'jsonv2',
                        'addressdetails' => 1, 'countrycodes' => 'br', 'limit' => 1,
                    ]);

                if ($response->failed() || empty($response->json())) {
                    throw new GeocoderException($address);
                }
                return GeocodeResult::fromNominatim($response->json()[0]); // lat, lon, display_name, importance, address
            },
        );
    }
}
```

```php
// FortifyServiceProvider.php (boot) — throttle parametrizado, padrão 'cnpj-lookup'
RateLimiter::for('geocoding', function (Request $request) {
    return Limit::perMinute(
        (int) Settings::get('seguranca.throttle.geocoding.por_minuto', 60), // ~1 req/s
    )->by($request->user()?->id ?: $request->ip());
});
```

### Parâmetros novos (ParameterSeeder + config/sile.php)

```php
// ParameterSeeder::catalog() — grupos seguindo o padrão existente
'features.geocoding' => ['group' => 'features', 'type' => 'boolean', 'default_value' => '1',
    'validation_rules' => ['required','boolean'], 'description' => 'Habilita a geocodificação de endereços'],
'integrations.geocoding.base_url' => ['group' => 'integracoes', 'type' => 'string',
    'default_value' => 'https://nominatim.openstreetmap.org', 'validation_rules' => ['required','url'],
    'requires_connection_test' => true, 'description' => 'URL base do serviço de geocodificação (Nominatim; trocável por self-host)'],
'seguranca.throttle.geocoding.por_minuto' => ['group' => 'seguranca', 'type' => 'integer', 'default_value' => '60',
    'validation_rules' => ['required','integer','min:1','max:300'], 'description' => 'Limite de geocodificações por minuto por usuário (Nominatim ~1 req/s)'],
'geo.validacao.sobreposicao_minima' => ['group' => 'geo', 'type' => 'integer', 'default_value' => '50',
    'validation_rules' => ['required','integer','min:1','max:100'], 'description' => 'Percentual mínimo de sobreposição polígono × lote antes de alertar'],
```

```php
// config/sile.php — fallbacks (constantes técnicas timeout/retries/cache_ttl SÓ aqui, precedente [02-02])
'features' => ['geocoding' => true],
'geo' => ['validacao' => ['sobreposicao_minima' => 50]],
'seguranca' => ['throttle' => ['geocoding' => ['por_minuto' => 60]]],
'integrations' => ['geocoding' => [
    'base_url' => 'https://nominatim.openstreetmap.org',
    'user_agent' => 'SILE-SEDUR-Salvador/1.0 (contato@sedur.salvador.ba.gov.br)',
    'timeout' => 8, 'retries' => 2, 'backoff_ms' => 1000, 'cache_ttl' => 86400,
]],
```

### Mapa (react-leaflet v5) — montagem client-side + ícone + GeoJSON

```tsx
// resources/js/components/geo/map-imovel.tsx
import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import iconUrl from 'leaflet/dist/images/marker-icon.png';
import iconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png';
import shadowUrl from 'leaflet/dist/images/marker-shadow.png';
import { MapContainer, TileLayer, Marker, GeoJSON, Popup } from 'react-leaflet';

L.Icon.Default.mergeOptions({ iconUrl, iconRetinaUrl, shadowUrl }); // Pitfall 10

export function MapImovel({ lat, lng, camadas, onMove }: MapImovelProps) {
  return (
    <MapContainer center={[lat, lng]} zoom={17} style={{ height: 420 }}> {/* altura explícita */}
      <TileLayer
        attribution='&copy; OpenStreetMap'
        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
      />
      <Marker draggable position={[lat, lng]}
        eventHandlers={{ dragend: (e) => onMove(e.target.getLatLng()) }}> {/* HU-037 ajuste */}
        <Popup>Localização do imóvel</Popup>
      </Marker>
      {camadas?.map((c) => <GeoJSON key={c.id} data={c.geojson} />)} {/* overlay HU-036 */}
    </MapContainer>
  );
}
```

```tsx
// Carregar só no cliente (Pitfall 9 — Inertia v3 com SSR)
import { lazy, Suspense, useState, useEffect } from 'react';
const MapImovel = lazy(() => import('@/components/geo/map-imovel').then(m => ({ default: m.MapImovel })));

export function MapaSection(props: MapImovelProps) {
  const [mounted, setMounted] = useState(false);
  useEffect(() => setMounted(true), []);
  if (!mounted) return <div className="h-[420px] animate-pulse rounded-2xl bg-gray-100" />;
  return <Suspense fallback={<div className="h-[420px] animate-pulse rounded-2xl bg-gray-100" />}><MapImovel {...props} /></Suspense>;
}
```

---

## Estratégia de testes PostGIS (recomendada)

**Problema:** `phpunit.xml` usa `DB_CONNECTION=sqlite` / `:memory:`. SQLite não tem funções PostGIS — e a migration do índice GiST falharia, derrubando os 385 testes atuais. "Sem fachada" proíbe testar `ST_Contains` contra um fake achando que cobriu o SQL real.

**Recomendação: estratégia híbrida (a)+(b) — conexão Postgres+PostGIS dedicada para o SQL espacial + repositório com fake para os consumidores.**

1. **Migrations driver-aware (pré-requisito):** índice GiST e qualquer DDL PostGIS só em `pgsql` (ver Code Examples). Isso mantém a suíte SQLite verde — a coluna `geometry` nativa compila no SQLite como tipo genérico (sem capacidade espacial), mas as tabelas são criadas.

2. **Conexão `pgsql_testing`** em `config/database.php` (lê env; default aponta para o Postgres do projeto). Base de teste real Postgres+PostGIS.

3. **`PostgisTestCase`** (base) que:
   - força `database.default = pgsql_testing`,
   - garante `CREATE EXTENSION IF NOT EXISTS postgis`,
   - usa `RefreshDatabase` nessa conexão,
   - marca o grupo `@group postgis`.
   - **Sem fachada:** se a conexão PostGIS não existir localmente, `markTestSkipped` com mensagem clara — **mas o CI sempre provê o container, então o grupo roda de verdade** (não é pulado em silêncio em produção/CI).

4. **Repositório fino (`SpatialRepository`)** isola o SQL. Os **consumidores** (motor da Fase 5, controllers) são unit-testados com um **fake em memória** (rápido, SQLite/sem DB). O **SQL real** (`PostgisSpatialRepository`) tem testes de integração `@group postgis` contra dados-fixture reais.

5. **Mudanças no `phpunit.xml` / CI:**
   - Manter o default SQLite para o grosso da suíte (rápido).
   - Adicionar diretório/suite `tests/Feature/Geo` (ou filtrar por `--group postgis`) que roda sob `pgsql_testing`.
   - **CI (GitHub Actions):** service container `postgis/postgis:16-3.5` + `shivammathur/setup-php` com extensão `pgsql`; rodar `php artisan test --group postgis` com `DB_CONNECTION=pgsql_testing`. Exemplo de serviço:

```yaml
services:
  postgres:
    image: postgis/postgis:16-3.5
    env: { POSTGRES_DB: testing, POSTGRES_USER: sile, POSTGRES_PASSWORD: secret }
    ports: ['5432:5432']
    options: --health-cmd pg_isready --health-interval 10s --health-timeout 5s --health-retries 5
```

**Por que não só fake (opção b pura):** testar a lógica espacial contra um fake é fachada — não prova que `ST_Contains`/`ST_DWithin`/`ST_Area` retornam o esperado no banco real. O fake serve só para os consumidores.
**Por que não migrar tudo para Postgres (camada "stop using sqlite"):** mais correto em teoria, mas reescreve a base de 385 testes e perde a velocidade; o híbrido entrega a cobertura real do SQL espacial sem esse custo agora (pode evoluir depois).

---

## Notas — Nominatim (HU-029)

Política oficial (`https://operations.osmfoundation.org/policies/nominatim/`, confiança ALTA):

- **≤ 1 requisição/segundo** (uso disparado pelo usuário final é OK com volume moderado; o parâmetro `seguranca.throttle.geocoding.por_minuto` default 60 está coerente).
- **User-Agent (ou Referer) obrigatório** identificando a aplicação — User-Agents padrão de libs HTTP são bloqueados (vira HTML de bloqueio, não JSON → tratar como falha).
- **Cache obrigatório** do lado cliente (consultas repetidas idênticas podem ser bloqueadas) — o `Cache::remember` de sucesso já cobre.
- **Sem uso pesado/distribuído**; scripts periódicos/batch são restritos a **4 req/min** e fortemente desencorajados — geocodificação em lote deve ir para **self-host** (a `base_url` parametrizável permite isso sem deploy).
- **Cláusula 2025:** a API pública não pode ser embutida/sugerida por plataformas no-code/low-code/"vibe-coding" como serviço genérico — uso só quando o desenvolvedor faz escolha deliberada e é responsável pela conformidade. SILE é app governamental deliberado → OK, mas reforça o caminho self-host em produção (já decidido como diferido no CONTEXT).
- **Endpoint:** `GET {base_url}/search?q=...&format=jsonv2&addressdetails=1&countrycodes=br&limit=1`. Resposta: `lat`, `lon`, `display_name`, `importance` (0–1, proxy de confiança), `address{}`, `boundingbox`.
- **Atribuição** OSM e licença **ODbL** (share-alike) devem ser exibidas no mapa.

Conclusão: **viável atrás de contrato com throttle**, exatamente como decidido no CONTEXT. A integração externa (chamada real ao Nominatim público) deve ser registrada como evidência no fechamento da fase (uma chamada real bem-sucedida em dev), seguindo a regra do projeto.

## Notas — Leaflet + react-leaflet

- **Versões:** `react-leaflet@^5.0.0` (peer dep React 19 — confirmado para o projeto) + `leaflet@^1.9.0` + `@types/leaflet` (dev). Leaflet v2 está em alpha — não usar.
- **SSR (Inertia v3):** Leaflet acessa `window` na importação → quebra SSR. Montar o componente do mapa **só no cliente** (guarda `mounted` + `useEffect`, ou `React.lazy`/import dinâmico). Ver Code Examples.
- **CSS/ícone:** importar `leaflet/dist/leaflet.css`; corrigir ícone do marcador com `L.Icon.Default.mergeOptions` sobre os assets importados; `MapContainer` precisa de **altura explícita**.
- **Funcionalidades da fase:** `<Marker draggable>` + `eventHandlers.dragend` (ajuste HU-037); `<GeoJSON data={...} />` para overlay de camadas (HU-036); `<Popup>` com zona/via/lote/bairro/restrições; tiles OSM sem chave. Componente reutilizável para Fases 7 e 8 (decisão do CONTEXT).
- **Atribuição OSM** obrigatória no `TileLayer`.

---

## Validation Architecture (para Nyquist / VALIDATION.md)

Mapeia cada garantia ao tipo de teste e ao que prova — respeitando "sem fachada".

### Camada 1 — Unit (rápido, SQLite/sem DB)
- **Geocoder** com `Http::fake()`: sucesso (DTO correto), endereço não encontrado (exceção), toggle `features.geocoding` desligado (bloqueio comunicado antes da chamada), cache só de sucesso (falha não cacheada), header User-Agent presente.
- **Versão/vigência:** seleção da camada vigente vs camada da data X (lógica pura sobre `valid_from`/`valid_to`).
- **Diff de carga (HU-036 RN-005):** contagem features +/−/~ (lógica pura).
- **Sobreposição (HU-037):** matemática do ratio e do limiar `geo.validacao.sobreposicao_minima` (pura, sem PostGIS).
- **Parâmetros:** `ParameterSeeder` cataloga as 4 chaves novas; `Settings::get` cai no fallback de `config/sile.php` sem banco.
- **Consumidores com fake `SpatialRepository`:** controllers/serviço que dependem de território testados sem banco.

### Camada 2 — Integração PostGIS (`@group postgis`, OBRIGATÓRIO no CI)
- **Migrations** criam `geometry(4326)` + índice GiST no Postgres.
- **Identificação (HU-031–035):** `ST_Contains` devolve o bairro/zona/lote correto para coordenada conhecida (golden point — ex.: ponto no Pelourinho → bairro esperado, a partir do GeoJSON real do GeoSalvador); `ST_DWithin`/`ST_Distance` devolve a via mais próxima; restrições por interseção.
- **Carga (HU-036):** `geo:importar` de um GeoJSON-fixture real (poucos bairros) cria features; nova versão não apaga a anterior; diff auditado.
- **Reprodução:** consulta por data da decisão usa a versão da época.
- **HU-037 RN-004:** `ST_Area(ST_Intersection)/ST_Area` ≥ limiar não alerta; abaixo, registra alerta no processo.
- **Auditoria (RN-002):** carga e consulta geram `activity` com usuário/origem/resultado/versão.

### Camada 3 — Evidência de integração real (fechamento da fase)
- **Nominatim:** uma chamada real bem-sucedida em dev (endereço de Salvador → coordenada) registrada como evidência.
- **Dado oficial real:** seed dev carrega bairros reais do GeoSalvador (ou IBGE) e a consulta espacial roda sobre eles.
- **Bloqueios visíveis:** zona LOUOS e lote cadastral marcados como bloqueados no STATE/ROADMAP e com aviso na UI (teste de que a UI comunica o bloqueio, não simula resultado).

### Golden cases (proteção de regressão de domínio)
- Conjunto pequeno de coordenadas conhecidas de Salvador → bairro/restrição esperados, derivado do dado oficial real carregado. Roda no `@group postgis` a cada mudança.

---

## State of the Art

| Abordagem antiga | Abordagem atual | Quando mudou | Impacto |
|------------------|-----------------|--------------|---------|
| `$table->point()/polygon()` + pacotes para colunas espaciais | `$table->geometry('c', subtype:..., srid:...)` / `geography(...)` nativos | Laravel 11 (PR #49634), mantido no 13 | Sem dependência de schema; magellan deprecou seus helpers |
| Reprojeção manual / download shapefile | ArcGIS REST `f=geojson&outSR=4326` (reprojeta no servidor) | — | Import direto em 4326 sem PROJ no cliente |
| react-leaflet v4 (React 18) | react-leaflet v5 (React 19) | 2024-12-14 | Casa com React 19 do projeto |

**Obsoleto/evitar:**
- Leaflet v2 (alpha) — aguardar estável.
- API `/confiabilidades` e helpers de schema do magellan (deprecados) — não usar.
- IBGE "bairros" como única fonte de bairro **legal**: são adaptados a setores censitários e não coincidem 100% com os bairros instituídos por lei — por isso o GeoSalvador (oficial municipal) é o primário.

## Open Questions

1. **Zona urbanística LOUOS (Quadro 10)** — não há camada vetorial pública. **Recomendação:** bloquear (STATE/ROADMAP + UI), pedir à SEDUR a base de zoneamento (SIGIS/CA 2000). É o insumo crítico da Fase 5; o bloqueio precisa ficar explícito porque a Fase 5 depende dele.
2. **Lote cadastral com inscrição imobiliária** — restrito (Cadastro Multifinalitário/SEFAZ). **Recomendação:** infra de sobreposição pronta e testada com fixtures; HU-033 e HU-037 RN-004/RN-005 bloqueadas até o dado oficial.
3. **PDDU ≟ LOUOS na classificação viária e nas restrições** — as camadas públicas são do PDDU 2016; confirmar com a SEDUR se são as operativas para os Quadros 11/11A e para as restrições da LOUOS, ou se há versão LOUOS específica a aguardar.
4. **Licença/uso do GeoSalvador** — endpoint `access:public`, mas confirmar com a SEDUR os termos de uso/atribuição para embutir os dados no SILE (provável trivial por ser o próprio órgão dono, mas registrar).
5. **CRS de import** — padronizar tudo em 4326 (via `outSR=4326` no ArcGIS; `ST_Transform` para IBGE 4674 / WFS 31984).

## Sources

### Primárias (confiança ALTA)
- Laravel 13 — Migrations (Spatial Types): `https://laravel.com/docs/13.x/migrations` + PR `https://github.com/laravel/framework/pull/49634`.
- Nominatim Usage Policy: `https://operations.osmfoundation.org/policies/nominatim/`.
- react-leaflet npm/changelog (v5, React 19): `https://www.npmjs.com/package/react-leaflet`, `https://github.com/PaulLeCam/react-leaflet/blob/master/CHANGELOG.md`.
- **GeoSalvador ArcGIS REST (verificado por chamada real 2026-06-13):** `https://geo.salvador.ba.gov.br/arcgis/rest/services?f=json`; `.../Bairros/MapServer/0?f=json` (SRID 31984, exporta 4326); `.../Bairros/MapServer/0/query?...&f=geojson&outSR=4326` (HTTP 200, GeoJSON válido); `.../PDDU_2016/MapServer`; `.../Logradouros/MapServer/0`; `.../MAPA_BASE/MAPA_BASE_Consulta/MapServer`.
- IBGE — Malha/Arquivo geoespacial de Bairros (Censo 2022): `https://www.ibge.gov.br/geociencias/organizacao-do-territorio/malhas-territoriais/26565-malhas-de-setores-censitarios-divisoes-intramunicipais.html` + GeoFTP `https://geoftp.ibge.gov.br/organizacao_do_territorio/malhas_territoriais/malhas_de_setores_censitarios__divisoes_intramunicipais/censo_2022/bairros/`.
- SEDUR — LOUOS legislação e mapas: `https://sedur.salvador.ba.gov.br/louos-2016/18-legislacao/62-louos` e `.../63-louos-mapas`. Catálogo de Geoserviços: `https://servicos.sedur.salvador.ba.gov.br/geoservicos`.

### Secundárias (confiança MÉDIA)
- `matanyadaev/laravel-eloquent-spatial` v4.7.0 (Laravel 13): `https://github.com/MatanYadaev/laravel-eloquent-spatial/blob/master/CHANGELOG.md`, `https://packagist.org/packages/matanyadaev/laravel-eloquent-spatial`.
- `clickbar/laravel-magellan` (helpers deprecados): `https://github.com/clickbar/laravel-magellan`.
- CI Postgres+PostGIS: `https://github.com/shivammathur/setup-php/blob/HEAD/examples/laravel-postgres.yml`; "Stop Using SQLite in Laravel Tests": `https://aaronsaray.com/2019/stop-using-sqlite-in-laravel-unit-tests/`.
- IDE Bahia / CONDER bairros (WMS estadual): `https://metadados.ide.ba.gov.br/geonetwork/srv/api/records/21f7663e-bc1e-4681-9426-d74508158ec4`.

### Terciárias (confiança BAIXA — validar)
- react-leaflet + SSR (padrão de montagem client-side): `https://janmueller.dev/blog/react-leaflet/`.

## Metadata

**Confiança por área:**
- Veredito de dados (entregável/bloqueada): ALTA — endpoints públicos verificados por chamada real; bloqueios confirmados por ausência (pasta LOUOS vazia, lote restrito).
- PostGIS no Laravel 13: ALTA — docs oficiais + PR de framework.
- Estratégia de testes: ALTA — consenso de mercado + exemplos de CI; ajuste fino do híbrido é MÉDIA (depende da execução).
- Nominatim: ALTA — política oficial.
- Leaflet/react-leaflet: ALTA — npm/changelog oficiais.
- Equivalência PDDU↔LOUOS (via/restrição): MÉDIA — precisa de confirmação SEDUR.

**Data da pesquisa:** 2026-06-13
**Validade estimada:** 30 dias (stack estável; endpoints públicos do GeoSalvador podem mudar de estrutura de pastas/camadas — revalidar URLs antes de codar a carga).

## RESEARCH COMPLETE

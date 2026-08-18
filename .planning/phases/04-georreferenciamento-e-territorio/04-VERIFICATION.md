---
phase: 04-georreferenciamento-e-territorio
verified: 2026-06-13T23:55:00Z
status: passed
score: 5/5 critérios verificados
re_verification: false
escopo_honesto:
  conforme: true
  resumo: "Zona urbanística LOUOS (HU-031) e lote cadastral (HU-033) não têm fonte vetorial pública — entregues como pendente_fonte, comunicados na UI como Indisponível/Pendente SEDUR, sem polígono inventado. Degradação comunicada e testada = atendimento honesto, NÃO é gap. Bloqueio registrado para a SEDUR."
entregue:
  - dimensao: "Bairro (HU-034)"
    estado: "vigente, 171 feições reais GeoSalvador"
    evidencia: "PhaseFourSmokeTest golden Farol da Barra→Barra (ST_Contains real)"
  - dimensao: "Via mais próxima (HU-032)"
    estado: "vigente, 800 feições (extrato central real)"
    evidencia: "PostgisSpatialRepositoryTest (ST_DWithin/ST_Distance::geography)"
  - dimensao: "Restrições ambientais ZEIS (HU-035)"
    estado: "vigente, 234 feições reais (PDDU 2016)"
    evidencia: "PostgisSpatialRepositoryTest (ST_Intersects)"
  - dimensao: "Geocodificação (HU-029)"
    estado: "Nominatim real, toggle ON, throttle/retry parametrizados"
    evidencia: "NominatimGeocoderTest (11) + chamada real 04-08 (Elevador Lacerda→-12.974/-38.513)"
  - dimensao: "Identificação + auditoria (HU-030/HU-036)"
    estado: "end-to-end com versão por camada (RN-004) e auditoria (RN-002)"
    evidencia: "TerritoryServiceTest (7) + PhaseFourSmokeTest"
  - dimensao: "Camadas versionadas com vigência (HU-036 RN-004)"
    estado: "geo_layers versionada (valid_from/valid_to/version), naData reproduz a época"
    evidencia: "GeoLayerTest + GeoLayerServiceTest (8)"
  - dimensao: "Infra de sobreposição (HU-037 RN-004)"
    estado: "ST_Area(ST_Intersection)/ST_Area com limiar parametrizado — liga sem reescrita quando o lote chegar"
    evidencia: "PostgisLocationValidationTest (~100% sem alerta; ~25% com alerta)"
  - dimensao: "CI/enforcement postgis"
    estado: "ativo, skip vira falha"
    evidencia: ".github/workflows/tests.yml (POSTGIS_TESTS_REQUIRED=true)"
bloqueado:
  - dimensao: "Zona urbanística LOUOS (HU-031)"
    motivo: "Sem camada vetorial pública (Quadro 10 só em PDF; pasta ArcGIS LOUOS vazia)"
    comunicado: "pendente_fonte (feature_count 0) → Indisponível 'Base de zoneamento pendente SEDUR'"
    conforme: true
  - dimensao: "Lote cadastral (HU-033)"
    motivo: "Base restrita (Cadastro Multifinalitário / SEFAZ)"
    comunicado: "pendente_fonte (feature_count 0) → Indisponível 'Base de lotes pendente SEDUR'"
    conforme: true
  - dimensao: "Validação de sobreposição por inscrição (HU-037 RN-005)"
    motivo: "Depende do lote cadastral acima"
    comunicado: "validar-localizacao retorna indisponivel + Alert info; polígono rotulado 'perímetro do imóvel' (placeholder honesto)"
    conforme: true
  - dimensao: "Classificação viária operacional (Quadros 11/11A — HU-032)"
    motivo: "Geometria da via entra; atributo de classificação pende confirmação SEDUR (PDDU ≟ LOUOS Mapa 04)"
    comunicado: "registrado em 04-04-SUMMARY; não impacta os 5 critérios da fase"
    conforme: true
dependencia_proxima_fase:
  - "Fase 5 (motor LOUOS) depende da ZONA — bloqueio precisa permanecer explícito no STATE/ROADMAP. Quando a SEDUR entregar zona/lote como camada vigente com feições, a MESMA lógica (identify/validate) passa a usá-las — a carga muda, a lógica não."
recommended_human_spotchecks:
  - test: "Abrir /gestao/territorio como analista, geocodificar 'Praça da Sé, Salvador', arrastar o marcador"
    expected: "Mapa Leaflet renderiza, marcador arrastável reidentifica o ponto; bairro/via reais; zona/lote como Indisponível/Pendente SEDUR"
    why_human: "Aparência visual e interatividade do mapa (arrastar/clicar) não são provadas por grep/testes — wiring e componentes reais já verificados estruturalmente; integração real já registrada no 04-08"
    blocking: false
---

# Fase 4: Georreferenciamento e Território — Relatório de Verificação

**Goal da fase (ROADMAP):** "O sistema localiza imóveis no território de Salvador e identifica zona urbanística, via, lote, bairro e restrições — insumos do motor de regras."
**Verificado:** 2026-06-13T23:55:00Z
**Status:** passed (5/5 critérios)
**Re-verificação:** Não — verificação inicial
**Método:** goal-backward, código verificado diretamente (não confiando nos SUMMARYs) + evidência de teste fresca.

## Veredito

**APROVADO.** Os 5 critérios de sucesso são VERDADE no código, com lógica real de ponta a ponta e dado público REAL carregado (bairro/via/restrição/geocodificação). A indisponibilidade de **zona** (HU-031) e **lote** (HU-033) é o **escopo honesto aprovado** (sem fonte pública): degradação comunicada na UI, testada e auditada — **CONFORME, não é gap**. Nenhuma fachada detectada: nenhuma geometria inventada para zona/lote; o gate de bloqueio é dirigido pelo dado (`pendente_fonte`) e prova-se que NÃO dispara consulta espacial nesse estado.

## Evidência fresca de teste (outputs lidos por inteiro)

| Comando | Resultado |
|---|---|
| `php artisan test --compact --exclude-group postgis` | `{"result":"passed","tests":448,"passed":448,"assertions":2176}` — sem regressão, sem `failed` |
| `php artisan test --compact --group postgis` | `{"result":"passed","tests":15,"passed":15,"assertions":85}` — **sem campo `skipped`**: o SQL espacial EXECUTOU (guard fail-not-skip) |
| `php artisan route:list --path=territorio` | 4 rotas: `index` (GET), `geocodificar`/`identificar`/`validar-localizacao` (POST) |

A ausência do campo `"skipped"` no grupo postgis é a prova de honestidade: com o container `sile-pgsql` de pé, qualquer problema viraria FALHA — os 15 testes rodaram `ST_Contains`/`ST_DWithin`/`ST_Distance`/`ST_Intersects`/`ST_Area`/`ST_Intersection` reais.

## Critérios de Sucesso (verdades observáveis)

| # | Critério | Status | Evidência (arquivo + teste) |
|---|---|---|---|
| 1 | Endereço geocodificado e imóvel em mapa interativo | ✓ VERIFICADO | `NominatimGeocoder` (HTTP real Nominatim, jsonv2/countrycodes=br); `GeocodeController` + rota `geocodificar` (auditada, throttle, toggle); `MapImovel` (react-leaflet: MapContainer/TileLayer/Marker arrastável) + `MapaSection` SSR-safe; `territorio/index.tsx` `localizar()→geocode→mapa→identificar`. Testes: `NominatimGeocoderTest` (11), `GeocodeEndpointTest` (8); chamada real 04-08 |
| 2 | Identifica zona, via, lote e bairro das camadas | ✓ VERIFICADO* | `TerritoryService.identify`: bairro `ST_Contains`, via `ST_DWithin/ST_Distance::geography`; `PostgisSpatialRepository` SQL real. *Zona/lote: `pendente_fonte`→indisponível SEM consulta (escopo honesto, CONFORME). Testes: `TerritoryServiceTest` (7, inclui `assertNotContains` para zona/lote), `PostgisSpatialRepositoryTest` (golden Colinas de Periperi) |
| 3 | Restrições identificadas; camadas consultáveis no mapa | ✓ VERIFICADO | `TerritoryService.identifyIntersecting` `ST_Intersects` (234 ZEIS); `TerritoryController.index` lista camadas vigentes; `territorio/index.tsx` card de camadas + render de restrições. Testes: `PostgisSpatialRepositoryTest`, `TerritoryPageTest` (7) |
| 4 | Localização validada; polígono × lote com alerta (RN-004) | ✓ VERIFICADO* | `LocationValidationService` `ST_Area(ST_Intersection)/ST_Area` + limiar parametrizado `geo.validacao.sobreposicao_minima`; marcador arrastável/clique (HU-037 ajuste); Alert success/warning/info. *Lote pendente→indisponível (honesto); infra provada contra PostGIS real com fixture. Testes: `LocationValidationServiceTest` (5), `PostgisLocationValidationTest` (~100% sem alerta; ~25% com alerta) |
| 5 | Camadas versionadas com vigência; decisão registra versão; reprodução usa a época | ✓ VERIFICADO | `geo_layers` (version/valid_from/valid_to/rules_version, unique(type,version)); `GeoLayer::scopeVigente/scopeNaData`; `GeoLayerService.openVersion` (fecha vigente, abre nova) + `auditLoad` (RN-002/RN-005); `TerritoryResult.versao_camada` por dimensão. Testes: `GeoLayerTest`, `GeoLayerServiceTest` (8), auditoria em `TerritoryServiceTest` |

\* Critérios 2 e 4: a parte de **zona/lote** é entregue como **indisponível/pendente SEDUR** — escopo honesto aprovado (sem fonte pública). Verificado que a degradação existe, é comunicada e é testada; **não classificado como gap**.

## Artefatos verificados (existência + substância + wiring)

| Artefato | Substância | Wiring |
|---|---|---|
| `app/Services/Geo/NominatimGeocoder.php` | HTTP real ao Nominatim, cache só de sucesso, retry/timeout parametrizados | `bind(Geocoder→NominatimGeocoder)` em AppServiceProvider (linha 41) |
| `app/Services/Geo/TerritoryService.php` | `identify` orquestra 5 dimensões; `isBlocked` = null/pendente_fonte → indisponível sem consulta | Injeta `SpatialRepository` + `AuditService`; consumido por `TerritoryController` |
| `app/Services/Geo/PostgisSpatialRepository.php` | `ST_Contains`/`ST_DWithin`/`ST_Distance::geography`/`ST_Intersects` reais | `bind(SpatialRepository→PostgisSpatialRepository)` (linha 46) |
| `app/Services/Geo/LocationValidationService.php` | `ST_Area(ST_Intersection)/ST_Area`, `decide()` puro, limiar parametrizado | Consumido por `TerritoryController.validateLocation` (auditado) |
| `app/Services/Geo/GeoLayerService.php` | `openVersion` (vigência), `computeDiff` (RN-005), `auditLoad` | Consumido por `GeoJsonLayerImporter`/comando/seeder |
| `app/Models/GeoLayer.php` | `scopeVigente`/`scopeNaData`, casts, HasAuditoria | Usado por TerritoryService/LocationValidationService/Controller |
| `app/Http/Controllers/Gestao/TerritoryController.php` | `index` (camadas+limiar+toggle), `identify`, `validateLocation` (auditado) | Rotas em `routes/gestao.php` sob `permission:consultar-territorio` |
| `resources/js/pages/gestao/territorio/index.tsx` | 518 linhas; `useHttp` nos 3 endpoints reais; render dirigido por status; sem fabricação | Layout GestaoLayout; item de nav por permissão |
| `resources/js/components/geo/map-imovel.tsx` | react-leaflet real (MapContainer/TileLayer/Marker arrastável/GeoJSON) | Montado via `MapaSection` (lazy SSR-safe) |
| `database/migrations/...geo_layers/geo_features` | versionamento + geometry(4326) + GiST driver-aware | Migram em SQLite (suíte) e pgsql |
| `database/seeders/ParameterSeeder.php` | 4 params geo (toggle/base_url/throttle/limiar) | `assertSame(29, ...)` no `ParameterSeederTest` |
| `database/seeders/GeoLayerSeeder.php` | bairro/via/restrição reais; zona/lote `pendente_fonte` | No `DatabaseSeeder` |
| `.github/workflows/tests.yml` | service container postgis + 2 passos de teste | `POSTGIS_TESTS_REQUIRED=true` (skip→falha) |

## Sem fachada (regra nº1) — confirmação no código

- **Dado público REAL carregado:** `database/data/geo/{bairros,vias,restricoes-ambientais}.geojson` commitados (171/800/234 feições); a carga lê o arquivo, nunca a rede.
- **Geocodificação real:** `NominatimGeocoder` faz GET real ao Nominatim; evidência de chamada ao vivo registrada no 04-08 (Elevador Lacerda → -12.974/-38.513, HTTP 200).
- **Nada inventado para zona/lote:** `GeoLayerSeeder.seedPendingLayer` cria `pendente_fonte` com `feature_count 0` (sem geometria); `TerritoryService.isBlocked`/`LocationValidationService.isBlocked` retornam indisponível **sem** consulta espacial — provado por `assertNotContains('zona'/'lote', $fake->containingCalls)` no `TerritoryServiceTest`.
- **UI comunica, não simula:** `territorio/index.tsx` renderiza Badge "Indisponível"/"Pendente SEDUR" + motivo do backend; nota fixa: "nenhum valor é exibido sem dado oficial".

## Anti-padrões

Nenhum anti-padrão bloqueante. O polígono de validação na UI é um quadrado mínimo (~33 m) **rotulado honestamente** como "perímetro do imóvel" (placeholder enquanto o lote é pendente); como o lote é `pendente_fonte`, o resultado é `indisponivel` — sem fachada. `alteradas` no diff é sempre 0 por decisão documentada (sem chave estável de feição na fonte) — reservado, não esconde lógica.

## Verificação humana recomendada (NÃO bloqueante)

A aparência visual e a interatividade do mapa Leaflet (arrastar/clicar, claro/escuro, mobile 375px) não são prováveis por grep/teste. O wiring e os componentes são reais (react-leaflet, `onMove` reidentifica) e a integração externa já foi validada e registrada no 04-08; recomenda-se um spot-check navegável em `/gestao/territorio`, mas isso **não bloqueia** a conclusão da fase.

## Resumo

5/5 critérios verificados com evidência fresca. Camadas com dado público real (bairro/via/restrição) + geocodificação real entregues e testadas; zona/lote bloqueados de forma honesta e comunicada (escopo aprovado — CONFORME). Suíte 448/448 sem regressão, grupo espacial 15/15 executando SQL real (sem skip), CI com enforcement. **Bloqueio de zona/lote deve permanecer explícito no STATE/ROADMAP** porque a Fase 5 depende da zona.

---
_Verificado: 2026-06-13T23:55:00Z_
_Verificador: gsd-verifier_

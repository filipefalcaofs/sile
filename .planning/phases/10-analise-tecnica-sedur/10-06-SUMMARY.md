---
phase: 10-analise-tecnica-sedur
plan: 06
subsystem: analise
tags: [hu-142, precedentes, postgis, st-intersects, repository-contract, fake, lgpd, parametrizacao, degradacao-honesta, jsonb]

# Dependency graph
requires:
  - phase: 08-solicitacao-de-viabilidade
    plan: 06
    provides: "geometry derivada property_polygon (Polygon,4326) + PropertyGeometryWriter (ST_MakeValid(ST_SetSRID(ST_GeomFromGeoJSON,4326))) + property_polygon_geojson (jsonb fonte)"
  - phase: 09-fluxo-expresso
    plan: 04
    provides: "ResolvedViability::toSnapshot — shape do engine_snapshot (por_cnae[].consulta = ConsultaViabilidadeResult::toArray, com territorio.zona = {status,nome,...})"
  - phase: 09-fluxo-expresso
    plan: 02
    provides: "viability_decisions (outcome deferida/indeferida, decided_at, decided_by_user_id) + DecisionOutcome + ViabilityDecisionFactory"
  - phase: 10-analise-tecnica-sedur
    plan: 01
    provides: "parâmetros analise.precedentes.janela_meses (12) e analise.precedentes.max_itens (10) + fallback config/sile.php"
  - phase: 10-analise-tecnica-sedur
    plan: 02
    provides: "analysis_records.engine_snapshot (jsonb) + currentAnalysisRecord (latestOfMany revision)"
  - phase: 04-georreferenciamento
    plan: "(geo)"
    provides: "padrão SpatialRepository + PostgisSpatialRepository + FakeSpatialRepository + PostgisTestCase (@group postgis)"
provides:
  - "App\\Services\\Analise\\PrecedentRepository — contrato que isola o SQL espacial/agregado dos precedentes (HU-142)"
  - "App\\Services\\Analise\\PostgisPrecedentRepository — implementação real (ST_Intersects + agregação jsonb por zona/janela)"
  - "Tests\\Support\\Analise\\FakePrecedentRepository — helper de teste em memória (injetado via instance() nos consumidores SQLite)"
  - "App\\Services\\Analise\\PrecedentService::forRecord(AnalysisRecord): array — payload {imovel, cnae_zona} parametrizado, LGPD e degradação honesta"
  - "binding INCONDICIONAL PrecedentRepository -> PostgisPrecedentRepository no AppServiceProvider"
affects: [10-08, 10-09, 10-16, 10-17, 10-18]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Contrato isola o SQL espacial/agregado (espelha SpatialRepository); consumidores testáveis com fake em memória, espacialidade provada em @group postgis"
    - "Binding INCONDICIONAL interface->Postgis no AppServiceProvider; o fake NUNCA entra no container de produção — é injetado via $this->app->instance() só nos testes SQLite"
    - "ST_Intersects exige SRID 4326 explícito nos DOIS operandos: ST_SetSRID(ST_GeomFromGeoJSON(?),4326) (sem isso estoura SRID misto contra geometry(Polygon,4326))"
    - "Zona da decisão resolvida pela ficha vigente (maior revisão) via jsonb (jsonb_array_elements + jsonb_typeof guard); sem zona identificada, não conta (degradação honesta, nunca estatística inventada)"
    - "tvl_product_number da ViabilityDecisionFactory é HARDCODED não-único: derivar do protocolo ao criar várias deferidas (mesma armadilha do protocol_number)"

key-files:
  created:
    - app/Services/Analise/PrecedentRepository.php
    - app/Services/Analise/PostgisPrecedentRepository.php
    - app/Services/Analise/PrecedentService.php
    - tests/Support/Analise/FakePrecedentRepository.php
    - tests/Feature/Analise/PrecedentServiceTest.php
    - tests/Feature/Analise/PrecedentRepositoryPostgisTest.php
  modified:
    - app/Providers/AppServiceProvider.php

key-decisions:
  - "Binding INCONDICIONAL (espelha bind(SpatialRepository, PostgisSpatialRepository)) — NÃO condicional a pgsql; o fake só existe em teste, injetado via instance()"
  - "ST_SetSRID(ST_GeomFromGeoJSON(?),4326) no ST_Intersects (igual ao PropertyGeometryWriter) — sem o SRID explícito o ST_Intersects estoura por SRID misto"
  - "Zona resolvida pela ficha vigente (engine_snapshot.por_cnae[].consulta.territorio.zona, status 'identificado') — sem zona, cnae_zona degrada para {disponivel:false}; nunca estatística inventada"
  - "PrecedentService usa o CNAE PRINCIPAL do engine_snapshot (is_primary, fallback ao primeiro) para a estatística da zona"
  - "LGPD (RN-004): a projeção do contrato tem só {viability_request_id, protocol_number, outcome, decided_at, service_type, analyst} — jamais CPF/dados do requerente"

patterns-established:
  - "PrecedentRepositoryPostgisTest re-declara #[Group('postgis')] explicitamente: o atributo herdado da classe abstrata PostgisTestCase NÃO basta para --group=postgis descobrir a classe"

# Metrics
duration: ~12min
completed: 2026-06-14
---

# Phase 10 Plan 06: Precedentes da análise técnica (HU-142) — Summary

**Contrato `PrecedentRepository` + implementação real `PostgisPrecedentRepository` (ST_Intersects sobre a geometry `property_polygon` da Fase 8) + `FakePrecedentRepository` (helper de teste) + `PrecedentService` (parâmetros + LGPD + degradação honesta), com binding INCONDICIONAL espelhando o `SpatialRepository`. TDD estrito: `PrecedentServiceTest` 5/5 (SQLite) + `PrecedentRepositoryPostgisTest` 3/3 (@group postgis, PostgreSQL real). ZERO dependência nova; reusa a geometry derivada e o `engine_snapshot` da ficha.**

## Performance
- **Duration:** ~12 min (início 2026-06-14T22:20:28Z)
- **Tasks:** 2 (1 commit atômico por task)
- **Files:** 6 criados + 1 modificado (AppServiceProvider) — ZERO dependência nova

## Contrato (assinaturas — insumo de 10-08/10-09/10-16/10-17/10-18)

### `App\Services\Analise\PrecedentRepository` (interface)
```php
/** @return list<array{viability_request_id:int, protocol_number:?string, outcome:string, decided_at:?string, service_type:?string, analyst:?string}> */
public function propertyPrecedents(array $currentGeojson, int $limit, ?string $fallbackStreet = null, ?string $fallbackNumber = null): array;

/** @return array{deferidos:int, indeferidos:int, total:int} */
public function cnaeZoneStats(string $cnae, string $zona, DateTimeInterface $since): array;
```
- `propertyPrecedents`: decisões anteriores no MESMO imóvel; `$currentGeojson` é o `property_polygon_geojson` (Polygon, anel `[lng,lat]`); vazio força o fallback por endereço (`street`+`number`).
- `cnaeZoneStats`: agregação por desfecho na janela; só é chamada COM zona identificada (sem zona, o consumidor degrada).

### `App\Services\Analise\PostgisPrecedentRepository` (implementação real, @group postgis)
- **propertyPrecedents (SQL):** `viability_requests vr` JOIN `viability_decisions vd` (+ leftJoin `viability_service_types`, `users` para o analista); quando há polígono → `whereNotNull(vr.property_polygon)` + `whereRaw('ST_Intersects(vr.property_polygon, ST_SetSRID(ST_GeomFromGeoJSON(?), 4326))', [json_encode($geojson)])`; senão → fallback `where address_street (+ address_number)`; `orderByDesc('vd.decided_at')->limit($limit)`. Projeção SEM dados pessoais.
- **cnaeZoneStats (SQL):** `COUNT(*) FILTER (WHERE vd.outcome='deferida'|'indeferida')` + total sobre `viability_decisions` com `vd.decided_at >= :since` e `EXISTS` na FICHA VIGENTE (maior `revision`) do processo: `jsonb_typeof(engine_snapshot->'por_cnae')='array'` e `EXISTS(jsonb_array_elements(engine_snapshot->'por_cnae') item WHERE item->>'cnae'=:cnae AND item->'consulta'->'territorio'->'zona'->>'status'='identificado' AND item->'consulta'->'territorio'->'zona'->>'nome'=:zona)`.

### `Tests\Support\Analise\FakePrecedentRepository` (HELPER DE TESTE — não produção)
- `setPropertyPrecedents(array $rows)`, `setCnaeZoneStats(string $cnae, string $zona, array $stats)`; aplica o `$limit` (espelha o LIMIT do SQL) e registra `propertyCalls`/`cnaeZoneCalls` (prova que sem zona NÃO há consulta da estatística).
- Injetado POPULADO via `$this->app->instance(PrecedentRepository::class, $fake)` — usado aqui (PrecedentServiceTest) e a ser reusado em 10-09 (PrecedenteEndpointTest) e 10-18 (AnaliseSmokeTest), nunca retornando precedentes vazios silenciosamente.

### Binding (AppServiceProvider — único toque na Wave 2)
```php
$this->app->bind(PrecedentRepository::class, PostgisPrecedentRepository::class); // INCONDICIONAL
```

### `App\Services\Analise\PrecedentService`
```php
public function __construct(private readonly PrecedentRepository $precedents) {}
public function forRecord(AnalysisRecord $record): array; // {imovel, cnae_zona}
```
- Parâmetros via `Settings::get('analise.precedentes.max_itens', 10)` (limit) e `Settings::get('analise.precedentes.janela_meses', 12)` (`since = now()->subMonths(janela)`).
- LGPD (RN-004): payload NUNCA carrega CPF/dados do requerente.
- Degradação honesta: sem zona identificada → `cnae_zona = {disponivel:false, motivo:'zona urbanística pendente'}`; sem polígono → fallback `street+number`; sem precedentes → `imovel = []` explícito (CA-03 não oculta).

## CHAVE EXATA da zona no engine_snapshot (shape do ResolvedViability::toSnapshot — Fase 9)
```
engine_snapshot['por_cnae'][i]['consulta']['territorio']['zona'] = {
    status: 'identificado' | 'nao_encontrado' | 'indisponivel',
    nome: string|null,        // usar SOMENTE quando status === 'identificado'
    propriedades, motivo, versao_camada
}
```
- O CNAE é `engine_snapshot['por_cnae'][i]['cnae']` (7 dígitos); o serviço usa o item `is_primary` (fallback: o primeiro).
- `por_cnae[i]['consulta']` é o `ConsultaViabilidadeResult::toArray()`; `consulta['territorio']` pode ser `null` (consulta sem local) → degrada.

## Shape do payload (`PrecedentService::forRecord` — insumo do endpoint 10-09 / painel 10-16/10-17)
```jsonc
{
  "imovel": [
    {
      "viability_request_id": 501,
      "protocol_number": "VIA-2025-000050",
      "outcome": "deferida",          // deferida | indeferida
      "decided_at": "2025-03-01 10:00:00",
      "service_type": "Viabilidade de localização",
      "analyst": "Maria Analista"     // nome do analista; SEM CPF/dados do requerente
    }
  ],
  "cnae_zona": {                        // COM zona identificada:
    "disponivel": true,
    "cnae": "4712100",
    "cnae_formatado": "4712-1/00",
    "zona": "ZR-1",
    "janela_meses": 12,
    "deferidos": 14,
    "indeferidos": 2,
    "total": 16
  }
  // SEM zona: "cnae_zona": { "disponivel": false, "motivo": "zona urbanística pendente" }
}
```

## Mapa CA -> teste (cobertos)
| HU-142 | Teste |
|---|---|
| CA-01 precedentes do imóvel | PrecedentRepositoryPostgisTest (ST_Intersects/ordenação) + PrecedentServiceTest |
| CA-02 estatística do CNAE na zona | PrecedentServiceTest (cnae_zona com zona) + PrecedentRepositoryPostgisTest (agregação) |
| CA-03 sem precedentes (não ocultar) | PrecedentServiceTest (listas vazias explícitas) |
| RN-003 janela/itens parametrizáveis | PrecedentServiceTest (max_itens) + PrecedentRepositoryPostgisTest (limit) |
| RN-004 LGPD (sem CPF) + degradação sem zona | PrecedentServiceTest (sem CPF; cnae_zona indisponível) + projeção do Postgis |

## Task Commits
1. **Task 1** `e6b9bab` (feat) — contrato + fake + PrecedentService + binding INCONDICIONAL (PrecedentServiceTest 5/5).
2. **Task 2** `66bc319` (feat) — PostgisPrecedentRepository (ST_Intersects + cnaeZoneStats jsonb) (@group postgis 3/3, PostgreSQL real).

_TDD estrito: RED confirmado antes do GREEN em cada task (Task 1 RED = classes ausentes; Task 2 RED = "Target class PostgisPrecedentRepository does not exist" rodando no Postgres). Commit único por task (teste+implementação coesos), espelhando 10-01/10-02._

## Decisions Made
- **Binding INCONDICIONAL** PrecedentRepository -> PostgisPrecedentRepository (espelha o do SpatialRepository); o fake é só de teste (instance()), nunca no container de produção.
- **SRID 4326 explícito** no ST_Intersects (`ST_SetSRID(ST_GeomFromGeoJSON(?),4326)`) — igual ao PropertyGeometryWriter; sem isso estoura SRID misto.
- **Zona pela ficha vigente** (maior revisão) via jsonb; sem zona identificada não conta — degradação honesta (Quadro 10 pendente SEDUR), nunca estatística inventada.
- **CNAE principal** do engine_snapshot para a estatística da zona.

## Deviations from Plan
Adições DENTRO do escopo (anti-fachada — provar o SQL real, não ultrapassar o contrato):
- **`#[Group('postgis')]` explícito** no teste: o atributo herdado da classe abstrata `PostgisTestCase` NÃO é suficiente para `--group=postgis` descobrir a classe (o `InformarImovelPostgisTest` já re-declara). Sem isso o filtro retornava "No tests found".
- **`tvl_product_number` único por decisão deferida** nos helpers do teste: a `ViabilityDecisionFactory` usa um valor HARDCODED (não-único) e várias deferidas colidiam na unique (mesma armadilha do `protocol_number` documentada no 10-02). Derivado do protocolo.
- **`test_estatistica_do_cnae_na_zona...` e `test_limite_parametrizado...`** adicionados ao PrecedentRepositoryPostgisTest (o plano descreve só a interseção): provam o `cnaeZoneStats` (agregação jsonb por zona/janela) e o LIMIT reais no PostgreSQL — anti-fachada.

## Issues Encountered
- Waves paralelas (10-04 Setores, 10-05 SLA) têm arquivos em disco não commitados (`routes/gestao.php`, `SectorController`, etc.). NÃO foram tocados; staging individual dos meus 7 arquivos (nunca `git add -A`). `STATE.md` NÃO alterado (consolidação a cargo do orquestrador — evita clobber entre executores concorrentes).

## Verification (evidência fresca)
- `vendor/bin/pint --dirty --format agent` → passed.
- `php artisan test --compact --filter=PrecedentServiceTest` → **5/5** (20 asserções).
- `POSTGIS_TESTS_REQUIRED=true php artisan test --compact --group=postgis --filter=PrecedentRepositoryPostgisTest` → **3/3** (13 asserções, PostgreSQL real / container sile-pgsql).
- `php artisan test --compact --exclude-group postgis` → **994/994** (5047 asserções) — sem regressão (inclui o trabalho em disco das waves paralelas).
- `POSTGIS_TESTS_REQUIRED=true php artisan test --compact --group=postgis` → **25/25** (144 asserções) — suíte espacial intacta.

## Next Phase Readiness
- **10-08** (pré-análise): grava `engine_snapshot` na rev. 1 da ficha — o `PrecedentService` lê a zona no caminho documentado acima.
- **10-09** (endpoint de precedentes na ficha): injeta o `PrecedentService`; serializa o payload `{imovel, cnae_zona}`; testar com `FakePrecedentRepository` populado via `instance()`.
- **10-16/10-17** (painel de precedentes na ficha SAPS): consome o shape do payload (imóvel + cnae_zona com degradação).
- **10-18** (smoke): injeta o fake populado (não retornar precedentes vazios silenciosamente).
- **Bloqueio herdado (degrada honesto):** zona urbanística (Quadro 10) pendente SEDUR — `cnae_zona` retorna `{disponivel:false}` até a base de zoneamento entrar; nunca inventa estatística.

---
*Phase: 10-analise-tecnica-sedur*
*Completed: 2026-06-14*

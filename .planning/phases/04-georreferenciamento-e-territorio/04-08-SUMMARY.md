---
phase: 04-georreferenciamento-e-territorio
plan: "04-08"
subsystem: geo
tags: [fechamento, postgis, smoke, ci, github-actions, enforcement, nominatim, integracao-real, evidencia-fresca, sem-fachada, golden-point, pendente-sedur]

# Dependency graph
requires:
  - phase: 04-01
    provides: "PostgisTestCase (guard fail-not-skip) + conexão pgsql_testing + comando geo:preparar-banco-de-testes"
  - phase: 04-03
    provides: "contrato Geocoder + NominatimGeocoder real (a chamada REAL ao Nominatim é a evidência de fechamento desta fase)"
  - phase: 04-04
    provides: "snapshots oficiais COMMITADOS (database/data/geo/) + GeoLayerSeeder driver-aware (bairro/via/restrição reais; zona/lote pendente_fonte)"
  - phase: 04-05
    provides: "TerritoryService.identify + PostgisSpatialRepository (SQL espacial real)"
  - phase: 04-07
    provides: "página gestao/territorio/index comunicando zona/lote como Indisponível/Pendente SEDUR (UI de bloqueio)"
provides:
  - "smoke @group postgis end-to-end (PhaseFourSmokeTest): golden points reais de Salvador → bairro oficial do snapshot COMMITADO via TerritoryService::identify, sem rede; bairro ausente é FALHA (não skip)"
  - "CI real (.github/workflows/tests.yml) com service container postgis/postgis:16-3.5: suíte SQLite (--exclude-group postgis) + grupo espacial (--group postgis) com POSTGIS_TESTS_REQUIRED=true — enforcement W3b (skip vira falha)"
  - "docs/deploy/postgis-testing.md: preflight local, execução dos grupos e regra de enforcement"
  - "Evidência de integração REAL registrada: 1 chamada ao Nominatim (endereço→coordenada) + 1 consulta espacial real (golden→bairro+versão)"
affects: [05-motor-de-enquadramento, 07-consultas, 08-solicitacoes]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Smoke end-to-end espacial sobre dado OFICIAL COMMITADO (sem rede): golden points conhecidos → bairro real, como proteção de regressão de domínio"
    - "CI com service container PostGIS + POSTGIS_TESTS_REQUIRED=true: o SQL espacial não pode deixar de rodar (skip = falha)"
    - "Env do job do CI vence o .env (Dotenv imutável do Laravel) — DB_TEST_PORT 5432 no CI vs 5433 no dev sem editar config"

key-files:
  created:
    - ".github/workflows/tests.yml"
    - "tests/Feature/Geo/PhaseFourSmokeTest.php"
    - "docs/deploy/postgis-testing.md"
  modified: []

key-decisions:
  - "CI alinhado ao runtime REAL do projeto: php-version '8.5' (AGENTS.md: 8.5; dev: 8.5.4), não o 8.4 sugerido no esqueleto do plano"
  - "Extensão 'redis' removida do setup-php do CI: a suíte usa cache=array/queue=sync/session=array (phpunit.xml) e o cliente é predis (PHP puro) — sem service Redis nem extensão"
  - "DevGeoSeeder NÃO criado: DevAdminSeeder + GeoLayerSeeder (ambos no DatabaseSeeder) já cobrem o estado navegável; a evidência da UI de bloqueio vem do TerritoryPageTest (7/7). Removido de files_modified."
  - "Golden points fixados por VERIFICAÇÃO contra o dado real (ST_Contains sobre o snapshot commitado), não por suposição: Farol da Barra→Barra, Pituba→Pituba, Shopping da Bahia→Caminho das Árvores"

# Metrics
duration: ~15 min
completed: 2026-06-13
---

# Fase 4 Plano 08: Fechamento e verificação integral (sem fachada) Summary

**Fase 4 FECHADA com evidência fresca e sem fachada: smoke @group postgis end-to-end sobre o snapshot OFICIAL COMMITADO do GeoSalvador (golden points reais de Salvador → bairro oficial via `TerritoryService::identify`, zona/lote comunicados como pendente SEDUR), CI real (`.github/workflows/tests.yml`) com PostGIS e `POSTGIS_TESTS_REQUIRED=true` (skip vira falha), doc de testes espaciais, e a integração REAL provada (1 chamada ao Nominatim endereço→coordenada + 1 consulta espacial golden→bairro+versão). Camadas entregues: bairro/via/restrição reais. Bloqueadas e comunicadas: zona LOUOS, lote cadastral e HU-037 RN-005 (pendentes SEDUR) — nunca inventadas.**

## Performance

- **Started:** 2026-06-13T23:32:00Z
- **Completed:** 2026-06-13T23:48:00Z
- **Duration:** ~15 min
- **Tasks:** 2 (Task 1 com TDD no smoke)
- **Files:** 3 criados (DevGeoSeeder não foi necessário)

## Task Commits

1. **Task 1 — smoke @group postgis + CI real + doc** — `c13d257` (feat) — TDD: RED (asserção propositalmente errada `Farol da Barra → Pelourinho`, falhou mostrando o real `Barra`) → GREEN (corrigido para `Barra`, 2/2, 24 asserções) → pint.
2. **Plan metadata** — este SUMMARY — `docs(04-08): completa fechamento e verificação da fase 4`.

_(STATE.md NÃO foi editado — consolidação é do orquestrador.)_

## Verificação integral (FRESCA — outputs lidos por inteiro)

| Comando | Resultado |
|---|---|
| `php artisan test --compact --exclude-group postgis` | **448/448 passed, 2176 asserções** (15.2s) — sem regressão |
| `php artisan test --compact --group postgis` | **15/15 passed, 85 asserções** (2.6s) — **EXECUTOU, sem skip** |
| `vendor/bin/pint --dirty --format agent` | `{"tool":"pint","result":"passed"}` — limpo |
| `npm run typecheck` (`tsc --noEmit`) | sem erros |
| `npm run build` (`vite build`) | verde — `map-imovel-*.js` (154.86 kB) é chunk lazy isolado do `app` (320.29 kB) |
| `php artisan migrate:fresh --seed` (Postgres dev) | concluído; GeoLayerSeeder 781ms |

### Prova de que o grupo postgis EXECUTOU (não skip)

A linha compacta do PHPUnit:

```json
{"tool":"phpunit","result":"passed","tests":15,"passed":15,"assertions":85,"duration_ms":2585}
```

Não há campo `"skipped"`: os 15 testes (13 herdados + 2 do smoke novo) **rodaram SQL espacial real** contra o container `sile-pgsql` (healthy, porta 5433). O guard do `PostgisTestCase` garante: servidor de pé ⇒ qualquer problema é FALHA, nunca skip; o CI define `POSTGIS_TESTS_REQUIRED=true` para falhar mesmo na ausência do servidor.

### Estado canônico das camadas (dev pós-`migrate:fresh --seed`)

```
camada bairro     status=vigente         features=171  versao=geosalvador-bairros-dec38776-2024
camada via        status=vigente         features=800  versao=geosalvador-logradouros-centro
camada restricao  status=vigente         features=234  versao=geosalvador-pddu2016-zeis
camada zona       status=pendente_fonte  features=0    versao=pendente-sedur
camada lote       status=pendente_fonte  features=0    versao=pendente-sedur
```

## CI real — a pendência "CI da suíte postgis" está FECHADA

`.github/workflows/tests.yml` (novo; o repo não tinha CI) roda em `push`/`pull_request`:

- **service container** `postgis/postgis:16-3.5` (`POSTGRES_DB=sile_testing`, healthcheck `pg_isready`); a extensão PostGIS já é criada no banco pelo init da imagem.
- `php-version: '8.5'` (alinhado ao runtime real — AGENTS.md/dev 8.5.4), extensões `pdo_pgsql, pgsql, mbstring`.
- passo 1: `php artisan test --compact --exclude-group postgis` (suíte SQLite).
- passo 2: `php artisan test --compact --group postgis` com `POSTGIS_TESTS_REQUIRED=true` (**enforcement W3b: skip vira FALHA**).
- `env` do job define `DB_TEST_PORT=5432` (CI) e vence o `.env` (Dotenv imutável do Laravel).

## Evidência de integração REAL (regra nº1 — sem fachada)

### 1. Chamada REAL ao Nominatim (endereço → coordenada)

Via o contrato de produção `app(App\Services\Geo\Geocoder::class)` (binding → `NominatimGeocoder`), cache limpo antes para garantir chamada ao vivo:

```
=== CHAMADA REAL AO NOMINATIM (via contrato Geocoder) 2026-06-13 23:45:44 ===
provider    : App\Services\Geo\NominatimGeocoder
endereco    : Elevador Lacerda, Salvador, Bahia, Brasil
latitude    : -12.9740882
longitude   : -38.5133597
confidence  : 0.34633143254185
display_name: Elevador Lacerda, Praça Visconde de Cayru, Comércio, Salvador, Bahia, Região Nordeste, 40015-170, Brasil
```

- Coordenada ≈ **lat -12.97 / lng -38.51** (central de Salvador), como esperado.
- Diagnóstico cru confirmou HTTP **200** do `https://nominatim.openstreetmap.org` real com User-Agent próprio (`SILE-SEDUR-Salvador/1.0 (contato@sedur.salvador.ba.gov.br)`) — **não é bloqueio**. ("Praça Municipal, Salvador, BA" retorna vazio legítimo no Nominatim; "Elevador Lacerda/Avenida Sete de Setembro/Salvador" resolvem normalmente.)
- Toggle `features.geocoding` = **ON** (default semeado `'1'`; `Settings::enabled('geocoding')=true`). **Nenhum toggle foi alterado** (a chamada usou o serviço diretamente) — nada a restaurar.

### 2. Consulta espacial REAL (golden point → bairro + versão)

`TerritoryService::identify` sobre o snapshot oficial após `migrate:fresh --seed`:

```
=== CONSULTA ESPACIAL REAL (golden) 2026-06-13 23:44:13 ===
Ponto: Farol da Barra (lat -13.0103 / lng -38.5320)
bairro.status = identificado
bairro.nome   = Barra
bairro.versao = geosalvador-bairros-dec38776-2024
zona.status   = indisponivel | motivo: Base de zoneamento pendente SEDUR
lote.status   = indisponivel | motivo: Base de lotes pendente SEDUR
auditoria territorio/identificacao (rows) = 1
```

Golden set verificado contra o dado real (regressão de domínio): **Farol da Barra→Barra**, **Pituba→Pituba**, **Shopping da Bahia→Caminho das Árvores** (171 bairros, versão `geosalvador-bairros-dec38776-2024`).

### 3. Bloqueios visíveis na UI (zona/lote)

- `TerritoryPageTest` **7/7 (39 asserções)** — inclui o teste que asserta `camadas` com `zona.status === 'pendente_fonte'` e `lote.status === 'pendente_fonte'` nas props da página.
- `resources/js/pages/gestao/territorio/index.tsx` renderiza, dirigido por status: `Badge "Indisponível"` para dimensões `indisponivel` (zona/lote) e `Badge "Pendente SEDUR"` + "Sem base pública — aguardando a SEDUR." para camadas `pendente_fonte`. **Nenhum valor é fabricado.**

## ENTREGUES vs BLOQUEADAS (para o orquestrador atualizar STATE.md/ROADMAP.md)

### Camadas ENTREGUES (lógica real, dado oficial commitado)

| Dimensão | HU | Estado | Evidência |
|---|---|---|---|
| **Bairro** | HU-034 | vigente, 171 feições | golden → "Barra" (ST_Contains real) |
| **Via** (mais próxima) | HU-032 | vigente, 800 feições (extrato central) | `PostgisSpatialRepositoryTest` (ST_DWithin/ST_Distance::geography) |
| **Restrição** (ZEIS) | HU-035 | vigente, 234 feições | `PostgisSpatialRepositoryTest` (ST_Intersects) |
| **Geocodificação** | HU-029 | Nominatim real (toggle ON) | chamada real → -12.974/-38.513 |
| **Identificação + auditoria** | HU-030/HU-036 | end-to-end | smoke + consulta real (audit RN-002) |
| **CI/enforcement postgis** | — | ativo | `.github/workflows/tests.yml` |

### BLOQUEADAS — pendentes SEDUR (comunicadas, NUNCA inventadas)

| Dimensão | HU | Motivo do bloqueio | Como está comunicado |
|---|---|---|---|
| **Zona urbanística LOUOS** | HU-031 | Sem camada vetorial pública (Quadro 10 só em PDF; pasta ArcGIS LOUOS vazia) | `pendente_fonte` + "Indisponível — Base de zoneamento pendente SEDUR" |
| **Lote cadastral** | HU-033 | Base restrita (Cadastro Multifinalitário / SEFAZ) | `pendente_fonte` + "Indisponível — Base de lotes pendente SEDUR" |
| **Validação de sobreposição com lote (RN-004/RN-005)** | HU-037 | Depende do lote cadastral acima | `validar-localizacao` retorna `indisponivel` + Alert info; polígono rotulado "perímetro do imóvel" (placeholder honesto) |
| **Classificação viária operacional (Quadros 11/11A)** | HU-032 | Geometria da via entra; atributo de classificação pende confirmação SEDUR (PDDU ≟ LOUOS Mapa 04) | nota no 04-04-SUMMARY |

A Fase 5 (motor de enquadramento) depende da **zona** — o bloqueio precisa permanecer explícito no STATE/ROADMAP. Quando a SEDUR entregar zona/lote como camada vigente com feições, a MESMA `identify` passa a identificá-las (a carga muda, a lógica não — sem fachada nos dois sentidos).

## TDD do smoke (RED → GREEN, evidência)

- **RED:** smoke escrito com expectativa propositalmente errada (`Farol da Barra → Pelourinho`); `--group postgis` rodou e falhou na asserção (não skip, não erro): `Failed asserting that two strings are identical. -'Pelourinho' +'Barra'`. Prova que a consulta espacial real executa e a asserção tem dentes.
- **GREEN:** corrigido para o valor real `Barra`; `2/2 passed, 24 asserções`.

## Decisões (com fundamentação)

- **PHP 8.5 no CI:** o esqueleto do plano sugeria 8.4, mas o runtime real é 8.5.4 (AGENTS.md fixa 8.5). CI fiel ao ambiente de dev/prod.
- **Sem Redis no CI:** `phpunit.xml` força cache=array, queue=sync, session=array sob `APP_ENV=testing`; `predis` é PHP puro. Sem service container Redis nem extensão.
- **DevGeoSeeder não criado:** `DevAdminSeeder` (admin) e `GeoLayerSeeder` (camadas reais) já estão no `DatabaseSeeder`; a evidência navegável da UI de bloqueio vem do `TerritoryPageTest`. Evita duplicar carga (que vive no GeoLayerSeeder). Removido de `files_modified`.
- **Golden points por verificação, não suposição:** os bairros esperados foram obtidos por `ST_Contains` sobre o snapshot commitado real (descoberta empírica), depois travados no teste como proteção de regressão.

## Desvios do plano

- **DevGeoSeeder removido de `files_modified`** (não necessário — ver decisão acima). Nenhum outro desvio.
- Nenhuma mudança arquitetural; nenhuma dependência nova; nenhum bug de produção encontrado (a divergência inicial `Settings::enabled` era erro do diagnóstico — prefixo `features.` duplicado; a chamada correta `enabled('geocoding')` retorna `true`).

## Critérios de pronto da Fase 4 (ROADMAP) — satisfeitos com evidência

- CAs HU-029..HU-037 cobertos por PHPUnit (incl. tratamento honesto dos bloqueados HU-031/HU-033/HU-037).
- Navegável ponta a ponta com lógica real (geocodificação real + consulta espacial real).
- Sem hardcode de valor de negócio (toggle/limiar/base_url parametrizados).
- Integração validada contra o real com evidência registrada (Nominatim 200; SQL espacial sobre dado oficial).
- pint/typecheck/build limpos; telas responsivas/acessíveis (04-07); auditoria mínima (RN-002).
- Enforcement real do grupo postgis no CI (skip vira falha).
- Componente de mapa (`MapImovel` + `MapaSection`) pronto para reuso nas Fases 7/8.

---
*Phase: 04-georreferenciamento-e-territorio*
*Completed: 2026-06-13*

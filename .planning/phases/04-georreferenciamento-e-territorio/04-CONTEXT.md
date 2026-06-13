# Phase 4: Georreferenciamento e Território - Context

**Gathered:** 2026-06-13
**Status:** Ready for planning
**Source:** Brainstorming aprovado pelo usuário (spec `docs/superpowers/specs/2026-06-13-georreferenciamento-design.md`)

<domain>
## Phase Boundary

Localizar imóveis no território de Salvador (geocodificação de endereço) e identificar zona urbanística, classificação da via, lote, bairro e restrições territoriais a partir de **camadas geográficas versionadas** (PostGIS) — insumos do motor de regras da LOUOS (Fase 5). Inclui mapa interativo (Leaflet) para localizar, consultar camadas e validar/ajustar a localização do imóvel (com alerta de baixa sobreposição ao lote). Tudo auditado, com camadas como dados versionados com vigência.

Fora do escopo: integração viva com SIGIS/CA 2000 (HU-107 — Fase 13, pendente acesso SEDUR); consumo dos dados pelo motor de enquadramento (Fase 5); validação por inscrição imobiliária quando não houver base cadastral pública (entra quando o dado existir).
</domain>

<decisions>
## Implementation Decisions

### Dados geográficos (decisão travada)
- Carregar **dados oficiais PÚBLICOS reais** agora, substituíveis pela base SEDUR quando entregue. **Bairros de Salvador (IBGE)** entram completos (GeoJSON público). Zona LOUOS / via / lote: a pesquisa confirma a fonte pública (portal de dados abertos de Salvador / anexos georreferenciados da Lei 9.148/2016); onde NÃO houver dado público, a camada fica **explicitamente bloqueada** (STATE/ROADMAP + aviso na UI) — NUNCA polígono inventado (regra de entrega funcional).

### Backend espacial (PostGIS já habilitado)
- Camadas versionadas (HU-036 RN-004): `geo_layers` (type enum bairro/zona/via/lote/restricao, version, valid_from, valid_to, source, rules_version, feature_count) + `geo_features` (layer_id, geometry SRID 4326, properties jsonb, índice GiST). Carga de nova versão não apaga a anterior; consulta operacional usa a vigente, reprodução usa a da época.
- `GeoLayerService`: carga/versão/vigência + diff auditado (HU-036 RN-005: origem, responsável, data, features +/-/~).
- `TerritoryService`: identificação por ponto — `ST_Contains` (bairro/zona/lote), `ST_DWithin`/`ST_Distance` (via mais próxima), interseção (restrições). Resultado registra a versão de cada camada.

### Geocodificação (HU-029) atrás de contrato
- Interface `Geocoder` + `NominatimGeocoder` (padrão `CnpjLookup` da Fase 3): endereço → lat/lng + endereço normalizado + confiança; cache de sucesso; throttle (≤1 req/s, User-Agent obrigatório) e retry/backoff parametrizados (reusa a infra da Fase 3.1). Toggle `features.geocoding`; `integrations.geocoding.base_url` parametrizado (trocável por self-host sem deploy). Endpoint auditado.

### Mapa (Leaflet — dependência aprovada)
- `leaflet` + `react-leaflet` + tiles OSM (sem chave). Mapa com ponto geocodificado, marcador arrastável (ajuste HU-037), overlay de camadas (HU-036), popup com zona/via/lote/bairro/restrições. Componente reutilizável (Fases 7 e 8).

### Validação de localização (HU-037)
- Polígono informado × lote oficial: `ST_Area(ST_Intersection)/ST_Area` ≥ `geo.validacao.sobreposicao_minima` (parâmetro); abaixo do limiar → alerta registrado no processo. Divergência zona/via (polígono × inscrição imobiliária) apontada quando houver dado cadastral.

### Parâmetros novos (catálogo HU-014)
- `features.geocoding` — boolean, default true (grupo features).
- `integrations.geocoding.base_url` — string url, default Nominatim, requires_connection_test (grupo integracoes).
- `seguranca.throttle.geocoding.por_minuto` — integer, default 60 (Nominatim ~1 req/s) (grupo seguranca).
- `geo.validacao.sobreposicao_minima` — integer/decimal (percentual), default 50, faixa válida (grupo novo `geo` ou `territorio`).
- Constantes técnicas (timeout/cache_ttl) em config/sile.php.

### Claude's Discretion
- Nomes exatos de tabelas/serviços/migrations; SRID (4326 recomendado); estrutura dos testes (com PostGIS — atenção ao banco de teste); organização dos componentes React do mapa.
</decisions>

<canonical_refs>
## Canonical References

### Spec da fase
- `docs/superpowers/specs/2026-06-13-georreferenciamento-design.md` — desenho aprovado.

### HUs (CAs BDD)
- `docs/SILE_HUs_Completas_MD/EP04-Georreferenciamento-e-Território/HU-029..HU-037.md` — HU-036 (camadas versionadas com vigência, diff auditado) e HU-037 (sobreposição polígono×lote, limiar parametrizado; divergência zona/via) têm as RNs específicas.

### Padrões a reusar
- `app/Services/Cnpj/CnpjLookup.php` + `BrasilApiCnpjLookup.php` — padrão de contrato+provider+cache para o `Geocoder`.
- Fase 3.1: throttle parametrizado (`RateLimiter::for` no FortifyServiceProvider) e retry HTTP via `Settings::get` — reusar para geocodificação.
- `app/Support/Settings.php` + `database/seeders/ParameterSeeder.php` — parâmetros novos (catálogo hoje 25).
- `app/Support/Audit/AuditService.php` + concern `HasAuditoria` — auditoria das cargas e consultas.
- `docker/postgres/init/01-extensions.sql` — PostGIS já habilitado (CREATE EXTENSION postgis).

### Atenção a testes com PostGIS
- A suíte usa SQLite `:memory:` (phpunit.xml) — geometria PostGIS NÃO roda em SQLite. Avaliar: testar `TerritoryService` contra Postgres de teste (conexão dedicada) OU isolar a lógica espacial atrás de um repositório com fake em memória para os testes de unidade, mantendo um teste de integração PostGIS. A pesquisa/decisão de planejamento deve resolver isto explicitamente (sem fachada nos testes).
</canonical_refs>

<specifics>
## Specific Ideas
- Geocodificação reusa o throttle/retry da Fase 3.1 (Nominatim exige ≤1 req/s + User-Agent identificável).
- SRID 4326 (WGS84) para compatibilidade com Leaflet/OSM.
- Carga de camada é idempotente por (type, version); diff auditado.
</specifics>

<deferred>
## Deferred Ideas
- SIGIS/CA 2000 vivo (HU-107) — Fase 13.
- Camadas sem fonte pública (zona/via/lote, se a pesquisa não achar dado aberto) — bloqueadas pendente SEDUR.
- Validação por inscrição imobiliária (HU-037 RN-005) — depende de base cadastral oficial.
- Self-host do Nominatim — decisão de deploy.
</deferred>

---

*Phase: 04-georreferenciamento-e-territorio*
*Context gathered: 2026-06-13 via brainstorming aprovado*

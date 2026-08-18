# Fase 4 — Georreferenciamento e Território

**Data:** 2026-06-13
**Status:** Aprovado
**Fase:** 4 (EP04)
**Requisitos:** HU-029 a HU-037

## Objetivo

O sistema localiza imóveis no território de Salvador (geocodificação) e identifica zona urbanística, classificação da via, lote, bairro e restrições territoriais a partir de **camadas geográficas versionadas** — insumos do motor de regras da LOUOS (Fase 5). Mapa interativo para localizar, consultar camadas e validar/ajustar a localização.

## Decisões travadas (aprovadas pelo usuário em 2026-06-13)

1. **Dados oficiais públicos agora**, substituíveis pela base SEDUR (SIGIS/CA 2000) quando entregue. Camada sem fonte pública real fica **explicitamente bloqueada** (STATE/ROADMAP + aviso na UI) — nunca polígono inventado (regra de entrega funcional).
2. **Leaflet + react-leaflet** (tiles OSM, sem chave) para o mapa — dependência front nova **aprovada**.
3. **Geocodificação via Nominatim/OSM atrás de contrato** (`Geocoder` interface + provider), `base_url`+toggle parametrizados, cache, herdando throttle/retry da Fase 3.1. Trocável por self-host sem deploy.
4. **PostGIS** (já habilitado no banco) para armazenamento e consulta espacial — sem dependência nova no backend.

## Arquitetura

### Backend espacial
- **Camadas como dados versionados** (HU-036 RN-004): `geo_layers` (type, version, valid_from, valid_to, source, rules_version, feature_count) + `geo_features` (layer_id, geometry [PostGIS geometry SRID 4326], properties jsonb). Carga de nova versão NÃO apaga a anterior; consulta operacional usa a vigente, reprodução usa a da época.
- **Tipos de camada:** `bairro`, `zona`, `via`, `lote`, `restricao` (enum). Cada um é dado, não código.
- **Identificação espacial** (`TerritoryService`): dado lat/lng → `ST_Contains` resolve bairro/zona/lote; `ST_DWithin`/`ST_Distance` resolve via mais próxima; restrições incidentes por interseção. Sempre na versão vigente (ou na versão de uma data, para reprodução). Resultado registra a versão de cada camada consultada.
- **Geocodificação** (`Geocoder` + `NominatimGeocoder`): endereço → lat/lng + endereço normalizado + confiança; cache de sucesso; throttle (≤1 req/s) e retry parametrizados (Fase 3.1). Toggle `features.geocoding` e `integrations.geocoding.base_url`.
- **Validação de localização** (HU-037): compara o polígono informado ao lote oficial (`ST_Area(ST_Intersection)/ST_Area` ≥ limiar parametrizado `geo.validacao.sobreposicao_minima`); abaixo do limiar gera alerta registrado. Divergência zona/via (polígono × inscrição imobiliária) apontada quando houver dado cadastral.

### Frontend
- **Leaflet + react-leaflet**: mapa com o ponto geocodificado, marcador arrastável para ajuste (HU-037), overlay das camadas consultáveis (HU-036), popup com zona/via/lote/bairro/restrições identificados. Tiles OSM. Componente reutilizável para a consulta prévia (Fase 7) e a solicitação (Fase 8).

### Carga de dados reais
- Comando/seed de import de **GeoJSON oficial** → `geo_features`, com versão+vigência+diff auditado (HU-036 RN-005).
- **Bairros de Salvador (IBGE)**: GeoJSON público confiável — entra completo.
- **Zonas LOUOS / vias / lotes**: a pesquisa da fase (gsd-plan-phase --research) confirma a fonte pública (portal de dados abertos de Salvador / anexos georreferenciados da Lei 9.148/2016). Onde houver dado real, carrega; onde não, a camada fica bloqueada pendente SEDUR.

## Decomposição (sub-entregas → planos)

1. **Fundação PostGIS**: migrations `geo_layers`/`geo_features` (geometry + gist index), models, `GeoLayerService` (versão/vigência/diff auditado), enums. Parâmetros novos (toggle geocoding, base_url, limiar de sobreposição).
2. **Geocodificação (HU-029)**: contrato `Geocoder` + `NominatimGeocoder` real + cache + throttle/retry + endpoint auditado.
3. **Carga de dados reais (HU-036 base)**: comando de import GeoJSON versionado/auditado; seed dev com bairros IBGE reais; demais camadas conforme pesquisa.
4. **Identificação territorial (HU-031–035)**: `TerritoryService` PostGIS (zona/via/lote/bairro/restrições) sobre as camadas; auditoria com versões.
5. **Mapa + validação (HU-030, HU-036 UI, HU-037)**: UI Leaflet, consulta de camadas, confirmação/ajuste da localização com alerta de sobreposição.
6. **Fechamento**: verificação integral + evidência (geocodificação real + consulta espacial real sobre dados oficiais carregados).

## Critério de pronto (espelha o ROADMAP)

1. Endereço geocodificado e imóvel exibido em mapa interativo.
2. Para uma localização, o sistema identifica zona, via, lote e bairro a partir das camadas carregadas (as que têm dado real).
3. Restrições incidentes identificadas; camadas consultáveis no mapa.
4. Localização validada (confirmada/ajustada) antes de prosseguir; polígono comparado ao lote com alerta por baixa sobreposição (limiar parametrizado).
5. Camadas são dados versionados com vigência; decisões registram a versão consultada; reprodução usa a versão da época.
6. Tudo auditado (RN-002); nada de fachada — camada sem dado público fica bloqueada, não inventada.

## Fora de escopo (YAGNI / bloqueado)

- Integração viva com SIGIS/CA 2000 (HU-107) — Fase 13, pendente acesso SEDUR.
- Camadas sem fonte pública (zona/via/lote, se a pesquisa não achar dado aberto) — bloqueadas até a SEDUR entregar, com aviso na UI.
- Validação por inscrição imobiliária (HU-037 RN-005) — depende de base cadastral oficial; entra quando o dado existir.

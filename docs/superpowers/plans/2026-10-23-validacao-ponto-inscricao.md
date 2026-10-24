# Validação do ponto da inscrição Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** O ponto devolvido pela SEFAZ só serve para achar lote, zona e via quando está a até 150 m da rua da inscrição; senão o sistema busca um ponto melhor e, se nenhum confirmar, encaminha o processo para análise com alerta.

**Architecture:** Um validador (`ValidadorPontoInscricao`) entra no `LocalizacaoInscricaoService`: mede a distância do ponto da SEFAZ até o eixo da rua (WFS da SEDUR, pelo CODLOG), tenta pontos alternativos em ordem (endereçamento SEDUR, SEMAP, Nominatim) e devolve a localização já com o ponto final e o resultado da conferência. O fluxo expresso encaminha para análise quando a conferência termina em "não confirmado".

**Tech Stack:** Laravel 13, PHP 8.5, PHPUnit 12 (`Http::fake`), GeoServer WFS (GeoJSON em EPSG:4326).

**Spec:** `docs/superpowers/specs/2026-10-23-validacao-ponto-inscricao-design.md`

## Global Constraints

- Limite de distância: **150 m**, configurável (`viabiliza.geo.validacao_ponto.distancia_maxima_m`).
- Ordem das fontes alternativas: endereçamento SEDUR, SEMAP, Nominatim.
- A analista só vê aviso quando nenhuma fonte confirmar; a troca bem-sucedida só vai para a auditoria.
- Falha de fonte (indisponível, desligada, sem resultado, fora de Salvador) passa para a próxima e nunca bloqueia o processo.
- Sem CODLOG utilizável ou sem eixo da rua na SEDUR: não verifica, o ponto da SEFAZ segue como hoje, auditoria registra "não verificada".
- Nome do sistema nos textos: Viabiliza. Sem coautoria de IA em commit.
- Testes em PHPUnit, sem tocar o GeoServer real (`Http::fake`).

## Review Focus

- Lote de esquina com o ponto da SEFAZ correto (a até 150 m da rua da inscrição): não pode ser trocado nem alertado (Task 4).
- Rua do CODLOG sem nenhum segmento na SEDUR: "não verificada", sem alerta e sem troca (Task 2 e 4).
- Todas as fontes alternativas indisponíveis ao mesmo tempo (token SEMAP ausente, Nominatim fora): vira "não confirmado", nunca exceção (Task 3 e 4).
- Nominatim devolvendo ponto fora de Salvador ou longe da rua: descartado (Task 3).
- Ponto exatamente em 150 m: conta como confirmado (Task 1).

---

### Task 1: Cálculo de distância geográfica

**Files:**
- Create: `app/Services/Geo/DistanciaGeografica.php`
- Test: `tests/Unit/Geo/DistanciaGeograficaTest.php`

**Interfaces:**
- Produces:
  - `DistanciaGeografica::entrePontos(float $lat1, float $lng1, float $lat2, float $lng2): float` — metros (haversine).
  - `DistanciaGeografica::ateLinhas(float $lat, float $lng, array $linhas): ?float` — metros do ponto até a linha mais próxima; `$linhas` é `list<list<array{0: float, 1: float}>>` (cada linha, uma lista de `[lng, lat]`); `null` se não houver linha com 2+ vértices.
  - `DistanciaGeografica::centroideDoPoligono(array $geojson): ?array{lat: float, lng: float}` — centroide do anel exterior de um Polygon/MultiPolygon; reaproveitar o cálculo de `ViabilityRequest::centroideDoPoligono` extraindo-o para cá se for simples, senão duplicar o mínimo.

- [ ] **Step 1: Escrever os testes**

`test_ponto_sobre_a_linha_tem_distancia_zero`, `test_distancia_ate_segmento_usa_a_projecao_e_nao_so_os_vertices` (ponto a ~100 m do meio de um segmento longo, com os vértices a mais de 1 km: esperar ≈100 m, tolerância 5 m), `test_sem_linhas_devolve_null`, `test_linha_com_um_vertice_e_ignorada`, `test_entre_pontos_conhece_um_grau_de_latitude` (≈111 km, tolerância 1%), `test_centroide_do_poligono_quadrado`.

- [ ] **Step 2: Rodar e ver falhar** — `php artisan test --compact --filter=DistanciaGeograficaTest` → FAIL (classe inexistente).

- [ ] **Step 3: Implementar a classe** em `app/Services/Geo/DistanciaGeografica.php`. Para o ponto-segmento, projetar em plano local equiretangular (metros) em torno do ponto e fazer a distância ponto-segmento clássica.

- [ ] **Step 4: Rodar e ver passar** — mesmo comando → PASS.

- [ ] **Step 5: Commit** — `git add app/Services/Geo/DistanciaGeografica.php tests/Unit/Geo/DistanciaGeograficaTest.php && git commit -m "feat: cálculo de distância entre ponto, linhas e polígonos"`

---

### Task 2: Eixo da rua pelo CODLOG (WFS da SEDUR)

**Files:**
- Create: `app/Services/Geo/GeoServerEixoViarioClient.php`, `app/Services/Geo/EixoViarioHit.php`
- Test: `tests/Feature/Geo/GeoServerEixoViarioClientTest.php`

**Interfaces:**
- Consumes: a mesma camada e URL base de `GeoServerClassificacaoViariaClient` (`viabiliza.integrations.geoserver.classificacao_viaria.type_name`, `Settings::get('integrations.geoserver.base_url', …)`, `user_agent`, `timeout`); toggle `Settings::enabled('geoserver_via_classe')`.
- Produces:
  - `EixoViarioHit` (`final readonly`): `status` (`IDENTIFICADO | NAO_ENCONTRADO | INDISPONIVEL | DESLIGADO`), `array $linhas` (formato de `DistanciaGeografica::ateLinhas`), `?string $motivo`.
  - `GeoServerEixoViarioClient::consultar(string $codLog): EixoViarioHit` — memoizado por CODLOG na instância e em `cache()` por 24 h só quando `IDENTIFICADO`.

- [ ] **Step 1: Escrever os testes** (`Http::fake`): `test_junta_todos_os_segmentos_da_rua` (3 features, uma `LineString` e uma `MultiLineString` → 3+ linhas), `test_pede_todos_os_segmentos_e_nao_so_um` (a URL não leva `maxFeatures=1`; leva `CQL_FILTER=CODLOG=<codigo>`), `test_codlog_sem_feicao_e_nao_encontrado`, `test_falha_de_rede_e_indisponivel_e_nao_estoura`, `test_toggle_desligado_nao_chama_o_servidor`, `test_segunda_consulta_do_mesmo_codlog_nao_chama_de_novo`.

- [ ] **Step 2: Rodar e ver falhar** — `php artisan test --compact --filter=GeoServerEixoViarioClientTest`.

- [ ] **Step 3: Implementar** seguindo `GeoServerClassificacaoViariaClient::consultarNoGeoServer` (mesma URL, mas `maxFeatures` alto, p.ex. 500, sem `propertyName` para trazer a geometria).

- [ ] **Step 4: Rodar e ver passar.**

- [ ] **Step 5: Commit** — `feat: eixo da rua pelo CODLOG na camada viária da SEDUR`.

---

### Task 3: Pontos alternativos por endereço

**Files:**
- Create: `app/Services/Geo/PontoAlternativo.php`, `app/Services/Geo/PontoAlternativoResolver.php`
- Modify: `app/Enums/PropertyLocationSource.php` (casos `EnderecamentoSedur = 'enderecamento'` e `Semap = 'semap'` com `label()`), e a migração/validação da coluna `viability_requests.property_location_source` **se** houver restrição de valores (checar `database/migrations`; criar migração só se necessário).
- Test: `tests/Feature/Geo/PontoAlternativoResolverTest.php`

**Interfaces:**
- Consumes: `GeoServerEnderecamentoClient::consultar(string $codLog, string $numero, ?string $complemento = null): EnderecamentoHit`; `SemapLoteClient::consultar(?string $inscricao): EnderecamentoHit`; `Geocoder::geocode(string $address): GeocodeResult`; `GeocodeResult::estaEmSalvador()`; `DistanciaGeografica::centroideDoPoligono`.
- Produces:
  - `PontoAlternativo` (`final readonly`): `float $lat`, `float $lng`, `PropertyLocationSource $fonte`.
  - `PontoAlternativoResolver::candidatos(string $inscricao, ?string $codLog, ?string $numero, ?string $enderecoParaGeocode): list<PontoAlternativo>` — na ordem endereçamento → SEMAP → Nominatim, **apenas as fontes que acharam**; fonte indisponível, desligada, ambígua, sem resultado, exceção (`AddressNotFoundException`, `GeocoderException`) ou ponto fora de Salvador é omitida.

- [ ] **Step 1: Escrever os testes**: `test_ordem_enderecamento_semap_nominatim`, `test_fonte_indisponivel_e_pulada_e_as_outras_seguem`, `test_todas_indisponiveis_devolve_lista_vazia_sem_excecao`, `test_nominatim_fora_de_salvador_e_descartado`, `test_enderecamento_ambiguo_e_descartado`, `test_sem_codlog_ou_numero_pula_o_enderecamento`, `test_sem_endereco_para_geocode_pula_o_nominatim`. Usar `Http::fake` e dublês do `Geocoder` (mesmo padrão de `LocalizacaoPelaInscricaoTest`).

- [ ] **Step 2: Rodar e ver falhar.**

- [ ] **Step 3: Implementar** o resolver; cada fonte em `try/catch` próprio.

- [ ] **Step 4: Rodar e ver passar.**

- [ ] **Step 5: Commit** — `feat: pontos alternativos por endereço (endereçamento, SEMAP, Nominatim)`.

---

### Task 4: Validador do ponto e integração na localização

**Files:**
- Create: `app/Services/Geo/ValidadorPontoInscricao.php`, `app/Services/Geo/ConfirmacaoPonto.php` (enum string-backed: `Confirmado`, `Substituido`, `NaoConfirmado`, `NaoVerificado`)
- Modify: `app/Services/Realty/LocalizacaoInscricaoService.php` (injetar o validador; aplicar em `consultar` quando `$temPonto`, origem `CadastroImobiliario`), `app/Services/Realty/LocalizacaoInscricao.php` (campos novos), `config/viabiliza.php` (bloco `geo.validacao_ponto`), `database/seeders/ParameterSeeder.php` (toggle `features.validacao_ponto_inscricao`, ligado)
- Test: `tests/Feature/Geo/ValidadorPontoInscricaoTest.php`; ajustar `tests/Feature/Geo/LocalizacaoPelaInscricaoTest.php` se um teste existente quebrar

**Interfaces:**
- Consumes: Tasks 1–3.
- Produces:
  - `ValidadorPontoInscricao::validar(float $lat, float $lng, ?string $codLog, ?string $numero, ?string $enderecoParaGeocode, string $inscricao): ResultadoPonto` — `ResultadoPonto` (`final readonly`, mesmo arquivo ou arquivo próprio): `ConfirmacaoPonto $confirmacao`, `float $lat`, `float $lng`, `PropertyLocationSource $origem`, `?float $distanciaOriginalM`, `?float $distanciaFinalM`.
  - Novos campos em `LocalizacaoInscricao` (todos com default `null`): `?ConfirmacaoPonto $confirmacaoPonto`, `?float $pontoSefazLat`, `?float $pontoSefazLng`, `?float $distanciaPontoM`.
  - Regra: eixo `DESLIGADO`/`INDISPONIVEL`/`NAO_ENCONTRADO` ou CODLOG ausente → `NaoVerificado` com o ponto da SEFAZ; distância ≤ limite → `Confirmado`; senão o primeiro candidato do resolver com distância ≤ limite → `Substituido` (lat/lng/origem do candidato); nenhum → `NaoConfirmado` com o ponto da SEFAZ.
  - Toggle desligado → ponto da SEFAZ sem conferência (`NaoVerificado`).

- [ ] **Step 1: Escrever os testes** com o eixo e as fontes dublados: `test_ponto_perto_da_rua_e_confirmado_sem_chamar_fontes_alternativas`, `test_lote_de_esquina_com_ponto_a_100m_da_rua_nao_e_trocado`, `test_ponto_a_exatos_150m_e_confirmado`, `test_ponto_na_barros_reis_com_codlog_da_heitor_dias_e_substituido_pelo_endereco` (caso do processo 1441: ponto a ~600 m do eixo, endereçamento devolve ponto a 20 m), `test_candidato_longe_da_rua_tambem_e_recusado_e_o_proximo_e_tentado`, `test_nenhuma_fonte_confirma_vira_nao_confirmado_com_ponto_da_sefaz`, `test_sem_eixo_na_sedur_vira_nao_verificado_e_mantem_o_ponto`, `test_sem_codlog_vira_nao_verificado`, `test_toggle_desligado_mantem_ponto_da_sefaz`; em `LocalizacaoPelaInscricaoTest`: `test_localizacao_expoe_ponto_original_origem_e_confirmacao_quando_substituido`.

- [ ] **Step 2: Rodar e ver falhar.**

- [ ] **Step 3: Implementar** o validador (usa `LocalizacaoInscricao::codLogDoEndereco()` como CODLOG e `PropertyRegistryResult::cadastro->numero_porta` como número) e ligá-lo em `LocalizacaoInscricaoService::consultar`: no ramo `$temPonto`, trocar `lat/lng/origemPonto` pelo resultado e preencher os campos novos. O ramo do geocode da SEFAZ (sem coordenada) não passa pelo validador.

- [ ] **Step 4: Rodar** `php artisan test --compact --filter=ValidadorPontoInscricaoTest` e `--filter=LocalizacaoPelaInscricaoTest` → PASS.

- [ ] **Step 5: Commit** — `feat: valida o ponto da SEFAZ pela rua da inscrição e busca ponto alternativo`.

---

### Task 5: Auditoria e alerta que encaminha para análise

**Files:**
- Modify: `app/Services/Geo/TerritorioProcessoService.php` (auditar a conferência ao gravar o ponto, em torno da linha 181), `app/Services/Expresso/FluxoExpressoService.php` (novo gate antes da divergência de zona, perto das linhas 372–395), `app/Services/Decisao/DecisionTextCatalog.php` e `database/seeders/DecisionTextSeeder.php` (texto `localizacao.motivo.ponto_nao_confirmado`)
- Test: `tests/Feature/Geo/TerritorioProcessoTest.php` (auditoria), `tests/Feature/Expresso/` (novo `PontoNaoConfirmadoTest.php`, seguindo o padrão dos testes de divergência já existentes nessa pasta)

**Interfaces:**
- Consumes: `LocalizacaoInscricao::$confirmacaoPonto`, `$pontoSefazLat/Lng`, `$distanciaPontoM`.
- Produces: texto `localizacao.motivo.ponto_nao_confirmado` — "Localização não confirmada: o ponto da inscrição no Cadastro Imobiliário está a :distancia m da :logradouro e nenhuma outra fonte de endereço confirmou o local — segue para análise técnica." (placeholders `:distancia`, `:logradouro`); gatilho `TipoGatilho::LocalizacaoDivergente`.

- [ ] **Step 1: Escrever os testes**: `test_ponto_nao_confirmado_encaminha_para_analise_com_o_motivo` (o motivo traz a distância e o nome da rua, e não sai decisão automática), `test_ponto_substituido_nao_gera_alerta_e_segue_o_fluxo`, `test_ponto_nao_verificado_nao_gera_alerta`, `test_auditoria_guarda_ponto_original_fonte_final_distancias_e_motivo` (evento de auditoria no log `geo`, propriedades `ponto_sefaz`, `ponto_final`, `fonte`, `confirmacao`, `distancia_original_m`, `distancia_final_m`).

- [ ] **Step 2: Rodar e ver falhar.**

- [ ] **Step 3: Implementar** o gate no `FluxoExpressoService` no mesmo estilo dos gates vizinhos (`encaminharAnalise(..., gatilho: TipoGatilho::LocalizacaoDivergente)`), a auditoria em `TerritorioProcessoService` e o texto no catálogo e no seeder.

- [ ] **Step 4: Rodar** os testes novos e `php artisan test --compact tests/Feature/Expresso tests/Feature/Geo/TerritorioProcessoTest.php` → PASS sem falhas novas.

- [ ] **Step 5: Commit** — `feat: ponto não confirmado encaminha para análise com alerta e audita a conferência`.

---

### Task 6: Fechamento

- [ ] **Step 1:** `vendor/bin/pint --dirty --format agent`.
- [ ] **Step 2:** `php artisan test --compact` completo; comparar as falhas com as já existentes (as falhas anteriores a esta mudança ficam registradas, não corrigidas).
- [ ] **Step 3:** Conferir manualmente o critério de aceite da spec com um teste: ponto da Barros Reis + CODLOG 2075 → `Substituido` ou `NaoConfirmado`, nunca `Confirmado`.
- [ ] **Step 4: Commit** do que o Pint ajustar, se houver.

---

## Self-review

- **Cobertura da spec:** conferência no serviço de localização (Task 4), 150 m configurável (Tasks 1 e 4), ordem das fontes (Task 3), troca e origem (Task 4), alerta sem fonte que bata (Task 5), aviso só nesse caso (Task 5), falha de fonte não bloqueia (Tasks 2–4), registro do ponto original e distâncias (Task 5). Fora do escopo respeitado: sem correção dos processos antigos.
- **Risco aberto:** a camada `VM_L_CLASSIF_VIARIA` precisa devolver a geometria das linhas; a consulta atual a traz sem restrição de propriedades, mas isso só se confirma no GeoServer real (exige VPN). Se vier sem geometria, a Task 2 falha no teste de integração real e a alternativa é o eixo em `logradouros:VM_LOGRADOURO`.
- **Consistência de tipos:** `ConfirmacaoPonto`, `ResultadoPonto`, `PontoAlternativo` e `EixoViarioHit` usados com os mesmos nomes nas Tasks 2–5.

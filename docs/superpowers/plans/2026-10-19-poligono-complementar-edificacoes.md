# Polígono complementar por edificações — Plano de implementação

> **Para agentes:** SUB-SKILL OBRIGATÓRIA: use superpowers:subagent-driven-development (recomendado) ou superpowers:executing-plans para executar tarefa a tarefa. Os passos usam checkbox (`- [ ]`).

**Objetivo:** quando o lote oficial (endereçamento por chave e SEMAP) faltar, o processo recebe automaticamente um polígono de edificação: Overture → Microsoft → IA. O endereçamento SEDUR pelo ponto fica como última tentativa.

**Arquitetura:**
- Uma base local `building_footprints`, só em PostGIS, recortada pelo limite oficial do município e alimentada por comandos Artisan idempotentes.
- Um `PoligonoComplementarService` consulta essa base pelo ponto (prédio que contém o ponto; havendo sobreposição, o de menor área) e grava o resultado no mesmo campo do lote (`address_polygon_geojson`). A fonte vai em `address_polygon_source`.
- A IA roda em job próprio contra um serviço Python separado. A decisão espera por ela com prazo, no padrão de `aguardarCadastroImobiliario`.

**Stack:** Laravel 13, PHP 8.5, PostgreSQL + PostGIS, PHPUnit 12, Inertia + React 19 (TSX), Python 3.12 + FastAPI + segment-geospatial/SAM 2 (só no serviço de inferência).

**Spec:** `docs/superpowers/specs/2026-10-19-poligono-complementar-edificacoes-design.md`. Leia a spec antes de cada tarefa.

## Restrições globais

- **Ordem fixa das fontes:** chave SEDUR → SEMAP → Overture → Microsoft → IA → endereçamento SEDUR pelo ponto → nada.
- **Escolha do prédio:** o que contém o ponto (`ST_Covers`). Se houver mais de um, o de menor `area_m2`. Ponto fora de qualquer prédio = não encontrado naquela fonte. Nunca "o mais próximo".
- **Nunca sobrescreve nem apaga** polígono já gravado, de qualquer fonte. Oficial achado depois não substitui o complementar.
- **A decisão não muda.** `zona_fonte` continua `enderecamento_maior_porcao`, com o rótulo "Maior porção do lote". Nenhum texto do `DecisionTextCatalog` muda.
- **Legendas exatas:**
  - "Contorno tracejado: polígono do endereçamento SEDUR." (chave e ponto)
  - "Contorno tracejado: polígono do cadastro municipal (SEMAP)."
  - "Contorno tracejado: polígono do Overture."
  - "Contorno tracejado: polígono da Microsoft."
  - "Contorno tracejado: polígono gerado por IA."
  - Nunca usar a palavra "estimado" na tela.
- **Atribuições:**
  - Overture: "© OpenStreetMap contributors, Overture Maps Foundation".
  - Microsoft: "Microsoft".
  - IA: "Ortofoto: Prefeitura Municipal de Salvador".
- **Ponto elegível para os passos 3–5:** existe, e não é geocodificação sem número (`property_location_precision = via`). Também não é geocodificação de processo antigo sem precisão gravada (nulo com origem `geocodificacao`).
- **Sem ponto:** nenhuma fonte complementar, e o fluxo segue como hoje.
- **Geometria:** SRID 4326, normalizada com `ST_Multi(ST_MakeValid(...))`. Área entre 10 e 20.000 m² na importação.
- **Aceite da IA:** contém o ponto; área entre 20 e 5.000 m²; compacidade 4πA/P² ≥ 0,2; não encosta na borda do recorte.
- **Confiança:** Microsoft quando ≠ −1; Overture a partir de `sources[].confidence`, quando vier. IA sempre nula, e o score do modelo vai só para `properties.score_modelo`.
- **SQL espacial só em pgsql.** Testes SQLite usam fakes; os testes espaciais estendem `Tests\PostgisTestCase`.
- **Toggles desligados por padrão:**
  - `features.edificacoes_overture`
  - `features.edificacoes_microsoft`
  - `features.edificacoes_ia`
  - `features.edificacoes_importacao_agendada`
- **Código fora do PHP:** serviço de inferência em `docker/footprint-ia/` e script da Overture em `scripts/overture-salvador.sh`. Nenhuma pasta nova na raiz.
- **Nenhuma dependência nova no `composer.json` nem no `package.json`.**
- **Commits sem coautoria de IA.** `resources/js/pages/gestao/processos/show.tsx` tem WIP alheio: commitar só os hunks desta tarefa (`git add -p`).
- **Depois de mexer em PHP:** `vendor/bin/pint --dirty --format agent`.

## Foco da revisão

1. **Mesmo processo em dois caminhos ao mesmo tempo** (job do REGIN e job da decisão, ou reprocesso duplo). Comportamento esperado: um só polígono gravado, uma só inferência despachada. Teste na Tarefa 7 (`test_duas_resolucoes_simultaneas_gravam_um_poligono`) e na Tarefa 8 (`test_nao_despacha_segunda_inferencia_para_o_mesmo_ponto`).
2. **Ponto no meio da rua** (Nominatim sem número, ou coordenada fora de prédio). Comportamento esperado: nenhum prédio vizinho escolhido. Testes na Tarefa 7.
3. **Feição MultiPolygon da Overture** (prédio com partes). Comportamento esperado: o ponto do imóvel e a maior porção funcionam. Teste na Tarefa 1 e `test_multipolygon_entra_na_maior_porcao` na Tarefa 7.
4. **Importação interrompida no meio** (arquivo truncado ou queda do banco). Comportamento esperado: a base anterior continua valendo e a reexecução completa. Teste na Tarefa 5.
5. **Serviço de inferência lento ou fora do ar.** Comportamento esperado: a decisão não fica presa além do prazo e segue para o passo 6. Teste na Tarefa 9 (`test_prazo_esgotado_segue_para_o_endereçamento_pelo_ponto`).

---

## Mapa de arquivos

| Arquivo | Responsabilidade |
|---|---|
| `app/Enums/PoligonoFonte.php` (mod) | Novos casos, `oficial()`, `legenda()`, `atribuicao()` |
| `app/Enums/PropertyLocationPrecision.php` | `Numero` \| `Via` |
| `app/Enums/GeoLayerType.php` (mod) | `LimiteMunicipio` |
| `app/Enums/FonteEdificacao.php` | `Overture` \| `Microsoft` \| `Ia` (coluna `source` da base) |
| `app/Enums/StatusResolucaoPoligono.php` | Status da tentativa |
| `database/migrations/2026_10_19_*` | 3 tabelas + coluna de precisão |
| `app/Models/{BuildingFootprint,BuildingFootprintImport,LotPolygonResolution}.php` | Models + factories |
| `app/Services/Geo/Edificacoes/BuildingFootprintRepository.php` | Contrato da base |
| `app/Services/Geo/Edificacoes/PostgisBuildingFootprintRepository.php` | SQL espacial |
| `app/Services/Geo/Edificacoes/EdificacaoCandidata.php` | DTO do prédio escolhido |
| `app/Services/Geo/Edificacoes/ImportadorEdificacoes.php` | Transação, contadores e idempotência das importações |
| `app/Console/Commands/ImportarEdificacoesOvertureCommand.php` | `geo:edificacoes:importar-overture` |
| `app/Console/Commands/ImportarEdificacoesMicrosoftCommand.php` | `geo:edificacoes:importar-microsoft` |
| `app/Services/Geo/Edificacoes/Quadkey.php` | Quadkeys nível 9 que cruzam um bbox |
| `app/Services/Geo/Edificacoes/PoligonoComplementarService.php` | Cadeia 3–5 e gravação |
| `app/Services/Geo/ResolucaoPoligonoPorPonto.php` | Orquestra 3–6 para job, REGIN e reprocesso |
| `app/Services/Geo/Edificacoes/OrtofotoClient.php` | Recorte da ortofoto, com cache |
| `app/Services/Geo/Edificacoes/InferenciaEdificacaoClient.php` | Contrato HTTP do serviço Python |
| `app/Services/Geo/Edificacoes/AceiteMascaraIa.php` | Critérios de aceite |
| `app/Jobs/GerarPoligonoIaJob.php` | Recorte → inferência → aceite → gravação |
| `app/Console/Commands/MetricasEdificacoesCommand.php` | `geo:edificacoes:metricas` |
| `docker/footprint-ia/` | FastAPI + SAM 2, Dockerfile, teste de contrato |
| `scripts/overture-salvador.sh` | Extrai GeoJSONSeq por bbox |
| `docs/integracoes/edificacoes-complementares.md` | Operação e licenças |
| `tests/Support/Geo/FakeBuildingFootprintRepository.php` | Fake em memória |

---

### Tarefa 1: Pré-requisito — centroide de MultiPolygon

Pule esta tarefa se `ViabilityRequest::centroideDoPoligono` já tratar MultiPolygon. Ela pode ter sido feita pela sessão separada. Confira com o teste do passo 1.

**Arquivos:**
- Modificar: `app/Models/ViabilityRequest.php:218`
- Teste: `tests/Feature/Geo/ViabilityRequestPontoDoImovelTest.php`

**Interfaces:**
- Produz: `pontoDoImovel()` correto para um `address_polygon_geojson` MultiPolygon.

- [ ] **Passo 1:** escrever `test_centro_do_lote_multipolygon`.
  - Processo sem ponto e sem desenho.
  - `address_polygon_geojson` = MultiPolygon com dois quadrados: um em (−38.46, −12.98), com lado 0,001, e outro menor.
  - Afirmação: `lat` entre −12.981 e −12.979, e `lng` entre −38.461 e −38.459, ou seja, o centro do maior polígono.
- [ ] **Passo 2:** `php artisan test --compact --filter=test_centro_do_lote_multipolygon`. Esperado: FAIL, com ponto perto de (1, 1).
- [ ] **Passo 3:** em MultiPolygon, usar o anel exterior do polígono de maior área planar (fórmula do laço). Polygon continua igual.
- [ ] **Passo 4:** rodar `tests/Feature/Geo/ViabilityRequestPontoDoImovelTest.php`. Esperado: PASS.
- [ ] **Passo 5:** commit `fix: ponto do imóvel pelo centro do lote quando o lote é MultiPolygon`.

### Tarefa 2: Enums, schema e models

**Arquivos:**
- Modificar: `app/Enums/PoligonoFonte.php`, `app/Enums/GeoLayerType.php`, `app/Models/ViabilityRequest.php` (fillable/casts)
- Criar:
  - `app/Enums/FonteEdificacao.php`
  - `app/Enums/StatusResolucaoPoligono.php`
  - `app/Enums/PropertyLocationPrecision.php`
  - migrations `2026_10_19_100000_create_building_footprint_imports_table.php`, `..._100100_create_building_footprints_table.php`, `..._100200_create_lot_polygon_resolutions_table.php`, `..._100300_add_property_location_precision_to_viability_requests_table.php`
  - os três models com factories
- Teste: `tests/Feature/Geo/PoligonoFonteTest.php` (existe), `tests/Feature/Geo/EdificacoesSchemaTest.php`

**Interfaces:**
- Produz:
  - `PoligonoFonte::Overture='overture'`, `Microsoft='microsoft'`, `Ia='ia'`.
  - `PoligonoFonte::oficial(): bool`: true só para `Chave`, `Semap` e `Ponto`.
  - `PoligonoFonte::legenda(): string`: o texto exato das Restrições globais. `Chave` e `Ponto` compartilham a legenda SEDUR.
  - `PoligonoFonte::atribuicao(): ?string`: nulo para as oficiais.
  - `FonteEdificacao: string` com `Overture`, `Microsoft`, `Ia` e `paraPoligonoFonte(): PoligonoFonte`.
  - `StatusResolucaoPoligono: string` com `Encontrado='encontrado'`, `NaoEncontrado='nao_encontrado'`, `Indisponivel='indisponivel'`, `Pendente='pendente'`, `NaoResolvido='nao_resolvido'`, `Desligado='desligado'`.
  - `PropertyLocationPrecision: string` com `Numero='numero'`, `Via='via'`.
  - `GeoLayerType::LimiteMunicipio='limite_municipio'`, com label "Limite do município" e `isBlockedSource()` false.
  - `BuildingFootprint` (tabela `building_footprints`): colunas da spec §2, `confidence` decimal(5,4) nulo, casts jsonb para `source_datasets`/`properties`, `source` como `FonteEdificacao`.
  - `BuildingFootprintImport`: `source`, `release`, `checksum`, `status` (`em_andamento|concluida|falhou|ignorada`), `counters` (jsonb), `started_at`, `finished_at`, `error`.
  - `LotPolygonResolution`: colunas da spec §6. `source` como `PoligonoFonte`, `status` como `StatusResolucaoPoligono`.
  - `ViabilityRequest.property_location_precision` com cast `PropertyLocationPrecision`.

- [ ] **Passo 1:** testes que falham.
  - Em `PoligonoFonteTest`: `test_legenda_de_cada_fonte` (as 5 legendas exatas por caso), `test_so_fontes_da_prefeitura_sao_oficiais` e `test_atribuicao_das_complementares`.
  - Em `EdificacoesSchemaTest` (SQLite): `test_tabelas_e_coluna_de_precisao_existem`, com `Schema::hasColumns` para as 3 tabelas e para `viability_requests.property_location_precision`.
- [ ] **Passo 2:** rodar os dois arquivos. Esperado: FAIL.
- [ ] **Passo 3:** implementar.
  - A coluna `geometry` de `building_footprints` segue o padrão de `2026_06_13_214420_create_geo_features_table.php` (`$table->geometry(..., subtype: 'multipolygon', srid: 4326)`). GiST e unique (`source`, `source_id`) só em pgsql. O índice parcial `WHERE retired_at IS NULL AND duplicate_of_id IS NULL` também só em pgsql.
  - Em `lot_polygon_resolutions`, indexar (`viability_request_id`, `created_at`) e (`source`, `status`, `created_at`).
- [ ] **Passo 4:** rodar os mesmos testes mais `tests/Feature/Geo`. Esperado: PASS.
- [ ] **Passo 5:** commit `feat: schema da base de edificações complementares e procedência do polígono do lote`.

### Tarefa 3: Precisão do ponto do Nominatim

**Arquivos:**
- Modificar: `app/Services/Geo/TerritorioProcessoService.php:177-181`, `app/Models/ViabilityRequest.php`
- Teste: `tests/Feature/Geo/LocalizacaoPelaInscricaoTest.php` (existe)

**Interfaces:**
- Consome: `LocalizacaoInscricao::$geocode` (`?GeocodeResult`, já existe) e `GeocodeResult::$address`.
- Produz: `ViabilityRequest::pontoElegivelParaEdificacao(): bool`.
  - Há ponto, e:
    - a origem não é `Geocodificacao`; ou
    - a origem é `Geocodificacao` e `property_location_precision === Numero`.
  - O desenho do requerente (centroide) conta como elegível.

- [ ] **Passo 1:** testes que falham.
  - `test_geocodificacao_com_numero_grava_precisao_numero`: o fake do Nominatim devolve `address.house_number = '70'`. Afirmações: `property_location_precision === PropertyLocationPrecision::Numero` e `pontoElegivelParaEdificacao()` true.
  - `test_geocodificacao_so_da_via_grava_precisao_via`: sem `house_number`. Afirmações: `Via` e elegível false.
  - `test_ponto_do_cadastro_e_elegivel_sem_precisao`.
  - `test_processo_antigo_geocodificado_sem_precisao_nao_e_elegivel`.
- [ ] **Passo 2:** rodar o arquivo. Esperado: FAIL.
- [ ] **Passo 3:** no `forceFill` de `localizarPelaInscricao`, gravar `property_location_precision`. É `Numero` quando `filled($localizacao->geocode?->address['house_number'] ?? null)`, `Via` quando há geocode sem número, e nulo sem geocode.
- [ ] **Passo 4:** rodar `tests/Feature/Geo`. Esperado: PASS.
- [ ] **Passo 5:** commit `feat: grava se o Nominatim casou o número do imóvel`.

### Tarefa 4: Repositório da base de edificações e limite do município

**Arquivos:**
- Criar:
  - `app/Services/Geo/Edificacoes/BuildingFootprintRepository.php`
  - `PostgisBuildingFootprintRepository.php`
  - `EdificacaoCandidata.php`
  - `tests/Support/Geo/FakeBuildingFootprintRepository.php`
- Modificar: `app/Providers/AppServiceProvider.php` (bind incondicional, como `PrecedentRepository`)
- Teste:
  - `tests/Feature/Geo/BuildingFootprintRepositoryPostgisTest.php` (estende `PostgisTestCase`)
  - `tests/Feature/Geo/ImportarCamadaWfsCommandTest.php` (existe)

**Interfaces:**
- Produz:
  ```php
  interface BuildingFootprintRepository {
      public function contendoPonto(FonteEdificacao $fonte, float $lat, float $lng): ?EdificacaoCandidata;
      /** @param array<string,mixed> $geojson @return array{valida: bool, geojson: array<string,mixed>|null, area_m2: float|null, notas: list<string>} */
      public function normalizar(array $geojson): array;
      /** @param array<string,mixed> $geojson */
      public function dentroDoLimite(array $geojson): bool;
      public function limiteDisponivel(): bool;
      /** @param array<string,mixed> $geojson @return int|null id do prédio Overture ativo com IoU > $limiar */
      public function duplicadoOverture(array $geojson, float $limiar = 0.5): ?int;
  }
  final readonly class EdificacaoCandidata {
      public function __construct(
          public int $id, public FonteEdificacao $fonte, public string $sourceId, public string $release,
          public array $geojson, public float $areaM2, public ?float $confidence,
          public string $metodo, // 'contem_ponto' | 'menor_area_entre_sobrepostos'
      ) {}
  }
  ```
  - `contendoPonto` considera só linhas com `retired_at` e `duplicate_of_id` nulos e ordena por `area_m2` asc. `metodo` vale `menor_area_entre_sobrepostos` quando mais de uma linha cobre o ponto.
  - `dentroDoLimite` testa `ST_PointOnSurface(geom)` contra a camada `limite_municipio` vigente (`geo_layers`/`geo_features`).
  - `normalizar` aplica `ST_Multi(ST_CollectionExtract(ST_MakeValid(...),3))`, devolve a área por `::geography` e é inválida fora de 10–20.000 m² (limites em `viabiliza.edificacoes.area_min_m2`/`area_max_m2`).

- [ ] **Passo 1:** testes Postgis que falham, com prédios inseridos por SQL:
  - `test_prédio_que_contem_o_ponto`
  - `test_ponto_fora_de_qualquer_prédio_devolve_nulo` (prédio a 3 m do ponto)
  - `test_sobrepostos_vence_o_de_menor_area`, com metodo `menor_area_entre_sobrepostos`
  - `test_ignora_aposentado_e_duplicado`
  - `test_normalizar_corrige_gravata_borboleta`
  - `test_normalizar_recusa_area_fora_dos_limites`
  - `test_dentro_do_limite_usa_ponto_interior`
  - `test_duplicado_overture_por_iou`: 0,6 é duplicado e 0,3 não é.

  Em `ImportarCamadaWfsCommandTest`: `test_importa_limite_do_municipio`, com `geo:importar-wfs limite_municipio limite_salvador:VM_LIMITE_SALVADOR` e WFS fake.
- [ ] **Passo 2:** rodar os dois arquivos. Esperado: FAIL.
- [ ] **Passo 3:** implementar. O fake implementa a interface em memória. Na Tarefa 7, `contendoPonto` devolve candidatas pré-carregadas por fonte.
- [ ] **Passo 4:** rodar `php artisan test --compact tests/Feature/Geo`. Esperado: PASS, com o grupo postgis rodando de fato (sem skip).
- [ ] **Passo 5:** commit `feat: base local de edificações com consulta pelo ponto e limite do município`.

### Tarefa 5: Importação da Overture

**Arquivos:**
- Criar:
  - `app/Services/Geo/Edificacoes/ImportadorEdificacoes.php`
  - `app/Console/Commands/ImportarEdificacoesOvertureCommand.php`
  - `scripts/overture-salvador.sh`
  - `tests/Fixtures/geo/overture-amostra.geojsonseq` (5 feições, uma inválida e uma fora do limite)
- Teste: `tests/Feature/Geo/ImportarEdificacoesOverturePostgisTest.php`

**Interfaces:**
- Consome: `BuildingFootprintRepository::normalizar/dentroDoLimite/limiteDisponivel`.
- Produz:
  - `ImportadorEdificacoes::importar(FonteEdificacao $fonte, string $release, string $checksum, iterable $feicoes, callable $mapear): BuildingFootprintImport`.
  - `$mapear(array $feature): ?array{source_id, geojson, confidence, source_datasets, properties}`.
  - Comando: `geo:edificacoes:importar-overture {arquivo} {--release=}`.

Regras:
- **Mesma fonte + release + checksum já `concluida`:** cria registro `ignorada` e não toca na base.
- **Tudo numa transação:** upsert por (`source`, `source_id`), limpando `retired_at`. No fim, linhas da fonte ausentes nesta release recebem `retired_at = now()`. Erro → rollback, registro `falhou` com `error`, e a base anterior fica intacta.
- **Contadores:** `lidos`, `fora_do_limite`, `invalidos`, `corrigidos`, `inseridos`, `atualizados`, `aposentados`.
- **Sem limite importado:** o comando sai com código 1 e a mensagem "Importe o limite do município antes: geo:importar-wfs limite_municipio limite_salvador:VM_LIMITE_SALVADOR".
- **Mapeamento Overture:**
  - `source_id = properties.id`;
  - `confidence = max(sources[].confidence)` quando houver;
  - `properties` com `height`, `num_floors`, `class`, `subtype`.
- **Script:** `scripts/overture-salvador.sh <release> <saida>` chama `overturemaps download --bbox=-38.70,-13.02,-38.29,-12.73 -f geojsonseq --type=building -o <saida>`, e o DuckDB fica documentado como alternativa para fixar a release.

- [ ] **Passo 1:** testes que falham:
  - `test_importa_e_recorta_pelo_limite`: 3 inseridos, 1 fora do limite, 1 inválido.
  - `test_reimportar_mesma_release_e_checksum_nao_altera`
  - `test_release_nova_aposenta_ausentes`
  - `test_falha_no_meio_preserva_base_anterior`: a fixture tem uma linha JSON truncada no fim. Afirmações: a contagem ativa da release anterior não muda e o status é `falhou`.
  - `test_sem_limite_recusa`
- [ ] **Passo 2:** rodar. Esperado: FAIL.
- [ ] **Passo 3:** implementar. A leitura usa `SplFileObject` linha a linha, sem carregar o arquivo inteiro.
- [ ] **Passo 4:** rodar o arquivo. Esperado: PASS.
- [ ] **Passo 5:** commit `feat: importação idempotente das edificações da Overture recortada pelo limite de Salvador`.

### Tarefa 6: Importação da Microsoft com deduplicação

**Arquivos:**
- Criar: `app/Services/Geo/Edificacoes/Quadkey.php`, `app/Console/Commands/ImportarEdificacoesMicrosoftCommand.php`
- Teste:
  - `tests/Unit/Geo/QuadkeyTest.php`
  - `tests/Feature/Geo/ImportarEdificacoesMicrosoftPostgisTest.php`

**Interfaces:**
- Consome: `ImportadorEdificacoes::importar`, `BuildingFootprintRepository::duplicadoOverture`.
- Produz:
  - `Quadkey::cobrindo(float $oeste, float $sul, float $leste, float $norte, int $nivel = 9): list<string>`.
  - Comando: `geo:edificacoes:importar-microsoft {--arquivo=* : .csv.gz locais} {--release=}`.

Regras:
- **Sem `--arquivo`:** baixa `viabiliza.edificacoes.microsoft.dataset_links_url` (padrão `https://minedbuildings.z5.web.core.windows.net/global-buildings/dataset-links.csv`), filtra `Location = Brazil` e os `QuadKey` de `Quadkey::cobrindo(bbox do limite)`, e baixa cada `Url` em streaming (`gzopen`).
- **Release:** a opção ou a data de hoje.
- **Checksum:** sha256 da lista de URLs + tamanhos.
- **`source_id`:** `sha1(quadkey . ':' . WKB normalizado arredondado a 7 casas)`.
- **Campos:** `confidence` nulo quando −1; `height` em `properties` quando ≠ −1.
- **Duplicado:** se `duplicadoOverture(...)` devolver id, a linha é gravada com `duplicate_of_id`, e o contador `duplicados` sobe.

- [ ] **Passo 1:** testes que falham:
  - `QuadkeyTest::test_bbox_de_salvador_nivel_9`: todos têm 9 caracteres em `[0-3]`, sem repetição, e a lista contém `Quadkey::cobrindo(-38.501, -12.971, -38.499, -12.969)` (bbox mínimo em volta de um ponto do centro).
  - `QuadkeyTest::test_ponto_conhecido`: `Quadkey::cobrindo(0.0001, 0.0001, 0.0002, 0.0002, 1) === ['1']` e `Quadkey::cobrindo(-0.0002, -0.0002, -0.0001, -0.0001, 1) === ['2']` (convenção Bing).
  - `test_importa_arquivo_local_e_marca_duplicado_da_overture`
  - `test_confidence_menos_um_vira_nulo`
  - `test_baixa_so_brasil_e_quadkeys_do_bbox`: `Http::fake` no `dataset-links.csv` com 3 linhas (Brasil no bbox, Brasil fora, Argentina). Afirmação: um único download de tile.
- [ ] **Passo 2:** rodar. Esperado: FAIL.
- [ ] **Passo 3:** implementar.
- [ ] **Passo 4:** rodar os dois arquivos. Esperado: PASS.
- [ ] **Passo 5:** commit `feat: importação das edificações da Microsoft com descarte do que a Overture já tem`.

### Tarefa 7: Serviço complementar (Overture, Microsoft, reuso da IA) e procedência

**Arquivos:**
- Criar: `app/Services/Geo/Edificacoes/PoligonoComplementarService.php`
- Modificar: `config/viabiliza.php` (bloco `edificacoes` + `features.edificacoes_*` false)
- Teste:
  - `tests/Feature/Geo/PoligonoComplementarServiceTest.php` (SQLite + fake)
  - `tests/Feature/Geo/PoligonoComplementarMaiorPorcaoPostgisTest.php`

**Interfaces:**
- Consome: `BuildingFootprintRepository::contendoPonto`, `ViabilityRequest::pontoElegivelParaEdificacao()`, `pontoDoImovel()`.
- Produz:
  ```php
  final readonly class ResultadoComplementar {
      public function __construct(public StatusResolucaoPoligono $status, public ?PoligonoFonte $fonte = null, public bool $precisaIa = false) {}
  }
  class PoligonoComplementarService {
      public function resolver(ViabilityRequest $request): ResultadoComplementar;
      public function gravar(ViabilityRequest $request, EdificacaoCandidata $candidata, string $metodo): void;
      public function registrar(ViabilityRequest $request, PoligonoFonte $fonte, StatusResolucaoPoligono $status, array $extra = []): LotPolygonResolution;
  }
  ```

Regras de `resolver`:
1. **Sai sem fazer nada** quando o processo já tem `address_polygon_geojson` (status `Encontrado` com a fonte gravada) ou quando o ponto não é elegível (status `NaoEncontrado`, com motivo registrado para cada fonte ligada).
2. **Overture, depois Microsoft:** para cada fonte com o toggle ligado, chama `contendoPonto`. Se achar, `gravar` e retorna `Encontrado`. Se não, `registrar` com `NaoEncontrado`. Fonte desligada registra `Desligado`. Exceção do repositório registra `Indisponivel` e passa adiante.
3. **IA ligada:** chama `contendoPonto(Ia)` para reaproveitar. Se achar, grava. Se não, retorna `Pendente` com `precisaIa: true`.
4. **Caso contrário:** retorna `NaoEncontrado`.

Regras de `gravar`:
- Usa `Cache::lock("poligono-ponto:{id}", 90)`.
- Faz `refresh` e só grava se ainda não houver polígono.
- Campos: `address_polygon_geojson`, `address_polygon_source`, `address_polygon_consulted_at`.

`registrar` preenche todas as colunas da spec §6, com `latency_ms` medido.

- [ ] **Passo 1:** testes que falham (`PoligonoComplementarServiceTest`):
  - `test_overture_grava_poligono_e_procedencia`
  - `test_microsoft_so_quando_overture_nao_acha`
  - `test_ponto_fora_de_predio_nao_grava_e_registra_nao_encontrado`
  - `test_nominatim_sem_numero_pula_as_fontes`
  - `test_sem_ponto_nao_consulta_nada`
  - `test_nao_sobrescreve_poligono_existente`
  - `test_fonte_desligada_registra_desligado`
  - `test_ia_reaproveita_poligono_ja_gerado`
  - `test_ia_sem_poligono_pede_inferencia`
  - `test_duas_resolucoes_simultaneas_gravam_um_poligono`: o lock já está tomado, a segunda chamada espera, e afirma-se um único update.
  - `test_procedencia_tem_fonte_id_release_area_metodo_e_oficial_false`

  Testes Postgis (`PoligonoComplementarMaiorPorcaoPostgisTest`):
  - `test_poligono_overture_entra_na_maior_porcao`: prédio cruzando duas zonas 70/30. Afirmações: `zona_codigo` da zona de 70% e `zona_fonte === ZonaFonte::EnderecamentoMaiorPorcao`.
  - `test_multipolygon_entra_na_maior_porcao`.
- [ ] **Passo 2:** rodar. Esperado: FAIL.
- [ ] **Passo 3:** implementar.
- [ ] **Passo 4:** rodar os dois arquivos e `tests/Feature/Geo`. Esperado: PASS.
- [ ] **Passo 5:** commit `feat: polígono do lote pelas edificações da Overture e da Microsoft, com procedência`.

### Tarefa 8: IA — ortofoto, inferência, aceite e job

**Arquivos:**
- Criar:
  - `app/Services/Geo/Edificacoes/OrtofotoClient.php`
  - `InferenciaEdificacaoClient.php`
  - `AceiteMascaraIa.php`
  - `app/Jobs/GerarPoligonoIaJob.php`
- Modificar: `config/viabiliza.php` (`edificacoes.ia.*`, `edificacoes.ortofoto.*`)
- Teste:
  - `tests/Unit/Geo/AceiteMascaraIaTest.php`
  - `tests/Feature/Geo/GerarPoligonoIaJobTest.php`

**Interfaces:**
- Produz:
  ```php
  final readonly class RecorteOrtofoto { public function __construct(public string $caminhoPng, public float $oeste, public float $sul, public float $leste, public float $norte, public int $largura, public int $altura, public string $servico, public string $ano) {} }
  class OrtofotoClient { public function recortar(float $lat, float $lng, float $ladoMetros = 60.0): ?RecorteOrtofoto; } // null = indisponível; tenta 2024 (exportImage, bboxSR 4326, 800x800 png) e depois WMS 2016/2017; cache em storage/app/ortofoto/{sha1}.png
  final readonly class MascaraIa { public function __construct(public string $status, public array $anelPixel, public ?float $score, public string $modelo) {} }
  class InferenciaEdificacaoClient { public function segmentar(RecorteOrtofoto $recorte, int $x, int $y): MascaraIa; } // POST {url}/segmentar multipart (imagem, x, y, largura, altura); lança InferenciaIndisponivelException em erro HTTP/timeout
  class AceiteMascaraIa { /** @return array{aceita: bool, motivo: string|null, geojson: array|null, area_m2: float|null} */ public function avaliar(MascaraIa $mascara, RecorteOrtofoto $recorte, float $lat, float $lng): array; }
  class GerarPoligonoIaJob implements ShouldQueue, ShouldBeUnique { public function __construct(public int $viabilityRequestId) {} } // uniqueId = "ia:" . sha1(lat,lng arredondados a 6 casas)
  ```
- **Georreferenciamento:** linear pelo bbox do recorte.
- **`AceiteMascaraIa`:** usa os limites de `viabiliza.edificacoes.ia.aceite` (`area_min_m2` 20, `area_max_m2` 5000, `compacidade_min` 0.2, `margem_borda_px` 2) e calcula a área em PHP (projeção local equiretangular).
- **O job:**
  1. Recorte nulo → `registrar(Ia, NaoResolvido, motivo 'imagem_indisponivel')`.
  2. Inferência com exceção → `Indisponivel`, motivo `servico_fora`.
  3. `sem_objeto` ou não aceita → `NaoResolvido` com o motivo do aceite.
  4. Aceita → insere `BuildingFootprint` (fonte `ia`, `release = "{modelo}+{servico}-{ano}"`, `properties.score_modelo`) e chama `complementar->gravar`.
  5. No fim, sempre despacha `DecidirFluxoExpressoJob` de novo para o processo.
- Nesta tarefa ninguém despacha o job ainda: o encaixe é a Tarefa 9.

- [ ] **Passo 1:** testes que falham.

  `AceiteMascaraIaTest`:
  - `test_aceita_quadrado_de_100m2_que_contem_o_ponto`
  - `test_recusa_sem_o_ponto`
  - `test_recusa_area_19m2`
  - `test_recusa_area_5001m2`
  - `test_recusa_tira_fina_compacidade_0_1`
  - `test_recusa_encostada_na_borda`

  `GerarPoligonoIaJobTest`, com `Http::fake` no ImageServer, no WMS e no serviço:
  - `test_aceita_e_grava_fonte_ia_sem_confianca`: `confidence` nulo e `properties.score_modelo` preenchido.
  - `test_imagem_2024_indisponivel_usa_2016`
  - `test_sem_imagem_registra_nao_resolvido`
  - `test_servico_fora_registra_indisponivel`
  - `test_mascara_recusada_nao_grava_nada`
  - `test_recorte_reaproveitado_do_cache`
  - `test_nao_despacha_segunda_inferencia_para_o_mesmo_ponto`
- [ ] **Passo 2:** rodar. Esperado: FAIL.
- [ ] **Passo 3:** implementar.
- [ ] **Passo 4:** rodar os dois arquivos e `tests/Feature/Geo`. Esperado: PASS.
- [ ] **Passo 5:** commit `feat: polígono por IA sobre a ortofoto da Prefeitura, com aceite automático`.

### Tarefa 9: Encaixe na ordem (job, REGIN, reprocesso)

**Arquivos:**
- Criar: `app/Services/Geo/ResolucaoPoligonoPorPonto.php`
- Modificar:
  - `app/Jobs/DecidirFluxoExpressoJob.php:95-98`
  - `app/Services/Regin/ReginProcessoProtocolador.php:291-293`
  - `app/Services/Motor/ReprocessarEtapasService.php:68`
- Teste:
  - `tests/Feature/Geo/ResolucaoPoligonoPorPontoTest.php`
  - `tests/Feature/Geo/PoligonoPorPontoCaminhosTest.php` (existe, ajustar)

**Interfaces:**
- Consome: `GerarPoligonoIaJob` (Tarefa 8), `PoligonoComplementarService::resolver`, `PoligonoEnderecamentoService::aplicarPorPonto/precisaConsultarPorPonto`.
- Produz:
  ```php
  class ResolucaoPoligonoPorPonto {
      /** Passos 3–6. Devolve true quando a decisão deve esperar a IA. */
      public function resolver(ViabilityRequest $request, bool $podeEsperarIa): bool;
      public function precisaResolver(ViabilityRequest $request): bool; // sem polígono e com ponto, e (algum toggle de edificação ligado ou endereçamento ligado)
  }
  ```

Comportamento:
- Chama `complementar->resolver`. Se vier `precisaIa`:
  - com `$podeEsperarIa`: despacha `GerarPoligonoIaJob` (Tarefa 8) e retorna true;
  - sem `$podeEsperarIa`: retorna true sem despachar.
- Se não, chama `aplicarPorPonto`.

Encaixes:
- **Job:** troca a linha 98 por `if ($resolucao->resolver($request, podeEsperarIa: $this->attempts() < $this->tries)) { $this->release(...); return; }`. O orçamento da IA é próprio, `viabiliza.edificacoes.ia.espera_tentativas` (padrão 3), contado a partir de `lot_polygon_resolutions` com status `Pendente` deste processo. Esgotado, chama `resolver(..., podeEsperarIa: false)` e segue para o passo 6.
- **REGIN:** depois de `aplicar($solicitacao)` sem `consultarPorPonto`, se `precisaResolver`, despacha `DecidirFluxoExpressoJob::dispatch($id)` em vez de `decide()`. Caso contrário, mantém o `decide()` síncrono.
- **Reprocesso:** trocar `precisaConsultarPorPonto` por `$resolucao->precisaResolver`.

- [ ] **Passo 1:** testes que falham:
  - `test_ordem_overture_microsoft_antes_do_endereçamento_pelo_ponto`: fakes registram a ordem de chamada.
  - `test_endereçamento_pelo_ponto_so_quando_complementares_falham`
  - `test_complementar_achou_nao_consulta_endereçamento_pelo_ponto`
  - `test_job_devolve_a_fila_enquanto_espera_ia`
  - `test_regin_sem_poligono_despacha_job_em_vez_de_decidir` (`Queue::fake`)
  - `test_reprocesso_agenda_decisao_com_edificacoes_ligadas_e_endereçamento_desligado`
  - `test_prazo_esgotado_segue_para_o_endereçamento_pelo_ponto`: com `espera_tentativas` = 1 e o serviço de inferência sem resposta, a segunda passada do job chama o endereçamento pelo ponto e decide.

  Ajustar em `PoligonoPorPontoCaminhosTest` as expectativas que assumiam o ponto em 3º.
- [ ] **Passo 2:** rodar. Esperado: FAIL.
- [ ] **Passo 3:** implementar.
- [ ] **Passo 4:** rodar `tests/Feature/Geo`, `tests/Feature/Expresso` e os testes do REGIN (`--filter=Regin`). Esperado: PASS.
- [ ] **Passo 5:** commit `feat: edificações complementares antes do endereçamento pelo ponto no job, no REGIN e no reprocesso`.

### Tarefa 10: Serviço de inferência (Python)

**Arquivos:**
- Criar:
  - `docker/footprint-ia/app.py`
  - `requirements.txt`
  - `Dockerfile`
  - `README.md`
  - `tests/test_contrato.py`

**Interfaces:**
- **Consome:** o contrato da Tarefa 8.
- **Rotas:**
  - `GET /saude` → `{"status":"ok","modelo":...}`;
  - `POST /segmentar` → `MascaraIa` em JSON.
- **Implementação:**
  - SAM 2 (`facebook/sam2.1-hiera-small`, Apache 2.0) via `samgeo`, com prompt de um ponto positivo;
  - maior contorno da máscara via `cv2.findContours` + `approxPolyDP` (epsilon 1,5 px);
  - `score` = IoU previsto pelo SAM;
  - variável de ambiente `FOOTPRINT_IA_DEVICE` (`cpu` | `cuda`), padrão `cpu`;
  - máscara vazia → `sem_objeto`.

- [ ] **Passo 1:** `test_contrato.py` com o modelo trocado por um stub via variável `FOOTPRINT_IA_STUB=1` (devolve um quadrado em volta do ponto):
  - `test_saude`
  - `test_segmentar_devolve_anel_em_pixel`
  - `test_requisicao_sem_imagem_da_422`
- [ ] **Passo 2:** `cd docker/footprint-ia && FOOTPRINT_IA_STUB=1 python -m pytest -q`. Esperado: FAIL.
- [ ] **Passo 3:** implementar o app e o Dockerfile (`python:3.12-slim`, pesos baixados no build). No README: build, run, CPU/GPU e licenças (samgeo MIT, SAM 2 Apache 2.0).
- [ ] **Passo 4:** rodar o mesmo pytest. Esperado: PASS. Se não houver Python 3.12 local, rodar em `docker run` e registrar isso no resumo final.
- [ ] **Passo 5:** commit `feat: serviço de inferência de edificações com SAM 2`.

### Tarefa 11: Configuração, painel, registro de chamadas e agendamento

**Arquivos:**
- Modificar:
  - `database/seeders/ParameterSeeder.php`
  - `app/Support/ApiIntegracaoCatalog.php` (nova aba `edificacoes` depois de `arcgisSemap`)
  - `app/Services/Integracao/IntegrationCallRecorder.php:131`
  - `routes/console.php`
  - `tests/Feature/Seeders/ParameterSeederTest.php:18`
  - `tests/Feature/Seeders/DatabaseSeederTest.php:102,399`
- Teste: os dois seeders e `tests/Feature/Integracao/*Catalog*` (se existir; senão `tests/Feature/Gestao/ApiIntegracao*`)

**Parâmetros** (todos `group`: `features` ou `integracoes`; nenhuma descrição menciona IA de terceiros pelo nome comercial):
- **Interruptores (boolean, default `0`):**
  - `features.edificacoes_overture`
  - `features.edificacoes_microsoft`
  - `features.edificacoes_ia`
  - `features.edificacoes_importacao_agendada`
- **Integrações:**
  - `integrations.edificacoes.ia_url` (url, nullable)
  - `integrations.edificacoes.ortofoto_2024_url` (default `https://geo.salvador.ba.gov.br/imageserver/rest/services/Ortofotos/2024/ImageServer`)
  - `integrations.edificacoes.ortofoto_2016_url` (default `https://mapeamento.salvador.ba.gov.br/wms`)
  - `integrations.edificacoes.ortofoto_token` (sensível, nullable)
- **Total:** 8 parâmetros novos, contagem 115 → 123.

**Registro de chamadas:** em `IntegrationCallRecorder::integracao`, antes de `'ia'`:
- `'overture' => ['overturemaps']`
- `'edificacoes_microsoft' => ['minedbuildings', 'bfppub']`
- `'ortofoto' => ['/imageserver/', 'mapeamento.salvador']`
- `'edificacoes_ia' => ['/segmentar', '/saude']`

**Agendamento**, só com `Settings::enabled('edificacoes_importacao_agendada')`, ambos `withoutOverlapping()->onOneServer()`:
- Overture mensal (dia 25, 03:00), rodando o script e depois o comando;
- Microsoft trimestral.

- [ ] **Passo 1:** testes que falham:
  - contagem 123 nos dois seeders;
  - `test_aba_edificacoes_tem_interruptores_urls_e_token_sensivel`;
  - `test_integracao_por_url` para as 4 regras novas, com um caso por URL.
- [ ] **Passo 2:** rodar. Esperado: FAIL.
- [ ] **Passo 3:** implementar.
- [ ] **Passo 4:** rodar os arquivos e `php artisan schedule:list | grep edificacoes` (com o toggle ligado via config). Esperado: PASS, e as 2 entradas aparecem.
- [ ] **Passo 5:** commit `feat: parâmetros, painel e agendamento das edificações complementares`.

### Tarefa 12: Interface — legenda pela fonte e procedência

**Arquivos:**
- Modificar:
  - `app/Http/Controllers/Gestao/ProcessoController.php:248-256`
  - `app/Http/Controllers/Gestao/AnalysisRecordController.php:520-523` (+ docblock)
  - `resources/js/pages/gestao/processos/show.tsx:712-716` (só os hunks desta tarefa)
  - `resources/js/pages/gestao/ficha-analise/show.tsx:1691-1698`
  - os tipos TS da página
- Teste: `tests/Feature/Gestao/ProcessoShowGeoTest.php` e `tests/Feature/Analise/FichaLocalizacaoFonteTest.php` (criar)

**Interfaces:**
- Produz a prop `poligono_fonte` em `geo` e em `localizacao`:
  `{ value: string, legenda: string, atribuicao: string|null, oficial: bool, procedencia: { source_id: string|null, release: string|null, obtido_em: string (ISO), area_m2: float|null } | null } | null`
- A procedência vem da última `LotPolygonResolution` `Encontrado` do processo.

- [ ] **Passo 1:** testes que falham, com `assertInertia`:
  - `test_processo_com_poligono_overture_envia_legenda_e_atribuicao`: legenda exata e atribuição "© OpenStreetMap contributors, Overture Maps Foundation".
  - `test_processo_com_lote_sedur_mantem_legenda_atual`
  - `test_semap_tem_legenda_propria`
  - `test_ficha_envia_procedencia_sem_a_palavra_estimado`: o JSON não contém "estimad".
- [ ] **Passo 2:** rodar. Esperado: FAIL.
- [ ] **Passo 3:** implementar.
  - No TSX, trocar o texto fixo por `{fonte?.legenda}`.
  - Mostrar `atribuicao` na linha do rodapé do mapa, junto de "Mapa © OpenStreetMap (ODbL).".
  - Na ficha, abaixo da legenda, uma linha "Fonte: {legenda sem o prefixo} · id {source_id} · {obtido_em dd/mm/aaaa} · {area_m2} m²", só quando `oficial` for false.
- [ ] **Passo 4:** rodar os testes, `npx tsc --noEmit` e `npm run build`. Esperado: PASS e build sem erro.
- [ ] **Passo 5:** commit `feat: legenda do contorno pela fonte do polígono e procedência na ficha`.

### Tarefa 13: Métricas e documentação de operação

**Arquivos:**
- Criar:
  - `app/Console/Commands/MetricasEdificacoesCommand.php`
  - `docs/integracoes/edificacoes-complementares.md`
- Teste: `tests/Feature/Geo/MetricasEdificacoesCommandTest.php`

**Interfaces:**
- Comando: `geo:edificacoes:metricas {--desde= : data ISO, padrão 30 dias} {--json}`.
- Calcula:
  - cobertura por fonte (% de processos do período com polígono por `address_polygon_source` e % sem polígono);
  - prédios ativos e duplicados por fonte, com a data da última importação concluída;
  - p50 e p95 de `latency_ms` por fonte;
  - taxa de falha por fonte e motivo (`Indisponivel`/`NaoResolvido`);
  - IA: recortes, inferências e tempo médio.
- Precisão: imprime "sem medida automática; ver amostragem mensal na documentação".

- [ ] **Passo 1:** testes que falham:
  - `test_cobertura_por_fonte`: com factories, 2 Overture, 1 SEDUR, 1 sem polígono. Afirmação no `--json`: `overture` 50.0, `sem_poligono` 25.0.
  - `test_latencia_p95`
  - `test_falhas_por_motivo`
- [ ] **Passo 2:** rodar. Esperado: FAIL.
- [ ] **Passo 3:** implementar o comando e escrever a documentação, com estas seções:
  - instalação (PostGIS; `scripts/overture-salvador.sh` com `pipx install overturemaps`; contêiner `docker/footprint-ia`);
  - ingestão inicial na ordem: limite → Overture → Microsoft;
  - atualização agendada;
  - licenças e atribuições: ODbL da Overture; a divergência CDLA 2.0 × ODbL da Microsoft; os termos da ortofoto 2016/2017 e a confirmação pendente da 2024 com cartografia@salvador.ba.gov.br;
  - operação (métricas, amostragem mensal de precisão);
  - o que depende de infraestrutura e credenciais.
- [ ] **Passo 4:** rodar o teste. Esperado: PASS.
- [ ] **Passo 5:** commit `feat: métricas das edificações complementares e guia de operação`.

### Tarefa 14: Verificação final

- [ ] `php artisan test --compact tests/Feature/Geo tests/Unit/Geo tests/Feature/Expresso tests/Feature/Seeders`. Esperado: tudo verde e o grupo postgis sem skip.
- [ ] `php artisan test --compact --filter=Regin`. Esperado: verde.
- [ ] `vendor/bin/pint --dirty --format agent`, `npx tsc --noEmit` e `npm run build`. Esperado: sem erro.
- [ ] Perguntar ao usuário se roda a suíte inteira (`php artisan test --compact`).

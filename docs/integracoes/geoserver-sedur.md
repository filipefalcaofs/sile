# GeoServer SEDUR — inventário do que está publicado

Inventário medido em **16/09/2026** contra o serviço ao vivo.
`updateSequence` do GetCapabilities: **15525**.

Fonte da verdade: Oracle Spatial da SEDUR, exposto por GeoServer via padrões OGC.
O Viabiliza **não** conecta no Oracle. Consome HTTP (WFS/WMS) ou, para ponto → território, o `ws-sigs`.

---

## 1. Serviço

| Item | Valor |
|---|---|
| URL | `https://geoserver.sedur.salvador.ba.gov.br/geoserver` |
| Organização | SEDUR — Secretaria de Desenvolvimento e Urbanismo |
| Contato no capabilities | André Fonseca, Gestor NTI — 71 3202-9565 — andrefonseca@salvador.ba.gov.br |
| Taxa / restrição declarada | NONE / NONE |
| FeatureTypes WFS | 656 |
| Camadas WMS nomeadas (queryable) | 652 |
| Workspaces WFS | 247 |
| CRS nativo | EPSG:31984 (SIRGAS 2000 / UTM 24S) em 654 de 656 camadas |
| Exceções de CRS | 1× EPSG:4326, 1× EPSG:404000 |
| UI administrativa | `https://geoserver.sedur.salvador.ba.gov.br/geoserver/web/` |
| REST (`/rest/workspaces.json`) | 401 Unauthorized |
| Namespace interno das camadas | `http://cassange:8080/geoserver/{workspace}` (hostname interno `cassange`) |

### Endpoints OGC

| Protocolo | Versões | Endpoint | Situação em 16/09/2026 |
|---|---|---|---|
| WFS | 1.0.0, 1.1.0, 2.0.0 | `/wfs` ou `/ows?SERVICE=WFS` | Operacional. Preferir **1.1.0** para GetFeature |
| WMS | 1.3.0 (GetCapabilities medido) | `/wms` ou `/ows?SERVICE=WMS` | Operacional. 652 camadas queryable |
| WCS | 2.0.1 | `/wcs` | Capabilities responde; **Contents vazio** — nenhum raster publicado |
| WMTS / GWC | — | `/gwc/service/wmts` | **400** — GeoWebCache dessincronizado |

Catálogo público (subconjunto): [servicos.sedur.salvador.ba.gov.br/geoservicos](https://servicos.sedur.salvador.ba.gov.br/geoservicos).
Em 16/09/2026 o portal anuncia só **três** conjuntos: Bairros oficiais, Logradouros e Revitalizar (APCP Centro Antigo).
O GeoServer completo tem as 656 camadas — o portal não é o inventário.

---

## 2. O que cada protocolo entrega

### WFS — vetor (substitui PostGIS na origem)

Operações anunciadas: GetCapabilities, DescribeFeatureType, GetFeature, GetPropertyValue, List/Describe/Create/Drop StoredQuery, LockFeature, GetFeatureWithLock, **Transaction**.

O capabilities declara `ImplementsTransactionalWFS = TRUE`. O Viabiliza **não deve** usar Transaction — só leitura.

Formatos de GetFeature: `application/json` / `json`, GML 2/3.1/3.2, KML, CSV, SHAPE-ZIP.

Filtros espaciais: Disjoint, Equals, DWithin, Beyond, Intersects, Touches, Crosses, Within, Contains, Overlaps, BBOX.

Também anuncia joins espaciais/temporais, paging e stored queries.

### WMS — imagem (overlay no Leaflet)

GetMap em PNG, JPEG, GIF, TIFF, GeoTIFF, SVG, PDF, KML/KMZ, OpenLayers HTML, UTFGrid.
GetFeatureInfo em texto, GML e HTML.
Todas as 652 camadas nomeadas estão `queryable=1`.

Exemplo de tile (WGS84, WMS 1.3 usa lat,lon na bbox):

```
https://geoserver.sedur.salvador.ba.gov.br/geoserver/wms
  ?SERVICE=WMS&VERSION=1.3.0&REQUEST=GetMap
  &LAYERS=louos_zpr1:VM_L_Z_USO_ZPR_1
  &CRS=EPSG:4326&BBOX=-13.01,-38.53,-12.96,-38.48
  &WIDTH=800&HEIGHT=600&FORMAT=image/png&TRANSPARENT=true
```

---

## 3. Armadilhas medidas

1. **WFS 2.0 GetFeature sem `sortBy` falha** com *Cannot do natural order without a primary key*. Usar WFS **1.1.0** + `maxFeatures` + `sortBy` no atributo estável (ex.: `NOME_BAIRRO`).
2. **WAF da PMS bloqueia `--` na URL** (hífen duplo aparece em feature-id do GeoServer). O CLE-IA contorna com HEX. Preferir filtro por atributo, não por `featureid` cru.
3. **`DWithin` em EPSG:4326 com raio em metros pode voltar vazio.** Distância fina: filtrar em EPSG:31984 ou usar o `ws-sigs`.
4. **A mesma feição é publicada duas ou três vezes** — workspace temático (`louos_zpr1:…`), workspace curto (`l_zpr:…`) e workspace `sedur:L_Z_USO_ZPR_1`. São aliases da mesma view Oracle.
5. **REST admin está fechado** (401). Não há listagem JSON oficial; o inventário sai do GetCapabilities.
6. **WMTS quebrado**; WMS GetMap é o caminho de tile.
7. Acesso externo já foi barrado pelo firewall “Salvador Digital” (jun/2026). Em set/2026 o GetCapabilities e GetFeature **responderam da rede de desenvolvimento**. Em produção do Viabiliza, confirmar allowlist do IP com o NTI.

---

## 4. Relação com o `ws-sigs`

O `POST /fiscalizacao/dados-localizacao` encapsula o ponto-em-polígono nestas views e devolve `camadas_incidentes` + JWT para `GET /fiscalizacao/geometrias-incidentes`.

| Uso | Caminho |
|---|---|
| Identificar zona/bairro/via/restrição de um ponto | `ws-sigs` (já testado em HML) |
| Overlay no mapa Leaflet | WMS deste GeoServer |
| Importar camada versionada para `geo_layers` (HU-107) | WFS 1.1 → GeoJSON (`srsName=EPSG:4326`) |
| Consulta espacial fina (polígono × logradouro) | WFS `Intersects` / `Contains` |
| Oracle / `pdo_oci` | Proibido no Viabiliza |

---

## 5. Camadas que o Viabiliza precisa — contrato de atributos

Schemas obtidos por `DescribeFeatureType` (WFS 1.1) em 16/09/2026.
Geometria: campo `GEOMETRY` (ou `GEOM` no CLE), tipo GML.

### 5.1 Bairro oficial — HU-034

`bairro_oficial:VM_BAIRRO_OFICIAL`

| Atributo | Tipo |
|---|---|
| ID | decimal |
| NOME_BAIRRO | string |
| AREA | decimal |
| UNIDADE_AREA | string |
| INSTITUIDO_POR | string |
| ALTERADO_POR | string |

Alias: `lei_bairros:VM_LEI_BAIRRO`, `sedur:LEI_BAIRRO`.
No catálogo público: [WFS bairro_oficial](https://geoserver.sedur.salvador.ba.gov.br/geoserver/bairro_oficial/ows?SERVICE=WFS&REQUEST=GetCapabilities).

### 5.2 Zona urbanística LOUOS — HU-031 / Quadro 10

Uma FeatureType por zona. Atributos típicos: `SUBZONA`, `LOCAL`, `CA_MIN`, `CA_BAS`, `CA_MAX`, `INSTITUIDO_POR`, `ALTERADO_POR`, `GEOMETRY`. ZPAM/ZDE acrescentam `IDENTIFICACAO`. ZPR-1/ZPR-3 têm `OBS`.

| Zona | typeName canônico | Alias em `sedur` |
|---|---|---|
| ZPR-1 | `louos_zpr1:VM_L_Z_USO_ZPR_1` | `sedur:L_Z_USO_ZPR_1` |
| ZPR-2 | `louos_zpr2:VM_L_Z_USO_ZPR_2` | `sedur:L_Z_USO_ZPR_2` |
| ZPR-3 | `louos_zpr3:VM_L_Z_USO_ZPR_3` | `sedur:L_Z_USO_ZPR_3` |
| ZPAM | `louos_zpam:VM_L_Z_USO_ZPAM` | `sedur:L_Z_USO_ZPAM` |
| ZDE-1 | `louos_zde1:VM_L_Z_USO_ZDE_1` | `sedur:L_Z_USO_ZDE_1` |
| ZDE-2 | `louos_zde2:VM_L_Z_USO_ZDE_2` | `sedur:L_Z_USO_ZDE_2` |
| ZUE | `louos_zue:VM_L_Z_USO_ZUE` | `sedur:L_Z_USO_ZUE` |
| ZUSI | `louos_zusi:VM_L_Z_USO_ZUSI` | `sedur:L_Z_USO_ZUSI` |
| ZEM | `louos_zem:VM_L_Z_USO_ZEM` | `sedur:L_Z_USO_ZEM` |
| ZIT | `louos_zit:VM_L_Z_USO_ZIT` | `sedur:L_Z_USO_ZIT` |
| ZEIS | `louos_zeis:VM_L_Z_USO_ZEIS` | `sedur:L_Z_USO_ZEIS` |
| ZCLME | `louos_zona_uso_zclme:…` | `sedur:L_Z_USO_ZCLME` |
| ZCLMU | `louos_zona_uso_zclmu:…` | `sedur:L_Z_USO_ZCLMU` |
| ZCME / ZCMU | `louos_zcme_*` / `louos_zcmu_*` | `sedur:L_Z_USO_ZCME_*` / `ZCMU_*` |

ZEIS (HU-035 / exceção de risco): `NOME_ZEIS`, `TIPO_ZEIS`, `OBSERVACAO`, `AREA`, `UNIDADE_AREA`.

O `ws-sigs` já devolve essas camadas em `camadas_incidentes` (ex.: `louos_zpr3:VM_L_Z_USO_ZPR_3` em Nazaré). Falta o dicionário SEDUR `IDENTIFICADOR_TECNICO` → código do Quadro 10.

### 5.3 Classificação da via — HU-032

**Atributo LOUOS existe.** Não está mais “sem fonte”.

`louos_classificacao_viaria:VM_L_CLASSIF_VIARIA` (alias `l_classificacao_viaria:…`, `sedur:L_CLASSIF_VIARIA`)

| Atributo | Tipo |
|---|---|
| CODLOG | decimal |
| LOGRADOURO | string |
| **CLASSIFICACAO_VIARIA** | string |
| OBS | string |
| INSTITUIDO_POR / ALTERADO_POR | string |

Eixo viário cadastral (geometria + hierarquia, não necessariamente Mapa 04):

`logradouro:VM_LOGRADOURO` e `logradouros:VM_LOGRADOURO` — `CODLOG`, `NOME_LOGRADOURO`, `HIERARQUIA`.

Pendência restante: confirmar com a SEDUR se `CLASSIFICACAO_VIARIA` é o Mapa 04 da LOUOS ou se `HIERARQUIA` do logradouro é que entra nos Quadros 11/11A.

### 5.4 Lote e matrícula — HU-033 / HU-037

`parcelamento_lotes:VM_PARCELAMENTO_LOTES_EDIF` (alias `sedur:PARCELAMENTO_LOTES_EDIF`)

| Atributo | Tipo |
|---|---|
| COD_PARCEL | string |
| N_LOTE | string |
| CAUCIONADO | string |
| AREA_DECLARADA / AREA_CALCULADA | decimal |
| OBS | string |

Não há inscrição imobiliária neste schema. A inscrição vem do `ws-sigs` (`inscricoes_imobiliaria`) ou de `/imobiliario/inscricao-imobiliaria`.

`matriculas_imoveis:VM_MATRICULAS_IMOVEIS` — `NUMERO_MATRICULA`, `CARTORIO`, `NUMERO_PROCESSO`, áreas. É matrícula de registro, não IPTU.

`enderecamento:VM_ENDERECAMENTO` — `CODLOG`, `NUMERACAO_METRICA`, `COMPLEMENTO`, `SUB_UNIDADE`, `ALVARA`, `PROCESSO`, `ANO_PROCESSO`, `ORIGEM`.

### 5.5 Restrições — HU-035

| Camada | typeName | Atributos vistos |
|---|---|---|
| Bens tombados | `bens_tombados:VM_BENS_TOMBADOS` | NOME, ENDERECO, GESTAO, PROTECAO, DATA_TOMBAMENTO, PROPRIEDADE, USO_ORIGEM, USO_ATUAL, ESTADO_CONSERVACAO, FONTE |
| Raio de tombamento | `bens_tombados:VM_BENS_TOMBADOS_RAIO` | (workspace próprio + `sedur:BENS_TOMBADOS_RAIO`) |
| ZEIS | `louos_zeis:VM_L_Z_USO_ZEIS` | NOME_ZEIS, TIPO_ZEIS |
| SAVAM / APA / APRN / APCP / UCM | `louos_savam_*` e `sedur:L_SAVAM_*` | — |
| Mata Atlântica | `louos_mata_atlantica:…` / `sedur:L_SAVAM_CLASSIF_MATA_ATLANT` | — |
| Áreas da União, militar, pública | `areas_da_uniao`, `sedur:AREA_*` | — |

O CLE-IA consulta tombados no Oracle direto (`SDO_WITHIN_DISTANCE`). No Viabiliza usar esta FeatureType ou o `ws-sigs`.

### 5.6 Área de eventos (CLE, fora do TVL)

`AREA_EVENTOS_CLE:VM_AREA_EVENTOS_CLE` — `ID`, `NOME`, `CAPACIDADE`, `GEOM`. Usada pelo CLE-IA, não pelo motor de viabilidade.

---

## 6. Exemplos de consulta (WFS 1.1)

Bairro que contém um ponto (reprojetar para 4326):

```
GET /geoserver/wfs
  ?service=WFS&version=1.1.0&request=GetFeature
  &typeName=bairro_oficial:VM_BAIRRO_OFICIAL
  &outputFormat=application/json
  &srsName=EPSG:4326
  &maxFeatures=5
  &sortBy=NOME_BAIRRO
```

Zona ZPR-1 no recorte de Nazaré:

```
GET /geoserver/wfs
  ?service=WFS&version=1.1.0&request=GetFeature
  &typeName=louos_zpr1:VM_L_Z_USO_ZPR_1
  &outputFormat=application/json
  &srsName=EPSG:4326
  &maxFeatures=1
  &sortBy=SUBZONA
  &bbox=-38.51,-12.98,-38.49,-12.96,EPSG:4326
```

---

## 7. Catálogo público (geoservicos)

Página: https://servicos.sedur.salvador.ba.gov.br/geoservicos

| Nome no portal | WFS | Extra |
|---|---|---|
| Bairros oficiais | `…/geoserver/bairro_oficial/ows?SERVICE=WFS&REQUEST=GetCapabilities` | PDF + metadado 2020/09 + mapa `mapeamento.salvador.ba.gov.br` |
| Logradouros | `…/geoserver/logradouros/ows?` | Metadado + eixos no mapeamento |
| Revitalizar | `…/geoserver/apcp_centro_antigo/ows?SERVICE=WFS&REQUEST=GetCapabilities` | Poligonal APCP Centro Antigo |

Zoneamento, lote, ZEIS e classificação viária **não** estão neste portal. Estão no GeoServer.

---

## 8. Workspaces (247) — contagem

O workspace `sedur` (232 camadas) replica LOUOS, PDDU, carnaval e cadastro.
Os demais workspaces são o recorte temático da mesma view (`VM_*` = view materializada Oracle).

| Workspace | Camadas |
|---|---:|
| `sedur` | 232 |
| `l_gabarito` | 18 |
| `transporte_coletivo` | 16 |
| `sistema_viario` | 12 |
| `l_savam` | 11 |
| `savam` | 11 |
| `centralidade` | 10 |
| `infraero` | 10 |
| `transporte_carga` | 10 |
| `hidrografia` | 9 |
| `l_centralidades` | 9 |
| `l_zee` | 9 |
| `area_especial` | 8 |
| `camadas_publicidade` | 8 |
| `parcelamento` | 8 |
| `plano_funcional` | 8 |
| `l_zona_uso` | 7 |
| `macroareas` | 6 |
| `apcp_zoneamento` | 4 |
| `decretos` | 4 |
| `delimitacoes` | 4 |
| `parcelamento_desenv_sirgas` | 4 |
| `aerodromo_portaria_812_ica_2019` | 3 |
| `aprn_zoneamento` | 3 |
| `l_zpr` | 3 |
| `areas_chesf` | 2 |
| `bens_tombados` | 2 |
| `borda_maritima` | 2 |
| `l_abm_2016` | 2 |
| `macrozona` | 2 |
| `parque_de_pituacu` | 2 |
| `terminal_rodoviario` | 2 |
| `AREA_EVENTOS_CLE` | 1 |
| `INCENTIVOS_FISCAIS` | 1 |
| `LETREIRO` | 1 |
| `MATRICULAS_IMOVEIS` | 1 |
| `aerodromo_ilha_dos_frades` | 1 |
| `analise_aop` | 1 |
| `analise_gcat` | 1 |
| `apcp_centro_antigo` | 1 |
| `apcp_zoneamento_guadalupe` | 1 |
| `apcp_zoneamento_guadalupe_sinal` | 1 |
| `apcp_zoneamento_loreto` | 1 |
| `apcp_zoneamento_loreto_sinal` | 1 |
| `aprn_cidade_jardim` | 1 |
| `aprn_jaguaribe` | 1 |
| `aprn_pituacu` | 1 |
| `area_institucionais_municipais` | 1 |
| `area_militar_aeronautica` | 1 |
| `area_militar_exercito` | 1 |
| `area_sob_analise_cnlu` | 1 |
| `areas_da_uniao` | 1 |
| `areas_publicas` | 1 |
| `aterro_sanitario_limpurb` | 1 |
| `bacias_hidrograficas_drenagem` | 1 |
| `bairro_oficial` | 1 |
| `borda_maritima_poligonal_cnlu` | 1 |
| `borda_maritima_trecho_cnlu` | 1 |
| `cemiterio` | 1 |
| `cemiterios` | 1 |
| `decreto_desapropriacao_estadual` | 1 |
| `decreto_desapropriacao_municipal` | 1 |
| `decreto_encampacao` | 1 |
| `decreto_municipal_revogado` | 1 |
| `desafetacao_municipal` | 1 |
| `documento_referencia` | 1 |
| `enderecamento` | 1 |
| `gasoduto_bahia_gas` | 1 |
| `heliponto` | 1 |
| `hidrografia_buffer_lagoas_30m` | 1 |
| `hidrografia_buffer_lagos_30m` | 1 |
| `hidrografia_dique_do_tororo` | 1 |
| `hidrografia_lagoas` | 1 |
| `hidrografia_lagos` | 1 |
| `hidrografia_porto` | 1 |
| `hidrografia_represas` | 1 |
| `hidrografia_rios` | 1 |
| `hidrografia_rios_buffer_30m` | 1 |
| `intervencao_sistema_viario` | 1 |
| `intervencao_viaria_ponte_ssa_itaparica` | 1 |
| `intervencao_viaria_projeto_brt` | 1 |
| `intervencao_viaria_projeto_metro` | 1 |
| `intervencao_viaria_projeto_vlt` | 1 |
| `l_classificacao_viaria` | 1 |
| `l_zeis` | 1 |
| `lei_bairros` | 1 |
| `licenca_obra` | 1 |
| `licenca_obra_com_inscricao` | 1 |
| `limite_salvador` | 1 |
| `logradouro` | 1 |
| `logradouros` | 1 |
| `louos_classificacao_viaria` | 1 |
| `louos_gabarito_06_metros` | 1 |
| `louos_gabarito_09_metros` | 1 |
| `louos_gabarito_12_metros` | 1 |
| `louos_gabarito_15_metros` | 1 |
| `louos_gabarito_18_metros` | 1 |
| `louos_gabarito_24_metros` | 1 |
| `louos_gabarito_30_metros` | 1 |
| `louos_gabarito_36_metros` | 1 |
| `louos_gabarito_45_metros` | 1 |
| `louos_gabarito_51_metros` | 1 |
| `louos_gabarito_60_metros` | 1 |
| `louos_gabarito_75_metros` | 1 |
| `louos_gabarito_apr` | 1 |
| `louos_gabarito_faixa_praia` | 1 |
| `louos_gabarito_limite_abm` | 1 |
| `louos_gabarito_preservacao_de_encosta` | 1 |
| `louos_gabarito_recorte_area_central` | 1 |
| `louos_gabarito_trechos_abm` | 1 |
| `louos_mata_atlantica` | 1 |
| `louos_savam_apa_estadual` | 1 |
| `louos_savam_apcp` | 1 |
| `louos_savam_aprn` | 1 |
| `louos_savam_limite_abm` | 1 |
| `louos_savam_parque_bairro` | 1 |
| `louos_savam_parque_urbano` | 1 |
| `louos_savam_parque_urbano_proposto` | 1 |
| `louos_savam_trecho_abm` | 1 |
| `louos_savam_uci` | 1 |
| `louos_savam_ucm` | 1 |
| `louos_zcme_aguas_claras` | 1 |
| `louos_zcme_camaragibe` | 1 |
| `louos_zcme_centro_antigo` | 1 |
| `louos_zcme_luis_viana_29_marco` | 1 |
| `louos_zcme_retiro_acesso_norte` | 1 |
| `louos_zcmu_municipal_1` | 1 |
| `louos_zcmu_municipal_2` | 1 |
| `louos_zde1` | 1 |
| `louos_zde2` | 1 |
| `louos_zee_apa_lagoa_e_dunas_abaete` | 1 |
| `louos_zee_ilha_bom_jesus_ilhota` | 1 |
| `louos_zee_ilha_bom_jesus_pier` | 1 |
| `louos_zee_ilha_dos_frades` | 1 |
| `louos_zee_ilha_dos_frades_pier` | 1 |
| `louos_zee_joanes_ipitanga` | 1 |
| `louos_zee_sinalizacao_nautica` | 1 |
| `louos_zeis` | 1 |
| `louos_zem` | 1 |
| `louos_zit` | 1 |
| `louos_zona_uso_zclme` | 1 |
| `louos_zona_uso_zclmu` | 1 |
| `louos_zpam` | 1 |
| `louos_zpr1` | 1 |
| `louos_zpr2` | 1 |
| `louos_zpr3` | 1 |
| `louos_zue` | 1 |
| `louos_zusi` | 1 |
| `matriculas_imoveis` | 1 |
| `operacoes_urbanas` | 1 |
| `parcelamento_areas` | 1 |
| `parcelamento_areas_cancelado` | 1 |
| `parcelamento_lotes` | 1 |
| `parcelamento_lotes_cancelado` | 1 |
| `parcelamento_poligonal` | 1 |
| `parcelamento_poligonal_cancelado` | 1 |
| `parcelamento_quadra` | 1 |
| `parcelamento_quadra_cancelado` | 1 |
| `parque_natural` | 1 |
| `parque_urbano` | 1 |
| `patrimonio_historico` | 1 |
| `patrimonio_historico_raio` | 1 |
| `pddu_cargas_aterro_sanitario` | 1 |
| `pddu_cargas_centros_abastecimento` | 1 |
| `pddu_cargas_corredor_primario_existente` | 1 |
| `pddu_cargas_corredor_primario_proposto` | 1 |
| `pddu_cargas_corredor_secundario_existente` | 1 |
| `pddu_cargas_corredor_secundario_proposto` | 1 |
| `pddu_cargas_pedreiras` | 1 |
| `pddu_cargas_polo_logistico` | 1 |
| `pddu_cargas_terminal_cargas_aereas` | 1 |
| `pddu_cargas_terminal_containers` | 1 |
| `pddu_centralidades_corredores_alta_capacidade` | 1 |
| `pddu_centralidades_corredores_media_capacidade` | 1 |
| `pddu_centralidades_corredores_media_capacidade_vlt` | 1 |
| `pddu_centralidades_metropolitana` | 1 |
| `pddu_centralidades_municipais` | 1 |
| `pddu_centralidades_transporte_aeroporto` | 1 |
| `pddu_centralidades_transporte_estacao_maritima` | 1 |
| `pddu_centralidades_transporte_estacao_metroviaria` | 1 |
| `pddu_centralidades_zusi` | 1 |
| `pddu_classificacao_mata_atlantica` | 1 |
| `pddu_intervencoes_pontuais_transito` | 1 |
| `pddu_macroarea_estruturacao_urbana` | 1 |
| `pddu_macroarea_integracao_metropolitana` | 1 |
| `pddu_macroarea_reestruturacao_borda_bts` | 1 |
| `pddu_macroarea_requalificacao_borda_atlantica` | 1 |
| `pddu_macroarea_urbanizacao_consolidada` | 1 |
| `pddu_macrozona_conservacao_ambiental` | 1 |
| `pddu_macrozona_ocupacao_urbana` | 1 |
| `pddu_operacoes_urbanas` | 1 |
| `pddu_prefeitura_bairro` | 1 |
| `pddu_savam_apa_estadual` | 1 |
| `pddu_savam_apcp` | 1 |
| `pddu_savam_aprn` | 1 |
| `pddu_savam_limite_abm` | 1 |
| `pddu_savam_parque_bairro` | 1 |
| `pddu_savam_parque_urbano` | 1 |
| `pddu_savam_parque_urbano_proposto` | 1 |
| `pddu_savam_trecho_abm` | 1 |
| `pddu_savam_uci` | 1 |
| `pddu_savam_ucm` | 1 |
| `pddu_setores_mim` | 1 |
| `pddu_transporte_atracadouros` | 1 |
| `pddu_transporte_corredor_media_capacidade` | 1 |
| `pddu_transporte_corredor_onibus_implantar` | 1 |
| `pddu_transporte_estacao_metro_1` | 1 |
| `pddu_transporte_estacao_metro_2` | 1 |
| `pddu_transporte_linha_hidroviaria` | 1 |
| `pddu_transporte_metro_existente` | 1 |
| `pddu_transporte_metro_implantacao_1` | 1 |
| `pddu_transporte_metro_implantacao_2` | 1 |
| `pddu_transporte_metropolitano_linha_maritima` | 1 |
| `pddu_transporte_rede_bicicletas` | 1 |
| `pddu_transporte_sistema_auxiliares_ascensores` | 1 |
| `pddu_transporte_terminais_auxiliares` | 1 |
| `pddu_transporte_terminais_conexao_intermodal` | 1 |
| `pddu_viario_via_arterial_construir` | 1 |
| `pddu_viario_via_arterial_duplicar` | 1 |
| `pddu_viario_via_arterial_existente` | 1 |
| `pddu_viario_via_coletora_construir` | 1 |
| `pddu_viario_via_coletora_duplicar` | 1 |
| `pddu_viario_via_coletora_existente` | 1 |
| `pddu_viario_via_expressa_construir` | 1 |
| `pddu_viario_via_expressa_existente` | 1 |
| `pddu_zeis` | 1 |
| `plano_funcional_ac_norte_viana` | 1 |
| `plano_funcional_afranio_peixoto` | 1 |
| `plano_funcional_avenida_anita_garibaldi` | 1 |
| `plano_funcional_avenida_jorge_amado` | 1 |
| `plano_funcional_avenida_juracy_magalhaes` | 1 |
| `plano_funcional_avenida_luis_viana` | 1 |
| `plano_funcional_avenida_orlando_gomes` | 1 |
| `plano_funcional_avenida_pinto_aguiar` | 1 |
| `plano_zoneamento_ruido_aerodromo` | 1 |
| `poli_parcelamento` | 1 |
| `poligonais_reducao_iptu` | 1 |
| `prefeitura_bairro` | 1 |
| `projeto_requalificacao_orla` | 1 |
| `rede_esgoto_cadastro` | 1 |
| `renova_centro` | 1 |
| `servidao_administrativa` | 1 |
| `sub_bacia_mane_dende` | 1 |
| `universidade_ufba` | 1 |
| `universidade_uneb` | 1 |
| `zee_joanes_ipitanga_alt_inema` | 1 |
| `zeis` | 1 |

---

## 9. Inventário completo das 656 FeatureTypes

Nome canônico `workspace:typeName` + título do GetCapabilities. Duplicatas (mesmo `VM_*` em workspaces diferentes) foram mantidas de propósito: é assim que a SEDUR publicou.

### Zoneamento LOUOS (uso do solo) (71)

| typeName | Título |
|---|---|
| `delimitacoes:VM_L_Z_USO_ZUSI` | LOUOS_ZONA_USO_ZUSI |
| `l_centralidades:VM_L_Z_USO_ZCLME` | LOUOS_ZONA_USO_ZCLME |
| `l_centralidades:VM_L_Z_USO_ZCLMU` | LOUOS_ZONA_USO_ZCLMU |
| `l_centralidades:VM_L_Z_USO_ZCME_AGUAS_CLARAS` | LOUOS_ZONA_USO_ZCME_AGUAS_CLARAS |
| `l_centralidades:VM_L_Z_USO_ZCME_CA` | LOUOS_ZONA_USO_ZCME_CA |
| `l_centralidades:VM_L_Z_USO_ZCME_CAMARAGIBE` | LOUOS_ZONA_USO_ZCME_CAMARAGIBE |
| `l_centralidades:VM_L_Z_USO_ZCME_L_VIANA_29_MAR` | LOUOS_ZONA_USO_ZCME_LUIS_VIANA_29_MARCO |
| `l_centralidades:VM_L_Z_USO_ZCME_RET_ACESS_NOR` | LOUOS_ZONA_USO_ZCME_RETIRO_ACESSO_NORTE |
| `l_centralidades:VM_L_Z_USO_ZCMU_1` | LOUOS_ZONA_USO_ZCMU_1 |
| `l_centralidades:VM_L_Z_USO_ZCMU_2` | LOUOS_ZONA_USO_ZCMU_2 |
| `l_zeis:VM_L_Z_USO_ZEIS` | LOUOS_ZEIS_USO_ZEIS |
| `l_zona_uso:VM_L_Z_USO_ZDE_1` | LOUOS_ZONA_USO_ZDE_1 |
| `l_zona_uso:VM_L_Z_USO_ZDE_2` | LOUOS_ZONA_USO_ZDE_2 |
| `l_zona_uso:VM_L_Z_USO_ZEM` | LOUOS_ZONA_DE_USO_ZEM |
| `l_zona_uso:VM_L_Z_USO_ZIT` | LOUOS_ZONA_USO_ZIT |
| `l_zona_uso:VM_L_Z_USO_ZPAM` | LOUOS_ZONA_USO_ZPAM |
| `l_zona_uso:VM_L_Z_USO_ZUE` | LOUOS_ZONA_USO_ZUE |
| `l_zona_uso:VM_L_Z_USO_ZUSI` | LOUOS_ZONA_USO_ZUSI |
| `l_zpr:VM_L_Z_USO_ZPR_1` | LOUOS_ZONA_USO_ZPR_1 |
| `l_zpr:VM_L_Z_USO_ZPR_2` | LOUOS_ZONA_USO_ZPR_2 |
| `l_zpr:VM_L_Z_USO_ZPR_3` | LOUOS_ZONA_USO_ZPR_3 |
| `louos_zcme_aguas_claras:VM_L_Z_USO_ZCME_AGUAS_CLARAS` | LOUOS_ZONA_USO_ZCME_AGUAS_CLARAS |
| `louos_zcme_camaragibe:VM_L_Z_USO_ZCME_CAMARAGIBE` | LOUOS_ZONA_USO_ZCME_CAMARAGIBE |
| `louos_zcme_centro_antigo:VM_L_Z_USO_ZCME_CA` | LOUOS_ZONA_USO_ZCME_CA |
| `louos_zcme_luis_viana_29_marco:VM_L_Z_USO_ZCME_L_VIANA_29_MAR` | LOUOS_ZONA_USO_ZCME_LIUIS_VIANA_29_MARCO |
| `louos_zcme_retiro_acesso_norte:VM_L_Z_USO_ZCME_RET_ACESS_NOR` | LOUOS_ZONA_USO_ZCME_RETIRO_ACESSO_NORTE |
| `louos_zcmu_municipal_1:VM_L_Z_USO_ZCMU_1` | LOUOS_ZONA_USO_ZCMU_1 |
| `louos_zcmu_municipal_2:VM_L_Z_USO_ZCMU_2` | LOUOS_ZONA_USO_ZCMU_2 |
| `louos_zde1:VM_L_Z_USO_ZDE_1` | LOUOS_ZONA_USO_ZDE_1 |
| `louos_zde2:VM_L_Z_USO_ZDE_2` | LOUOS_ZONA_USO_ZDE_2 |
| `louos_zee_apa_lagoa_e_dunas_abaete:VM_L_Z_AMB_ZEE_LAGOA_DUNA_ABAE` | LOUOS_ZEE_LAGOA_DUNAS_ABAETE |
| `louos_zee_ilha_bom_jesus_ilhota:VM_L_Z_AMB_ZEE_BOM_JESUS_ILHOT` | LOUOS_ZEE_BOM_JESUS_ILHOTA |
| `louos_zee_ilha_bom_jesus_pier:VM_L_Z_AMB_ZEE_BOM_JESUS_PIER` | LOUOS_ZEE_BOM_JESUS_PIER |
| `louos_zee_ilha_dos_frades:VM_L_Z_AMB_ZEE_FRADES` | LOUOS_ZEE_ILHA_DOS_FRADES |
| `louos_zee_ilha_dos_frades_pier:VM_L_Z_AMB_ZEE_FRADES_PIER` | LOUOS_ZEE_ILHA_DOS_FRADES_PIER |
| `louos_zee_joanes_ipitanga:VM_L_Z_AMB_ZEE_JOANE_IPITANGA` | LOUOS_ZEE_JOANES_IPITANGA |
| `louos_zee_sinalizacao_nautica:VM_L_Z_AMB_ZEE_FRADES_SINA_NAU` | LOUOS_ZEE_ILHA_FRADES_SINALIZACAO_NAUTICA |
| `louos_zeis:VM_L_Z_USO_ZEIS` | LOUOS_ZONA_USO_ZEIS |
| `louos_zem:VM_L_Z_USO_ZEM` | LOUOS_ZONA_USO_ZEM |
| `louos_zit:VM_L_Z_USO_ZIT` | LOUOS_ZONA_USO_ZIT |
| `louos_zona_uso_zclme:VM_L_Z_USO_ZCLME` | LOUOS_ZONA_USO_ZCLME |
| `louos_zona_uso_zclmu:VM_L_Z_USO_ZCLMU` | LOUOS_ZONA_USO_ZCLMU |
| `louos_zpam:VM_L_Z_USO_ZPAM` | LOUOS_ZONA_USO_ZPAM |
| `louos_zpr1:VM_L_Z_USO_ZPR_1` | LOUOS_ZONA_USO_ZPR_1 |
| `louos_zpr2:VM_L_Z_USO_ZPR_2` | LOUOS_ZONA_USO_ZPR_2 |
| `louos_zpr3:VM_L_Z_USO_ZPR_3` | LOUOS_ZONA_USO_ZPR_3 |
| `louos_zue:VM_L_Z_USO_ZUE` | LOUOS_ZONA_USO_ZUE |
| `louos_zusi:VM_L_Z_USO_ZUSI` | LOUOS_ZONA_USO_ZUSI |
| `pddu_zeis:VM_P_MAPA_ZEIS` | PDDU_MAPA_ZEIS |
| `sedur:L_Z_USO_ZCLME` | LOUOS_ZONAS_DE_USO_ZCLME |
| `sedur:L_Z_USO_ZCLMU` | LOUOS_ZONAS_DE_USO_ZCLMU |
| `sedur:L_Z_USO_ZCME_AGUAS_CLARAS` | LOUOS_ZONAS_DE_USO_ZCME_AGUAS_CLARAS |
| `sedur:L_Z_USO_ZCME_CA` | LOUOS_ZONAS_DE_USO_ZCME_CA |
| `sedur:L_Z_USO_ZCME_CAMARAGIBE` | LOUOS_ZONAS_DE_USO_ZCME_CAMARAGIBE |
| `sedur:L_Z_USO_ZCME_L_VIANA_29_MAR` | LOUOS_ZONAS_DE_USO_ZCME_LUIS_VIANA_29_MARCO |
| `sedur:L_Z_USO_ZCME_RET_ACESS_NOR` | LOUOS_ZONAS_DE_USO_ZCME_RETIRO_ACESSO_NORTE |
| `sedur:L_Z_USO_ZCMU_1` | LOUOS_ZONAS_DE_USO_ZCMU_1 |
| `sedur:L_Z_USO_ZCMU_2` | LOUOS_ZONAS_DE_USO_ZCMU_2 |
| `sedur:L_Z_USO_ZDE_1` | LOUOS_ZONAS_DE_USO_ZDE_1 |
| `sedur:L_Z_USO_ZDE_2` | LOUOS_ZONAS_DE_USO_ZDE_2 |
| `sedur:L_Z_USO_ZEIS` | LOUOS_ZONA_DE_USO_ZEIS |
| `sedur:L_Z_USO_ZEM` | LOUOS_ZONAS_DE_USO_ZEM |
| `sedur:L_Z_USO_ZIT` | LOUOS_ZONAS_DE_USO_ZIT |
| `sedur:L_Z_USO_ZPAM` | LOUOS_ZONAS_DE_USO_ZPAM |
| `sedur:L_Z_USO_ZPR_1` | LOUOS_ZONAS_DE_USO_ZPR_1 |
| `sedur:L_Z_USO_ZPR_2` | LOUOS_ZONAS_DE_USO_ZPR_2 |
| `sedur:L_Z_USO_ZPR_3` | LOUOS_ZONAS_DE_USO_ZPR_3 |
| `sedur:L_Z_USO_ZUE` | LOUOS_ZONAS_DE_USO_ZUE |
| `sedur:L_Z_USO_ZUSI` | LOUOS_ZONAS_DE_USO_ZUSI |
| `sedur:P_MAPA_ZEIS` | PDDU_MAPA_ZEIS |
| `zeis:VM_P_MAPA_ZEIS` | PDDU_MAPA_ZEIS |

### Classificação viária e gabarito LOUOS (55)

| typeName | Título |
|---|---|
| `l_classificacao_viaria:VM_L_CLASSIF_VIARIA` | LOUOS_CLASSIFICACAO_VIARIA |
| `l_gabarito:VM_L_GAB_06M` | LOUOS_GABARITO_06METROS |
| `l_gabarito:VM_L_GAB_09M` | LOUOS_GABARITO_09METROS |
| `l_gabarito:VM_L_GAB_12M` | LOUOS_GABARITO_12METROS |
| `l_gabarito:VM_L_GAB_15M` | LOUOS_GABARITO_15METROS |
| `l_gabarito:VM_L_GAB_18M` | LOUOS_GABARITO_18METROS |
| `l_gabarito:VM_L_GAB_24M` | LOUOS_GABARITO_24METROS |
| `l_gabarito:VM_L_GAB_30M` | LOUOS_GABARITO_30METROS |
| `l_gabarito:VM_L_GAB_36M` | LOUOS_GABARITO_36METROS |
| `l_gabarito:VM_L_GAB_45M` | LOUOS_GABARITO_45METROS |
| `l_gabarito:VM_L_GAB_51M` | LOUOS_GABARITO_51METROS |
| `l_gabarito:VM_L_GAB_60M` | LOUOS_GABARITO_60METROS |
| `l_gabarito:VM_L_GAB_75M` | LOUOS_GABARITO_75METROS |
| `l_gabarito:VM_L_GAB_ABM` | LOUOS_GABARITO_ABM |
| `l_gabarito:VM_L_GAB_APR` | LOUOS_GABARITO_AREA_DE_PROTECAO_RIGOROSA |
| `l_gabarito:VM_L_GAB_FAIXA_PRAIA` | LOUOS_GABARITO_FAIXA_PRAIA |
| `l_gabarito:VM_L_GAB_PRESERVACAO_ENCOSTA` | LOUOS_GABARITO_PRESERVACAO_ENCOSTA |
| `l_gabarito:VM_L_GAB_RECORTE_AREA_CENTRAL` | LOUOS_GABARITO_RECORTE_AREA_CENTRAL |
| `l_gabarito:VM_L_GAB_TRECHO_ABM_2016` | LOUOS_GABARITO_TRECHO_ABM_2016 |
| `louos_classificacao_viaria:VM_L_CLASSIF_VIARIA` | LOUOS_CLASSIFICACAO_VIARIA |
| `louos_gabarito_06_metros:VM_L_GAB_06M` | LOUOS_GABARITO_06_METROS |
| `louos_gabarito_09_metros:VM_L_GAB_09M` | LOUOS_GABARITO_09_METROS |
| `louos_gabarito_12_metros:VM_L_GAB_12M` | LOUOS_GABARITO_12_METROS |
| `louos_gabarito_15_metros:VM_L_GAB_15M` | LOUOS_GABARITO_15_METROS |
| `louos_gabarito_18_metros:VM_L_GAB_18M` | LOUOS_GABARITO_18_METROS |
| `louos_gabarito_24_metros:VM_L_GAB_24M` | LOUOS_GABARITO_24_METROS |
| `louos_gabarito_30_metros:VM_L_GAB_30M` | LOUOS_GABARITO_30_METROS |
| `louos_gabarito_36_metros:VM_L_GAB_36M` | LOUOS_GABARITO_36_METROS |
| `louos_gabarito_45_metros:VM_L_GAB_45M` | LOUOS_GABARITO_45_METROS |
| `louos_gabarito_51_metros:VM_L_GAB_51M` | LOUOS_GABARITO_51_METROS |
| `louos_gabarito_60_metros:VM_L_GAB_60M` | LOUOS_GABARITO_60_METROS |
| `louos_gabarito_75_metros:VM_L_GAB_75M` | LOUOS_GABARITO_75_METROS |
| `louos_gabarito_apr:VM_L_GAB_APR` | LOUOS_GABARITO_AREA_PROTECAO_RIGOROSA |
| `louos_gabarito_faixa_praia:VM_L_GAB_FAIXA_PRAIA` | LOUOS_GABARITO_FAIXA_DE_PRAIA |
| `louos_gabarito_limite_abm:VM_L_GAB_ABM` | LOUOS_GABARITO_AREA_DE_BORDA_MARITIMA |
| `louos_gabarito_preservacao_de_encosta:VM_L_GAB_PRESERVACAO_ENCOSTA` | LOUOS_GABARITO_PRESERVACAO_ENCOSTA |
| `louos_gabarito_recorte_area_central:VM_L_GAB_RECORTE_AREA_CENTRAL` | LOUOS_GABARITO_RECORTE_AREA_CENTRAL |
| `louos_gabarito_trechos_abm:VM_L_GAB_TRECHO_ABM_2016` | LOUOS_GABARITO_TRECHO_AREA_DE_BORDA_MARITIMA |
| `sedur:L_CLASSIF_VIARIA` | LOUOS_CLASSIFICACAO_VIARIA |
| `sedur:L_GAB_06M` | LOUOS_GABARITO_06M |
| `sedur:L_GAB_09M` | LOUOS_GABARITO_09M |
| `sedur:L_GAB_12M` | LOUOS_GABARITO_12M |
| `sedur:L_GAB_15M` | LOUOS_GABARITO_15M |
| `sedur:L_GAB_18M` | LOUOS_GABARITO_18M |
| `sedur:L_GAB_24M` | LOUOS_GABARITO_24M |
| `sedur:L_GAB_30M` | LOUOS_GABARITO_30M |
| `sedur:L_GAB_36M` | LOUOS_GABARITO_36M |
| `sedur:L_GAB_45M` | LOUOS_GABARITO_45M |
| `sedur:L_GAB_51M` | LOUOS_GABARITO_51M |
| `sedur:L_GAB_60M` | LOUOS_GABARITO_60M |
| `sedur:L_GAB_75M` | LOUOS_GABARITO_75M |
| `sedur:L_GAB_APR` | LOUOS_GABARITO_APR |
| `sedur:L_GAB_FAIXA_PRAIA` | LOUOS_GABARITO_FAIXA_DE_PRAIA |
| `sedur:L_GAB_PRESERVACAO_ENCOSTA` | LOUOS_GABARITO_PRESERVACAO_DE_ENCOSTA |
| `sedur:L_GAB_RECORTE_AREA_CENTRAL` | LOUOS_GABARITO_RECORTE_AREA_CENTRAL |

### SAVAM / ambiental LOUOS (79)

| typeName | Título |
|---|---|
| `apcp_centro_antigo:VM_L_SAVAM_APCP` | LOUOS_SAVAM_APCP_CENTRO_ANTIGO |
| `l_abm_2016:VM_L_SAVAM_ABM` | LOUOS_SAVAM_ABM |
| `l_abm_2016:VM_L_SAVAM_TRECHOS_ABM_2016` | LOUOS_SAVAM_TRECHOS_ABM_2016 |
| `l_savam:VM_L_SAVAM_ABM` | LOUOS_SAVAM_ABM |
| `l_savam:VM_L_SAVAM_APA_ESTADUAL` | LOUOS_SAVAM_APA_ESTADUAL |
| `l_savam:VM_L_SAVAM_APCP` | LOUOS_SAVAM_APCP |
| `l_savam:VM_L_SAVAM_APRN` | LOUOS_SAVAM_APRN |
| `l_savam:VM_L_SAVAM_CLASSIF_MATA_ATLANT` | LOUOS_SAVAM_CLASSIFACAO_MATA_ATLANTICA |
| `l_savam:VM_L_SAVAM_PQ_BAIRRO` | LOUOS_SAVAM_PARQUE_BAIRRO |
| `l_savam:VM_L_SAVAM_PQ_URBANO` | LOUOS_SAVAM_PARQUE_URBANO |
| `l_savam:VM_L_SAVAM_PQ_URBANO_PROP` | LOUOS_SAVAM_PARQUE_URBANO_PROPOSTO |
| `l_savam:VM_L_SAVAM_TRECHOS_ABM_2016` | LOUOS_SAVAM_TRECHOS_ABM_2016 |
| `l_savam:VM_L_SAVAM_UC_INDICADAS` | LOUOS_SAVAM_UC_INDICADAS |
| `l_savam:VM_L_SAVAM_UCM` | LOUOS_SAVAM_UCM |
| `l_zee:VM_L_Z_AMB_REFERENCIAS_GNL` | LOUOS_ZONA_AMBIENTAL_REFERENCIAS_GNL |
| `l_zee:VM_L_Z_AMB_REFERENCIAS_ILHAS` | LOUOS_ZEE_AMBIENTAL_REFERENCIAS_ILHAS |
| `l_zee:VM_L_Z_AMB_ZEE_BOM_JESUS_ILHOT` | LOUOS_ZONA_AMBIENTAL_ZEE_BOM_JESUS_ILHOTA |
| `l_zee:VM_L_Z_AMB_ZEE_BOM_JESUS_PIER` | LOUOS_ZONA_AMBIENTAL_ZEE_BOM_JESUS_PIER |
| `l_zee:VM_L_Z_AMB_ZEE_FRADES` | LOUOS_ZEE_AMBIENTAL_ILHA_FRADES |
| `l_zee:VM_L_Z_AMB_ZEE_FRADES_PIER` | LOUOS_ZEE_AMBIENTAL_ILHA_FRADES_PIER |
| `l_zee:VM_L_Z_AMB_ZEE_FRADES_SINA_NAU` | LOUOS_ZEE_AMBIENTAL_ILHA_FRADES_SINALIZACAO_NAUTICA |
| `l_zee:VM_L_Z_AMB_ZEE_JOANE_IPITANGA` | LOUOS_ZEE_AMBIENTAL_ILHA_JOANE_IPITANGA |
| `l_zee:VM_L_Z_AMB_ZEE_LAGOA_DUNA_ABAE` | LOUOS_ZEE_AMBIENTAL_LAGOA_DUNA_ABAETE |
| `louos_mata_atlantica:VM_L_SAVAM_CLASSIF_MATA_ATLANT` | LOUOS_SAVAM_CLASSIFICACAO_MATA_ATLANTICA |
| `louos_savam_apa_estadual:VM_L_SAVAM_APA_ESTADUAL` | LOUOS_SAVAM_APA_ESTADUAL |
| `louos_savam_apcp:VM_L_SAVAM_APCP` | LOUOS_SAVAM_APCP |
| `louos_savam_aprn:VM_L_SAVAM_APRN` | LOUOS_SAVAM_APRN |
| `louos_savam_limite_abm:VM_L_SAVAM_ABM` | LOUOS_SAVAM_LIMITE_ABM |
| `louos_savam_parque_bairro:VM_L_SAVAM_PQ_BAIRRO` | LOUOS_SAVAM_PARQUE_BAIRRO |
| `louos_savam_parque_urbano:VM_L_SAVAM_PQ_URBANO` | LOUOS_SAVAM_PARQUE_URBANO |
| `louos_savam_parque_urbano_proposto:VM_L_SAVAM_PQ_URBANO_PROP` | LOUOS_SAVAM_PARQUE_URBANO_PROPOSTO |
| `louos_savam_trecho_abm:VM_L_SAVAM_TRECHOS_ABM_2016` | LOUOS_SAVAM_TRECHOS_ABM_2016 |
| `louos_savam_uci:VM_L_SAVAM_UC_INDICADAS` | LOUOS_SAVAM_UNIDADE_CONSERVACAO_INDICADAS |
| `louos_savam_ucm:VM_L_SAVAM_UCM` | LOUOS_SAVAM_UCM |
| `pddu_classificacao_mata_atlantica:VM_P_SAVAM_CLASSIF_MATA_ATLANT` | PDDU_SAVAM_CLASSIFiCACAO_MATA_ATLANTICA |
| `pddu_savam_apa_estadual:VM_P_SAVAM_APA_ESTADUAL` | PDDU_SAVAM_APA_ESTADUAL |
| `pddu_savam_apcp:VM_P_SAVAM_APCP` | PDDU_SAVAM_APCP |
| `pddu_savam_aprn:VM_P_SAVAM_APRN` | PDDU_SAVAM_APRN |
| `pddu_savam_limite_abm:VM_P_SAVAM_ABM` | PDDU_SAVAM_LIMITE_ABM |
| `pddu_savam_parque_bairro:VM_P_SAVAM_PQ_BAIRRO` | PDDU_SAVAM_PARQUE_BAIRRO |
| `pddu_savam_parque_urbano:VM_P_SAVAM_PQ_URBANO` | PDDU_SAVAM_PARQUE_URBANO |
| `pddu_savam_parque_urbano_proposto:VM_P_SAVAM_PQ_URBANO_PROP` | PDDU_SAVAM_PARQUE_URBANO_PROPOSTO |
| `pddu_savam_trecho_abm:VM_P_SAVAM_TRECHOS_ABM_2016` | PDDU_SAVAM_TRECHOS_ABM_2016 |
| `pddu_savam_uci:VM_P_SAVAM_UC_INDICADAS` | PDDU_SAVAM_UC_INDICADAS |
| `pddu_savam_ucm:VM_P_SAVAM_UCM` | PDDU_SAVAM_UCM |
| `savam:VM_P_SAVAM_ABM` | PDDU_SAVAM_ABM |
| `savam:VM_P_SAVAM_APA_ESTADUAL` | PDDU_SAVAM_APA_ESTADUAL |
| `savam:VM_P_SAVAM_APCP` | PDDU_SAVAM_APCP |
| `savam:VM_P_SAVAM_APRN` | PDDU_SAVAM_APRN |
| `savam:VM_P_SAVAM_CLASSIF_MATA_ATLANT` | PDDU_SAVAM_CLASSIFACAO_MATA_ATLANTICA |
| `savam:VM_P_SAVAM_PQ_BAIRRO` | PDDU_SAVAM_PARQUE_BAIRRO |
| `savam:VM_P_SAVAM_PQ_URBANO` | PDDU_SAVAM_PARQUE_URBANO |
| `savam:VM_P_SAVAM_PQ_URBANO_PROP` | PDDU_SAVAM_PARQUE_URBANO_PROPOSTO |
| `savam:VM_P_SAVAM_TRECHOS_ABM_2016` | PDDU_SAVAM_TRECHOS_ABM_2016 |
| `savam:VM_P_SAVAM_UC_INDICADAS` | PDDU_SAVAM_UC_INDICADAS |
| `savam:VM_P_SAVAM_UCM` | PDDU_SAVAM_UCM |
| `sedur:L_SAVAM_ABM` | LOUOS_SAVAM_ABM |
| `sedur:L_SAVAM_APA_ESTADUAL` | LOUOS_SAVAM_APA_ESTADUAL |
| `sedur:L_SAVAM_APCP` | LOUOS_SAVAM_APCP |
| `sedur:L_SAVAM_APRN` | LOUOS_SAVAM_APRN |
| `sedur:L_SAVAM_CLASSIF_MATA_ATLANT` | LOUOS_SAVAM_CLASSIFICACAO_MATA_ATLANTICA |
| `sedur:L_SAVAM_PQ_BAIRRO` | LOUOS_SAVAM_PARQUE_DE_BAIRRO |
| `sedur:L_SAVAM_PQ_URBANO` | LOUOS_SAVAM_PARQUE_URBANO |
| `sedur:L_SAVAM_PQ_URBANO_PROP` | LOUOS_SAVAM_PARQUE_URBANO_PROPOSTO |
| `sedur:L_SAVAM_TRECHOS_ABM_2016` | LOUOS_SAVAM_TRECHOS_ABM_2016 |
| `sedur:L_SAVAM_UC_INDICADAS` | LOUOS_SAVAM_UC_INDICADAS |
| `sedur:L_SAVAM_UCM` | LOUOS_SAVAM_UCM |
| `sedur:P_SAVAM_ABM` | PDDU_SAVAM_ABM |
| `sedur:P_SAVAM_APA_ESTADUAL` | PDDU_SAVAM_APA_ESTADUAL |
| `sedur:P_SAVAM_APCP` | PDDU_SAVAM_APCP |
| `sedur:P_SAVAM_APRN` | PDDU_SAVAM_APRN |
| `sedur:P_SAVAM_CLASSIF_MATA_ATLANT` | PDDU_SAVAM_CLASSIFICACAO_MATA_ATLANTICA |
| `sedur:P_SAVAM_PQ_BAIRRO` | PDDU_SAVAM_PARQUE_DE_BAIRRO |
| `sedur:P_SAVAM_PQ_URBANO` | PDDU_SAVAM_PARQUE_URBANO |
| `sedur:P_SAVAM_PQ_URBANO_PROP` | PDDU_SAVAM_PARQUE_URBANO_PROPOSTO |
| `sedur:P_SAVAM_TRECHOS_ABM_2016` | PDDU_SAVAM_TRECHOS_ABM_2016 |
| `sedur:P_SAVAM_UC_INDICADAS` | PDDU_SAVAM_UC_INDICADAS |
| `sedur:P_SAVAM_UCM` | PDDU_SAVAM_UCM |
| `zee_joanes_ipitanga_alt_inema:VM_ZEE_JOANEIPITANGA_INEMA_ALT` | ZEE_JOANEIPITANGA_INEMA_ALTERACAO_PONTUAL |

### Cadastro territorial (38)

| typeName | Título |
|---|---|
| `bairro_oficial:VM_BAIRRO_OFICIAL` | BAIRRO_OFICIAL |
| `enderecamento:VM_ENDERECAMENTO` | ENDERECAMENTO |
| `lei_bairros:VM_LEI_BAIRRO` | LEI_BAIRRO |
| `limite_salvador:VM_LIMITE_SALVADOR` | LIMITE_SALVADOR_SEI_DVPA |
| `logradouro:VM_LOGRADOURO` | LOGRADOURO |
| `logradouros:VM_LOGRADOURO` | LOGRADOUROS |
| `matriculas_imoveis:VM_MATRICULAS_IMOVEIS` | MATRICULAS_IMOVEIS |
| `MATRICULAS_IMOVEIS:VM_MATRICULAS_IMOVEIS` | VM_MATRICULAS_IMOVEIS |
| `parcelamento:VM_PARCELAMENTO` | PARCELAMENTO |
| `parcelamento:VM_PARCELAMENTO_AREA` | PARCELAMENTO_AREA |
| `parcelamento:VM_PARCELAMENTO_CANC_LOTESEDIF` | PARCELAMENTO_CANCELADO_LOTESEDIF |
| `parcelamento:VM_PARCELAMENTO_CANCELA_QUADRA` | PARCELAMENTO_CANCELADO_QUADRA |
| `parcelamento:VM_PARCELAMENTO_CANCELADO` | PARCELAMENTO_CANCELADO |
| `parcelamento:VM_PARCELAMENTO_CANCELADO_AREA` | PARCELAMENTO_CANCELADO_AREA |
| `parcelamento:VM_PARCELAMENTO_LOTES_EDIF` | PARCELAMENTO_LOTES_EDIF |
| `parcelamento:VM_PARCELAMENTO_QUADRA` | PARCELAMENTO_QUADRA |
| `parcelamento_areas:VM_PARCELAMENTO_AREA` | PARCELAMENTO_AREA |
| `parcelamento_areas_cancelado:VM_PARCELAMENTO_CANCELADO_AREA` | PARCELAMENTO_AREA_CANCELADO |
| `parcelamento_desenv_sirgas:PARCELAMENTO` | PARCELAMENTO |
| `parcelamento_desenv_sirgas:PARCELAMENTO_AREA` | PARCELAMENTO_AREA |
| `parcelamento_desenv_sirgas:PARCELAMENTO_LOTES_EDIF` | PARCELAMENTO_LOTES_EDIF |
| `parcelamento_desenv_sirgas:PARCELAMENTO_QUADRA` | PARCELAMENTO_QUADRA |
| `parcelamento_lotes:VM_PARCELAMENTO_LOTES_EDIF` | PARCELAMENTO_LOTES_EDIFICACAO |
| `parcelamento_lotes_cancelado:VM_PARCELAMENTO_CANC_LOTESEDIF` | PARCELAMENTO_LOTES_CANCELADO |
| `parcelamento_poligonal:VM_PARCELAMENTO` | PARCELAMENTO_POLIGONAL |
| `parcelamento_poligonal_cancelado:VM_PARCELAMENTO_CANCELADO` | PARCELAMENTO_POLIGONAL_CANCELADO |
| `parcelamento_quadra:VM_PARCELAMENTO_QUADRA` | PARCELAMENTO_QUADRA |
| `parcelamento_quadra_cancelado:VM_PARCELAMENTO_CANCELA_QUADRA` | PARCELAMENTO_QUADRA_CANCELADO |
| `poli_parcelamento:VM_POLI_PARCELAMENTOS` | POLI_PARCELAMENTOS |
| `prefeitura_bairro:VM_P_PREFEITURA_BAIRRO` | PDDU_PREFEITURA_BAIRRO |
| `sedur:ENDERECAMENTO` | ENDERECAMENTO |
| `sedur:LEI_BAIRRO` | LEI_BAIRRO |
| `sedur:LIMITE_SALVADOR` | LIMITE_SALVADOR |
| `sedur:LOGRADOURO` | LOGRADOURO |
| `sedur:PARCELAMENTO` | PARCELAMENTO |
| `sedur:PARCELAMENTO_AREA` | PARCELAMENTO_AREA |
| `sedur:PARCELAMENTO_LOTES_EDIF` | PARCELAMENTO_LOTES_EDIF |
| `sedur:PARCELAMENTO_QUADRA` | PARCELAMENTO_QUADRA |

### Patrimônio e restrições pontuais (27)

| typeName | Título |
|---|---|
| `area_especial:VM_AREA_AERONAUTICA` | AREA_AERONAUTICA |
| `area_especial:VM_AREA_ESPECIAL_MUNICIPAL` | AREA_ESPECIAL_MUNICIPAL |
| `area_especial:VM_AREA_EXERCITO` | AREA_EXERCITO |
| `area_especial:VM_CHESF` | CHESF |
| `area_especial:VM_HELIPONTO` | HELIPONTO |
| `area_especial:VM_LIMITE_PREAMAR` | LIMITE_PREAMAR |
| `area_especial:VM_UFBA` | UFBA |
| `area_especial:VM_UNEB` | UNEB |
| `area_militar_aeronautica:VM_AREA_AERONAUTICA` | AREA_MILITAR_AERONAUTICA |
| `area_militar_exercito:VM_AREA_EXERCITO` | AREA_MILITAR_EXERCITO |
| `areas_da_uniao:VM_LIMITE_PREAMAR` | LIMITE_PREAMAR_AREA_DA_UNIAO |
| `areas_publicas:VM_AREA_PUBLICA` | AREA_PUBLICA |
| `bens_tombados:VM_BENS_TOMBADOS` | BENS_TOMBADOS |
| `bens_tombados:VM_BENS_TOMBADOS_RAIO` | BENS_TOMBADOS_RAIO |
| `cemiterio:VM_CEMITERIO` | CEMITERIO |
| `cemiterios:VM_CEMITERIO` | CEMITERIO |
| `decreto_desapropriacao_estadual:VM_DEC_ESTADUAL_DESAPROPRIACAO` | DECRETO_ESTADUAL_DESAPROPRIACAO |
| `decreto_desapropriacao_municipal:VM_DEC_MUNICIPAL_DESAPROPRIA` | DECRETO_MUNICIPAL_DESAPROPRIACAO |
| `desafetacao_municipal:VM_DESAFETACAO_MUNICIPAL` | DESAFETACAO_MUNICIPAL |
| `patrimonio_historico:VM_BENS_TOMBADOS` | VM_BENS_TOMBADOS |
| `patrimonio_historico_raio:VM_BENS_TOMBADOS_RAIO` | BENS_TOMBADOS_RAIO |
| `sedur:AREA_AERONAUTICA` | AREA_AERONAUTICA |
| `sedur:AREA_EXERCITO` | AREA_EXERCITO |
| `sedur:AREA_PUBLICA` | AREA_PUBLICA |
| `sedur:BENS_TOMBADOS` | BENS_TOMBADOS |
| `sedur:BENS_TOMBADOS_RAIO` | BENS_TOMBADOS_RAIO |
| `servidao_administrativa:VM_SERVIDAO_ADM` | SERVIDAO_ADMINISTRATIVA |

### PDDU (macroárea, centralidade, transporte) (171)

| typeName | Título |
|---|---|
| `centralidade:VM_P_CENTRALIDADE_AEROPORTO` | PDDU_CENTRALIDADE_AEROPORTO |
| `centralidade:VM_P_CENTRALIDADE_CO_ME_CA_VLT` | PDDU_CENTRALIDADE_CORREDOR_MEDIA_CAPACIDADE_VLT |
| `centralidade:VM_P_CENTRALIDADE_CORR_ALT_CAP` | PDDU_CENTRALIDADE_CORREDOR_ALTA_CAPACIDADE |
| `centralidade:VM_P_CENTRALIDADE_CORR_MED_CAP` | PDDU_CENTRALIDADE_CORREDOR_MEDIA_CAPACIDADE |
| `centralidade:VM_P_CENTRALIDADE_EST_MARITIMA` | PDDU_CENTRALIDADE_ESTACAO_MARITIMA |
| `centralidade:VM_P_CENTRALIDADE_EST_METR` | PDDU_CENTRALIDADE_ESTACAO_METROVIARIA |
| `centralidade:VM_P_CENTRALIDADE_EST_VLT` | PDDU_CENTRALIDADE_ESTACAO_VLT |
| `centralidade:VM_P_CENTRALIDADE_METROP` | PDDU_CENTRALIDADE_METROPOLITANA |
| `centralidade:VM_P_CENTRALIDADE_MUNICIPAIS` | PDDU_CENTRALIDADE_MUNICIPAIS |
| `centralidade:VM_P_CENTRALIDADE_ZUSI_CONCEIT` | PDDU_CENTRALIDADE_ZUSI_CONCEITUAL |
| `macroareas:VM_P_MACROAREA_ESTRUT_URBANA` | PDDU_MACROAREA_ESTRUTURACAO_URBANA |
| `macroareas:VM_P_MACROAREA_INTEGRA_METROP` | PDDU_MACROAREA_INTEGRACAO_METROPOLITANA |
| `macroareas:VM_P_MACROAREA_REEST_BORD_BTS` | PDDU_MACROAREA_REESTRUTURACAO_BORDA_BTS |
| `macroareas:VM_P_MACROAREA_REQUA_BORD_ATLA` | PDDU_MACROAREA_REQUALIFICACAO_BORDA_ATLANTICA |
| `macroareas:VM_P_MACROAREA_SETORES_MIM` | PDDU_MACROAREA_SETORES_MIM |
| `macroareas:VM_P_MACROAREA_URBAN_CONSOLI` | PDDU_MACROAREA_URBANA_CONSOLIDADA |
| `macrozona:VM_P_MACROZONA_CONSERV_AMB` | PDDU_MACROZONA_CONSERVACAO_AMBIENTAL |
| `macrozona:VM_P_MACROZONA_OCUPACAO_URBANA` | PDDU_MACROZONA_OCUPACAO_URBANA |
| `pddu_cargas_aterro_sanitario:VM_P_TRANSP_CAR_ATER_SANITARIO` | PDDU_TRANSPORTE_CARGA_ATERRO_SANITARIO |
| `pddu_cargas_centros_abastecimento:VM_P_TRANSP_CAR_CENTRAL_ABAST` | PDDU_TRANSPORTE_CARGA_CENTRAL_ABASTECIMENTO |
| `pddu_cargas_corredor_primario_existente:VM_P_TRANSP_CAR_CO_CAR_PRI_EFE` | PDDU_TRANSPORTE_CARGA_CORREDOR_CARGA_PRIMARIO_EXISTENTE |
| `pddu_cargas_corredor_primario_proposto:VM_P_TRANSP_CAR_COR_CA_PRI_PRO` | PDDU_TRANSPORTE_CARGA_CORRREDOR_CARGA_PRIMARIO_PROPOSTO |
| `pddu_cargas_corredor_secundario_existente:VM_P_TRANSP_CAR_CO_CAR_SEC_EFE` | PDDU_TRANSPORTE_CARGA_CORREDOR_SECUNDARIO_EXISTENTE |
| `pddu_cargas_corredor_secundario_proposto:VM_P_TRANSP_CAR_COR_CA_SEC_PRO` | PDDU_TRANSPORTE_CARGA_CORREDORCA_SECUNDARIO_PROPOSTO |
| `pddu_cargas_pedreiras:VM_P_TRANSP_CAR_PEDREIRAS` | PDDU_TRANSPORTE_CARGA_PEDREIRAS |
| `pddu_cargas_polo_logistico:VM_P_TRANSP_CAR_POLO_LOGISTICO` | PDDU_TRANSPORTE_CARGA_POLO_LOGISTICO |
| `pddu_cargas_terminal_cargas_aereas:VM_P_TRANSP_CAR_TERM_CAR_AEREA` | PDDU_TRANSPORTE_CARGA_TERMINAL_CARGA_AEREA |
| `pddu_cargas_terminal_containers:VM_P_TRANSP_TERM_CONTEINERS` | PDDU_TRANSPORTE_TERMINAL_CONTEINERS |
| `pddu_centralidades_corredores_alta_capacidade:VM_P_CENTRALIDADE_CORR_ALT_CAP` | PDDU_CENTRALIDADE_CORREDOR_ALTA_CAPACIDADE |
| `pddu_centralidades_corredores_media_capacidade:VM_P_CENTRALIDADE_CORR_MED_CAP` | PDDU_CENTRALIDADE_CORREDOR_MEDIA_CAPACIDADE |
| `pddu_centralidades_corredores_media_capacidade_vlt:VM_P_CENTRALIDADE_CO_ME_CA_VLT` | PDDU_CENTRALIDADE_CORREDOR_MEDIA_CAPACIDADE_VLT |
| `pddu_centralidades_metropolitana:VM_P_CENTRALIDADE_METROP` | PDDU_CENTRALIDADES_METROPOLITANA |
| `pddu_centralidades_municipais:VM_P_CENTRALIDADE_MUNICIPAIS` | PDDU_CENTRALIDADE_MUNICIPAIS |
| `pddu_centralidades_transporte_aeroporto:VM_P_CENTRALIDADE_AEROPORTO` | PDDU_CENTRALIDADE_AEROPORTO |
| `pddu_centralidades_transporte_estacao_maritima:VM_P_CENTRALIDADE_EST_MARITIMA` | PDDU_CENTRALIDADE_ESTACAO_MARITIMA |
| `pddu_centralidades_transporte_estacao_metroviaria:VM_P_CENTRALIDADE_EST_METR` | PDDU_CENTRALIDADE_ESTACAO_METROVIARIA |
| `pddu_centralidades_zusi:VM_P_CENTRALIDADE_ZUSI_CONCEIT` | PDDU_CENTRALIDADES_ZUSI_CONCEITUAL |
| `pddu_intervencoes_pontuais_transito:VM_P_SIST_VIARIO_INTERVEN_PONT` | PDDU_SISTEMA_VIARIO_INTERVENCAO_PONTUAL_TRANSITO |
| `pddu_macroarea_estruturacao_urbana:VM_P_MACROAREA_ESTRUT_URBANA` | PDDU_MACROAREA_ESTRUTURACAO_URBANA |
| `pddu_macroarea_integracao_metropolitana:VM_P_MACROAREA_INTEGRA_METROP` | PDDU_MACROAREA_INTEGRACAO_METROPOLITANA |
| `pddu_macroarea_reestruturacao_borda_bts:VM_P_MACROAREA_REEST_BORD_BTS` | PDDU_MACROAREA_REESTRUTURACAO_BORDA_BTS |
| `pddu_macroarea_requalificacao_borda_atlantica:VM_P_MACROAREA_REQUA_BORD_ATLA` | PDDU_MACROAREA_REQUALIFICACAO_BORDA_ATLANTICA |
| `pddu_macroarea_urbanizacao_consolidada:VM_P_MACROAREA_URBAN_CONSOLI` | PDDU_MACROAREA_URBANIZACAO_CONSOLIDADA |
| `pddu_macrozona_conservacao_ambiental:VM_P_MACROZONA_CONSERV_AMB` | PDDU_MACROZONA_CONSERVACAO_AMBIENTAL |
| `pddu_macrozona_ocupacao_urbana:VM_P_MACROZONA_OCUPACAO_URBANA` | PDDU_MACROZONA_OCUPACAO_URBANA |
| `pddu_operacoes_urbanas:VM_P_OPER_URB_CONSORCIADAS` | PDDU_OPERACOES_URBANAS_CONSORCIADAS |
| `pddu_prefeitura_bairro:VM_P_PREFEITURA_BAIRRO` | PDDU_PREFEITURA_BAIRRO |
| `pddu_setores_mim:VM_P_MACROAREA_SETORES_MIM` | PDDU_MACROAREA_SETORES_MIM |
| `pddu_transporte_atracadouros:VM_P_TRANSP_SIST_AUX_ATRACADOU` | PDDU_TRANSPORTE_SISTEMA_AUXILIAR_ATRACADOURO |
| `pddu_transporte_corredor_media_capacidade:VM_P_TRANSP_SIST_MEDIA_CAP` | PDDU_TRANSPORTE_SISTEMA_MEDIA_CAPACIDADE |
| `pddu_transporte_corredor_onibus_implantar:VM_P_TRANSP_SIST_BX_CAP_BUS_IM` | PDDU_TRANSPORTE_SISTEMA_BAIXA_CAPACIDADE_BUS_IMPLANTAR |
| `pddu_transporte_estacao_metro_1:VM_P_TRANSP_SIST_AUX_EST_METL1` | PDDU_TRANSPORTE_SISTEMA_AUXILIAR_ESTACAO_METROVIARIA_LINHA1 |
| `pddu_transporte_estacao_metro_2:VM_P_TRANSP_SIST_AUX_EST_METL2` | PDDU_TRANSPORTE_SISTEMA_AUXILIAR_ESTACAO_METRO_LINHA2 |
| `pddu_transporte_linha_hidroviaria:VM_P_TRANSP_SIST_BX_CAP_LN_NAU` | PDDU_TRANSPORTE_SISTEMA_BAIXA_CAPACIDADE_LINHA_NAUTICA |
| `pddu_transporte_metro_existente:VM_P_TRANSP_SIST_ALTA_CAP_LN1` | PDDU_TRANSPORTE_SISTEMA_ALTA_CAPACIDADE_LINHA_1 |
| `pddu_transporte_metro_implantacao_1:VM_P_TRANSP_SIST_ALTA_CAP_L1_I` | PDDU_TRANSPORTE_SISTEMA_ALTA_CAPACIDADE_LINHA_1 |
| `pddu_transporte_metro_implantacao_2:VM_P_TRANSP_SIST_ALTA_CAP_LN2` | PDDU_TRANSPORTE_SISTEMA_ALTA_CAPACIDADE_LINHA2 |
| `pddu_transporte_metropolitano_linha_maritima:VM_P_TRANSP_SIST_METROP_LN_NAU` | PDDU_TRANSPORTE_SISTEMA_METROPOLITANA_LINHA_NAUTICA |
| `pddu_transporte_rede_bicicletas:VM_P_TRANSP_SIST_CICLOVIARIO` | PDDU_TRANSPORTE_SISTEMA_CICLOVIARIO |
| `pddu_transporte_sistema_auxiliares_ascensores:VM_P_TRANSP_SIST_AUX_ASCENSORE` | PDDU_TRANSPORTE_SISTEMA_AUXILIAR_ASCENSORES |
| `pddu_transporte_terminais_auxiliares:VM_P_TRANSP_SIST_AUX_TERM` | PDDU_TRANSPORTE_SISTEMA_AUXILIAR_TERMINAIS |
| `pddu_transporte_terminais_conexao_intermodal:VM_P_TRANSP_SIST_AUX_TERM_INTE` | PDDU_TRANSPORTE_SISTEMA_AUXILIAR_TERMINAIS_INTERMODAL |
| `pddu_viario_via_arterial_construir:VM_P_SIST_VIARIO_VIA_ARTER_CON` | PDDU_SISTEMA_VIARIO_VIA_ARTERIAL_CONSTRUIR |
| `pddu_viario_via_arterial_duplicar:VM_P_SIST_VIARIO_VIA_ARTER_DUP` | PDDU_SISTEMA_VIARIO_VIA_ARTERIAL_DUPLICAR |
| `pddu_viario_via_arterial_existente:VM_P_SIST_VIARIO_VIA_ARTER_EFE` | PDDU_SISTEMA_VIARIO_VIA_ARTERIAL_EXISTENTE |
| `pddu_viario_via_coletora_construir:VM_P_SIST_VIARIO_VIA_COLET_CON` | PDDU_SISTEMA_VIARIO_VIA_COLETORA_CONSTRUIR |
| `pddu_viario_via_coletora_duplicar:VM_P_SIST_VIARIO_VIA_COLET_DUP` | PDDU_SISTEMA_VIARIO_VIA_COLETORA_DUPLICAR |
| `pddu_viario_via_coletora_existente:VM_P_SIST_VIARIO_VIA_COLET_EXI` | PDDU_SISTEMA_VIARIO_VIA_COLETORA_EXISTENTE |
| `pddu_viario_via_expressa_construir:VM_P_SIST_VIARIO_VIA_EXP_A_CON` | PDDU_SISTEMA_VIARIO_VIA_EXPRESSA_A_CONSTRUIR |
| `pddu_viario_via_expressa_existente:VM_P_SIST_VIARIO_VIA_EXP_EFET` | PDDU_SISTEMA_VIARIO_VIA_EXPRESSA_EXISTENTE |
| `plano_funcional:VM_FUNCIONAL_ACNORTE_LUISVIANA` | PLANO_FUNCIONAL_ACESSO_NORTE_LUIS_VIANA |
| `plano_funcional:VM_FUNCIONAL_AFRANIO_PEIXOTO` | PLANO_FUNCIONAL_AFRANIO_PEIXOTO |
| `plano_funcional:VM_FUNCIONAL_ANITA_GARIBALDI` | PLANO_FUNCIONAL_ANITA_GARIBALDI |
| `plano_funcional:VM_FUNCIONAL_JORGE_AMADO` | PLANO_FUNCIONAL_JORGE_AMADO |
| `plano_funcional:VM_FUNCIONAL_JURACY_MAGALHAES` | PLANO_FUNCIONAL_JURACY_MAGALHAES |
| `plano_funcional:VM_FUNCIONAL_LUIS_VIANA` | PLANO_FUNCIONAL_LUIS_VIANA |
| `plano_funcional:VM_FUNCIONAL_ORLANDO_GOMES` | PLANO_FUNCIONAL_ORLANDO_GOMES |
| `plano_funcional:VM_FUNCIONAL_PINTO_AGUIAR` | PLANO_FUNCIONAL_PINTO_AGUIAR |
| `sedur:P_CENTRALIDADE_CORR_ALTA_CAP` | PDDU_CENTRALIDADES_CORREDORES_ALTA_CAPACIDADE |
| `sedur:P_CENTRALIDADE_CORR_MED_CAP` | P_CENTRALIDADE_CORR_MED_CAP |
| `sedur:P_CENTRALIDADE_EST_MARITIMA` | PDDU_CENTRALIDADES_ESTACAO_MARITIMA |
| `sedur:P_CENTRALIDADE_EST_METR` | PDDU_CENTRALIDADES_ESTACOES_METRO |
| `sedur:P_CENTRALIDADE_EST_VLT` | PDDU_CENTRALIDADES_ESTACOES_VLT |
| `sedur:P_CENTRALIDADE_METROP` | PDDU_CENTRALIDADES_METROPOLITANAS |
| `sedur:P_CENTRALIDADE_MUNICIPAIS` | PDDU_CENTRALIDADES_MUNICIPAIS |
| `sedur:P_CENTRALIDADE_ZUSI_CONCEITUAL` | PDDU_CENTRALIDADES_ZUSI_CONCEITUAL |
| `sedur:P_MACROAREA_ESTRUT_URBANA` | PDDU_MACROAREA_ESTRUTURACAO_URBANA |
| `sedur:P_MACROAREA_INTEGRACAO_METROP` | PDDU_MACROAREA_INTEGRACAO_METROPOLITANA |
| `sedur:P_MACROAREA_REEST_BORD_BTS` | PDDU_MACROAREA_REESTRUTURACAO_BORDA_BTS |
| `sedur:P_MACROAREA_REQUAL_BORD_ATLANT` | PDDU_MACROAREA_REQUALIFICACAO_BORDA_ATLANTICA |
| `sedur:P_MACROAREA_SETORES_MIM` | P_MACROAREA_SETORES_MIM |
| `sedur:P_MACROAREA_URBAN_CONSOLIDADA` | PDDU_MACROAREA_URBANIZACAO_CONSOLIDADA |
| `sedur:P_MACROZONA_CONSERV_AMB` | PDDU_MACROZONA_CONSERVACAO_AMBIENTAL |
| `sedur:P_MACROZONA_OCUPACAO_URBANA` | PDDU_MACROZONA_OCUPACAO_URBANA |
| `sedur:P_OPER_URB_CONSORCIADAS` | PDDU_OPERACOES_URBANAS_CONSORCIADAS |
| `sedur:P_PREFEITURA_BAIRRO` | PDDU_PREFEITURA_BAIRRO |
| `sedur:P_SIST_AUX_EST_MARITIMA` | PDDU_SISTEMA_AUXILIAR_ESTACAO_MARITIMA |
| `sedur:P_SIST_AUX_EST_RODOV_EXIST` | PDDU_SISTEMA_AUXILIAR_ESTACAO_RODOVIARIA_EXISTENTE |
| `sedur:P_SIST_METRO_LINHA_METRO_TEMP` | PDDU_SISTEMA_METRO_LINHA_METRO_TEMPORARIA |
| `sedur:P_SIST_VIARIO_INTERVEN_PONTUAL` | PDDU_SISTEMA_VIARIO_INTERVENCOES_PONTUAIS |
| `sedur:P_SIST_VIARIO_VIA_ARTER_A_DUP` | PDDU_SISTEMA_VIARIO_VIA_ARTERIAL_A_DUPLICAR |
| `sedur:P_SIST_VIARIO_VIA_ARTER_CONST` | PDDU_SISTEMA_VIARIO_VIA_ARTERIAL_CONSTRUIR |
| `sedur:P_SIST_VIARIO_VIA_ARTER_EFET` | PDDU_SISTEMA_VIARIO_VIA_ARTERIAL_EXISTENTE |
| `sedur:P_SIST_VIARIO_VIA_COLET_A_DUP` | PDDU_SISTEMA_VIARIO_VIA_COLETORA_A_DUPLICAR |
| `sedur:P_SIST_VIARIO_VIA_COLET_CONST` | PDDU_SISTEMA_VIARIO_VIA_COLETORA_CONSTRUIR |
| `sedur:P_SIST_VIARIO_VIA_COLET_EXIS` | P_SIST_VIARIO_VIA_COLET_EXIS |
| `sedur:P_SIST_VIARIO_VIA_EXP_A_CONST` | PDDU_SISTEMA_VIARIO_VIA_EXPRESSA_A_CONSTRUIR |
| `sedur:P_SIST_VIARIO_VIA_EXP_EFET` | PDDU_SISTEMA_VIARIO_VIA_EXPRESSA_EXISTENTE |
| `sedur:P_TRANSP_CAR_ATERRO_SANITARIO` | PDDU_TRANSPORTE_CARGA_ATERRO_SANITARIO |
| `sedur:P_TRANSP_CAR_CENTRAL_ABAST` | PDDU_TRANSPORTE_CARGA_CENTRAIS_DE_ABASTECIMENTO |
| `sedur:P_TRANSP_CAR_CORR_CAR_PRI_EFET` | PDDU_TRANSPORTE_CARGA_CORREDOR_CARGA_PRIMARIO_EXISTENTE |
| `sedur:P_TRANSP_CAR_CORR_CAR_PRI_PROP` | PDDU_TRANSPORTE_CARGA_CORREDOR_CARGA_PRIMARIO_PROPOSTO |
| `sedur:P_TRANSP_CAR_CORR_CAR_SEC_EFET` | PDDU_TRANSPORTE_CARGA_CORREDOR_CARGA_SECUNDARIO_EXISTENTE |
| `sedur:P_TRANSP_CAR_CORR_CAR_SEC_PROP` | PDDU_TRANSPORTE_CARGA_CORREDOR_CARGA_SECUNDARIO_PROPOSTO |
| `sedur:P_TRANSP_CAR_PEDREIRAS` | PDDU_TRANSPORTE_CARGA_PEDREIRAS |
| `sedur:P_TRANSP_CAR_POLO_LOGISTICO` | PDDU_TRANSPORTE_CARGA_POLO_LOGISTICO |
| `sedur:P_TRANSP_CAR_TERM_CARS_AEREAS` | PDDU_TRANSPORTE_CARGA_TERMINAL_DE_CARGAS_AEREAS |
| `sedur:P_TRANSP_SIST_ALTA_CAP_L1_A_I` | PDDU_TRANSPORTE_SISTEMA_ALTA_CAPACIDADE_L1_A_I |
| `sedur:P_TRANSP_SIST_ALTA_CAP_LN1` | PDDU_TRANSPORTE_SISTEMA_ALTA_CAPACIDADE_LINHA1 |
| `sedur:P_TRANSP_SIST_ALTA_CAP_LN2` | PDDU_TRANSPORTE_SISTEMA_ALTA_CAPACIDADE_LINHA2 |
| `sedur:P_TRANSP_SIST_AUX_ASCENSORES` | PDDU_TRANSPORTE_SISTEMA_AUXILIAR_ASCENSORES |
| `sedur:P_TRANSP_SIST_AUX_ATRACADOUROS` | PDDU_TRANSPORTE_SISTEMA_AUXILIAR_ATRACADOUROS |
| `sedur:P_TRANSP_SIST_AUX_EST_METR_L1` | PDDU_TRANSPORTE_SISTEMA_AUXILIAR_ESTACAO_METRO_L1 |
| `sedur:P_TRANSP_SIST_AUX_EST_METR_L2` | PDDU_TRANSPORTE_SISTEMA_AUXILIAR_ESTACAO_METRO_L2 |
| `sedur:P_TRANSP_SIST_AUX_TERM` | PDDU_TRANSPORTE_SISTEMA_AUXILIAR_TERMINAIS |
| `sedur:P_TRANSP_SIST_AUX_TERM_INTERM` | PDDU_TRANSPORTE_SISTEMA_AUXILIAR_TERMINAIS_CONEXAO_INTERMODAL |
| `sedur:P_TRANSP_SIST_BX_CAP_BUS_EX` | PDDU_TRANSPORTE_SISTEMA_BAIXA_CAPACIDADE_CORREDORES_ONIBUS_EXISTENTE |
| `sedur:P_TRANSP_SIST_BX_CAP_BUS_IMP` | PDDU_TRANSPORTE_SISTEMA_BAIXA_CAPACIDADE_CORREDORES_ONIBUS_IMPLANTAR |
| `sedur:P_TRANSP_SIST_CICLOVIARIO` | P_TRANSP_SIST_CICLOVIARIO |
| `sedur:P_TRANSP_SIST_MEDIA_CAP` | P_TRANSP_SIST_MEDIA_CAP |
| `sedur:P_TRANSP_SIST_METROP_LN_NAUT` | PDDU_TRANSPORTE_SISTEMA_METROPOLITANO_LINHA_NAUTICA |
| `sedur:P_TRANSP_SIST_METROP_LN_TEMP` | PDDU_TRANSPORTE_SISTEMA_METROPOLITANO_LINHA_TEMPORARIA |
| `sedur:VM_P_TRANSP_SIST_BX_CAP_LN_NAUT` | PDDU_TRANSPORTE_SISTEMA_BAIXA_CAPACIDADE_LINHAS_NAUTICAS |
| `sistema_viario:VM_P_SIST_AUX_EST_MARITIMA` | PDDU_SISTEMA_AUXILIAR_ESTACAO_MARITIMA |
| `sistema_viario:VM_P_SIST_AUX_EST_RODOV_EXIST` | PDDU_SISTEMA_AUXILIAR_ESTACAO_RODOVIARIA_EXISTENTE |
| `sistema_viario:VM_P_SIST_METRO_LINHA_METR_TEM` | PDDU_SISTEMA_METRO_LINHA_METROPOLITANA_TEMPORARIO |
| `sistema_viario:VM_P_SIST_VIARIO_INTERVEN_PONT` | PDDU_SISTEMA_VIARIO_INTERVENCAO_PONTO |
| `sistema_viario:VM_P_SIST_VIARIO_VIA_ARTER_CON` | PDDU_SISTEMA_VIARIO_VIA_ARTERIAL_CONSTRUIR |
| `sistema_viario:VM_P_SIST_VIARIO_VIA_ARTER_DUP` | PDDU_SISTEMA_VIARIO_VIA_ARTERIAL_DUPLICAR |
| `sistema_viario:VM_P_SIST_VIARIO_VIA_ARTER_EFE` | PDDU_SISTEMA_VIARIO_VIA_ARTERIAL_EFETIVO |
| `sistema_viario:VM_P_SIST_VIARIO_VIA_COLET_CON` | PDDU_SISTEMA_VIARIO_VIA_COLETORA_CONSTRUIR |
| `sistema_viario:VM_P_SIST_VIARIO_VIA_COLET_DUP` | PDDU_SISTEMA_VIARIO_VIA_COLETORA_DUPLICAR |
| `sistema_viario:VM_P_SIST_VIARIO_VIA_COLET_EXI` | PDDU_SISTEMA_VIARIO_VIA_COLETORA_EXISTENTE |
| `sistema_viario:VM_P_SIST_VIARIO_VIA_EXP_A_CON` | PDDU_SISTEMA_VIARIO_VIA_EXPRESSA_A_CONSTRUIR |
| `sistema_viario:VM_P_SIST_VIARIO_VIA_EXP_EFET` | PDDU_SISTEMA_VIARIO_VIA_EXPRESSA_EFETETIVO |
| `transporte_carga:VM_P_TRANSP_CAR_ATER_SANITARIO` | PDDU_TRANSPORTE_CARGA_ATERRO_SANITARIO |
| `transporte_carga:VM_P_TRANSP_CAR_CENTRAL_ABAST` | PDDU_TRANSPORTE_CARGA_CENTRAL_ABASTECIMENTO |
| `transporte_carga:VM_P_TRANSP_CAR_CO_CAR_PRI_EFE` | PDDU_TRANSPORTE_CARGA_CORREDOR_PRIMARIO_EFETIVO |
| `transporte_carga:VM_P_TRANSP_CAR_CO_CAR_SEC_EFE` | PDDU_TRANSPORTE_CARGA_CORREDOR_SECUNDARIO_EFETIVO |
| `transporte_carga:VM_P_TRANSP_CAR_COR_CA_PRI_PRO` | PDDU_TRANSPORTE_CARGA_CORREDOR_PRIMARIO_PROPOSTO |
| `transporte_carga:VM_P_TRANSP_CAR_COR_CA_SEC_PRO` | PDDU_TRANSPORTE_CARGA_CORREDOR_SECUNDARIO_PROPOSTO |
| `transporte_carga:VM_P_TRANSP_CAR_PEDREIRAS` | PDDU_TRANSPORTE_CARGA_PEDREIRAS |
| `transporte_carga:VM_P_TRANSP_CAR_POLO_LOGISTICO` | PDDU_TRANSPORTE_CARGA_POLO_LOGISTICO |
| `transporte_carga:VM_P_TRANSP_CAR_TERM_CAR_AEREA` | PDDU_TRANSPORTE_CARGA_TERMINAL_CARGA_AEREA |
| `transporte_carga:VM_P_TRANSP_TERM_CONTEINERS` | PDDU_TRANSPORTE_TERMINAL_CONTEINERS |
| `transporte_coletivo:VM_P_TRANSP_SIST_ALTA_CAP_L1_I` | PDDU_TRANSPORTE_SISTEMA_ALTA_CAPACIDADE_L1_IMPLANTAR |
| `transporte_coletivo:VM_P_TRANSP_SIST_ALTA_CAP_LN1` | PDDU_TRANSPORTE_SISTEMA_ALTA_CAPACIDADE_LINHA1 |
| `transporte_coletivo:VM_P_TRANSP_SIST_ALTA_CAP_LN2` | PDDU_TRANSPORTE_SISTEMA_ALTA_CAPACIDADE_LINHA_2 |
| `transporte_coletivo:VM_P_TRANSP_SIST_AUX_ASCENSORE` | PDDU_TRANSPORTE_SISTEMA_AUXILIAR_ASCENSORES |
| `transporte_coletivo:VM_P_TRANSP_SIST_AUX_ATRACADOU` | PDDU_TRANSPORTE_SISTEMA_AUXILIAR_ATRACADOUROS |
| `transporte_coletivo:VM_P_TRANSP_SIST_AUX_EST_METL1` | PDDU_TRANSPORTE_SISTEMA_AUXILIAR_ESTACAO_METRO_L1 |
| `transporte_coletivo:VM_P_TRANSP_SIST_AUX_EST_METL2` | PDDU_TRANSPORTE_SISTEMA_AUXILIAR_ESTACAO_METRO_L2 |
| `transporte_coletivo:VM_P_TRANSP_SIST_AUX_TERM` | PDDU_TRANSPORTE_SISTEMA_AUXILIAR_TERMINAIS |
| `transporte_coletivo:VM_P_TRANSP_SIST_AUX_TERM_INTE` | PDDU_TRANSPORTE_COLETIVO_TERMINAL_INTERMODAL |
| `transporte_coletivo:VM_P_TRANSP_SIST_BX_CAP_BUS_EX` | PDDU_TRANSPORTE_SISTEMA_BAIXA_CAPACIDADE_ONIBUS_EXISTENTE |
| `transporte_coletivo:VM_P_TRANSP_SIST_BX_CAP_BUS_IM` | PDDU_TRANSPORTE_SISTEMA_BAIXA_CAPACIDADE_BUS_IMPLANTAR |
| `transporte_coletivo:VM_P_TRANSP_SIST_BX_CAP_LN_NAU` | PDDU_TRANSPORTE_SISTEMA_BAIXA_CAPACIDADE_LINHA_NAUTICA |
| `transporte_coletivo:VM_P_TRANSP_SIST_CICLOVIARIO` | PDDU_TRANSPORTE_SISTEMA_CICLOVIARIO |
| `transporte_coletivo:VM_P_TRANSP_SIST_MEDIA_CAP` | PDDU_TRANSPORTE_SISTEMA_MEDIA_CAPACIDADE |
| `transporte_coletivo:VM_P_TRANSP_SIST_METROP_LN_NAU` | PDDU_TRANSPORTE_SISTEMA_METROPOLITANO_LINHA_NAUTICA |
| `transporte_coletivo:VM_P_TRANSP_SIST_METROP_LN_TEM` | PDDU_TRANSPORTE_COLETIVO_LINHA_METROPOLITANA |

### Carnaval e eventos (65)

| typeName | Título |
|---|---|
| `AREA_EVENTOS_CLE:VM_AREA_EVENTOS_CLE` | VM_AREA_EVENTOS_CLE |
| `camadas_publicidade:EMPENA_PONTO` | EMPENA_PONTO |
| `camadas_publicidade:OUTDOOR_PUBLICIDADE` | OUTDOOR_PUBLICIDADE |
| `camadas_publicidade:PAINEL_PUBLICITARIO` | PAINEL_PUBLICITARIO |
| `camadas_publicidade:PAINEL_RELOGIOS_PUBLICIDADE` | PAINEL_RELOGIOS_PUBLICIDADE |
| `camadas_publicidade:VM_OUTDOOR_PUBLICIDADE` | OUTDOOR_PUBLICIDADE |
| `camadas_publicidade:VM_PAINEL_ABRIGO_ONIBUS` | PAINEL_ABRIGO_ONIBUS |
| `camadas_publicidade:VM_PAINEL_RELOGIOS_PUBLICIDADE` | PAINEL_RELOGIOS_PUBLICIDADE |
| `camadas_publicidade:VM_TOPO_PREDIO_PUBLICIDADE` | TOPO_PREDIO_PUBLICIDADE |
| `INCENTIVOS_FISCAIS:VM_INCENTIVOS_FISCAIS` | VM_INCENTIVOS_FISCAIS |
| `LETREIRO:VM_LETREIRO` | VM_LETREIRO |
| `sedur:CARNAVAL_AMBULANTES` | CARNAVAL_AMBULANTES |
| `sedur:CARNAVAL_ARQUIBANCADA` | CARNAVAL_ARQUIBANCADA |
| `sedur:CARNAVAL_BARRACAS` | CARNAVAL_BARRACAS |
| `sedur:CARNAVAL_BARREIRAS` | CARNAVAL_BARREIRAS |
| `sedur:CARNAVAL_BEBIDAS` | CARNAVAL_BEBIDAS |
| `sedur:CARNAVAL_BOMBEIROS` | CARNAVAL_BOMBEIROS |
| `sedur:CARNAVAL_CAMAROTE` | CARNAVAL_CAMAROTE |
| `sedur:CARNAVAL_CENTRAL_CATADORES` | CARNAVAL_CENTRAL_CATADORES |
| `sedur:CARNAVAL_CIRCUITO_BARONDI_CONC` | CARNAVAL_CIRCUITO_BARRAONDINA_CONCENTRACAO |
| `sedur:CARNAVAL_CIRCUITO_BARONDI_ESTA` | CARNAVAL_CIRCUITO_BARRAONDINA_ESTACIONAMENTO |
| `sedur:CARNAVAL_CIRCUITO_BARONDI_FILA` | CARNAVAL_CIRCUITO_BARRAONDINA_FILADETRIOS |
| `sedur:CARNAVAL_CIRCUITO_BARRAONDINA` | CARNAVAL_CIRCUITO_BARRAONDINA |
| `sedur:CARNAVAL_CIRCUITO_BATATINHA` | CARNAVAL_CIRCUITO_BATATINHA |
| `sedur:CARNAVAL_CIRCUITO_CAMPOGRANDE` | CARNAVAL_CIRCUITO_CAMPOGRANDE |
| `sedur:CARNAVAL_CIRCUITO_CGRANDE_CONC` | CARNAVAL_CIRCUITO_CAMPOGRANDE_CONCENTRACAO |
| `sedur:CARNAVAL_CIRCUITO_CGRANDE_ESTA` | CARNAVAL_CIRCUITO_CAMPOGRANDE_ESTACIONAMENTO |
| `sedur:CARNAVAL_CIRCUITO_FILADETRIOS` | CARNAVAL_CIRCUITO_AVENIDA_FILADETRIOS |
| `sedur:CARNAVAL_CIRCUITO_MORADAGARCIA` | CARNAVAL_CIRCUITO_MORADA_DO_GARCIA |
| `sedur:CARNAVAL_COLETA_SELETIVA` | CARNAVAL_COLETA_SELETIVA |
| `sedur:CARNAVAL_COMBATE_TRAB_INFANTIL` | CARNAVAL_COMBATE_TRABALHO_INFANTIL |
| `sedur:CARNAVAL_CONTAINER` | CARNAVAL_CONTAINER |
| `sedur:CARNAVAL_DETRAN` | CARNAVAL_DETRAN |
| `sedur:CARNAVAL_EDIF_DE_REFERENCIA` | CARNAVAL_EDIFICACOES_DE_REFERENCIA |
| `sedur:CARNAVAL_ESTACIONA_REGULA` | CARNAVAL_ESTACIONAMENTO_REGULAMENTADO |
| `sedur:CARNAVAL_ESTACIONA_REGULA_LVIA` | CARNAVAL_ESTACIONAMENTO_REGULAMENTACAO_LONGODAVIA |
| `sedur:CARNAVAL_GELO` | CARNAVAL_GELO |
| `sedur:CARNAVAL_IMPRENSA` | CARNAVAL_IMPRENSA |
| `sedur:CARNAVAL_JUIZADO_DE_MENORES` | CARNAVAL_JUIZADO_DE_MENORES |
| `sedur:CARNAVAL_LICENCIAMENTO` | CARNAVAL_LICENCIAMENTO |
| `sedur:CARNAVAL_LIMPURB_CARRO` | CARNAVAL_LIMPURB_CARRO |
| `sedur:CARNAVAL_LIMPURB_DEPOSITO` | CARNAVAL_LIMPURB_DEPOSITO |
| `sedur:CARNAVAL_OBSERVACAO` | CARNAVAL_OBSERVACAO |
| `sedur:CARNAVAL_POLICIA_CIVIL` | CARNAVAL_POLICIA_CIVIL |
| `sedur:CARNAVAL_POLICIA_MILITAR` | CARNAVAL_POLICIA_MILITAR |
| `sedur:CARNAVAL_PORTOES` | CARNAVAL_PORTOES |
| `sedur:CARNAVAL_POSTO_DE_SAUDE` | CARNAVAL_POSTO_DE_SAUDE |
| `sedur:CARNAVAL_POSTO_ELEVADO_OBS` | CARNAVAL_POSTO_ELEVADO_OBS |
| `sedur:CARNAVAL_ROTA_DE_FUGA` | CARNAVAL_ROTA_DE_FUGA |
| `sedur:CARNAVAL_SAC` | CARNAVAL_SAC |
| `sedur:CARNAVAL_SALTUR` | CARNAVAL_SALTUR |
| `sedur:CARNAVAL_SALVAMAR_SALVA_VIDAS` | CARNAVAL_SALVAMAR_SALVA_VIDAS |
| `sedur:CARNAVAL_SANITARIO` | CARNAVAL_SANITARIO |
| `sedur:CARNAVAL_SANITARIO_AMBULANTE` | CARNAVAL_SANITARIO_AMBULANTE |
| `sedur:CARNAVAL_SEDUR` | CARNAVAL_SEDUR |
| `sedur:CARNAVAL_SEMOP` | CARNAVAL_SEMOP |
| `sedur:CARNAVAL_SUCOP` | CARNAVAL_SUCOP |
| `sedur:CARNAVAL_TAXI` | CARNAVAL_TAXI |
| `sedur:CARNAVAL_TERMINAL_DE_ONIBUS` | CARNAVAL_TERMINAL_DE_ONIBUS |
| `sedur:CARNAVAL_TERMINAL_DE_ONIBUSPRO` | CARNAVAL_TERMINAL_DE_ONIBUS_PROVISORIO |
| `sedur:CARNAVAL_TRANSALVADOR` | CARNAVAL_TRANSALVADOR |
| `sedur:CARNAVAL_VIGILANCIA_SANITARIA` | CARNAVAL_VIGILANCIA_SANITARIA |
| `sedur:CARNAVAL_ZONA_DE_INFLUENCIA` | CARNAVAL_ZONA_DE_INFLUENCIA |
| `sedur:CARNAVAL_ZONA_DE_SILENCIO` | CARNAVAL_ZONA_DE_SILENCIO |
| `sedur:CARNAVAL_ZONA_PROIBIDA` | CARNAVAL_ZONA_PROIBIDA |

### Infraero / aeródromo (28)

| typeName | Título |
|---|---|
| `aerodromo_ilha_dos_frades:VM_INFRAER_AERODRO_ILHA_FRADE` | INFRAERO_AERODROMO_ILHA_DOS_FRADES |
| `aerodromo_portaria_812_ica_2019:VM_INFRAERO_AERODROMO_PBZPA` | INFRAERO_AERODROMO_PBZPA |
| `aerodromo_portaria_812_ica_2019:VM_INFRAERO_AERODROMO_PBZPA_TI` | INFRAERO_AERODROMO_PBZPA_TI |
| `aerodromo_portaria_812_ica_2019:VM_INFRAERO_AERODROMO_PZPANA` | INFRAERO_AERODROMO_PZPANA |
| `heliponto:VM_HELIPONTO` | HELIPONTO |
| `infraero:VM_INFRAERO_APROXIMACAO` | INFRAERO_APROXIMACAO |
| `infraero:VM_INFRAERO_CONICA` | INFRAERO_CONICA |
| `infraero:VM_INFRAERO_DECOLAGEM` | INFRAERO_DECOLAGEM |
| `infraero:VM_INFRAERO_FAIXA_PISTA` | INFRAERO_FAIXA_PISTA |
| `infraero:VM_INFRAERO_HORIZONTAL_EXTERNA` | INFRAERO_HORIZONTAL_EXTERNA |
| `infraero:VM_INFRAERO_HORIZONTAL_INTERNA` | INFRAERO_HORIZONTAL_INTERNA |
| `infraero:VM_INFRAERO_PISTA` | INFRAERO_PISTA |
| `infraero:VM_INFRAERO_TRANSICAO` | INFRAERO_TRANSICAO |
| `infraero:VM_INFRAERO_TRANSICAO_INTERNA` | INFRAERO_TRANSICAO_INTERNA |
| `infraero:VM_INFRAERO_VISUAL_AERO` | INFRAERO_VISUAL_AERO |
| `plano_zoneamento_ruido_aerodromo:VM_PLANO_ZONEAMENTO_RUIDO_AERODROMO` | VM_PLANO_ZONEAMENTO_RUIDO_AERODROMO |
| `sedur:HELIPONTO` | HELIPONTO |
| `sedur:INFRAERO_APROXIMACAO` | INFRAERO_APROXIMACAO |
| `sedur:INFRAERO_CONICA` | INFRAERO_CONICA |
| `sedur:INFRAERO_DECOLAGEM` | INFRAERO_DECOLAGEM |
| `sedur:INFRAERO_FAIXA_PISTA` | INFRAERO_FAIXA_PISTA |
| `sedur:INFRAERO_HORIZONTAL_EXTERNA` | INFRAERO_HORIZONTAL_EXTERNA |
| `sedur:INFRAERO_HORIZONTAL_INTERNA` | INFRAERO_HORIZONTAL_INTERNA |
| `sedur:INFRAERO_PISTA` | INFRAERO_PISTA |
| `sedur:INFRAERO_TEXTO` | INFRAERO_TEXTO |
| `sedur:INFRAERO_TRANSICAO` | INFRAERO_TRANSICAO |
| `sedur:INFRAERO_TRANSICAO_INTERNA` | INFRAERO_TRANSICAO_INTERNA |
| `sedur:INFRAERO_VISUAL_AERO` | INFRAERO_VISUAL_AERO |

### Demais camadas (122)

| typeName | Título |
|---|---|
| `analise_aop:ANALISE_AOP` | ANALISE_AOP |
| `analise_gcat:VM_ANALISE_GCAT` | ANALISE_GCAT |
| `apcp_zoneamento:VM_APCP_LORETO` | APCP_LORETO |
| `apcp_zoneamento:VM_APCP_LORETO_SINAL` | APCP_LORETO_SINAL |
| `apcp_zoneamento:VM_APCP_N_SRA_GUADALUPE` | APCP_N_SRA_GUADALUPE |
| `apcp_zoneamento:VM_APCP_N_SRA_GUADALUPE_SINAL` | APCP_N_SRA_GUADALUPE_SINAL |
| `apcp_zoneamento_guadalupe:VM_APCP_N_SRA_GUADALUPE` | APCP_NOSSA_SENHORA_GUADALUPE |
| `apcp_zoneamento_guadalupe_sinal:VM_APCP_N_SRA_GUADALUPE_SINAL` | APCP_NOSSA_SENHORA_GUADALUPE_SINAL |
| `apcp_zoneamento_loreto:VM_APCP_LORETO` | APCP_ZONEAMENTO_LORETO |
| `apcp_zoneamento_loreto_sinal:VM_APCP_LORETO_SINAL` | APCP_ZONEAMENTO_LORETO_SINALIZACAO |
| `aprn_cidade_jardim:VM_APRN_ZONEAMENTO_CIDADE_JAR` | APRN_ZONEAMENTO_CIDADE_JARDIM |
| `aprn_jaguaribe:VM_APRN_ZONEAMENTO_JAGUARIBE` | APRN_ZONEAMENTO_JAGUARIBE |
| `aprn_pituacu:VM_APRN_ZONEAMENTO_PITUACU` | APRN_ZONEAMENTO_PITUACU |
| `aprn_zoneamento:VM_APRN_ZONEAMENTO_CIDADE_JAR` | APRN_ZONEAMENTO_CIDADE_JAR |
| `aprn_zoneamento:VM_APRN_ZONEAMENTO_JAGUARIBE` | APRN_ZONEAMENTO_JAGUARIBE |
| `aprn_zoneamento:VM_APRN_ZONEAMENTO_PITUACU` | APRN_ZONEAMENTO_PITUACU |
| `area_institucionais_municipais:VM_AREA_ESPECIAL_MUNICIPAL` | AREA_ESPECIAL_INSTITUCIONAL_MUNICIPAL |
| `area_sob_analise_cnlu:VM_AREA_SOB_ANALISE_CNLU` | AREA_SOB_ANALISE_CNLU |
| `areas_chesf:VM_CHESF` | AREAS_DA_CHESF |
| `areas_chesf:VM_FAIXA_SERVIDAO_LINHA_CHESF` | FAIXA_SERVIDAO_LINHA_CHESF |
| `aterro_sanitario_limpurb:ATERRO_SANITARIO_LIMPURB` | ATERRO_SANITARIO_LIMPURB |
| `bacias_hidrograficas_drenagem:VM_BACIA_HIDROGRAFIA_DRENAGEM` | BACIA_HIDROGRAFIA_DRENAGEM |
| `borda_maritima:VM_BORDA_MARITIMA_POLIGONAL` | BORDA_MARITIMA_POLIGONAL |
| `borda_maritima:VM_BORDA_MARITIMA_TRECHOS` | BORDA_MARITIMA_TRECHOS |
| `borda_maritima_poligonal_cnlu:VM_BORDA_MARITIMA_POLIGONAL` | BORDA_MARITIMA_POLIGONAL_CNLU |
| `borda_maritima_trecho_cnlu:VM_BORDA_MARITIMA_TRECHOS` | BORDA_MARITIMA_TRECHOS_CNLU |
| `decreto_encampacao:VM_DEC_ENCAMPACAO` | DECRETO_DE_ENCAMPACAO |
| `decreto_municipal_revogado:VM_DEC_MUNICIPAL_REVOGADO` | DECRETO_MUNICIPAL_REVOGADO |
| `decretos:VM_DEC_ENCAMPACAO` | DECRETO_ENCAMPACAO |
| `decretos:VM_DEC_ESTADUAL_DESAPROPRIACAO` | DECRETO_ESTADUAL_DESAPROPRIACAO |
| `decretos:VM_DEC_MUNICIPAL_DESAPROPRIA` | DECRETO_MUNICIPAL_DESAPROPRIA |
| `decretos:VM_DEC_MUNICIPAL_REVOGADO` | DECRETO_MUNICIPAL_REVOGADO |
| `delimitacoes:VM_BACIA_HIDROGRAFIA_DRENAGEM` | BACIA_HIDROGRAFIA_E_DRENAGEM |
| `delimitacoes:VM_LEI_BAIRRO` | LIMITE_BAIRRO |
| `delimitacoes:VM_LIMITE_SALVADOR` | LIMITE_MUNICIPIOS_SALVADOR |
| `documento_referencia:DOCUMENTO_GEOREFERENCIA` | DOCUMENTO_GEOREFERENCIA |
| `gasoduto_bahia_gas:VM_GASODUTO_BAHIA_GAS` | GASODUTO_BAHIA_GAS |
| `hidrografia:VM_HIDROGRAFIA_ABAETE_PITUACU` | HIDROGRAFIA_ABAETE_PITUACU |
| `hidrografia:VM_HIDROGRAFIA_APP_LAGOA30M` | HIDROGRAFIA_AREA_DE_PROTECAO_PERMANENTE_LAGOA30M |
| `hidrografia:VM_HIDROGRAFIA_APP_LAGOS30M` | HIDROGRAFIA_AREA_DE_PROTECAO_PERMANENTE_LAGOS30M |
| `hidrografia:VM_HIDROGRAFIA_APP_RIO30M` | HIDROGRAFIA_AREA_DE_PROTECAO_PERMANENTE_RIO30M |
| `hidrografia:VM_HIDROGRAFIA_DIQUE` | HIDROGRAFIA_DIQUE |
| `hidrografia:VM_HIDROGRAFIA_LAGOS` | HIDROGRAFIA_LAGOS |
| `hidrografia:VM_HIDROGRAFIA_PORTO` | HIDROGRAFIA_PORTO |
| `hidrografia:VM_HIDROGRAFIA_REPRESAS` | HIDROGRAFIA_REPRESAS |
| `hidrografia:VM_HIDROGRAFIA_RIOS` | HIDROGRAFIA_RIOS |
| `hidrografia_buffer_lagoas_30m:VM_HIDROGRAFIA_APP_LAGOA30M` | HIDROGRAFIA_APP_LAGOAS_30_METROS |
| `hidrografia_buffer_lagos_30m:VM_HIDROGRAFIA_APP_LAGOS30M` | HIDROGRAFIA_APP_LAGOS_30_METROS |
| `hidrografia_dique_do_tororo:VM_HIDROGRAFIA_DIQUE` | HIDROGRAFIA_DIQUE |
| `hidrografia_lagoas:VM_HIDROGRAFIA_ABAETE_PITUACU` | HIDROGRAFIA_ABAETE_PITUACU |
| `hidrografia_lagos:VM_HIDROGRAFIA_LAGOS` | HIDROGRAFIA_LAGOS |
| `hidrografia_porto:VM_HIDROGRAFIA_PORTO` | HIDROGRAFIA_PORTO |
| `hidrografia_represas:VM_HIDROGRAFIA_REPRESAS` | HIDROGRAFIA_REPRESAS |
| `hidrografia_rios:VM_HIDROGRAFIA_RIOS` | HIDROGRAFIA_RIOS |
| `hidrografia_rios_buffer_30m:VM_HIDROGRAFIA_APP_RIO30M` | HIDROGRAFIA_APP_RIO_30_METROS |
| `intervencao_sistema_viario:VM_INTERVENCAO_SISTEMA_VIARIO` | VM_INTERVENCAO_SISTEMA_VIARIO |
| `intervencao_viaria_ponte_ssa_itaparica:VM_INTERVENCAO_VIARIA_PT_SSA_ILHA` | INTERVENCAO_VIARIA_PT_SSA_ILHA |
| `intervencao_viaria_projeto_brt:VM_INTERVENCAO_VIARIA_PROJETO_BRT` | INTERVENCAO_VIARIA_PROJETO_BRT |
| `intervencao_viaria_projeto_metro:VM_INTERVENCAO_VIARIA_METRO` | INTERVENCAO_VIARIA_METRO |
| `intervencao_viaria_projeto_vlt:VM_INTERVENCAO_VIARIA_PROJETO_VLT` | INTERVENCAO_VIARIA_PROJETO_VLT |
| `licenca_obra:VM_LICENCA_DE_OBRAS` | VM_LICENCA_DE_OBRAS |
| `licenca_obra_com_inscricao:VM_LICENCA_OBRAS_COM_INSCRICAO` | LICENCA_OBRAS_COM_INSCRICAO |
| `operacoes_urbanas:VM_P_OPER_URB_CONSORCIADAS` | PDDU_OPERACOES_URBANAS_CONSORCIADAS |
| `parque_de_pituacu:PARQUE_PITUACU` | VM_PARQUE_PITUACU |
| `parque_de_pituacu:VM_PARQUE_PITUACU` | PARQUE_PITUACU |
| `parque_natural:VM_PARQUE_NATURAL` | PARQUE_NATURAL |
| `parque_urbano:VM_PARQUE_URBANO` | PARQUE_URBANO |
| `plano_funcional_ac_norte_viana:VM_FUNCIONAL_ACNORTE_LUISVIANA` | PLANO_FUNCIONAL_ACESSO_NORTE_LUIS_VIANA |
| `plano_funcional_afranio_peixoto:VM_FUNCIONAL_AFRANIO_PEIXOTO` | PLANO_FUNCIONAL_AFRANIO_PEIXOTO |
| `plano_funcional_avenida_anita_garibaldi:VM_FUNCIONAL_ANITA_GARIBALDI` | PLANO_FUNCIONAL_AVENIDA_ANITA_GARIBALDI |
| `plano_funcional_avenida_jorge_amado:VM_FUNCIONAL_JORGE_AMADO` | PLANO_FUNCIONAL_AVENIDA_JORGE_AMADO |
| `plano_funcional_avenida_juracy_magalhaes:VM_FUNCIONAL_JURACY_MAGALHAES` | PLANO_FUNCIONAL_AVENIDA_JURACY_MAGALHAES |
| `plano_funcional_avenida_luis_viana:VM_FUNCIONAL_LUIS_VIANA` | PLANO_FUNCIONAL_AVENIDA_LUIS_VIANA |
| `plano_funcional_avenida_orlando_gomes:VM_FUNCIONAL_ORLANDO_GOMES` | PLANO_FUNCIONAL_AVENIDA_ORLANDO_GOMES |
| `plano_funcional_avenida_pinto_aguiar:VM_FUNCIONAL_PINTO_AGUIAR` | PLANO_FUNCIONAL_AVENIDA_PINTO_AGUIAR |
| `poligonais_reducao_iptu:VM_POLIGONAIS_REDUCAO_IPTU` | POLIGONAIS_REDUCAO_IPTU |
| `projeto_requalificacao_orla:VM_PROJETO_REQUALIFICACAO_ORLA` | VM_PROJETO_REQUALIFICACAO_ORLA |
| `rede_esgoto_cadastro:VM_REDE_ESGOTO_CADASTRO` | REDE_ESGOTO_CADASTRO |
| `renova_centro:VM_POLIGONAL_RENOVA_CENTRO` | VM_POLIGONAL_RENOVA_CENTRO |
| `sedur:AREA_EXPROPRIADA` | AREA_EXPROPRIADA |
| `sedur:AREA_REFUGIO` | AREA_REFUGIO |
| `sedur:CEMITERIO` | CEMITERIO |
| `sedur:CHESF` | CHESF |
| `sedur:DEC_ESTADUAL_DESAPROPRIACAO` | DECRETO_ESTADUAL_DESAPROPRIACAO |
| `sedur:DEC_MUNICIPAL_DESAPROPRIACAO` | DEC_MUNICIPAL_DESAPROPRIACAO |
| `sedur:DEC_MUNICIPAL_REVOGADO` | DECRETO_MUNICIPAL_REVOGADO |
| `sedur:DESAFETACAO_MUNICIPAL` | DESAFETACAO_MUNICIPAL |
| `sedur:FUNCIONAL_ANITA_GARIBALDI` | FUNCIONAL_ANITA_GARIBALDI |
| `sedur:FUNCIONAL_JORGE_AMADO` | FUNCIONAL_JORGE_AMADO |
| `sedur:FUNCIONAL_JURACY_MAGALHAES` | FUNCIONAL_JURACY_MAGALHAES |
| `sedur:FUNCIONAL_LUIS_VIANA` | FUNCIONAL_LUIS_VIANA |
| `sedur:FUNCIONAL_ORLANDO_GOMES` | FUNCIONAL_ORLANDO_GOMES |
| `sedur:FUNCIONAL_PINTO_AGUIAR` | FUNCIONAL_PINTO_AGUIAR |
| `sedur:HIDROGRAFIA_ABAETE_PITUACU` | HIDROGRAFIA_ABAETE_PITUACU |
| `sedur:HIDROGRAFIA_DIQUE` | HIDROGRAFIA_DIQUE |
| `sedur:HIDROGRAFIA_LAGOS` | HIDROGRAFIA_LAGOS |
| `sedur:HIDROGRAFIA_OCEANO` | HIDROGRAFIA_OCEANO |
| `sedur:HIDROGRAFIA_PORTO` | HIDROGRAFIA_PORTO |
| `sedur:HIDROGRAFIA_REPRESAS` | HIDROGRAFIA_REPRESAS |
| `sedur:HIDROGRAFIA_RIOS` | HIDROGRAFIA_RIOS |
| `sedur:L_Z_AMB_REFERENCIAS_GNL` | LOUOS_ZONAS_AMBIENTAIS_REFERENCIAS_GNL |
| `sedur:L_Z_AMB_REFERENCIAS_ILHAS` | LOUOS_ZONAS_AMBIENTAIS_REFERENCIAS_ILHAS |
| `sedur:L_Z_AMB_ZEE_BOM_JESUS_ILHOTA` | LOUOS_ZONAS_AMBIENTAIS_ZEE_ILHA_BOM_JESUS_ILHOTAS |
| `sedur:L_Z_AMB_ZEE_BOM_JESUS_PIER` | LOUOS_ZONAS_AMBIENTAIS_ZEE_ILHA_BOM_JESUS_ILHOTAS_PIER |
| `sedur:L_Z_AMB_ZEE_FRADES` | LOUOS_ZONAS_AMBIENTAIS_ZEE_ILHA_DOS_FRADES |
| `sedur:L_Z_AMB_ZEE_FRADES_PIER` | LOUOS_ZONAS_AMBIENTAIS_ZEE_ILHA_BOM_JESUS_ILHOTAS_PIER |
| `sedur:L_Z_AMB_ZEE_FRADES_SINAL_NAUT` | LOUOS_ZONAS_AMBIENTAIS_ZEE_ILHA_DOS_FRADES_SINALIZACAO_NAUTICA |
| `sedur:L_Z_AMB_ZEE_JOANE_IPITANGA` | LOUOS_ZONAS_AMBIENTAIS_ZEE_JOANE_IPITANGA |
| `sedur:L_Z_AMB_ZEE_LAGOA_DUNA_ABAETE` | LOUOS_ZONAS_AMBIENTAIS_ZEE_LAGOAS_DUNAS_DO_ABAETE |
| `sedur:LIMITE_PREAMAR` | LIMITE_PREAMAR |
| `sedur:LOGRADOURO_LOCALIZACAO_PROC` | LOGRADOURO_LOCALIZACAO_PROC |
| `sedur:LOGRADOURO_PLACA` | LOGRADOURO_PLACA |
| `sedur:PARQUE_PITUACU` | PARQUE_PITUACU |
| `sedur:SUB_BACIA_MANE_DENDE` | SUB_BACIA_MANE_DENDE |
| `sedur:TOPOGRAFIA_LINHA` | TOPOGRAFIA_LINHA |
| `sedur:UFBA` | UFBA |
| `sedur:UNEB` | UNEB |
| `sub_bacia_mane_dende:VM_SUB_BACIA_MANE_DENDE` | SUB_BACIA_MANE_DENDE |
| `terminal_rodoviario:VM_TERMINAL_RODOVIARIO` | TERMINAL_RODOVIARIO |
| `terminal_rodoviario:VM_TERMINAL_RODOVIARIO_VIARIO` | TERMINAL_RODOVIARIO_VIARIO |
| `universidade_ufba:VM_UFBA` | UNIVERSIDADE_UFBA |
| `universidade_uneb:VM_UNEB` | UNIVERSIDADE_UNEB |

---

## 10. Como atualizar este inventário

```bash
curl -sS "https://geoserver.sedur.salvador.ba.gov.br/geoserver/wfs?service=WFS&version=2.0.0&request=GetCapabilities"
```

Comparar `updateSequence` (hoje 15525). Se mudar, regenerar a seção 9 a partir do `FeatureTypeList`.

Não versionar o XML bruto no git — é ~470 KB (WFS) + ~1,2 MB (WMS).


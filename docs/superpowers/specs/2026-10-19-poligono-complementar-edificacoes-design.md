# Polígono complementar por edificações (Overture, Microsoft e IA) — Design

Data: 2026-10-19

## Problema

A zona do processo sai pela maior porção do lote (art. 18, § 2º, Lei
9.148/2016). O lote vem de fontes oficiais: o endereçamento SEDUR
(`enderecamento:VM_ENDERECAMENTO`, só endereço com alvará) e o SEMAP por
inscrição, que exige token desde 14/10/2026. Quando nenhuma das duas acha o
lote, a zona sai pelo ponto e as regras por área ficam sem polígono.

O usuário decidiu (19/10/2026) usar polígonos de **edificação** de bases
complementares como substitutos do lote nesse caso, sem desenho manual:
Overture Maps Buildings, Microsoft Global ML Building Footprints e, por
último, extração por IA sobre a ortofoto da Prefeitura.

Edificação não é lote. O polígono de um prédio é menor que o terreno e pode
dar outra zona num lote de divisa. O usuário conhece e aceitou esse risco.
Este documento registra a decisão e não a reabre.

## Decisões do usuário

1. **Ordem das fontes do polígono:**
   1. endereçamento SEDUR por CODLOG + número (existe);
   2. SEMAP por inscrição (existe);
   3. **Overture**;
   4. **Microsoft**;
   5. **IA** sobre ortofoto;
   6. endereçamento SEDUR pelo ponto (existe, ~30 s, passa a ser o último);
   7. nenhuma fonte → segue sem polígono, como hoje. Sem ponto → análise sem
      ponto e sem polígono, como hoje.
2. **Uso no motor.** O polígono complementar entra na maior porção igual ao
   lote SEDUR. Se tocar mais de uma zona, vale a maior porção. Empate continua
   indo para análise, como já é hoje.
3. **Exibição.** No mapa, a legenda nomeia a fonte, no mesmo formato da atual,
   sem a palavra "estimado":
   - "Contorno tracejado: polígono do endereçamento SEDUR." (chave e ponto)
   - "Contorno tracejado: polígono do Overture."
   - "Contorno tracejado: polígono da Microsoft."
   - "Contorno tracejado: polígono gerado por IA."
4. **Ponto do imóvel:** coordenada da inscrição → centro do lote do
   endereçamento → Nominatim (a cadeia de ponto atual).
5. **Escolha do prédio:** o prédio que contém o ponto. Se mais de um contém o
   ponto (polígonos sobrepostos), vale o de **menor área**.
6. **Microsoft mesmo duplicando a Overture.** A Overture já conflaciona os
   prédios da Microsoft e do Google, mas a fonte Microsoft é mantida como
   passo próprio, com deduplicação.
7. **Imagem da IA:** ortofoto da Prefeitura (ImageServer GeoSalvador 2024,
   ~7 cm, com WMS 2016/2017 de 10 cm como reserva). OpenStreetMap e Leaflet não
   são imagem.
8. **O primeiro polígono gravado fica.** Se o processo recebeu um polígono
   complementar e depois aparece um lote oficial (reprocesso, chave, token do
   SEMAP), o complementar é mantido e o oficial é ignorado. A regra atual de
   "nunca sobrescrever" vale para todas as fontes.
9. **O texto da decisão não muda.** A zona continua com
   `zona_fonte = enderecamento_maior_porcao` e o rótulo "Maior porção do
   lote", qualquer que seja a fonte do polígono. A fonte real aparece só no
   mapa (legenda) e na procedência.

## Decisões minhas (o usuário pode reverter)

- **Ponto fora de qualquer prédio = não encontrado naquela fonte.** Não há
  busca por "prédio mais próximo". Passa para a próxima fonte. Motivo: o pedido
  original proíbe selecionar construção vizinha, e coordenada de cadastro ou
  do Nominatim fora do prédio costuma estar em quintal, calçada ou rua.
- **Nominatim só serve para escolher prédio quando casou o número da casa**
  (`address.house_number` presente na resposta). Ponto só da via pula os
  passos 3–5 e vai direto ao 6.
- **O polígono complementar ocupa o mesmo campo do lote**
  (`address_polygon_geojson`) com `address_polygon_source` = `overture`,
  `microsoft` ou `ia`. Assim, maior porção, centro do lote, divergência com o
  desenho e mapa funcionam sem mudança.
- **A legenda passa a ser pela fonte.** Hoje ela diz "endereçamento SEDUR"
  mesmo quando o lote veio do SEMAP. Com a fonte enviada à tela, o SEMAP ganha
  a legenda "Contorno tracejado: polígono do cadastro municipal (SEMAP).".
- **Overture e Microsoft são consultadas na base local, dentro do job da
  decisão.** São consultas de milissegundos. A IA roda em job próprio, e o job
  da decisão espera por ela com prazo.
- **Polígono gerado por IA é guardado e reaproveitado** por qualquer pedido
  futuro cujo ponto caia dentro dele.
- **REGIN usa a mesma cadeia.** `ReginProcessoProtocolador` (hoje `aplicar(...,
  consultarPorPonto: true)` seguido de `decide()` síncrono) passa a chamar o
  mesmo método de resolução por ponto que o job, na ordem decidida (3–6).
  Como ali não há `release()`, quando a IA for necessária o REGIN despacha
  `DecidirFluxoExpressoJob` em vez de decidir na hora. O protocolo síncrono
  do portal continua só com chave e SEMAP.
- **Onde fica o código fora do PHP:** o serviço de inferência fica em
  `docker/footprint-ia/` (FastAPI + segment-geospatial + SAM 2, com
  Dockerfile) e o script de extração da Overture em `scripts/`. As duas pastas
  já existem. O Laravel é testado contra um fake do contrato HTTP (seção 5). O
  serviço Python tem testes próprios mínimos do contrato.
- **Precisão do Nominatim fica gravada.** Nova coluna
  `property_location_precision` (`numero` | `via`), preenchida a partir de
  `address.house_number`. Processos antigos geocodificados sem essa
  informação ficam inelegíveis para os passos 3–5.
- **Reprocesso:** a cadeia complementar dispara pelas regras atuais de
  `podeReavaliar` (protocolada, ou em análise sem analista e sem decisão),
  independentemente do toggle do endereçamento. O orçamento de tentativas do
  job é separado entre a espera do Cadastro e a espera da IA.
- **Confiança:** grava-se a da Microsoft (`confidence` 0–1, quando ≠ −1) e a
  que vier em `sources[].confidence` da Overture. Na IA o campo fica vazio:
  nenhum modelo pesquisado fornece confiança calibrada, e o pedido proíbe
  inventar métrica. O score interno do modelo fica só em `propriedades`, como
  diagnóstico.

## Fora de escopo

- Botão "Mapa / Foto aérea" na ficha.
- Importação local da camada de endereçamento para acelerar o passo 6.
- Correção do centroide de MultiPolygon em `ViabilityRequest::centroideDoPoligono`
  (há um card de sessão separado). **Atenção:** este design depende dela,
  porque polígonos da Overture podem ser MultiPolygon. Se a correção não tiver
  entrado antes, ela entra no plano deste design como pré-requisito.
- Área construída total, pavimentos e altura (não são usados pelo motor).

## Pesquisa que embasa (19/10/2026)

- **Overture:**
  - Releases mensais. A última é `2026-09-23.1`. Licença ODbL, com atribuição
    "© OpenStreetMap contributors, Overture Maps Foundation".
  - Acesso por bbox com o CLI `overturemaps download --bbox=... --type=building
    -f geojsonseq` ou com DuckDB sobre
    `s3://overturemaps-us-west-2/release/<rel>/theme=buildings/type=building/*`.
  - Campos: `id` (GERS), `sources[]` (`dataset`, `record_id`, `update_time`,
    `confidence`), `height`, `num_floors`, `geometry` (Polygon/MultiPolygon).
  - O GERS id se mantém enquanto o desenho tiver IoU > 0,5 com a versão
    anterior. Não há mapa de id antigo para novo.
- **Microsoft:**
  - `dataset-links.csv` por país + quadkey nível 9, em GeoJSON linha a linha
    (`.csv.gz`). Último refresh em 13/08/2026.
  - `confidence` e `height` valem −1 quando ausentes.
  - O README atual cita licença CDLA Permissive 2.0, mas a Overture cita ODbL.
    A divergência fica registrada no documento de operação.
- **Ortofoto:** os dois serviços responderam sem token em 19/10/2026 (HTTP 200
  com imagem real).
  - A 2024 tem copyright e termos em branco.
  - A 2016/2017 permite uso não comercial, citando a Prefeitura.
  - Pendente: confirmação por escrito da Cartografia
    (cartografia@salvador.ba.gov.br) para o uso automatizado da 2024.
  - O host `geo.salvador.ba.gov.br` é o mesmo do SEMAP, que passou a exigir
    token.
- **Modelos:** segment-geospatial (MIT) com SAM 2 (Apache 2.0), com prompt por
  ponto e saída vetorial. Roda em CPU para dezenas de recortes por dia, e
  melhor em GPU. Google, Bing, Esri e Mapbox têm termos que proíbem extrair
  contorno das imagens deles e ficam fora.
- **Cobertura em Salvador:** não há número publicado. Ela será medida na
  primeira importação (ver Métricas).

## Desenho

### 1. Limite do município

Importar `limite_salvador:VM_LIMITE_SALVADOR` do GeoServer SEDUR como nova
camada `GeoLayerType::LimiteMunicipio`, pelo `geo:importar-wfs` existente. A
importação de edificações só aceita feições cujo ponto interior
(`ST_PointOnSurface`) esteja dentro do limite vigente. Sem limite importado, a
importação recusa e explica o motivo.

### 2. Base local de edificações

Tabela `building_footprints`, só em pgsql (como `property_polygon`):

| Coluna | Conteúdo |
|---|---|
| `source` | `overture` \| `microsoft` \| `ia` |
| `source_id` | GERS id; na Microsoft, hash estável da geometria normalizada + quadkey; na IA, uuid |
| `release` | release da Overture, data do refresh da Microsoft, ou `modelo@versão+ortofoto` na IA |
| `geometry` | `geometry(MultiPolygon,4326)` normalizada com `ST_Multi(ST_MakeValid(...))`, índice GiST |
| `area_m2` | `ST_Area(geometry::geography)` |
| `confidence` | decimal nulo (regra em "Decisões minhas") |
| `source_datasets` | jsonb, o `sources[]` da Overture |
| `properties` | jsonb (height, num_floors, class, score do modelo etc.) |
| `duplicate_of_id` | FK para o prédio Overture equivalente (só Microsoft) |
| `import_id` | FK para a importação |
| `retired_at` | preenchido quando a feição some numa release nova |
| `validated_at`, `validation_notes` | resultado da validação topológica |

Unicidade em (`source`, `source_id`). A consulta usa só as linhas com
`retired_at` nulo e `duplicate_of_id` nulo.

**Validação de geometria na entrada:**
- aplicar `ST_MakeValid`;
- descartar o que não virar polígono;
- descartar área < 10 m² ou > 20.000 m² (configurável);
- registrar o que foi corrigido em `validation_notes`.

### 3. Importação e atualização

Comandos Artisan idempotentes, cada um registrando uma linha em
`building_footprint_imports` com fonte, release, checksum do arquivo, status,
contadores (lidos, fora do limite, inválidos, corrigidos, duplicados,
inseridos, atualizados, aposentados), início, fim e erro.

- **`geo:edificacoes:importar-overture {arquivo} --release=`**
  - Lê GeoJSONSeq em streaming.
  - O arquivo é gerado fora do PHP por um script versionado no repositório,
    que chama o CLI `overturemaps` ou o DuckDB com o bbox do limite.
  - Upsert por GERS id. Ids ausentes na release nova recebem `retired_at`.
- **`geo:edificacoes:importar-microsoft`**
  - Baixa `dataset-links.csv`, filtra Brasil e os quadkeys que cruzam o bbox
    do limite, e baixa e lê os `.csv.gz` em streaming.
  - Marca `duplicate_of_id` quando o IoU com um prédio Overture ativo for
    > 0,5.
  - Também pode receber um arquivo local, para ambiente sem saída à internet.
- **Mesma release + mesmo checksum = no-op.** Falha no meio desfaz a
  transação da release, e a base anterior continua valendo.
- **Agendamento:** Overture mensal e Microsoft trimestral, com
  `withoutOverlapping()->onOneServer()`. As duas ficam atrás de toggle
  (desligadas por padrão).

O download nunca acontece durante um pedido.

### 4. Resolução no processo

Novo `PoligonoComplementarService`, chamado no `DecidirFluxoExpressoJob`
antes do `aplicarPorPonto` atual. Pré-condições: o processo não tem polígono,
tem ponto, e o ponto é elegível (Nominatim só com número).

Para cada fonte, na ordem Overture → Microsoft → IA, se o toggle dela estiver
ligado:

1. **Overture/Microsoft:** prédios ativos da fonte com `ST_Covers(geometry,
   ponto)`.
   - Nenhum → próxima fonte.
   - Um ou mais → o de menor `area_m2`. Método `contem_ponto` ou
     `menor_area_entre_sobrepostos`.
2. **IA, primeiro a base:** procurar um polígono `ia` já gerado que contenha o
   ponto. Se houver, reaproveitar.
3. **IA, geração:** sem polígono reaproveitável, despachar
   `GerarPoligonoIaJob` e devolver o job da decisão à fila (`release`), no
   mesmo padrão de `aguardarCadastroImobiliario`.
   - O prazo é configurável (padrão: 3 tentativas, backoff 30/60/120 s).
   - Esgotado o prazo, segue sem IA.

Encontrado o polígono, ele é gravado em `address_polygon_geojson` com
`address_polygon_source` = fonte e `address_polygon_consulted_at`. Não
encontrado, o fluxo segue para o passo 6 (endereçamento pelo ponto) e, sem
resultado, decide como hoje.

A resolução nunca sobrescreve polígono já gravado e nunca apaga polígono. A
trava por processo segue o padrão `poligono-ponto:{id}`.

### 5. IA

O Laravel não roda o modelo. `GerarPoligonoIaJob` (fila própria, timeout
configurável):

1. Pede o recorte da ortofoto em volta do ponto: ~60 × 60 m via
   `exportImage` do ImageServer 2024, com reserva no WMS 2016/2017. Guarda o
   recorte em `storage` pelo hash de ponto + serviço + ano, para não pedir de
   novo.
2. Envia o recorte e o ponto (em pixel) ao **serviço de inferência**, um
   contêiner Python à parte (segment-geospatial + SAM 2) com URL configurável.
   Contrato: `POST /segmentar`, multipart com `imagem` (PNG) e
   `x`/`y`/`largura`/`altura` em pixel. Resposta JSON
   `{ "status": "ok"|"sem_objeto", "anel": [[px,py],...], "score": float|null,
   "modelo": "sam2-...@versão" }`. O anel vem em pixel, e o Laravel faz o
   georreferenciamento.
3. Recebe a máscara vetorizada e georreferencia com a transformação do
   recorte.
4. Aceita só se **todos** os critérios valerem (todos configuráveis):
   - o polígono contém o ponto;
   - a área está entre 20 e 5.000 m²;
   - a compacidade (4πA/P²) é ≥ 0,2;
   - o polígono não encosta na borda do recorte.
5. Aceito: grava em `building_footprints` (source `ia`) e no processo.
   Recusado ou com erro: registra a resolução como `nao_resolvido` com o
   motivo (sem imagem, imagem indisponível, máscara recusada, serviço fora).
   **Nunca grava polígono recusado.**

O serviço de inferência é entregue como código + Dockerfile + instruções.
Subir o contêiner no OKD é etapa de infraestrutura.

### 6. Procedência e histórico

Tabela `lot_polygon_resolutions`, uma linha por tentativa e por fonte:

| Coluna | Conteúdo |
|---|---|
| `viability_request_id` | processo |
| `source` | fonte (`PoligonoFonte`) |
| `building_footprint_id` | prédio usado, quando houver |
| `source_id` | id original da fonte |
| `release` | versão da base ou do modelo |
| `status` | encontrado \| nao_encontrado \| ambiguo \| indisponivel \| pendente \| nao_resolvido |
| `method` | método de associação |
| `official` | true para SEDUR e SEMAP; false para as complementares |
| `area_m2` | área do polígono |
| `confidence` | confiança, quando a fonte fornecer |
| `point_lat`, `point_lng`, `point_origin` | ponto usado e sua origem |
| `reason` | motivo |
| `latency_ms` | tempo da tentativa |
| `created_at` | quando |

Ela convive com a auditoria `geo/enderecamento-poligono`, que continua
existindo, e alimenta as métricas.

`PoligonoFonte` ganha `Overture`, `Microsoft` e `Ia`, com rótulo e um
`oficial(): bool`.

### 7. Interface

- `ProcessoController` (`geo`) e `AnalysisRecordController::localizacaoDoImovel`
  passam a enviar `poligono_fonte` (valor + legenda).
- `processos/show.tsx:714` e `ficha-analise/show.tsx:1693` trocam o texto fixo
  pela legenda recebida.
- O estilo do contorno é o mesmo. O rodapé do mapa ganha a atribuição exigida
  pela fonte quando ela não for oficial: "© OpenStreetMap contributors,
  Overture Maps Foundation" para a Overture; "Microsoft" e "Prefeitura
  Municipal de Salvador" para IA/ortofoto.
- A ficha mostra, junto do mapa, a procedência (fonte, id original, data e
  área). Não mostra a palavra "estimado".

### 8. Configuração

Na tela de Configuração de APIs, uma aba "Edificações complementares" no
padrão de `ApiIntegracaoCatalog`:
- interruptores Overture, Microsoft e IA;
- URL do serviço de inferência;
- URLs do ImageServer 2024 e do WMS 2016/2017;
- token da ortofoto (sensível, sem uso hoje);
- limites de área e compacidade;
- prazo de espera da IA.

Novos parâmetros entram no `ParameterSeeder`. Os testes que contam
parâmetros são ajustados.

`RegistrarChamadaIntegracao` ganha os hosts da Overture, da Microsoft, do
ImageServer/WMS e do serviço de inferência.

### 9. Métricas

Comando `geo:edificacoes:metricas` e um bloco na tela de integrações, a
partir de `lot_polygon_resolutions`, `building_footprint_imports` e
`integration_calls`:
- **Cobertura:** % de processos com polígono por fonte e % sem polígono.
- **Base:** prédios ativos por fonte e duplicados descartados na última
  importação.
- **Latência:** p50/p95 por fonte.
- **Falhas:** taxa de falha por fonte e motivo.
- **IA:** recortes pedidos, inferências e tempo médio.
- **Precisão:** não há medida automática. O oficial achado depois não
  substitui o complementar (decisão 8), e não existe amostra rotulada.
  Proposta para a operação: conferir por amostragem mensal, na ficha, os
  polígonos complementares sobre a ortofoto.

## Testes

Feature tests no padrão de `tests/Feature/Geo`, com SQLite e fakes para a
cadeia, e `PostgisTestCase` para as consultas espaciais e a importação. Os
cenários:
- Ordem das fontes: cada fonte só roda quando as anteriores falham; SEDUR
  pelo ponto por último.
- Prédio que contém o ponto; ponto fora de prédio → próxima fonte; prédios
  sobrepostos → menor área.
- Nominatim sem número → pula 3–5.
- Sem ponto → nenhuma fonte complementar e decisão como hoje.
- Microsoft duplicado da Overture ignorado; aposentado ignorado.
- Importação: idempotência (mesma release e checksum), recorte pelo limite,
  geometria inválida corrigida ou descartada, falha no meio preserva a base
  anterior.
- IA: reaproveitamento sem novo job; imagem indisponível; serviço fora;
  máscara recusada por cada critério; prazo esgotado segue para o passo 6.
- Polígono complementar entra na maior porção (incluindo duas zonas e
  empate).
- Legenda e procedência chegam à tela por fonte.
- Nunca sobrescreve polígono existente; trava por processo.

## Documentação

`docs/integracoes/edificacoes-complementares.md`:
- instalação (PostGIS, script Overture, contêiner de inferência);
- configuração e ingestão inicial;
- atualização periódica;
- licenças e atribuições (ODbL Overture; divergência CDLA/ODbL da Microsoft;
  termos da ortofoto da Prefeitura e a confirmação pendente);
- operação e métricas;
- o que depende de infraestrutura e credenciais.

## Riscos

- **Edificação no lugar do lote** pode dar zona diferente da do lote em
  terreno de divisa. Risco aceito pelo usuário. Como a decisão diz "Maior
  porção do lote" (decisão 9) e o oficial achado depois não substitui o
  prédio (decisão 8), o erro não aparece sozinho. Só a procedência na ficha
  permite identificá-lo.
- **O passo 6 (lote oficial pelo ponto) fica atrás das complementares**, por
  decisão do usuário. Quando os dois existem, vale o prédio.
- **Ortofoto 2024 pode passar a exigir token**, como o SEMAP, e tem licença
  não confirmada.
- **A IA depende de infraestrutura nova** (contêiner Python, idealmente com
  GPU) fora do repositório Laravel.
- **A cobertura real em Salvador é desconhecida** até a primeira importação.

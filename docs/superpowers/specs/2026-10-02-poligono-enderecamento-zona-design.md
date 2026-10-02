# Spec — Polígono do endereçamento no processo de viabilidade e na zona do motor

Data: 2026-10-02
Origem: a zona urbanística do processo é decidida hoje por um único ponto (latitude e longitude do Cadastro Imobiliário da SEFAZ). O art. 18, § 2º, da Lei Municipal nº 9.148/2016 (LOUOS) decide por área: terreno em mais de uma zona de uso segue a zona que contiver a **maior porção**. Um ponto não sabe dizer qual porção é maior.

## 1. Problema

1. **A zona vem de um ponto.** `TerritoryService::identify(lat, lng)` consulta as camadas de zona com `INTERSECTS(GEOMETRY, POINT(...))`. Em lote de divisa, o ponto cadastral cai de um lado e o lote pertence ao outro. O motor enquadra pelo Quadro 10 e pelo Quadro 11A com a zona errada, e o fluxo expresso defere ou indefere com ela.
2. **A lei não é pontual.** O art. 18, § 2º manda aplicar as restrições e os parâmetros da zona de **maior porção** do terreno. Não há percentual mínimo na lei: compara-se a área em cada zona e prevalece a maior. Empate exato a lei não resolve.
3. **Existe geometria oficial e ela não é usada.** A camada `enderecamento:VM_ENDERECAMENTO` do GeoServer SEDUR publica o polígono do endereço licenciado, chaveado por `CODLOG` + `NUMERACAO_METRICA` (+ `COMPLEMENTO`/`SUB_UNIDADE`). O processo não a consulta, as fichas não a mostram e a decisão não a registra.

## 2. Fontes verificadas (produção, 2026-10-02)

Medições feitas contra o serviço ao vivo.

| Fonte | O que oferece | Medição |
|---|---|---|
| `enderecamento:VM_ENDERECAMENTO` (WFS 1.1, GeoJSON, `srsName=EPSG:4326`) | Polígono do endereço licenciado + `CODLOG`, `NUMERACAO_METRICA`, `COMPLEMENTO`, `SUB_UNIDADE`, `ALVARA`, `PROCESSO`, `ANO_PROCESSO`, `ORIGEM` | HTTP 200, 47.308 feições; polígonos simples (mediana de 6 vértices, sem anel interno); áreas de 46 m² a 7.337 m² na amostra |
| Mesma camada, filtro `CODLOG = <n> AND NUMERACAO_METRICA = <n>` | Recorte do endereço | Funciona em 4326; `CODLOG` e `NUMERACAO_METRICA` são `xsd:decimal`, `COMPLEMENTO` e `SUB_UNIDADE` são `xsd:string`; chave inexistente devolve `numberMatched: 0` |
| Camadas de zona (`louos_*`), filtro `BBOX` | Geometria da zona em GeoJSON | Funciona; `louos_zpam` devolveu `MultiPolygon` de 762 vértices (22 KB); `louos_zeis`, `Polygon` de 184 vértices |
| Camadas de zona, filtro `INTERSECTS(GEOMETRY, SRID=4326;POLYGON((...)))` | — | **Bloqueado pelo WAF da Prefeitura**: HTTP 500 com página “URL Bloqueada”, inclusive com polígono de 4 vértices (URL de 375 caracteres). `POINT` e `BBOX` passam |

### Limites medidos da camada de endereçamento

- **Cobertura parcial.** Só endereço que passou por endereçamento na SEDUR (origens `sedur`, `sedham`, `fmlf`, `memo`). Residência nunca licenciada não existe na camada.
- **`CODLOG` + número não é chave única.** `CODLOG 24574`, número 70 devolve 3 feições (`BL-3`, `BL-25`, `BL-20`), com polígonos diferentes. `CODLOG 9745`, número 73 devolve 4 (`A`, `B`, `C`, `D`), 4 geometrias distintas. O `COMPLEMENTO` desempata: o mesmo filtro com `COMPLEMENTO = 'BL-3'` devolve 1.
- **Apartamento não tem polígono próprio.** Subunidades do mesmo número repetem o mesmo polígono (`CODLOG 4923`, número 7416: subunidades 715 e 716, polígono único de 2.701 m²). A camada não desenha planta de unidade.
- **Há zona linear.** `louos_zona_uso_zclmu:VM_L_Z_USO_ZCLMU` devolveu `LineString`. Centralidade linear é eixo, não polígono — não tem área para comparar.

### O que a entrada do processo oferece

O processo da JUCEB chega pelo `/recebe` com o RUC: endereço em texto, número, área utilizada (`RES_AREA`) e inscrição imobiliária. **Não vem polígono nem coordenada**, e não se pretende mudar o contrato com a Junta. O portal do Viabiliza e a contingência continuam gravando o polígono desenhado em `property_polygon_geojson`.

## 3. Decisões

1. **O polígono do endereçamento entra no processo.** Nova coluna `address_polygon_geojson` em `viability_requests`, materializada no protocolo a partir do `cod_log` (logradouro tributário da SEFAZ, já aplicado hoje) + `address_number` + `address_complement`. É dado do sistema: fora do `fillable`, nunca escrito pelo cidadão. Não substitui `property_polygon_geojson` (o desenho do requerente), que continua intacto.

2. **Ambiguidade não vira escolha.** Mais de uma feição para a mesma chave, com geometrias distintas e sem complemento que desempate, é `ambiguo`: nada é gravado e o motivo fica na auditoria. Geometrias idênticas (caso das subunidades) contam como uma só e são gravadas.

3. **Com polígono do endereçamento, a zona sai da maior porção.** Calcula-se a área da interseção do polígono com cada zona candidata e vence a maior (art. 18, § 2º). Grava `zona_codigo` e a nova coluna `zona_fonte`.

4. **Sem polígono do endereçamento, nada muda.** A zona continua pelo ponto, exatamente como hoje (`zona_fonte = 'ponto'`). O sistema **não** desenha polígono a partir do ponto e da área do terreno da SEFAZ: ponto mais área não determina forma, orientação nem frente, e um quadrado inventado mudaria a maior porção do art. 18, § 2º.

5. **Como as zonas candidatas são obtidas.** O WAF bloqueia polígono na URL (medido). O caminho é: `BBOX` do polígono do endereçamento em cada camada de zona ativa (`geoserver_layers`), trazendo a geometria em GeoJSON, e a interseção é calculada **no PostGIS da aplicação** (`ST_Area(ST_Intersection(lote, zona)::geography)`). Nenhuma geometria de zona é persistida: é insumo de cálculo da execução.

6. **Degradação é honesta e conservadora.** Se qualquer camada de zona ativa falhar na consulta, o resultado é `indisponivel` — com uma camada faltando, “a maior porção” não é verificável. Fora do PostgreSQL (suíte em SQLite) o resolvedor também devolve `indisponivel`. Em todos esses casos vale a decisão 4: zona pelo ponto, com o motivo registrado.

7. **Empate exato vai para análise.** A lei não resolve empate. Duas zonas com a mesma área (comparação com tolerância de 0,01 m², para não transformar ruído de ponto flutuante em empate) encaminham o processo à análise técnica com o motivo das duas zonas. Não é parâmetro: é o silêncio da lei, não um limiar de negócio.

8. **Divergência entre o desenho e o endereçamento encaminha à análise.** Quando o processo tem os dois polígonos e a zona de cada um difere, a decisão automática é bloqueada e o processo vai à análise com as duas zonas no motivo. Espelha a HU-037 RN-005, que hoje compara o desenho com o ponto da inscrição. A zona gravada continua sendo a do endereçamento.

9. **Precedência no expresso:** indeferimento territorial (RT-01) → empate de zona → divergência de zona (endereçamento × desenho) → divergência de localização já existente (RN-005) → demais regras territoriais → fluxo atual. A RT-01 continua primeiro porque se apoia no logradouro tributário, não na geometria.

10. **Zona linear não entra no cálculo de área.** Camada que responde `LineString`/`MultiLineString` é registrada no inventário como linear e não disputa a maior porção. O comportamento atual dessas camadas (interseção por ponto) não muda — esta spec não cria nem remove incidência de centralidade linear.

11. **Interruptor próprio.** `features.geoserver_enderecamento` (parâmetro booleano, default ligado), no mesmo padrão de `geoserver_zona`, `geoserver_territorio` e `geoserver_via_classe`. Desligado: o polígono não é consultado e a zona segue pelo ponto, com o motivo registrado. **Sem parâmetro de limiar percentual** — a lei diz “maior porção”, não um percentual mínimo.

12. **Uma consulta por execução.** O cliente do endereçamento memoriza por (CODLOG, número, complemento) e o resolvedor de zona por polígono, como já fazem `GeoServerWfsCamadaClient` e `LocalizacaoInscricaoService`.

13. **Rastro na decisão.** A zona usada, sua fonte e as porções por zona (área em m² e percentual) entram em `camadas_incidentes` e na auditoria da identificação. A decisão expressa já grava `camadas_incidentes`; o analista e a auditoria veem por que aquela zona venceu.

## 4. Onde isso aparece

| Superfície | O que passa a mostrar |
|---|---|
| Ficha de análise (`gestao/ficha-analise/show.tsx`) | Polígono do endereçamento no mini-mapa, junto do desenho quando houver; bloco “Camadas do imóvel” com a fonte da zona e as porções |
| Detalhe do processo (`gestao/processos/show.tsx`) | Mesmo mapa e mesma lista de camadas |
| Inventário de camadas (`CamadasImovelLista`) | Linha da zona com fonte (“maior porção do lote” ou “ponto da inscrição”) e, havendo mais de uma, o percentual de cada |
| Ficha de vistoria | Já lê `zona_codigo`; passa a refletir a zona por área sem alteração de código |
| Auditoria / explicabilidade | Evento `geo/zona-maior-porcao` com porções, zona vencedora, versão das camadas e motivo da degradação |

O portal do cidadão não ganha o detalhe das porções: continua exibindo o resultado, não o cálculo.

## 5. Critérios de aceite (viram feature tests)

- **CA-PE-01** Processo com `cod_log` e número cujo endereçamento devolve um polígono grava `address_polygon_geojson` e `address_polygon_consulted_at`.
- **CA-PE-02** Chave com 3 feições de geometrias distintas e sem complemento não grava polígono; a auditoria registra `ambiguo` com a contagem de candidatas.
- **CA-PE-03** Chave com 3 feições e complemento que casa com uma grava o polígono dessa feição.
- **CA-PE-04** Chave com 2 feições de geometria idêntica (subunidades) grava o polígono.
- **CA-PE-05** Endereçamento sem feição para a chave não grava nada e segue o fluxo; auditoria `nao_encontrado`.
- **CA-PE-06** `features.geoserver_enderecamento` desligado não consulta o GeoServer e a zona permanece pela regra do ponto.
- **CA-ZN-01** Polígono do endereçamento com 78% em ZPR 3 e 22% em ZEIS grava `zona_codigo = 'ZPR 3'` e `zona_fonte = 'enderecamento_maior_porcao'`.
- **CA-ZN-02** As porções (zona, área em m², percentual) ficam em `camadas_incidentes['zona']['porcoes']`, ordenadas da maior para a menor.
- **CA-ZN-03** Uma camada de zona ativa falhando torna o resultado `indisponivel`: a zona volta a sair do ponto e o motivo fica na auditoria.
- **CA-ZN-04** Empate (diferença de área ≤ 0,01 m²) encaminha à análise com o motivo citando as duas zonas; não grava zona vencedora por desempate inventado.
- **CA-ZN-05** Polígono sem interseção com qualquer camada de zona devolve `sem_intersecao` e a zona segue pelo ponto.
- **CA-ZN-06** Camada que responde geometria linear não entra nas porções e não impede a decisão pelas camadas poligonais.
- **CA-ZN-07** Fora do PostgreSQL o resolvedor devolve `indisponivel` sem consultar o GeoServer (sem cálculo aproximado silencioso).
- **CA-ZN-08** Uma consulta por polígono e por camada em cada execução (memo), como nas demais integrações.
- **CA-DV-01** Zona do desenho diferente da zona do endereçamento encaminha à análise com as duas zonas no motivo; `zona_codigo` permanece a do endereçamento.
- **CA-DV-02** Zonas iguais seguem o fluxo normal, com a coincidência auditada.
- **CA-FC-01** A ficha de análise expõe `poligono_enderecamento` e o mini-mapa desenha as duas geometrias quando existem.
- **CA-FC-02** O detalhe do processo expõe o mesmo polígono.
- **CA-FC-03** A lista de camadas mostra a fonte da zona e o percentual de cada porção quando houver mais de uma.
- **CA-AU-01** A identificação por área gera evento de auditoria com zona vencedora, porções, camadas consultadas e versão/typeName de cada.

## 6. Riscos

- **Cobertura da camada.** A maior parte dos endereços não licenciados não tem polígono; o ganho é incremental e o fallback é o comportamento atual. Mitigação: `zona_fonte` deixa explícito em cada processo qual regra decidiu.
- **Precisão do polígono do endereçamento.** É o endereço licenciado, não necessariamente o lote cadastral do IPTU. Quando houver divergência de zona com o desenho do requerente, a decisão 8 manda à análise em vez de arbitrar.
- **Peso da consulta.** Até 20 camadas de zona por polígono, com geometrias de dezenas de KB. Mitigação: `BBOX` do lote (recorte mínimo), memo por execução e `timeout` já configurado em `sile.integrations.geoserver.timeout`.
- **WAF.** O bloqueio de polígono na URL é contornado pelo `BBOX`, mas o WAF pode mudar de regra. Mitigação: falha de camada é `indisponivel` e o processo degrada para o ponto, nunca para zona errada.
- **Dependência de PostGIS.** O cálculo da maior porção só roda em produção (pgsql). A suíte prova a matemática em testes `@group postgis` e o restante com um fake do resolvedor.

## 7. Fora de escopo

- Pedir polígono ou coordenada à JUCEB no `/recebe`.
- Desenhar polígono a partir do ponto e da área do terreno da SEFAZ.
- Polígono do lote cadastral com cobertura total (`parcelamento_lotes`, cadastro multifinalitário): pendência SEDUR já registrada, bloqueia a HU-037 RN-004.
- Aplicar parâmetros urbanísticos por parcela do terreno (gabarito, coeficiente): esta spec decide a **zona do processo**, não o cálculo de parâmetros por porção.
- Incidência de centralidade linear (ZCLMe/ZCLMu) por geometria de linha.
- Exibir porções no portal do cidadão.

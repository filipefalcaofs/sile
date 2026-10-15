# Polígono do lote pelo ponto no GeoServer — Design

Data: 2026-10-14

## Problema

Processos chegam à ficha de análise com zona e classe viária resolvidas, mas sem
polígono do imóvel ("Polígono do imóvel não cadastrado neste processo"). O
`address_polygon_geojson` só é gravado por `PoligonoEnderecamentoService::aplicar`,
que tenta duas fontes:

1. Endereçamento SEDUR (GeoServer) por `CODLOG` + número métrico.
2. Cadastro municipal (SEMAP/ArcGIS) pela inscrição.

Quando as duas falham, a zona é resolvida pelo ponto (`zona_fonte = ponto`) e a
decisão sai sem a regra da maior porção do lote (art. 18, § 2º, Lei 9.148/2016).

O processo deve ser definido pelo polígono. O ponto só decide quando nenhuma
fonte de polígono achar o lote.

## Evidência medida (GeoServer SEDUR, 2026-10-14)

Consulta espacial `INTERSECTS(GEOMETRY,SRID=4326;POINT(lng lat))` em
`enderecamento:VM_ENDERECAMENTO`, com 10 pontos extraídos de polígonos reais:

- Devolveu o lote certo em 10 de 10.
- Tempo por consulta: cerca de 30 s (a consulta por CODLOG + número leva 0,2 s).
- 1 de 10 pontos devolveu 3 polígonos distintos para o mesmo CODLOG e número
  (subunidades). Isso é ambíguo.
- Sem o prefixo `SRID=4326;` a consulta devolve 0 resultados mesmo para um
  ponto dentro do polígono.
- `parcelamento_lotes:VM_PARCELAMENTO_LOTES_EDIF` cobriu só 3 de 10 (camada de
  loteamentos aprovados) e fica fora deste escopo.

## Objetivo e critérios de sucesso

- Quando a chave e o SEMAP não acham polígono, uma terceira tentativa busca o
  lote pelo ponto do imóvel no endereçamento do GeoServer.
- A zona por maior porção e a decisão rodam depois dessa tentativa.
- Só um resultado único é gravado. Ambíguo, vazio, erro ou timeout mantêm o
  comportamento atual (zona pelo ponto), sem inventar polígono e sem apagar um
  polígono já gravado.
- O protocolo síncrono do portal não espera a consulta lenta.

## Fora de escopo

- SEMAP (token e fallback): tratado depois.
- Ponto no mapa da ficha e preenchimento de "Via" pela classe do motor.
- Trava por zona ambígua no raio do ponto.
- Camada `parcelamento_lotes`.

## Desenho

### 1. Ordem de fontes

1. Endereçamento por CODLOG + número (+ complemento).
2. SEMAP por inscrição.
3. Novo: endereçamento por ponto.

A etapa 3 só roda se `address_polygon_geojson` continuar vazio, se houver ponto
(`ViabilityRequest::pontoDoImovel()`) e se o chamador pediu a consulta por ponto.

### 2. Onde a consulta por ponto roda

`PoligonoEnderecamentoService` ganha:

- `aplicar(ViabilityRequest $request, bool $consultarPorPonto = false): bool`.
  O padrão desligado preserva o tempo dos chamadores síncronos. Ligado, a
  terceira fonte roda depois da chave e do SEMAP.
- `aplicarPorPonto(ViabilityRequest $request): bool`, público. Só a terceira
  fonte, com guarda própria (polígono já presente ou sem ponto: não consulta).
  É o que o job de decisão chama, para não repetir a chave e o SEMAP que já
  falharam no protocolo.

| Caminho | Consulta por ponto |
|---|---|
| Protocolo do portal (`ProtocolarSolicitacaoService::protocol`, HTTP síncrono) | não |
| Renovação (`RenovacaoProtocoloService`, HTTP síncrono) | não |
| Reprocessamento de etapas (`ReprocessarProcessoController` e comando, via `ReprocessarEtapasService`) | não inline; agenda o job quando falta o polígono (§6) |
| Simulação REGIN (`ReginProtocoloSimulacaoController`, HTTP síncrono) | não |
| `FluxoExpressoService::garantirPoligonoRegin` (alcançável pelos HTTP acima) | não |
| `DecidirFluxoExpressoJob`, antes de `decide()` (timeout 120 s, 3 tentativas) | sim, `aplicarPorPonto` |
| `ReginProcessoProtocolador` (`ProtocolarRecebimentoReginJob`, timeout 120 s) | sim, `aplicar(..., true)` |

Pior caso no job do REGIN: a chave e o SEMAP rodam no protocolo e de novo antes
da decisão, e o SEMAP pode rodar uma terceira vez em `garantirPoligonoRegin`;
com o SEMAP fora do ar e o ponto lento, o job pode chegar perto dos 120 s. Por
isso o recebimento é vinculado ao processo logo após o protocolo: se o job
estourar, a nova tentativa não abre um segundo processo. A zona por maior porção é materializada dentro de `decide()`
([FluxoExpressoService.php:249](app/Services/Expresso/FluxoExpressoService.php:249)),
então gravar o polígono antes de `decide()` basta.

### 3. Regra do resultado

- Exatamente 1 geometria distinta: grava `address_polygon_geojson`,
  `address_polygon_consulted_at` e `address_polygon_source = ponto`.
- Mais de 1 geometria distinta: `ambiguo`, não grava.
- Nenhuma, erro HTTP, resposta fora do contrato ou timeout: não grava e não
  apaga polígono existente.
- Polígono já presente: não consulta.
- Só aceita `Polygon` ou `MultiPolygon`, como o cliente atual.

### 4. Cliente e timeout

`GeoServerEnderecamentoClient` ganha `consultarPorPonto(float $lat, float $lng)`.

- Mesmo `typeName` e mesmo parser de features do `consultar`.
- `CQL_FILTER = INTERSECTS(GEOMETRY,SRID=4326;POINT(<lng> <lat>))`. O ponto
  entra em (longitude, latitude).
- Timeout próprio: `viabiliza.integrations.geoserver.enderecamento.timeout_ponto`,
  45 s. Sem retry interno, porque o job já tem tentativas.
- O timeout geral de 8 s continua para as demais consultas.
- Respeita o interruptor `geoserver_enderecamento`.

### 5. Rastro e exibição

- Auditoria `enderecamento-poligono` com `fonte = ponto`, o status, o número de
  candidatas e o motivo, no mesmo formato das outras duas fontes.
- Nova coluna `viability_requests.address_polygon_source` (string nullable),
  com enum `PoligonoFonte` (`chave`, `semap`, `ponto`). Hoje a origem do
  polígono só existe na auditoria. A coluna passa a ser preenchida nas três
  fontes, para a ficha poder indicar a origem.
- A exibição na ficha fica para a próxima etapa. Esta entrega só grava a coluna.

### 6. Reprocesso (opção 1, aprovada em 2026-10-14)

O reprocesso manual (botão da gestão e `processos:reprocessar-etapas`) também
não pode decidir pelo ponto quando o polígono pode ser obtido.

- `PoligonoEnderecamentoService::precisaConsultarPorPonto(ViabilityRequest): bool`
  (público): `address_polygon_geojson` vazio, `pontoDoImovel()` não nulo e
  interruptor `geoserver_enderecamento` ligado. `aplicarPorPonto` usa a mesma
  guarda.
- `ReprocessarEtapasService`: depois de `completarTerritorio`, se
  `precisaConsultarPorPonto` e `podeReavaliar`, despacha
  `DecidirFluxoExpressoJob($id, reavaliacao: true, atorId: $ator?->id)` em vez de
  reavaliar na hora. Mensagem: "Etapas reprocessadas. O polígono do lote está
  sendo buscado pelo ponto; o veredito sai em seguida." A auditoria `reprocesso`
  ganha `decisao_agendada`.
- Com polígono já gravado, sem ponto ou com o interruptor desligado, o reprocesso
  segue síncrono como hoje.
- `DecidirFluxoExpressoJob` ganha `bool $reavaliacao = false` e `?int $atorId =
  null`. Com `reavaliacao`, a guarda passa a ser `FluxoExpressoService::podeReavaliar()`
  (protocolada, ou em análise sem analista e sem decisão), a mesma regra que o
  reprocesso já usa. Sem `reavaliacao`, a guarda segue `Protocolada`, sem mudança
  para os despachos atuais. O ator, quando informado, vai para `decide()`.

## Testes (PHPUnit, `Http::fake`)

- Ponto devolve 1 polígono: grava com `address_polygon_source = ponto`.
- Ponto devolve 3 polígonos distintos: não grava, audita `ambiguo`.
- Resposta vazia, HTTP 500, JSON fora do contrato e timeout: não gravam e não
  apagam polígono existente.
- Precedência: a chave achou o lote, então a consulta por ponto não roda.
- Sem ponto: a etapa é pulada.
- Polígono já presente: não consulta.
- Interruptor `geoserver_enderecamento` desligado: não consulta.
- O protocolo do portal e a renovação não disparam a consulta por ponto.
- `DecidirFluxoExpressoJob` dispara a consulta antes de `decide()`.
- O CQL enviado contém `SRID=4326;POINT(lng lat)` na ordem certa.

## Riscos

- O ponto do catálogo do REGIN ou da geocodificação pode cair em lote vizinho.
  Por isso a fonte fica registrada em `address_polygon_source` e na auditoria.
- A latência da consulta (~30 s) é do servidor e pode variar. Falha de
  timeout degrada para o comportamento atual, sem erro para o processo.

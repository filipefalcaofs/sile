# HU-033 — Identificar lote

## Épica
**EP04 — Georreferenciamento e Território**

## Situação na implementação
- **Status:** Bloqueada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Identificar o lote cadastral do ponto na camada de lotes do território. Essa identificação não ocorre: a camada local está pendente de fonte e o serviço não consulta geometria nesse estado.

## História de Usuário
**Como** sistema,  
**quero** saber em qual lote o ponto do imóvel cai,  
**para** validar a localização contra o lote da inscrição.

## Atores e permissões
- A chamada de identificação exige `consultar-territorio`.
- Não há papel que desbloqueie a dimensão `lote` pela interface. O desbloqueio depende de uma camada `lote` vigente, com feições, no lugar do registro `pendente_fonte`.

## Fluxo evidenciado
1. `GeoLayerType::Lote` é fonte bloqueada, no mesmo grupo da zona. O seed cria a camada `pendente-sedur`, status `pendente_fonte`, zero feições, sem geometria.
2. `TerritoryService::identifyContaining` para lote, com camada nula ou `pendente_fonte`, devolve `indisponivel`, motivo “Base de lotes pendente SEDUR”, e não chama o repositório espacial.
3. `LocationValidationService` usa a mesma trava. Com a camada bloqueada, a sobreposição não é calculada (HU-037).
4. `TerritorioProcessoService` não grava identificador de lote. As colunas materializadas são zona, bairro oficial e CODLOG.
5. Existe consulta pontual por inscrição em `SemapLoteClient` (camada SEMAP / ArcGIS, parâmetro `arcgis_semap`), usada como fallback do endereçamento quando o endereço licenciado não tem a inscrição. Ela devolve geometria daquela inscrição, não preenche a dimensão `lote` de `TerritoryService` e não cobre a cidade como camada vigente de `geo_layers`. Com o parâmetro desligado, essa consulta também fica desligada.

## Regras de negócio evidenciadas
- Camada `pendente_fonte` nunca dispara `ST_Contains` de lote.
- Sem feição oficial, o sistema não fabrica polígono de lote.
- A lógica de contenção já está no serviço: se uma camada de lote vigente com feições for carregada, a mesma identificação por contenção passa a valer. Hoje o dado carregado não é esse.

## Validações
- Não há validação de inscrição contra lote na identificação por ponto. O ponto só produz o status `indisponivel` para a dimensão.

## Critérios de aceite
**Dado** que a camada de lote vigente está `pendente_fonte` ou não existe,  
**Quando** um ponto é identificado,  
**Então** a dimensão `lote` volta `indisponivel`, com o motivo de base pendente, e nenhuma consulta espacial de lote é feita.

**Dado** que a consulta SEMAP por inscrição está desligada,  
**Quando** o endereçamento procura o lote da inscrição,  
**Então** o cliente devolve status desligado e não inventa geometria.

## Divergências da redação anterior
A redação anterior apresentava a identificação do lote como capacidade operacional. O código da dimensão `lote` só comunica a pendência. A consulta SEMAP não substitui essa dimensão.

## Evidências
- `GeoLayerType::isBlockedSource` (zona e lote)
- `GeoLayerSeeder::seedPendingBlockedLayers`
- `TerritoryService::identifyContaining` e `motivoFor` (lote)
- `LocationValidationService::isBlocked`
- `SemapLoteClient` (consulta por inscrição, fora da dimensão `lote`)

## Não evidenciado
- Base de lotes cobrindo o município carregada em `geo_layers`.
- Número oficial do lote, quadra ou inscrição devolvidos por `TerritoryService::identify`.
- Uso do lote SEMAP como lote da identificação territorial por ponto.

# HU-107 — Integrar com GIS Municipal

## Épica
**EP13 — Integrações**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Identificar zona, via, endereço e camadas incidentes consultando o GeoServer da SEDUR e, no lote, o ArcGIS da SEMAP quando esse for o fallback.

## História de Usuário
**Como** sistema, **quero** consultar as camadas geográficas oficiais no ponto ou no logradouro do imóvel, **para** gravar zona e restrições a partir da resposta do serviço, e não de um polígono inventado.

## Atores e permissões
- Clientes usados por `TerritoryService`, `TerritorialRuleService`, `CamadasIncidentesService`, `PoligonoEnderecamentoService` e `TerritorioProcessoService`.
- Camadas administráveis em `GeoServerLayerController`. URLs no catálogo de APIs (aba GeoServer e ArcGIS SEMAP), permissão `manter-parametros` para a configuração.
- Consulta territorial da gestão exige `consultar-territorio`.

## Fluxo evidenciado
1. Zona: `GeoServerWfsZonaClient` faz WFS no ponto (INTERSECTS, GeoJSON). A maior porção do polígono do imóvel usa o mesmo GeoServer em pool de requisições.
2. Classificação viária: `GeoServerClassificacaoViariaClient` consulta pelo código de logradouro.
3. Endereçamento: `GeoServerEnderecamentoClient` consulta logradouro e número, ou o ponto. `SemapLoteClient` é o fallback de lote no ArcGIS da SEMAP.
4. Demais camadas: `GeoServerWfsCamadaClient` no type name configurado.
5. Resposta em ISO-8859-1 é normalizada. Falha de rede ou feição ausente segue o tratamento do cliente (exceção ou hit vazio), sem geometria substituta.
6. O código da zona e o bairro oficial materializados no processo alimentam o painel por bairro. O indicador “por zona” da tela de indicadores ainda agrupa por bairro e declara a zona indisponível naquela agregação.

## Regras de negócio evidenciadas
- Identificação territorial do processo chama o GeoServer de fato.
- Nota interna de falha de camada não é enviada como justificativa à JUCEB.
- Camada não cadastrada não é consultada com type name inventado.

## Validações
- Consultas exigem coordenada ou código de logradouro conforme o cliente.
- Timeout e URL saem da configuração da integração, não de constante de tela.

## Critérios de aceite
- Identificar um ponto dispara WFS ao GeoServer da zona e não preenche zona se a resposta não trouxer feição.
- Endereçamento sem retorno do GeoServer pode cair no lote SEMAP; sem os dois, não há endereço oficial fabricado.
- A configuração da camada é a linha de `GeoServerLayer`, não um nome fixo só no código da tela.

## Divergências da redação anterior
O texto tratava a integração como pendência genérica de SIG. Os clientes HTTP do GeoServer e do ArcGIS SEMAP estão no fluxo de território. A agregação “solicitações por zona” dos indicadores não usa essa zona: ela degrada para bairro, com rótulo explícito.

## Evidências
- `app/Services/Geo/GeoServerWfsZonaClient.php`
- `app/Services/Geo/GeoServerClassificacaoViariaClient.php`
- `app/Services/Geo/GeoServerEnderecamentoClient.php`
- `app/Services/Geo/GeoServerWfsCamadaClient.php`
- `app/Services/Geo/SemapLoteClient.php`
- `app/Services/Geo/TerritoryService.php`
- `app/Providers/AppServiceProvider.php`

## Não evidenciado
- Uso da zona oficial na série “solicitações por zona” de `IndicadoresViabilidadeService::porZona` (essa série declara degradação para bairro).

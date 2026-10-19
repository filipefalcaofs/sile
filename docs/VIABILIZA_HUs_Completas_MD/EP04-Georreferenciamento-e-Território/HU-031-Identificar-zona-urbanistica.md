# HU-031 — Identificar zona urbanística

## Épica
**EP04 — Georreferenciamento e Território**

## Situação na implementação
- **Status:** Parcial
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Informar a zona urbanística do ponto. A camada local de zona nasce pendente de fonte. A identificação usada pelo motor vem do GeoServer SEDUR quando o parâmetro está ligado, ou da maior porção do polígono de endereçamento no processo.

## História de Usuário
**Como** sistema,  
**quero** obter o código da zona do imóvel a partir da fonte que estiver disponível,  
**para** o Quadro 10 consultar a permissão sem inventar zona.

## Atores e permissões
- Identificação avulsa: `consultar-territorio`.
- No processo, a gravação é do sistema (`zona_codigo` fora do preenchimento do cidadão).
- A consulta WFS depende do parâmetro `geoserver_zona` e das linhas ativas de `geoserver_layers`.

## Fluxo evidenciado
1. `GeoLayerType::Zona` é fonte bloqueada. O seed registra a camada local como `pendente_fonte`, versão `pendente-sedur`, zero feições.
2. Em `TerritoryService::identifyContaining`, camada ausente ou `pendente_fonte` não consulta o PostGIS. Para zona, tenta `identifyZonaFromGeoServer`.
3. Sem cliente WFS ou com `geoserver_zona` desligado, a zona fica `indisponivel`, motivo “Base de zoneamento pendente SEDUR”.
4. Com o parâmetro ligado, `GeoServerWfsZonaClient::identificar` consulta as camadas ativas. Status `identificado` devolve o código (atributo `ZONA`, fonte `geoserver-wfs`). Coleção vazia é `nao_encontrado`. Falha HTTP ou resposta fora do contrato é `indisponivel`, com o motivo do cliente.
5. Se a camada local existir e estiver vigente, mas o ponto não cair em feição, o mesmo fallback WFS é tentado.
6. `TerritorioProcessoService` grava `zona_codigo` só quando o nome veio identificado.
7. No fluxo do processo com polígono de endereçamento, `ZonaProcessoService` materializa a zona pela maior porção do lote (`ZonaFonte::EnderecamentoMaiorPorcao`). Porções iguais viram empate e o expresso encaminha à análise com o gatilho `zona_empate`.

## Regras de negócio evidenciadas
- O nome usado depois no Quadro 10 é o `nome` da dimensão ou, na falta dele, os atributos configurados em `geo.zona.atributos_nome` (padrão `ZONA`, `zona`, `SIGLA_ZONA`, `SUBZONA`).
- `Quadro10Zona::oficializar` traduz alias de GIS para a grafia da matriz. Não cria zona que não esteja na lista.
- Zona não identificada deixa o Quadro 10 indisponível e o consolidado `pendente`.

## Validações
- Ponto com latitude e longitude numéricas, como na HU-030.
- Código de zona só entra no processo quando o status da dimensão é `identificado` e o nome não é vazio.

## Critérios de aceite
**Dado** que `geoserver_zona` está ligado e alguma camada ativa contém o ponto,  
**Quando** o território é identificado,  
**Então** a zona volta `identificado` com o código e a versão `geoserver-wfs`.

**Dado** que a consulta ao GeoServer está desligada, sem camada ativa, ou o serviço não responde,  
**Quando** a camada local está pendente de fonte,  
**Então** a zona volta `indisponivel` e nenhuma permissão de zona é inventada.

**Dado** que o polígono do endereçamento tem a mesma área em duas zonas,  
**Quando** o fluxo expresso avalia a maior porção,  
**Então** o processo vai à análise com o gatilho de empate.

## Divergências da redação anterior
A redação anterior tratava a identificação da zona como passo sempre concluído. O código distingue camada local pendente, WFS opcional e maior porção do endereçamento.

## Evidências
- `TerritoryService::identifyContaining` e `identifyZonaFromGeoServer`
- `GeoServerWfsZonaClient`
- `GeoLayerSeeder` (zona `pendente_fonte`)
- `ZonaProcessoService`, `ZonaMaiorPorcao`
- `TerritorioProcessoService::materializar`

## Não evidenciado
- Base vetorial local de zoneamento cobrindo o município. O enum marca zona como fonte bloqueada e o seed não carrega feições.
- Regra urbanística de desempate quando duas zonas têm a mesma área. O código só registra o empate e encaminha à análise.

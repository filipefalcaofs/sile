# HU-030 — Localizar imóvel no mapa

## Épica
**EP04 — Georreferenciamento e Território**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Mostrar o mapa de Salvador na consulta territorial e identificar bairro, via, zona, lote e restrições a partir de um ponto informado.

## História de Usuário
**Como** servidor com permissão de consultar território,  
**quero** posicionar um ponto no mapa e ver o que o território devolve para aquele ponto,  
**para** conferir o imóvel sem depender só do texto do endereço.

## Atores e permissões
- Página e identificação: `consultar-territorio`.
- O requerente, ao salvar o imóvel do próprio rascunho, dispara a mesma identificação pelo centroide do polígono (`SolicitacaoImovelController`). Essa gravação usa a policy da solicitação, não a permissão de consulta territorial.

## Fluxo evidenciado
1. `GET /gestao/territorio` renderiza a consulta com as camadas abertas (`valid_to` nulo), o limiar de sobreposição, se a geocodificação está ligada, e o centro do mapa em latitude −12,97 e longitude −38,5, zoom 13.
2. `POST /gestao/territorio/identificar` recebe `lat` e `lng` e chama `TerritoryService::identify`.
3. O serviço devolve bairro (feição que contém o ponto), via mais próxima dentro do raio, zona, lote e restrições que intersectam o ponto. Cada dimensão traz status `identificado`, `nao_encontrado` ou `indisponivel`, com a versão da camada.
4. A identificação é auditada em `territorio` / `identificacao`, com as versões e o resumo de status. O resultado é sempre `sucesso` da auditoria, mesmo quando uma dimensão está indisponível: a degradação vai no conteúdo, não como falha da chamada.
5. No rascunho do portal, o polígono GeoJSON é a fonte. O centroide alimenta `identify` e `TerritorioProcessoService::materializar` grava zona, bairro oficial e CODLOG somente quando a dimensão veio identificada. Valor indisponível ou não encontrado não apaga o que já estava gravado.

## Regras de negócio evidenciadas
- O raio da via mais próxima é `viabiliza.geo.via_max_metros` (padrão 50 metros).
- Camada ausente ou com status `pendente_fonte` não dispara consulta espacial. Zona ainda pode cair no GeoServer (HU-031). Lote permanece indisponível nesse estado (HU-033).
- Sem ponto do imóvel, `materializar` não grava nada.
- A data opcional de `identify` reproduz a camada vigente naquela data.

## Validações
- `lat` obrigatória, numérica, entre −90 e 90.
- `lng` obrigatória, numérica, entre −180 e 180.

## Critérios de aceite
**Dado** que o servidor informa um ponto válido,  
**Quando** solicita a identificação,  
**Então** o sistema devolve as cinco dimensões com status e versão de camada, e registra a auditoria.

**Dado** que uma camada está `pendente_fonte` ou não existe,  
**Quando** o ponto é identificado,  
**Então** essa dimensão volta `indisponivel` com motivo, sem feição inventada. A exceção evidenciada é a zona, que pode ser lida no GeoServer quando o parâmetro correspondente está ligado.

**Dado** que o requerente grava o polígono do imóvel,  
**Quando** o centroide é identificado,  
**Então** zona, bairro e CODLOG só são gravados se o status for `identificado`.

## Divergências da redação anterior
A redação anterior não distinguia consulta do mapa, identificação por ponto e materialização no processo, nem o comportamento de não sobrescrever dado já identificado.

## Evidências
- `TerritoryController::index` e `identify`
- `TerritoryService::identify`
- `TerritorioProcessoService::materializar`
- `SolicitacaoImovelController::update`
- Página `gestao/territorio/index`

## Não evidenciado
- Desenho do lote oficial em cima do mapa a partir da dimensão `lote`, porque essa dimensão fica indisponível enquanto a camada local está pendente de fonte.
- Escolha de uma feição entre várias na interface: o contrato devolve o resultado do serviço.

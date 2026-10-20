# HU-043 — Identificar bairro

## Épica
**EP04 — Georreferenciamento e Território**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Descobrir o bairro oficial do ponto pela camada de bairros e gravá-lo no processo quando a feição for encontrada.

## História de Usuário
**Como** sistema,  
**quero** saber o bairro que contém o ponto do imóvel,  
**para** registrá-lo no processo e usá-lo nas allow-lists que comparam bairro.

## Atores e permissões
- Consulta do ponto: `consultar-territorio`.
- A gravação de `bairro_oficial` é do sistema, na materialização do território.
- A carga da camada é da manutenção de camadas (`manter-territorio`) ou do seed em PostGIS.

## Fluxo evidenciado
1. `TerritoryService` trata bairro por contenção (`ST_Contains` via `containingFeature`) na camada vigente, ou na vigente na data informada.
2. O seed, em PostgreSQL, importa `database/data/geo/bairros.geojson`, versão `geosalvador-bairros-dec38776-2024`, origem do serviço público de bairros do GeoSalvador. Arquivo ausente ou JSON inválido registra a camada como pendente, sem inventar polígono.
3. Feição encontrada: status `identificado`, nome lido de `NOME_BAIRRO` (ou das outras chaves de nome da feição) e propriedades completas. Nenhuma feição: `nao_encontrado`. Camada ausente ou `pendente_fonte`: `indisponivel`, motivo “Camada de Bairro indisponível”.
4. `TerritorioProcessoService` grava `bairro_oficial` só com status `identificado` e nome não vazio. A etapa do motor `bairro` fica ok, indisponível (reprocessável) ou definitiva quando não encontrado.
5. O nome do bairro identificado entra na verificação da observação (a) do Quadro 10 (`observacaoQuadro10Permite`), comparando o texto normalizado com a allow-list da regra `RT-05-A`.

## Regras de negócio evidenciadas
- Bairro não encontrado não é substituído por um bairro padrão.
- Indisponível ou não encontrado não apaga `bairro_oficial` já gravado.
- Em SQLite o seed não carrega o GeoJSON, porque não há PostGIS. A identificação espacial depende do banco com a camada vigente.

## Validações
- Ponto com latitude e longitude válidas.
- Nome vazio na feição identificada não é gravado como bairro do processo.

## Critérios de aceite
**Dado** que existe camada de bairro vigente com feições e o ponto cai dentro de um polígono,  
**Quando** o território é identificado,  
**Então** o bairro volta `identificado` com o nome e a versão da camada, e pode ser gravado em `bairro_oficial`.

**Dado** que o ponto não cai em nenhum bairro da camada vigente,  
**Quando** o território é identificado,  
**Então** o status é `nao_encontrado` e o bairro do processo não é preenchido por essa chamada.

**Dado** que não há camada vigente de bairro,  
**Quando** o território é identificado,  
**Então** o status é `indisponivel` e não há consulta espacial.

## Divergências da redação anterior
A redação anterior não citava a contenção espacial, a versão do snapshot GeoSalvador nem o uso do bairro na allow-list da observação (a).

## Evidências
- `TerritoryService::identifyContaining` para `GeoLayerType::Bairro`
- `GeoLayerSeeder` (`bairros.geojson`)
- `TerritorioProcessoService::materializar` e `registrarEtapasDoTerritorio`
- `LouosEnquadramentoService::bairroDoTerritorio`

## Não evidenciado
- Bairro digitado pelo requerente (`address_neighborhood`) substituindo o bairro oficial da camada. São campos diferentes. A allow-list da observação (a) lê o bairro do território identificado, não o texto livre do endereço.

# HU-036 — Consultar camadas geográficas

## Épica
**EP04 — Georreferenciamento e Território**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Listar as versões das camadas geográficas locais e importar um GeoJSON oficial, abrindo nova versão vigente sem apagar a anterior.

## História de Usuário
**Como** administrador,  
**quero** ver a versão vigente de cada tipo de camada e importar um GeoJSON,  
**para** trocar a base territorial sem deploy e sem perder o histórico.

## Atores e permissões
- Leitura e importação: `manter-territorio`, somente o papel administrador.
- A consulta territorial (`consultar-territorio`) mostra o status das camadas abertas, sem importar arquivo.
- Menu: Território → Camadas geográficas (`/gestao/territorio/camadas`).

## Fluxo evidenciado
1. `GET /gestao/territorio/camadas` agrupa `geo_layers` pelos cinco tipos: bairro, zona urbanística, via, lote e restrição territorial.
2. Cada grupo traz a vigente (status `vigente`), o histórico ordenado por início de vigência e o indicador `fonte_bloqueada` (verdadeiro para zona e lote).
3. Cada versão expõe identificador, código de versão, status (`vigente`, `substituida`, `pendente_fonte`), origem, vigência, e quantidade de feições.
4. `POST /gestao/territorio/camadas` exige tipo, versão e arquivo. Origem vazia vira `importacao-pela-gestao`.
5. Fora de PostgreSQL, a importação é recusada com erro e nada é criado.
6. JSON inválido é recusado. JSON válido segue `GeoJsonLayerImporter`, o mesmo da carga por comando: a nova versão fica vigente e a anterior é fechada, não apagada.
7. A mensagem de retorno informa versão, feições lidas, inseridas e, se houver, as inválidas não inseridas (até cinco motivos).

## Regras de negócio evidenciadas
- O importador depende de PostGIS (`ST_GeomFromGeoJSON` / `ST_MakeValid`). Conexão que não é `pgsql` não simula a carga.
- Zona e lote continuam marcados como fonte bloqueada na tela. Importar GeoJSON desses tipos é aceito pelo formulário se o arquivo for válido e o banco for PostGIS. O seed, por si, não traz feições oficiais desses dois tipos.
- O tamanho máximo do arquivo é `viabiliza.geo.upload_max_mb` (padrão 20 MB).

## Validações
- Tipo obrigatório, um dos valores do enum.
- Versão obrigatória, até 100 caracteres.
- Origem opcional, até 255 caracteres.
- Arquivo obrigatório, até o limite em megabytes.

## Critérios de aceite
**Dado** que o administrador abre as camadas,  
**Quando** a listagem carrega,  
**Então** os cinco tipos aparecem com a vigente, quando houver, e o histórico de versões.

**Dado** que o banco não é PostgreSQL,  
**Quando** um GeoJSON é enviado,  
**Então** a importação é recusada e nenhum registro novo é criado.

**Dado** que o banco é PostGIS e o arquivo é um GeoJSON válido,  
**Quando** a importação conclui,  
**Então** a nova versão fica vigente, a anterior deixa de ser a vigente, e a tela informa lidas, inseridas e inválidas.

## Divergências da redação anterior
A redação anterior descrevia consulta genérica de camadas. O código é a manutenção versionada do GeoJSON local, com recusa explícita fora do PostGIS.

## Evidências
- `GeoLayerController`
- `ImportGeoLayerRequest`
- `GeoJsonLayerImporter`
- `GeoLayerType`, `GeoLayerStatus`
- Rotas `gestao.territorio.camadas.index` e `store`

## Não evidenciado
- Exclusão de versão já importada.
- Edição de feição individual na tela.
- Publicação da camada no GeoServer a partir deste formulário. O catálogo WFS é a HU-169.

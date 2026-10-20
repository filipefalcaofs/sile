# HU-025 — Manter camadas do GeoServer

## Épica
**EP04 — Georreferenciamento e Território**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Cadastrar, ajustar e ligar ou desligar as camadas WFS do GeoServer SEDUR que a identificação de zona consulta, sem deploy e sem apagar o histórico.

## História de Usuário
**Como** administrador,  
**quero** manter o catálogo de camadas do GeoServer,  
**para** incluir uma zona nova da LOUOS na consulta WFS assim que a camada existir no servidor.

## Atores e permissões
- Permissão `manter-territorio`, somente o papel administrador.
- Menu: Território → Camadas do GeoServer (`/gestao/territorio/geoserver`).
- A consulta da zona no processo usa as linhas ativas. Quem só tem `consultar-territorio` não edita este catálogo.

## Fluxo evidenciado
1. `GET /gestao/territorio/geoserver` lista todas as camadas, sem paginação, ordenadas por `ordem` e `id`. Cada item traz workspace, nome do tipo, nome completo, rótulo, ordem e se está ativa.
2. `POST` cria a camada com os campos validados. A situação padrão é ativa quando o campo não vem no formulário.
3. `PUT` atualiza rótulo, ordem e, se enviado, a situação. Workspace e nome do tipo não entram na validação de edição: o valor enviado é descartado.
4. `PUT .../ativacao` inverte `ativo`. Não exclui o registro. Camada desativada sai do conjunto que o cliente WFS consulta. Camada reativada volta a ser consultada na identificação da zona.
5. A auditoria das alterações é a do model (`HasAuditoria`), não um evento escrito no controller.

## Regras de negócio evidenciadas
- O par workspace + nome do tipo é único e imutável. Trocar a camada é desativar a antiga e cadastrar outra.
- Uma linha ativa é consultada de verdade por `GeoServerWfsZonaClient` quando `geoserver_zona` está ligado. Cadastrar a linha não identifica zona se o parâmetro estiver desligado ou se o WFS não responder.
- Workspace só aceita letras minúsculas, dígitos e sublinhado. O nome do tipo aceita letras, dígitos e sublinhado.

## Validações
- Workspace obrigatório, até 100 caracteres, padrão `^[a-z0-9_]+$`.
- Nome do tipo obrigatório, até 150 caracteres, padrão `^[A-Za-z0-9_]+$`, único no mesmo workspace.
- Rótulo opcional, até 255 caracteres.
- Ordem obrigatória, inteiro de 0 a 65535.
- Situação booleana na criação. Na edição, omitir `ativo` preserva o valor atual.

## Critérios de aceite
**Dado** que o administrador informa workspace, nome do tipo e ordem ainda não cadastrados,  
**Quando** grava a camada,  
**Então** o registro é criado e a mensagem confirma o cadastro.

**Dado** que a camada já existe,  
**Quando** o administrador altera o rótulo ou a ordem,  
**Então** workspace e nome do tipo permanecem os gravados.

**Dado** que o administrador desativa a camada,  
**Quando** a ativação é invertida,  
**Então** o registro continua na lista, deixa de ser consultado na identificação da zona, e a mensagem informa a desativação.

## Divergências da redação anterior
Não havia redação anterior desta HU no épico. A capacidade está no `GeoServerLayerController` e nas rotas `territorio/geoserver`.

## Evidências
- `GeoServerLayerController`
- `StoreGeoServerLayerRequest`, `UpdateGeoServerLayerRequest`
- Model `GeoServerLayer`
- Rotas `gestao.territorio.geoserver.*`
- `GeoServerWfsZonaClient` (consome as camadas ativas)

## Não evidenciado
- Exclusão física da camada.
- Teste de conexão com o GeoServer nesta tela.
- Uso deste catálogo pela classificação viária ou pelas poligonais RT. Essas consultas usam o nome de camada configurado na regra ou na configuração da integração, não a lista desta tela.

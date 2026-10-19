# HU-062 — Informar imóvel

## Épica
**EP08 — Solicitação de Viabilidade**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Gravar, no rascunho, o polígono do imóvel, o endereço informado, o ponto de referência, a inscrição imobiliária opcional e os indicadores do local.

## História de Usuário
**Como** requerente dono do rascunho,  
**quero** demarcar o imóvel e informar o ponto de referência,  
**para** que o processo tenha a localização usada na análise territorial.

## Atores e permissões
- Dono efetivo da solicitação, somente com status rascunho (`ViabilityRequestPolicy::update`).
- Rota `PUT portal/solicitacoes/{solicitacao}/imovel`, com `auth:web`, e-mail verificado, LGPD aceita, representação resolvida e middleware `portal.solicitacao`.

## Fluxo evidenciado
1. O requerente envia o polígono GeoJSON, a área utilizada, o ponto de referência e, se quiser, logradouro, número, complemento, bairro, CEP e inscrição.
2. O sistema calcula o centroide do anel exterior e consulta `TerritoryService::identify`. O resultado vai em flash `territorio`. Sem zona identificada, a resposta degrada sem inventar zona.
3. Na mesma transação grava endereço, indicadores e polígono; `PropertyGeometryWriter` deriva a geometria quando o driver é PostgreSQL; `TerritorioProcessoService::materializar` grava zona, bairro e demais dados já obtidos, sem segunda consulta WFS.
4. A simulação anterior, se existir, é invalidada (`markSimulationStale`).
5. A resposta volta ao wizard com a mensagem de imóvel e área atualizados.

## Regras de negócio evidenciadas
- O polígono GeoJSON é a fonte. Precisa ser `Polygon` com anel exterior de pelo menos quatro pontos, cada um com longitude e latitude numéricas.
- O ponto de referência é obrigatório. Logradouro, número, complemento, bairro, CEP e inscrição são opcionais. O complemento é texto livre.
- Indicadores aceitos: escritório virtual, sede, abrigado, área pública e acesso independente. Chave ausente de sede ou abrigado preserva o valor já gravado; não apaga a resposta anterior.
- A identificação territorial neste passo não recalcula o veredito de viabilidade.
- Mudança de imóvel ou área invalida o snapshot de simulação.

## Validações
- Polígono obrigatório, tipo `Polygon`, coordenadas presentes e numéricas, anel com no mínimo quatro posições.
- `address_reference` obrigatório, até 255 caracteres.
- Campos de endereço e inscrição opcionais, com tamanho máximo.
- Indicadores booleanos somente quando enviados.
- A área (`used_area_m2`) viaja neste mesmo pedido; a regra da área está na HU-063.

## Critérios de aceite
- Dado o dono de um rascunho com polígono válido e ponto de referência, quando salva o imóvel, então o GeoJSON, o endereço e os indicadores ficam gravados e a simulação anterior deixa de valer.
- Dado polígono ausente, tipo diferente de Polygon, anel com menos de quatro pontos ou ponto de referência vazio, quando envia o formulário, então a validação recusa e nada é gravado.
- Dado usuário que não é o requerente efetivo, ou solicitação que não está em rascunho, quando chama a rota, então a policy nega a edição.

## Divergências da redação anterior
A redação anterior situava esta etapa no formulário hospedado pelo Regin/Simplifica. No portal, o imóvel é um `PUT` do rascunho, com polígono obrigatório e endereço textual em grande parte opcional. Não há, neste controller, recepção de dados da Junta.

## Evidências
- `App\Http\Controllers\Portal\SolicitacaoImovelController::update`
- `App\Http\Requests\Portal\UpdateSolicitacaoImovelRequest`
- `App\Services\Solicitacao\PropertyGeometryWriter`
- `App\Services\Geo\TerritoryService`
- `App\Services\Geo\TerritorioProcessoService`
- `routes/portal.php` (`portal.solicitacoes.imovel`)

## Não evidenciado
- Preenchimento do imóvel a partir de mensagem Regin neste controller.
- Obrigatoriedade de logradouro, número, bairro ou CEP no portal.
- Bloqueio do salvamento quando a zona urbanística não é identificada.

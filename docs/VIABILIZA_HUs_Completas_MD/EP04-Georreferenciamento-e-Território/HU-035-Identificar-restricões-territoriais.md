# HU-035 — Identificar restrições territoriais

## Épica
**EP04 — Georreferenciamento e Território**

## Situação na implementação
- **Status:** Parcial
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Listar as feições de restrição que intersectam o ponto e, no processo, avaliar as regras territoriais versionadas RT-01 a RT-04 e as allow-lists RT-05-A e RT-05-B. A observação (c) não está no código.

## História de Usuário
**Como** sistema,  
**quero** saber quais restrições e regras de lugar incidem sobre o imóvel,  
**para** condicionar o parecer ou tirar o processo do fluxo automático quando a regra de lugar mandar.

## Atores e permissões
- Consulta do ponto: `consultar-territorio`.
- A avaliação no processo é do fluxo expresso, sobre a versão vigente do domínio `territorial`.
- Publicar ou editar as regras exige `manter-louos` (rascunho, quatro olhos, ativação e descarte).

## Fluxo evidenciado
1. `TerritoryService::identifyIntersecting` lê a camada vigente do tipo `restricao`. O seed em PostGIS importa `restricoes-ambientais.geojson`, versão `geosalvador-pddu2016-zeis`.
2. Camada bloqueada: status `indisponivel`, lista vazia, sem consulta espacial. Camada vigente sem interseção: `nao_encontrado`. Com interseção: `identificado` e itens com nome e propriedades.
3. Cada item identificado vira condicionante do tipo `restricao` no enquadramento da LOUOS. O texto do código cita ZEIS como exemplo. Isso rebaixa o consolidado para `permitido_com_condicoes` quando o Quadro 10 não proibiu o uso. Não decide o risco sozinho.
4. `TerritorialRuleService::avaliar` lê só as regras ativas da versão vigente e devolve `indeferir`, `encaminhar_analise` ou `sem_incidencia`.
5. RT-01 (`logradouro_cnae`): CNAE da solicitação que não está marcado para exclusão e que consta da regra, mais o mesmo logradouro (CODLOG materializado ou, sem CODLOG, nome normalizado). Casa também o logradouro tributário da inscrição. Cadastro imobiliário indisponível, com CNAE da regra, encaminha à análise em vez de descartar a regra. O indeferimento tem precedência e encerra a avaliação. O texto usa o template `territorial.indeferimento.logradouro_cnae` com alvo e fundamento da própria regra.
6. RT-02, RT-03 e RT-04 (`poligonal_analise` e `poligonal_allowlist_expresso`): ponto dentro da camada WFS da regra encaminha à análise com `motivo_analise`. A allow-list de endereços (Itaigara no seed) não defere: endereço da lista apenas não encaminha por essa regra. Ponto ausente, camada vazia ou WFS indisponível encaminha à análise e registra a indisponibilidade. Se o único encaminhamento vier de camada que não respondeu, o veredito marca `aguardarCamada`.
7. O seed vigente cria RT-01 (logradouro 212), RT-02-VELA-BRANCA, RT-02-ITAIGARA, RT-03-ZEM, RT-03-ZPAM, RT-03-ABM (trechos 5 e 6), RT-04-ZUE, RT-05-A e RT-05-B. RT-05-A e RT-05-B não passam pelo loop de `avaliar`: o enquadramento consulta `observacaoQuadro10Permite`.
8. O seed declara que RT-05 (c) não é carregada. Não há código `RT-05-C` nem tipo de regra para “depósitos de produtos perigosos”.
9. Incidência de indeferir ou encaminhar é auditada em `regras` / `regra-territorial`, com a versão do dado. Com ponto, a etapa `regra_territorial` fica ok ou indisponível.

## Regras de negócio evidenciadas
- Sem versão vigente do domínio `territorial`, o veredito é `sem_incidencia`.
- CNAE com intenção de excluir não conta para a RT-01.
- Número de porta da allow-list compara os dígitos iniciais (“135 A” casa com “135”), sem zeros à esquerda.
- Estar na allow-list de Itaigara não produz deferimento. Só devolve a solicitação ao fluxo seguinte.
- O gatilho de risco `zeis_especial` não é preenchido por esta identificação. A consulta prévia não envia gatilhos de contexto ao classificador.

## Validações
- Regra de poligonal sem nome de camada, ou WFS indisponível, não é tratada como “fora da área”.
- Allow-list de endereço sem número de porta no processo não considera o endereço como da lista.

## Critérios de aceite
**Dado** que a camada de restrição vigente intersecta o ponto,  
**Quando** o território é identificado e o enquadramento consolida um uso não proibido,  
**Então** cada feição vira condicionante de restrição e o resultado pode ser `permitido_com_condicoes`.

**Dado** que a RT-01 vigente casa CNAE e logradouro,  
**Quando** o processo é avaliado,  
**Então** o veredito é `indeferir`, com o texto legal da regra, e as poligonais seguintes não são aplicadas.

**Dado** que o ponto está na poligonal de uma regra ativa e fora da allow-list, quando houver lista,  
**Quando** a camada WFS responde,  
**Então** o veredito é `encaminhar_analise` com o motivo gravado na regra.

**Dado** que a regra RT-05 (c) é procurada no seed e no avaliador,  
**Quando** se lê o código,  
**Então** ela não existe: só RT-05-A e RT-05-B.

## Divergências da redação anterior
A redação anterior falava em restrições territoriais sem separar a camada de interseção, as regras RT e a ausência da observação (c).

## Evidências
- `TerritoryService::identifyIntersecting`
- `GeoLayerSeeder` (`restricoes-ambientais.geojson`)
- `TerritorialRuleService`, `TerritorialRuleType`, `TerritorialRuleSeeder`
- `LouosEnquadramentoService::avaliarRestricoes`
- `FluxoExpressoService` (avaliação territorial antes do risco)

## Não evidenciado
- RT-05 (c), depósitos de produtos perigosos, ou qualquer regra com esse código.
- Ligação automática da feição ZEIS ao gatilho `zeis_especial` do classificador de risco.
- Deferimento porque o endereço está na allow-list de uma poligonal.

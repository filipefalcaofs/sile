# HU-037 — Validar localização do imóvel

## Épica
**EP04 — Georreferenciamento e Território**

## Situação na implementação
- **Status:** Parcial
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Comparar o polígono informado com o lote oficial e alertar quando a sobreposição fica abaixo do limiar. Enquanto a camada de lote está pendente, a comparação não roda. No processo, divergência de zona entre o desenho e a fonte oficial encaminha à análise.

## História de Usuário
**Como** servidor que consulta o território,  
**quero** saber se o polígono informado cobre o lote oficial,  
**para** não aceitar um desenho deslocado sem registro.

## Atores e permissões
- `POST /gestao/territorio/validar-localizacao`: `consultar-territorio`.
- O limiar `geo.validacao.sobreposicao_minima` é parâmetro (padrão 50, inteiro de 1 a 100).
- A divergência de zona no expresso é decisão do sistema, não uma ação do requerente.

## Fluxo evidenciado
1. O endpoint recebe um GeoJSON `Polygon` e chama `LocationValidationService::validate`.
2. Se não há camada de lote ou ela está `pendente_fonte`, o resultado é `indisponivel`, sobreposição nula, alerta falso, motivo de base de lotes (inscrição imobiliária) pendente. Nenhuma consulta espacial ocorre. Esse é o estado do seed.
3. Com camada de lote vigente, o SQL calcula `ST_Area(ST_Intersection) / ST_Area(polígono)` da feição mais sobreposta. Sem interseção, o percentual é 0.
4. Percentual abaixo do limiar: status `alerta_sobreposicao` e `alerta` verdadeiro. No limiar ou acima: `validado` e alerta falso.
5. O controller audita `validacao-localizacao` com o resultado e o alerta.
6. No rascunho, a área declarada é comparada à área do próprio polígono, com tolerância. Acima da tolerância há alerta auditado (`imovel-area-inconsistente`). Não bloqueia o salvamento. O comentário do controller registra que a comparação com o lote oficial degrada enquanto o lote está pendente.
7. No expresso, `LocalizacaoDivergenciaService` encaminha à análise, com gatilho `localizacao_divergente`, quando o polígono desenhado cai em zona diferente da zona do endereçamento ou da zona da inscrição. Isso ocorre depois do indeferimento territorial por logradouro e antes do encaminhamento das poligonais.

## Regras de negócio evidenciadas
- A decisão do limiar é percentual estritamente menor que o limiar.
- Indisponibilidade do lote não gera alerta falso de sobreposição baixa.
- A data opcional de `validate` usa a camada de lote vigente naquela data. O endpoint da gestão não envia data: usa a vigente agora.

## Validações
- `polygon` obrigatório, tipo `Polygon`, com `coordinates`.
- A topologia fica com o PostGIS. O request só exige a estrutura mínima do GeoJSON.

## Critérios de aceite
**Dado** que a camada de lote está `pendente_fonte`,  
**Quando** um polígono é enviado para validação,  
**Então** o status é `indisponivel`, a sobreposição é nula, o alerta é falso e não há consulta espacial.

**Dado** que existe camada de lote vigente e a maior sobreposição é menor que o limiar,  
**Quando** o polígono é validado,  
**Então** o status é `alerta_sobreposicao` e a auditoria guarda o alerta.

**Dado** que a sobreposição é igual ou maior que o limiar,  
**Quando** o polígono é validado,  
**Então** o status é `validado`.

**Dado** que a zona do desenho diverge da zona da inscrição ou do endereçamento,  
**Quando** o fluxo expresso avalia a localização,  
**Então** o processo vai à análise com o gatilho `localizacao_divergente`.

## Divergências da redação anterior
A redação anterior tratava a validação contra o lote como disponível. O código só calcula a sobreposição com camada de lote vigente. O seed deixa essa camada pendente.

## Evidências
- `LocationValidationService`
- `TerritoryController::validateLocation`
- `ValidateLocationRequest`
- Parâmetro `geo.validacao.sobreposicao_minima`
- `SolicitacaoImovelController` (alerta de área do polígono)
- `FluxoExpressoService` e `TipoGatilho::LocalizacaoDivergente`

## Não evidenciado
- Bloqueio do salvamento do rascunho por sobreposição baixa com o lote oficial.
- Cálculo da sobreposição em PHP. A área da interseção é SQL no PostGIS.
- Identificação do lote oficial da cidade. Ver HU-033.

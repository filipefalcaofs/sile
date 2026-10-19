# HU-050 — Aplicar regra de alto risco

## Épica
**EP06 — Classificação de Risco**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Encaminhar o nível `alto` à análise, no mapa padrão, e impedir decisão automática do expresso quando o processo tem alto risco fora de um ramo de planilha marcado como expresso.

## História de Usuário
**Como** sistema,  
**quero** reconhecer o CNAE de alto risco e não deferi-lo no automático,  
**para** a análise técnica receber o caso, salvo o ramo da planilha que declara fluxo expresso.

## Atores e permissões
- Nível gravado na versão municipal (`manter-cnaes` ou importação) e destino no parâmetro `risco.mapa_encaminhamento`.
- O bloqueio do expresso é do `FluxoExpressoService`, sobre o resultado já classificado.

## Fluxo evidenciado
1. `RiscoMunicipal::Alto` vale `alto`, rótulo “Alto”, severidade 3. `fromDecreto` aceita `ALTO`.
2. O mapa padrão associa `alto` a `analise`. O motivo cita o nível e a dimensão decisiva.
3. Sanitário `alto` segue o mesmo mapa somente se a dimensão decisiva for `sanitario`.
4. Ramo da planilha com fluxo `expresso` grava encaminhamento expresso mesmo quando o texto do ramo fala em alto risco. O comentário do expresso cita a regra 51 da planilha como exemplo de ramo curado assim. A planilha prevalece sobre o gate.
5. No expresso, depois do veto locacional (`nao_permitido` é emitido mesmo em alto risco), `temAltoRiscoForaDoExpresso` encaminha à análise com o texto `expresso.motivo.alto_risco`. O processo não é deferido nem indeferido por ser alto.
6. Gatilho ativo também força análise, com o motivo do gatilho, antes de chegar a esse gate.
7. A fundamentação municipal inclui as frases de condicionante importadas da linha. Elas não alteram o nível para alto. O código do expresso menciona pergunta condicional que eleve o risco; no classificador, quem eleva o nível municipal é o ramo resolvido da planilha (`municipalDoRamo`), não a resposta de `RiskCondicionante`.

## Regras de negócio evidenciadas
- Alto risco não é, por si, indeferimento. Indeferimento locacional do Quadro 10 ou do 11A ocorre antes e independe do risco.
- Sem veto locacional, alto risco fora do expresso explícito da planilha vai à análise.
- Alterar o mapa para `alto` → `expresso` mudaria o encaminhamento do classificador. O gate do expresso ainda olha alto risco fora do ramo expresso.

## Validações
- Célula diferente de `ALTO` não vira `alto` na importação municipal.
- A chave `alto` é obrigatória no mapa, com valor `expresso` ou `analise`.

## Critérios de aceite
**Dado** que o nível decisivo é `alto`, o mapa padrão está vigente e não há ramo expresso da planilha,  
**Quando** o motor classifica,  
**Então** o fluxo é `analise`.

**Dado** que o consolidado da LOUOS é `nao_permitido`,  
**Quando** o expresso decide,  
**Então** o indeferimento locacional é emitido antes do gate de alto risco.

**Dado** que não há veto locacional e o processo tem alto risco fora do ramo expresso da planilha,  
**Quando** o expresso decide,  
**Então** o processo vai à análise e não é deferido nem indeferido por esse motivo.

## Divergências da redação anterior
A redação anterior não separava o mapa `alto` → `analise`, o veto locacional que indefere antes, e a exceção do ramo da planilha marcado como expresso.

## Evidências
- `RiscoMunicipal::Alto`
- `RiscoClassificationService::resolveEncaminhamento` e `municipalDoRamo`
- `FluxoExpressoService` (gate de alto risco depois do `nao_permitido`)
- Parâmetro `risco.mapa_encaminhamento`

## Não evidenciado
- Indeferimento automático só porque o risco é alto.
- Reclassificação para alto por pergunta da VISA. O nível sanitário final permanece o da tabela.

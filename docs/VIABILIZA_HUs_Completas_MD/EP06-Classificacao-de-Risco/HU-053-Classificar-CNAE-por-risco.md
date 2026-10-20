# HU-053 — Classificar CNAE por risco

## Épica
**EP06 — Classificação de Risco**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Classificar o CNAE em duas dimensões separadas — municipal e sanitária — e indicar o encaminhamento (expresso ou análise) pela dimensão decisiva, pela planilha de tratamento ou por gatilho.

## História de Usuário
**Como** sistema,  
**quero** classificar o CNAE com a versão vigente de cada dimensão,  
**para** saber se o caso é elegível ao fluxo expresso ou se vai à análise, com a versão registrada.

## Atores e permissões
- A classificação é serviço interno (`RiscoClassificationService::classify`), usado pela consulta de viabilidade e pelo comando de classificação.
- A ficha do CNAE, que grava o nível municipal, exige `manter-cnaes` para editar e `consultar-cnaes` para a listagem.
- O mapa de encaminhamento e a dimensão decisiva são parâmetros (`risco.mapa_encaminhamento` e `risco.dimensao_tvl`).

## Fluxo evidenciado
1. O CNAE é reduzido a dígitos.
2. Dimensão municipal: versão vigente de `risco_municipal`, ou a vigente na data. Busca `risk_classifications` por versão e CNAE. Acha: status `classificado`, nível `baixo_a`, `baixo_b` ou `alto`, rótulo, condicionantes da linha e versão. Não acha, ou não há versão: `nao_classificado`, nível nulo.
3. Dimensão sanitária: versão de `risco_sanitario` e `sanitary_risk_classifications`. Níveis `baixo`, `medio` e `alto`. O nível final é o da tabela. `reclassificado` sai sempre falso e a lista de perguntas sai vazia.
4. Se o ramo da planilha (`TratamentoRamoResolver`) resolve com CNAE de 7 dígitos, o nível municipal exibido passa a ser o risco do ramo. A versão da dimensão municipal não é trocada pela versão da planilha.
5. Encaminhamento: com ramo resolvido, o fluxo do ramo (`expresso` ou análise). Sem ramo, usa a dimensão do parâmetro `risco.dimensao_tvl` (padrão `municipal`). Nível ausente ou fora do mapa cai em `analise`.
6. Gatilho ativo presente no contexto força `analise`, com o motivo do gatilho.
7. A fundamentação cita o texto `base_legal.risco_municipal` e as condicionantes textuais da linha municipal quando a dimensão está classificada. Nada da VISA entra nessa lista.
8. Auditoria `risco` / `classificacao` grava CNAE, dimensão decisiva, níveis, fluxo, gatilhos e versões.

## Regras de negócio evidenciadas
- Nível municipal desconhecido no CSV é rejeitado por `RiscoMunicipal::fromDecreto`. Os rótulos aceitos são `BAIXO A`, `BAIXO B` e `ALTO`.
- Médio risco municipal é o nome da SEDUR para `baixo_b`. A chave gravada continua `baixo_b`.
- A dimensão sanitária tem a própria chave `medio`, também rotulada Médio risco. Ela não substitui o Médio risco municipal.
- CNAE sem classificação não recebe nível inventado e o encaminhamento vai à análise.
- A importação municipal guarda condicionantes como texto. O classificador não usa essas frases para mudar o nível.

## Validações
- Máscara do CNAE é ignorada na busca: valem os dígitos.
- Ramo da planilha só é resolvido com 7 dígitos.
- Mapa de encaminhamento precisa das chaves `baixo_a`, `baixo_b` e `alto`, com valor `expresso` ou `analise`.

## Critérios de aceite
**Dado** que o CNAE tem classificação na versão municipal vigente,  
**Quando** o motor classifica,  
**Então** devolve nível `baixo_a`, `baixo_b` ou `alto`, a versão, e um fluxo expresso ou análise.

**Dado** que não há versão ou linha para o CNAE na dimensão decisiva, e a planilha não resolve ramo,  
**Quando** o motor classifica,  
**Então** o status é `nao_classificado`, o nível é nulo e o fluxo é `analise`.

**Dado** que a dimensão sanitária está classificada,  
**Quando** o resultado é lido,  
**Então** `nivel_final` é igual a `nivel_original` e `reclassificado` é falso.

## Divergências da redação anterior
A redação anterior não separava risco municipal e sanitário. No municipal, Médio risco é o nome de `baixo_b`. No sanitário, Médio risco é a chave `medio`.

## Evidências
- `RiscoClassificationService`
- `RiscoMunicipal`, `RiscoSanitario`
- `RiscoMunicipalImportService`, `RiscoSanitarioImportService`
- Parâmetros `risco.mapa_encaminhamento` e `risco.dimensao_tvl`
- `ConsultaViabilidadeService::riscoInput`

## Não evidenciado
- Chave municipal `medio` além de `baixo_b`. O termo Médio risco permanece e aponta para `baixo_b`.
- Reclassificação do nível sanitário por resposta de condicionante.
- Perguntas da VISA na ficha do CNAE. O controller registra que essa validação foi retirada.

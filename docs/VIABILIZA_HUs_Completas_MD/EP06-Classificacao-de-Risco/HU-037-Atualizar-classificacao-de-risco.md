# HU-037 — Atualizar classificação de risco

## Épica
**EP06 — Classificação de Risco**

## Situação na implementação
- **Status:** Parcial
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Atualizar o nível municipal do CNAE. A ficha grava na versão vigente já publicada, sem quatro olhos. A carga da planilha oficial grava numa versão de regra e rejeita nível desconhecido. Não há publicação de nível novo pela ficha.

## História de Usuário
**Como** mantenedor de CNAEs,  
**quero** gravar o risco municipal do código na versão vigente,  
**para** o motor passar a ler esse nível na próxima classificação.

## Atores e permissões
- Criar e editar CNAE, inclusive o nível: `manter-cnaes`.
- Importação oficial: `RiscoMunicipalImportService` sobre uma `RuleVersion` de `risco_municipal`. O controller afirma que a publicação em lote com quatro olhos permanece nesse import, não na ficha.
- O domínio `risco_municipal` é sensível no enum (`isSensitive`). A ficha não usa esse fluxo de quatro olhos.
- Gatilho ligado ou desligado: `manter-gatilhos-risco`. Mudar o gatilho altera o roteamento e fica auditado.

## Fluxo evidenciado
1. Na criação do CNAE, se não há versão municipal vigente, a gravação volta com erro e nada é criado.
2. Com versão vigente, o CNAE é criado e `RiskClassification::updateOrCreate` grava `risco_municipal` nessa versão e nesse código.
3. Na edição, o mesmo `updateOrCreate` atualiza a linha vigente. Troca de código do CNAE realoca as referências lógicas antes de gravar o nível no código novo.
4. A ficha não grava condicionantes, observação nem risco sanitário. O import municipal faz upsert de `risco_municipal` e `condicionantes` e não altera `observacao`.
5. A importação exige cabeçalho `cnae,descricao,condicionantes,risco_municipal_unificado`. Código que não tem 7 dígitos, duplicado ou com nível fora de `BAIXO A`, `BAIXO B` e `ALTO` entra no relatório de rejeitados e não é gravado.
6. Classificação sanitária tem importador próprio (`RiscoSanitarioImportService`), níveis `baixo`, `medio` e `alto`, também ligados a `rule_version_id`. A ficha do CNAE não atualiza essa tabela.
7. Reproduzir uma decisão antiga continua possível: `classify` com data usa `RuleVersion::naData`. A edição na ficha não abre essa versão histórica. Ela altera a vigente.
8. Desligar um gatilho não apaga o registro. O motor deixa de aplicá-lo.

## Regras de negócio evidenciadas
- Atualização pela ficha é imediata na versão vigente. A próxima classificação sem data já lê o valor novo.
- Nível municipal que não seja as três chaves não é aceito na importação.
- Condicionante importada não reclassifica. Atualizar o texto da condicionante não muda `baixo_a`, `baixo_b` ou `alto`.
- Sem versão vigente, não há onde gravar o nível e a operação é recusada.

## Validações
- `risco_municipal` na ficha é um caso do enum.
- CSV com cabeçalho diferente interrompe a importação.
- Linha rejeitada não cria classificação.

## Critérios de aceite
**Dado** que há versão municipal vigente,  
**Quando** o mantenedor salva o CNAE com `baixo_a`, `baixo_b` ou `alto`,  
**Então** a linha dessa versão e desse código fica com o nível informado.

**Dado** que não há versão vigente,  
**Quando** o mantenedor tenta criar ou editar o CNAE com nível,  
**Então** a operação é recusada e a mensagem diz que não há versão vigente.

**Dado** que o CSV traz nível diferente de `BAIXO A`, `BAIXO B` e `ALTO`,  
**Quando** a importação municipal roda,  
**Então** a linha é rejeitada e nenhum nível extra é criado.

**Dado** que a ficha é salva,  
**Quando** se observa o que foi gravado,  
**Então** não nasce versão nova nem há segundo usuário publicando.

## Divergências da redação anterior
A redação anterior descrevia atualização versionada genérica. A ficha altera a versão vigente no lugar. A versão nova com quatro olhos não é o caminho desta tela.

## Evidências
- `CnaeController::store` e `update` (comentário: sem quatro olhos na ficha)
- `RiscoMunicipalImportService`
- `RiscoSanitarioImportService`
- `RuleDomain::RiscoMunicipal` (`isSensitive`)
- `RiskTriggerController`

## Não evidenciado
- Quatro olhos na edição do nível pela ficha do CNAE.
- Atualização do risco sanitário na mesma ficha.
- Publicação que transforme condicionante em outro nível de risco.

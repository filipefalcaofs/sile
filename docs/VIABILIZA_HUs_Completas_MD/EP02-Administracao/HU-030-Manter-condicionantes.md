# HU-030 — Manter condicionantes

## Épica
**EP02 — Administração**

## Situação na implementação
- **Status:** Parcial
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
A redação anterior pedia um cadastro próprio de condicionante (pergunta, regra, crítica e textos de parecer). O código não tem esse CRUD. Condicionantes aparecem como linhas da planilha de tratamento e como referência no Quadro 10.

## História de Usuário
**Como** consulente do motor com `consultar-cnaes`,
**quero** ver as condicionantes da planilha de tratamento vigente,
**para** saber quais textos acompanham a versão publicada.

Não há, no código, o papel de cadastrar pergunta reutilizável, regra numérica e crítica em tela própria.

## Atores e permissões
- A lista de condicionantes sai na aba Enquadramento do hub, junto com a planilha (HU-027). Leitura: `consultar-cnaes`. Troca da lista: importar e publicar a planilha com `manter-louos`.
- No rascunho do Quadro 10, o campo `condicionante_ref` é texto opcional de até 50 caracteres, gravado com `manter-louos`.
- A ficha do CNAE não edita condicionante sanitária.

## Fluxo evidenciado
1. `LouosController::dadosEnquadramento` lê `TratamentoCondicionante` da versão vigente de `risco_tratamento`, ordenado por número, com `numero`, `texto` e `observacao`.
2. A contagem `condicionantes` entra no resumo da aba.
3. Essas linhas mudam quando uma planilha nova é importada e publicada. Não há rota de criar, editar ou excluir uma condicionante isolada.
4. A linha do Quadro 10 pode guardar `condicionante_ref`. O enum `Quadro10Permissao` documenta que `permitido_condicionado` remete a essa referência, resolvida pelo motor no consolidado.
5. `RuleDomain::Condicionante` existe e `isSensitive()` devolve falso. Não há controller de manutenção desse domínio.
6. O `CnaeController` declara que a validação de condicionantes da VISA foi retirada da ficha.

## Regras de negócio evidenciadas
- A condicionante da planilha é dado da versão de `risco_tratamento`, não um catálogo editável linha a linha.
- A referência no Quadro 10 é texto livre limitado, não uma chave estrangeira validada contra um cadastro de condicionantes.
- Não há versionamento próprio de «decisões já tomadas mantêm a condicionante da época» nesta tela. O que é versionado é a planilha inteira e o Quadro 10.

## Validações
- Não há Form Request de condicionante.
- Na linha do Quadro 10, `condicionante_ref` é opcional, string, máximo 50.

## Critérios de aceite
### CA-01 — Leitura na aba Enquadramento
**Dado** uma versão vigente de `risco_tratamento` com condicionantes,
**Quando** um usuário com `consultar-cnaes` abre a aba Enquadramento,
**Então** recebe número, texto e observação de cada condicionante dessa versão.

### CA-02 — Sem CRUD isolado
**Dado** as rotas de `routes/gestao.php`,
**Quando** se procura um recurso de condicionante com store, update ou destroy,
**Então** não há controller nem rota de manutenção própria. A alteração vem da importação da planilha.

### CA-03 — Referência no Quadro 10
**Dado** um rascunho do Quadro 10,
**Quando** o mantenedor grava `condicionante_ref`,
**Então** o texto de até 50 caracteres fica na linha do rascunho, sem consulta a um cadastro de perguntas.

## Divergências da redação anterior
A redação anterior exigia pergunta reutilizável em select, regra numérica, crítica em select, textos de parecer e efeito de reclassificação de risco, no modelo do SAPS. Esse cadastro não está implementado. A ficha do CNAE também não mantém mais a pergunta sanitária. O que permanece é a lista importada na planilha e a referência textual no Quadro 10.

## Evidências
- `LouosController::dadosEnquadramento` (`TratamentoCondicionante`).
- `app/Models/TratamentoCondicionante.php` consumido só nessa leitura e no import da planilha.
- `LouosLinhaRules` campo `condicionante_ref`.
- `CnaeController::edit`: sem condicionantes sanitárias.
- `RuleDomain::Condicionante` sem rotas de manutenção em `routes/gestao.php`.

## Não evidenciado
- CRUD de pergunta, regra, crítica e texto de parecer.
- Select de perguntas reutilizáveis.
- Versionamento isolado da condicionante, separado da versão da planilha ou do Quadro 10.
- Reclassificação de risco pela resposta do requerente nesta tela de administração.

# HU-027 — Manter Quadro 7

## Épica
**EP02 — Administração**

## Situação na implementação
- **Status:** Parcial
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Manter o enquadramento da atividade (código LOUOS, faixas de área, classificação e código TLL) pela planilha de tratamento, no hub Motor de regras.

## História de Usuário
**Como** mantenedor com `manter-louos`,
**quero** importar a planilha de regras de tratamento e publicar o rascunho,
**para** o enquadramento vigente passar a ser o da planilha importada.

## Atores e permissões
- Consultar a aba Enquadramento do hub (`/gestao/louos?aba=enquadramento`) exige `consultar-cnaes`. Quem tem só `consultar-louos` não abre essa aba.
- Importar (`louos.enquadramento.importar`) e publicar o rascunho (`louos.enquadramento.publicar`) exigem `manter-louos`.
- Menu Regras, item Motor de regras (`/gestao/louos`). A rota antiga `/gestao/regras-tratamento` redireciona para a aba.

## Fluxo evidenciado
1. A aba mostra a versão vigente do domínio `risco_tratamento`: contagens, perguntas, regras, ramos, condicionantes, itens CNLU, notas, pendências da versão vigente e a lista paginada de enquadramentos.
2. A busca da lista atinge CNAE, denominação, código LOUOS, denominação LOUOS, subcategoria, segundo enquadramento, terceiro enquadramento e risco.
3. Cada linha de enquadramento expõe CNAE, denominação, código e denominação LOUOS, subcategoria, grupo, risco, `ate_m2`, segundo enquadramento, `ate_m2_2`, terceiro enquadramento, `acima_m2`, classificação, código TLL e regra.
4. O import recebe um `.xlsx` de até 20 MB, abre rascunho e informa quantas regras ficaram sem ramo explícito. Arquivo já importado ou já vigente não gera versão nova.
5. Publicar só aceita rascunho desse domínio. A chamada usa `fourEyes: false`: o autor do rascunho pode publicar.

## Regras de negócio evidenciadas
- Não existe domínio `louos_quadro7` em `RuleDomain`. O enquadramento por faixa de área está na planilha de tratamento (`TratamentoEnquadramento`), não num quadro versionado próprio.
- Não há inclusão, edição ou exclusão de linha avulsa desta aba. A manutenção é o arquivo.
- A publicação desta planilha não exige publicador diferente do autor, embora `RuleDomain::RiscoTratamento` esteja marcado como sensível.
- Pendências do rascunho (regra e motivo) são listadas à parte das pendências da versão vigente.

## Validações
- `arquivo` obrigatório, arquivo, MIME `xlsx`, tamanho máximo 20.480 KB.
- Mensagens: «Envie a planilha de regras.» e «A planilha precisa ser um arquivo .xlsx.»
- Publicar versão de outro domínio ou que não seja rascunho devolve «Só um rascunho da planilha de tratamento pode ser publicado.»

## Critérios de aceite
### CA-01 — Consulta da vigente
**Dado** um usuário com `consultar-cnaes` e uma versão vigente de `risco_tratamento`,
**Quando** abre a aba Enquadramento,
**Então** vê a versão, as contagens e as linhas com código LOUOS, faixas de área e código TLL.

### CA-02 — Importar planilha nova
**Dado** um usuário com `manter-louos` e um `.xlsx` ainda não importado,
**Quando** envia o arquivo,
**Então** abre rascunho e a versão vigente permanece até a publicação.

### CA-03 — Arquivo repetido
**Dado** um arquivo cujo conteúdo já gerou versão,
**Quando** o mantenedor importa de novo,
**Então** nenhuma versão nova é criada e a mensagem informa que o arquivo já está vigente ou já foi importado.

### CA-04 — Publicar sem segundo usuário
**Dado** um rascunho da planilha,
**Quando** o autor publica,
**Então** a versão entra em uso. O controller não exige outro publicador.

## Divergências da redação anterior
A redação anterior pedia o Quadro 7 da LOUOS como cadastro por código LOUOS, com até três enquadramentos por faixa de área e opção «Qualquer Área», e exigia encaminhar à análise técnica quando não houvesse regra. O domínio `louos_quadro7` não está no código atual. O que existe é a aba Enquadramento da planilha de tratamento, com as colunas `ate_m2`, `ate_m2_2` e `acima_m2`. A regra de encaminhamento quando falta enquadramento é comportamento do motor e dos gatilhos (HU-036), não desta tela. A publicação desta planilha não usa quatro olhos.

## Evidências
- `app/Enums/RuleDomain.php`: `RiscoTratamento`, sem caso de Quadro 7.
- `app/Http/Controllers/Gestao/LouosController.php`: aba `enquadramento` e `dadosEnquadramento`.
- `app/Http/Controllers/Gestao/TratamentoPlanilhaController.php`.
- `app/Http/Requests/Gestao/ImportarPlanilhaTratamentoRequest.php`.
- `routes/gestao.php`: `louos.enquadramento.importar` e `louos.enquadramento.publicar`.

## Não evidenciado
- CRUD de linha do antigo Quadro 7.
- Quatro olhos na publicação da planilha.
- Domínio versionado `louos_quadro7`.

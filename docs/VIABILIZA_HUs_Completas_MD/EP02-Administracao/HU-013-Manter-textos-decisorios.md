# HU-013 — Manter textos decisórios

## Épica
**EP02 — Administração**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Editar o texto e a descrição das chaves que o motor já emite em documento oficial.

## História de Usuário
**Como** administrador com `manter-parametros`,
**quero** corrigir o template e a descrição de um texto decisório existente,
**para** o documento passar a usar o texto novo sem deploy e sem chave órfã.

## Atores e permissões
- A tela exige `manter-parametros`. Menu Configuração, item Textos decisórios (`/gestao/textos-decisao`).
- Não há criação, exclusão nem ativação.

## Fluxo evidenciado
1. A lista agrupa os textos pelo prefixo da chave, sem paginação. Os rótulos de grupo são bases legais, LOUOS, consulta pública, análise técnica, justificativa fundamentada, explicação da decisão, regras territoriais, localização do imóvel, zona urbanística, planilha de tratamento e fluxo expresso. Prefixo sem rótulo aparece com o próprio prefixo.
2. Cada texto traz chave, template e descrição. A descrição carrega o contrato de placeholders.
3. Atualizar grava template e descrição da chave que já existe. Chave desconhecida na URL responde 404. A chave enviada no corpo é ignorada.
4. O model audita a alteração e invalida o cache do `DecisionTextCatalog` na escrita.

## Regras de negócio evidenciadas
- A chave é ligada ao ponto do motor que a lê. A tela não cria chave sem esse ponto e não apaga chave em uso.
- A alteração vale na leitura seguinte do catálogo, porque o cache é invalidado na gravação.
- Novas chaves entram pelo motor e pelo seed, não por esta tela.

## Validações
- `template` obrigatório, string.
- `description` obrigatória, até 255 caracteres.

## Critérios de aceite
### CA-01 — Editar chave existente
**Dado** um texto já semeado e um usuário com `manter-parametros`,
**Quando** grava outro template e outra descrição,
**Então** a chave permanece e o template vigente passa a ser o enviado.

### CA-02 — Chave desconhecida
**Dado** uma chave que não existe em `decision_texts`,
**Quando** a atualização usa essa chave na rota,
**Então** a resposta é 404.

### CA-03 — Sem cadastro novo
**Dado** as rotas `textos-decisao`,
**Quando** se procura store, destroy ou ativação,
**Então** só existem a listagem e a atualização.

## Divergências da redação anterior
Não havia HU. A capacidade existia no código sem rastreabilidade.

## Evidências
- `routes/gestao.php`: `textos-decisao.index` e `textos-decisao.update` com binding `{decisionText:key}`.
- `app/Http/Controllers/Gestao/DecisionTextController.php`.
- `app/Http/Requests/Gestao/UpdateDecisionTextRequest.php`.
- `resources/js/navigation/gestao-nav.ts`: Textos decisórios em Configuração.

## Não evidenciado
- Inclusão, exclusão ou desativação de chave pela tela.
- Minutas do parecer. Esse catálogo é a HU-014.

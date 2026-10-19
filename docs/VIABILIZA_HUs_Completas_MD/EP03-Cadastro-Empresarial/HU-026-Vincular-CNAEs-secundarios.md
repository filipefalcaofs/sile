# HU-026 — Vincular CNAEs secundários

## Épica
**EP03 — Cadastro Empresarial**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Substituir o conjunto de CNAEs secundários da empresa pelos CNAEs ativos escolhidos, preservando o principal.

## História de Usuário
**Como** cidadão com vínculo ativo com a empresa,  
**quero** marcar os CNAEs secundários,  
**para** que o cadastro reflita as atividades complementares.

## Atores e permissões
- Iguais às do CNAE principal: portal autenticado e policy `manageCnaes` (vínculo ativo do usuário efetivo).
- A busca de opções é a mesma `GET /portal/cnaes`, somente ativos.

## Fluxo evidenciado
1. No detalhe, o cidadão monta a lista de secundários e salva.
2. `PUT /portal/empresas/{company}/cnaes-secundarios` envia `cnaes` com os identificadores.
3. `CompanyCnaeService::syncSecondaries` sincroniza o conjunto exato: mantém a linha do principal e grava os ids informados como não principais. Lista vazia remove todos os secundários.
4. Auditoria `empresas` / `cnaes-secundarios` com os códigos de antes e de depois.
5. Retorno: "CNAEs secundários atualizados com sucesso."

## Regras de negócio evidenciadas
- O array enviado é o conjunto final, não um acréscimo. O que sair da lista deixa de estar vinculado, salvo o principal.
- O CNAE principal atual não pode constar da lista. Mensagem: "O CNAE principal não pode ser incluído entre os secundários."
- Cada item precisa ser CNAE ativo e sem repetição.

## Validações
`UpdateSecondaryCnaesRequest`:
- `cnaes`: presente e array (vazio é válido).
- Cada item: inteiro, distinto, existente em `cnaes` ativos.
- Mensagens: "Há CNAE inativo ou inexistente na seleção." e "Há CNAE duplicado na seleção."

## Critérios de aceite
### CA-01 — Sincronizar secundários
**Dado** vínculo ativo e uma lista de CNAEs ativos diferente do principal,  
**Quando** o cidadão salva,  
**Então** os secundários passam a ser exatamente essa lista, o principal permanece e a auditoria guarda antes e depois.

### CA-02 — Remover todos
**Dado** vínculo ativo,  
**Quando** o cidadão salva a lista vazia,  
**Então** a empresa fica sem CNAEs secundários.

### CA-03 — Principal na lista ou CNAE inválido
**Dado** a lista contém o principal, um inativo, um inexistente ou um id repetido,  
**Quando** a requisição é enviada,  
**Então** o sistema recusa com a mensagem correspondente.

## Divergências da redação anterior
A redação anterior não descrevia a substituição do conjunto nem a proibição de repetir o principal. O código trata a lista como o conjunto final e recusa incluir o principal entre os secundários.

## Evidências
- `app/Http/Controllers/Portal/CompanyCnaeController.php`
- `app/Services/CompanyCnaeService.php`
- `app/Http/Requests/Portal/UpdateSecondaryCnaesRequest.php`
- `resources/js/pages/portal/empresas/detalhe.tsx`

## Não evidenciado
- Limite máximo de secundários no cadastro da empresa.
- Inclusão de CNAE inativo pela tela do portal.

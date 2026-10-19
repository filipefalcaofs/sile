# HU-152 — Solicitar acesso ao console

## Épica
**EP01 — Identidade, Acesso e Segurança**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Quem entra no console e ainda não tem papel pede um perfil. Um administrador aprova, podendo conceder outro papel, ou recusa com motivo. A decisão fica registrada.

## História de Usuário
**Como** servidor autenticado no console sem papel, **quero** pedir um perfil e justificar o pedido, **para** um administrador liberar meu trabalho. **Como** administrador, **quero** aprovar ou recusar esse pedido na lista de usuários, **para** atribuir o papel com rastro da decisão.

## Atores e permissões
- Pedido: usuário do guard `gestao` com `acessar-gestao` e termo LGPD aceito. POST `/gestao/solicitacao-acesso` (`gestao.solicitacao-acesso.store`). Não há permissão extra. O formulário aparece no painel quando `DashboardController::acessoPendente` encontra a conta sem nenhum papel.
- Decisão: permissão `manter-usuarios`. PUT `/gestao/usuarios/solicitacoes/{accessRequest}/aprovar` e `.../recusar`. Menu Administração > Usuários (`/gestao/usuarios`). A lista de pendências é carregada em `UserManagementController::index`.

Perfis que o solicitante pode escolher: papéis do guard `web` que tenham a permissão `acessar-gestao` e não sejam protegidos (`PerfisSolicitaveis` e `RoleProtection`). O papel `cidadao` e papéis com `protegido` ficam de fora. Na aprovação, o administrador pode informar qualquer papel existente no guard `web`, não só o pedido.

O primeiro acesso AD (HU-002) pode conceder `acessar-gestao` sem atribuir papel. É essa conta sem papel que vê o pedido.

## Fluxo evidenciado
1. No painel, se a conta não tem papel, a seção "Seu acesso ainda não foi liberado" lista os perfis solicitáveis. Se já existe pedido `pendente`, o texto informa o perfil pedido e não mostra o formulário. Se o último pedido está `recusada`, mostra o motivo (`decision_note`) e permite pedir de novo. Se não há perfil solicitável, informa para procurar o administrador.
2. `SolicitacaoAcessoController::store`: se a conta já tem papel, redireciona ao painel e não cria pedido. Se já há pedido em `AccessRequest::pendentes()` daquele usuário, volta com "Você já tem uma solicitação de acesso aguardando decisão."
3. Caso contrário, grava `user_id`, `role_id`, `justificativa` e status `pendente`. Audita `usuarios` / `solicitacao-acesso-criada` com o perfil pedido. Mensagem: "Solicitação enviada. Um administrador vai analisar o pedido."
4. Aprovar (`approve`): se o status não é pendente, "Esta solicitação já foi decidida." Se é pendente, em transação, `syncRoles` com o papel enviado e atualiza a solicitação para `aprovada`, `decided_by_user_id` e `decided_at`. Audita `solicitacao-acesso-aprovada` com perfil pedido, perfil concedido e `personalData`. Mensagem: "Solicitação aprovada e papel atribuído."
5. Recusar (`reject`): a mesma trava de pedido já decidido. Atualiza para `recusada`, autor, data e `decision_note` com o motivo. Audita `solicitacao-acesso-recusada` com o motivo e `personalData`. Mensagem: "Solicitação recusada." Não atribui papel.

## Regras de negócio evidenciadas
- RN-001: só conta sem papel cria solicitação (`store` e `acessoPendente`).
- RN-002: no máximo um pedido pendente por usuário (`store`).
- RN-003: o perfil pedido precisa estar em `PerfisSolicitaveis`. O perfil concedido na aprovação é qualquer papel do guard `web` (`UpdateUserRoleRequest`).
- RN-004: pedido já aprovado ou recusado não é decidido de novo (`approve` e `reject`).
- RN-005: criar, aprovar e recusar geram atividade em `AuditService`. Aprovar e recusar marcam dado pessoal.
- RN-006: a conta fica com um papel (`syncRoles` com um único nome), o modelo usado também em `UserManagementController::updateRole`.

## Validações
`StoreAccessRequestRequest`:

- `role`: obrigatório, texto, `Rule::in` dos nomes de `PerfisSolicitaveis::roles()` (rótulo "perfil").
- `justificativa`: obrigatória, texto, mínimo 10, máximo 500 (rótulo "justificativa").

`UpdateUserRoleRequest` (aprovação): `role` obrigatório, texto, existente em `roles.name` com `guard_name` `web` (rótulo "papel").

`RejectAccessRequestRequest`: `motivo` obrigatório, texto, máximo 300 (rótulo "motivo").

## Critérios de aceite
### CA-01 — Servidor sem papel pede acesso
**Dado** um usuário do console sem papéis e sem pedido pendente, **quando** envia perfil solicitável e justificativa com pelo menos 10 caracteres, **então** nasce `access_requests` pendente e a atividade `solicitacao-acesso-criada` é registrada.

### CA-02 — Pedido duplicado
**Dado** um pedido pendente da mesma conta, **quando** envia outro, **então** nenhum registro novo é criado e a mensagem informa que já há solicitação aguardando decisão.

### CA-03 — Aprovação com outro papel
**Dado** um administrador com `manter-usuarios` e um pedido pendente, **quando** aprova informando um papel existente no guard `web`, **então** a conta fica só com esse papel, o pedido fica `aprovada` e a atividade registra o perfil pedido e o concedido.

### CA-04 — Recusa
**Dado** um pedido pendente, **quando** o administrador recusa com motivo de até 300 caracteres, **então** o status fica `recusada`, o motivo vai para `decision_note`, nenhum papel é atribuído por esse método e a atividade `solicitacao-acesso-recusada` é registrada.

### CA-05 — Decisão repetida
**Dado** um pedido que não está pendente, **quando** alguém tenta aprovar ou recusar de novo, **então** a resposta é "Esta solicitação já foi decidida." e o status não muda.

## Divergências da redação anterior
- Esta história não existia no catálogo do EP01. O comportamento está em `SolicitacaoAcessoController` (`store`, `approve`, `reject`), separado do login AD, que permanece na HU-002.
- Não é autocadastro de cidadão (HU-001) nem troca do próprio nome (HU-007).

## Evidências
- `app/Http/Controllers/Gestao/SolicitacaoAcessoController.php`.
- `app/Http/Controllers/Gestao/DashboardController.php` — `acessoPendente`.
- `app/Http/Controllers/Gestao/UserManagementController.php` — lista `solicitacoes` pendentes.
- `app/Http/Requests/Gestao/StoreAccessRequestRequest.php`, `UpdateUserRoleRequest.php`, `RejectAccessRequestRequest.php`.
- `app/Support/PerfisSolicitaveis.php` e `app/Support/RoleProtection.php`.
- `app/Enums/AccessRequestStatus.php`.
- `routes/gestao.php`.
- `resources/js/pages/gestao/dashboard.tsx` — `SolicitarAcesso`.
- `resources/js/pages/gestao/usuarios/index.tsx` — aprovar e recusar.
- `resources/js/navigation/gestao-nav.ts` — Usuários, permissão `manter-usuarios`.

## Não evidenciado
Não há fila de mais de um aprovador, nem concessão de várias papéis no mesmo pedido. Não há e-mail ao solicitante nestes métodos. O login AD em si não é esta história.

# HU-012 — Manter usuários

## Épica
**EP02 — Administração**

## Situação na implementação
- **Status:** Parcial
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Administrar contas já existentes: papel, situação, mecanismo de autenticação e pedidos de primeiro acesso.

## História de Usuário
**Como** administrador com a permissão `manter-usuarios`,
**quero** localizar contas, trocar o papel, inativar ou reativar, definir se a autenticação é AD ou local e decidir solicitações de acesso,
**para** controlar quem entra no console e no portal.

## Atores e permissões
- Toda a tela exige `manter-usuarios`. Menu Administração, item Usuários (`/gestao/usuarios`).
- O próprio administrador não inativa a própria conta.
- A auditoria dessas ações marca dado pessoal (`personalData: true`).

## Fluxo evidenciado
1. A lista tem duas abas. `gestao` (padrão) mostra a equipe SEDUR: contas AD e papéis com `acessar-gestao`. `portal` mostra usuários externos, nunca contas AD. A busca é por nome ou e-mail. A paginação usa o parâmetro `ui.users.per_page` (15 se ausente).
2. Cada linha expõe nome, e-mail, papéis, data de inativação, CPF mascarado (`***.***.***-` e os dois últimos dígitos), tipo de autenticação e conta AD. O CPF completo não sai na lista nem na exportação.
3. Com `formato` `csv`, `xlsx` ou `pdf`, exporta o conjunto da aba e da busca.
4. A troca de papel sincroniza um único papel do guard `web` e registra papel anterior e novo.
5. A inativação grava `inactivated_at`; a reativação limpa o campo. O atributo fica fora do `fillable` e só é gravado por `forceFill`.
6. A troca de autenticação aceita `ad` ou `local`. Em AD, a conta (`ad_username`) é obrigatória, única e normalizada para minúsculas. Em local, a conta AD é proibida. Se o mecanismo muda, a senha anterior é substituída por um valor aleatório. Na conversão de AD para local, o sistema envia o link de redefinição de senha do console.
7. A mesma tela lista solicitações de acesso pendentes (nome, e-mail, conta AD, perfil pedido, justificativa e data). Aprovar vincula o papel informado e marca a solicitação como aprovada. Recusar exige motivo de até 300 caracteres. Solicitação já decidida não é decidida de novo.

## Regras de negócio evidenciadas
- Uma conta tem um papel por vez (`syncRoles` com um único nome).
- O administrador não inativa a si mesmo. A mensagem é «Você não pode inativar a própria conta.»
- CPF não é exibido em claro nesta tela.
- Não existe, neste controller, cadastro de nome, e-mail, CPF ou senha inicial.
- Conta convertida para local recebe a nova senha pelo fluxo de recuperação do console, não por senha digitada nesta tela.
- Se o tipo e a conta AD enviados já são os atuais, nada é regravado.

## Validações
- Papel: obrigatório e existente em `roles.name` com `guard_name` `web`.
- Inativação: bloqueada quando o alvo é o usuário autenticado.
- `auth_type`: enum `AuthType` (`ad`, `local`).
- `ad_username`: obrigatório se `auth_type` é `ad`; proibido se é `local`; até 100 caracteres; padrão `^[a-zA-Z0-9._-]+$`; único em `users.ad_username`, ignorando a própria conta.
- Recusa de solicitação: `motivo` obrigatório, até 300 caracteres.

## Critérios de aceite
### CA-01 — Abas e CPF mascarado
**Dado** um usuário com `manter-usuarios`,
**Quando** abre a lista na aba `gestao` ou `portal`,
**Então** vê só o conjunto da aba e o CPF apenas com os dois últimos dígitos.

### CA-02 — Um papel e trilha
**Dado** uma conta com papel atual,
**Quando** o administrador grava outro papel existente no guard `web`,
**Então** a conta fica com esse único papel e a auditoria registra papel anterior e novo.

### CA-03 — Anti-lockout
**Dado** o administrador autenticado,
**Quando** tenta inativar a própria conta,
**Então** a inativação é recusada.

### CA-04 — AD para local
**Dado** uma conta autenticada por AD,
**Quando** o administrador muda o tipo para local,
**Então** a conta AD é removida, a senha anterior deixa de valer e o fluxo de redefinição do console é disparado para o e-mail da conta.

### CA-05 — Solicitação já decidida
**Dado** uma solicitação que não está pendente,
**Quando** o administrador tenta aprovar ou recusar,
**Então** a decisão não é reaplicada e a mensagem informa que a solicitação já foi decidida.

## Divergências da redação anterior
A redação anterior tratava «manter usuários» como cadastro genérico, com fluxos de processo, GIS e motor. O código não cria usuário nesta tela e não edita nome, e-mail ou CPF. O que existe é gestão de papel, situação, autenticação e fila de primeiro acesso AD.

## Evidências
- `routes/gestao.php`: `usuarios.index`, `usuarios.papel.update`, `usuarios.inativacao.update`, `usuarios.autenticacao.update`, `usuarios.solicitacoes.aprovar`, `usuarios.solicitacoes.recusar`, todas com `manter-usuarios`.
- `app/Http/Controllers/Gestao/UserManagementController.php` e `SolicitacaoAcessoController.php`.
- `UpdateUserRoleRequest`, `ToggleUserActivationRequest`, `UpdateUserAuthTypeRequest`, `RejectAccessRequestRequest`.
- `resources/js/navigation/gestao-nav.ts`: Usuários em Administração.

## Não evidenciado
- Formulário de criação de conta, edição de identidade ou definição de senha nesta tela.
- Exclusão física de usuário.
- Mais de um papel simultâneo na mesma conta.

# HU-004 — Cadastrar usuário pela gestão

## Épica
**EP02 — Administração**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 19/10/2026, com o código-fonte como referência.

## Objetivo
Criar uma conta local pela gestão, com o papel definido e o convite por e-mail para o usuário definir a própria senha.

## História de Usuário
**Como** administrador com a permissão `manter-usuarios`,
**quero** cadastrar uma conta local com nome, e-mail, CPF, telefone e papel,
**para** que o novo usuário defina a própria senha e entre no console.

## Atores e permissões
- Exige `manter-usuarios` (`POST /gestao/usuarios`). O botão «Novo usuário» fica em Administração, item Usuários (`/gestao/usuarios`).
- A auditoria do cadastro marca dado pessoal (`personalData: true`).

## Fluxo evidenciado
1. O botão «Novo usuário» abre um formulário com nome, e-mail, CPF, telefone (opcional) e papel.
2. `UserManagementController::store` cria a conta com `auth_type` local, `email_verified_at` preenchido e senha aleatória de 64 caracteres (inutilizável) e vincula o papel.
3. O sistema envia o link de redefinição do console (`sendGestaoPasswordResetNotification`) e audita `usuario-criado` com dado pessoal.
4. A mensagem é «Conta criada. Enviamos a {e-mail} o link para definir a senha.»

## Regras de negócio evidenciadas
- O cadastro cria só conta local. A senha nasce inutilizável e o usuário a define pelo link enviado por e-mail.
- O e-mail nasce verificado porque quem cadastra administra o acesso.
- O CPF vai só com dígitos e o e-mail em minúsculas, para a unicidade valer independente da máscara e da caixa digitadas.
- Este cadastro é diferente do feito pelo visitante no portal (HU-140), que escolhe a senha e começa no perfil de cidadão.

## Validações
- `name` obrigatório, até 255 caracteres.
- `email` obrigatório, formato RFC, até 255, único em `users.email`.
- `cpf` obrigatório, válido e único em `users.cpf`.
- `phone` opcional, até 20 caracteres e com DDD.
- `role` obrigatório e existente em `roles.name` com `guard_name` `web`.

## Critérios de aceite
### CA-01 — Cadastro de conta local
**Dado** um usuário com `manter-usuarios` e dados válidos,
**Quando** confirma Novo usuário,
**Então** a conta nasce local, com e-mail verificado e senha inutilizável, o papel é vinculado, o link de definição de senha é enviado e a auditoria registra `usuario-criado` como dado pessoal.

### CA-02 — Telefone é opcional
**Dado** um cadastro sem telefone,
**Quando** confirma Novo usuário,
**Então** a conta é criada sem telefone.

### CA-03 — Cadastro recusado
**Dado** e-mail ou CPF já usados, CPF inválido, telefone sem DDD ou papel inexistente,
**Quando** confirma Novo usuário,
**Então** o cadastro é recusado e nenhuma conta é criada.

## Divergências da redação anterior
Não havia HU para o cadastro de usuário pela gestão. A redação anterior de «Manter usuários» (HU-005) dizia que a tela não cadastra. Em 19/10/2026 o botão «Novo usuário» passou a criar conta local e ganhou HU própria.

## Evidências
- `routes/gestao.php`: `usuarios.store`, com `manter-usuarios`.
- `app/Http/Controllers/Gestao/UserManagementController.php` (`store`) e `app/Http/Requests/Gestao/StoreUserRequest.php`.
- `resources/js/pages/gestao/usuarios/index.tsx`: `CreateUserModal`.
- `tests/Feature/Gestao/UserCreationTest.php`.

## Não evidenciado
- Edição de nome, e-mail, CPF ou telefone de uma conta existente.
- Definição de senha digitada pelo administrador.

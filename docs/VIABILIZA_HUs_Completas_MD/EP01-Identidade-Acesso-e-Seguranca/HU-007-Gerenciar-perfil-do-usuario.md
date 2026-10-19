# HU-007 — Gerenciar perfil do usuário

## Épica
**EP01 — Identidade, Acesso e Segurança**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
O usuário autenticado atualiza o próprio nome, e-mail e telefone. O CPF é exibido e não entra na atualização. Trocar o e-mail exige nova confirmação.

## História de Usuário
**Como** usuário autenticado, **quero** corrigir meu nome, e-mail e telefone, **para** manter meus dados de contato sem alterar o CPF da conta.

## Atores e permissões
A própria conta. `ProfileUpdateRequest::authorize` retorna verdadeiro; a proteção é a rota.

- Portal: menu Minha conta > Meus dados (`/portal/conta/perfil`). Middleware `auth:web`, `verified`, `lgpd.accepted`, `ResolveRepresentation`. Controller `App\Http\Controllers\Settings\ProfileController`.
- Console: o cabeçalho aponta `accountHref` para `/gestao/conta/perfil`. Não há item em `gestao-nav.ts`. Middleware `auth:gestao`, `acessar-gestao`, `lgpd.accepted`. Controller `App\Http\Controllers\Gestao\Conta\ProfileController`.

Papel, inativação e tipo de autenticação de outra conta não são esta tela. Isso está em `UserManagementController`, permissão `manter-usuarios`, menu Administração > Usuários.

## Fluxo evidenciado
1. `edit` envia à tela `id`, `name`, `email`, `cpf` e `phone`, além de `mustVerifyEmail` (verdadeiro porque `User` implementa `MustVerifyEmail`).
2. O formulário envia PATCH com nome, e-mail e telefone. O CPF aparece com a orientação "O CPF não pode ser alterado." e não está nas regras do request.
3. `update` preenche só o que foi validado. Se o e-mail mudou, `email_verified_at` é anulado e, após salvar, `sendEmailVerificationNotification` é chamada.
4. A resposta volta à mesma tela com status "Perfil atualizado com sucesso."
5. `User` usa `HasAuditoria`: alterações de atributos fillable (nome, e-mail, telefone) entram no activity log; senha e remember token ficam de fora (`logExcept`).

## Regras de negócio evidenciadas
- RN-001: só nome, e-mail e telefone são atualizáveis neste fluxo (`ProfileUpdateRequest`). O CPF fica de fora de propósito, conforme o comentário e as regras do request.
- RN-002: e-mail novo zera a verificação e dispara `VerifyEmailQueued` (`ProfileController::update` nos dois namespaces).
- RN-003: a mudança de dados fillable é auditada por `HasAuditoria` no model `User`.
- Não há, nestes controllers, troca de papel nem de `auth_type`.

## Validações
`ProfileUpdateRequest`:

- `name`: obrigatório, texto, máximo 255 (rótulo "nome").
- `email`: obrigatório, e-mail, máximo 255, único em `users` ignorando o usuário atual (rótulo "e-mail").
- `phone`: opcional, texto, máximo 20, `TelefoneComDdd` (rótulo "telefone"). Vazio passa na regra do telefone.

`UpdateUserProfileInformation` (ação do Fortify) valida só nome e e-mail e também zera a verificação. As telas de conta desta história chamam o `ProfileController`, não essa action.

## Critérios de aceite
### CA-01 — Cidadão atualiza o próprio contato
**Dado** um cidadão em `/portal/conta/perfil`, **quando** salva nome, e-mail único e telefone com DDD, **então** esses campos são gravados e a mensagem é "Perfil atualizado com sucesso."

### CA-02 — CPF não é aceito no PATCH
**Dado** um envio que inclua CPF diferente, **quando** `ProfileUpdateRequest` valida, **então** o CPF não faz parte de `validated()` e o valor gravado no preenchimento do model não vem desse campo.

### CA-03 — E-mail alterado pede confirmação
**Dado** um e-mail diferente do atual, **quando** o perfil é salvo no portal ou no console, **então** `email_verified_at` fica nulo e a notificação de verificação é enviada.

### CA-04 — Servidor edita a própria conta no console
**Dado** um servidor com `acessar-gestao` e termo aceito, **quando** salva `/gestao/conta/perfil`, **então** a conta do guard `gestao` é atualizada com as mesmas regras do portal.

## Divergências da redação anterior
- "Perfil" aqui é o cadastro da própria pessoa, não o papel Spatie.
- O fluxo antigo genérico (permissão de processo, GIS, REDESIM, LOUOS) não existe nesta tela.
- O telefone é opcional na edição e obrigatório só no cadastro (HU-001).

## Evidências
- `routes/portal.php` e `routes/gestao.php` — prefixo `conta`.
- `app/Http/Controllers/Settings/ProfileController.php`.
- `app/Http/Controllers/Gestao/Conta/ProfileController.php`.
- `app/Http/Requests/ProfileUpdateRequest.php`.
- `app/Concerns/HasAuditoria.php`.
- `resources/js/navigation/portal-nav.ts` — "Meus dados".
- `resources/js/pages/settings/profile.tsx` e `resources/js/pages/gestao/conta/perfil.tsx`.

## Não evidenciado
Não há exclusão da própria conta, alteração de CPF nem upload de foto nestes controllers. A gestão de papéis de terceiros, a inativação e a troca local/AD estão em `UserManagementController` e não nesta história.

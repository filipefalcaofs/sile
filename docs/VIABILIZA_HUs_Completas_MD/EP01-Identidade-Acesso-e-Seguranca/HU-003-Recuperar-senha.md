# HU-003 — Recuperar senha

## Épica
**EP01 — Identidade, Acesso e Segurança**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Quem esqueceu a senha pede um link por e-mail e define outra senha. O portal e o console têm fluxos separados. No console, conta inativa, conta AD e conta sem acesso ao console não recebem o link, e a resposta não revela isso.

## História de Usuário
**Como** usuário que não lembra a senha, **quero** receber um link de redefinição no meu e-mail, **para** voltar a entrar sem que o sistema informe se a conta existe.

## Atores e permissões
Visitante. Não há permissão Spatie.

- Portal: views do Fortify `auth/forgot-password` e `auth/reset-password`, com `Features::resetPasswords()` e prefixo `/portal`.
- Console: `/gestao/forgot-password` e `/gestao/reset-password/{token}`, middleware `gestao.guest`.

Nenhum dos dois endereços está no menu lateral.

## Fluxo evidenciado
### Portal
1. O usuário informa o e-mail na tela de esqueci a senha do Fortify.
2. O broker `users` envia o link por `User::sendPasswordResetNotification`, que grava `EmailLog` e notifica com `ResetPasswordQueued`.
3. Se o broker responde usuário inválido, `RespostaRecuperacaoSenhaSemRevelarConta` devolve a mesma resposta de link enviado. Outros status de falha, inclusive limite de tentativas, continuam como erro.
4. No link, `ResetUserPassword::reset` valida a nova senha com a política padrão e grava o hash. O evento `PasswordReset` é auditado por `RecordPasswordResetActivity` (`activity` `seguranca`, evento `senha-redefinida`).
5. Esta action não verifica `isAdAccount` nem `isInactive`.

### Console
1. `ForgotPasswordController::store` exige e-mail. A mensagem de retorno é sempre: "Se a conta tiver acesso ao console, enviamos as instruções de recuperação."
2. O link só é enviado se a conta existe, não está inativa, não é AD e `can('acessar-gestao')`. A notificação é `User::sendGestaoPasswordResetNotification` (`GestaoResetPasswordQueued`), com link do console.
3. `ResetPasswordController::store` usa o mesmo broker. Se a conta for AD, a redefinição lança: "Contas corporativas (AD) têm a senha gerenciada pela rede corporativa."
4. Senha aceita: grava o hash, dispara `PasswordReset` e redireciona a `gestao.login` com o status do broker. Não há `Auth::login` neste controller.
5. Token ou e-mail recusado pelo broker volta com erro no campo e-mail.

## Regras de negócio evidenciadas
- RN-001: a resposta do pedido no portal não distingue e-mail inexistente (`RespostaRecuperacaoSenhaSemRevelarConta`). No console a frase também é única (`ForgotPasswordController::store`).
- RN-002: no console, conta AD não recebe link e não redefine senha (`isAdAccount`).
- RN-003: no console, conta inativa ou sem `acessar-gestao` não recebe link.
- RN-004: a nova senha usa `PasswordValidationRules` / `PasswordPolicy`.
- RN-005: a redefinição bem-sucedida gera atividade `senha-redefinida` (`RecordPasswordResetActivity`). O envio gera `EmailLog`.

## Validações
Portal, pedido: o Fortify espera o campo `email` (`config/fortify.php`). Nova senha: `ResetUserPassword` aplica `passwordRules()` (obrigatória, política padrão, confirmada).

Console, pedido: `email` obrigatório, texto, e-mail (`ForgotPasswordController::store`).

Console, redefinição (`ResetPasswordRequest`): `token` obrigatório; `email` obrigatório e e-mail; `password` com `passwordRules()`.

## Critérios de aceite
### CA-01 — E-mail desconhecido no portal
**Dado** um e-mail que não está em `users`, **quando** o visitante pede recuperação no portal, **então** a resposta apresentada é a de link enviado.

### CA-02 — Conta local do console recebe o link
**Dado** uma conta ativa, local e com `acessar-gestao`, **quando** pede recuperação em `/gestao/forgot-password`, **então** `sendGestaoPasswordResetNotification` é chamada e a mensagem exibida é a frase genérica de instruções enviadas.

### CA-03 — Conta AD no console
**Dado** uma conta com `auth_type` AD, **quando** pede o link ou abre a redefinição do console, **então** o link não é enviado no pedido e, na redefinição, a mensagem informa que a senha é da rede corporativa.

### CA-04 — Nova senha no console
**Dado** um token válido de conta local, **quando** a nova senha cumpre a política e a confirmação, **então** o hash é gravado, a atividade `senha-redefinida` é registrada e o usuário volta a `/gestao/login`.

## Divergências da redação anterior
- Há dois fluxos, com notificações diferentes. O texto antigo descrevia um único caminho e citava GIS, REDESIM e LOUOS, que não participam da recuperação.
- O console recusa AD, inativo e quem não tem `acessar-gestao`. O `ResetUserPassword` do portal não aplica esses filtros.
- A redefinição do console não abre sessão.

## Evidências
- `config/fortify.php` — `Features::resetPasswords()`.
- `app/Http/Responses/RespostaRecuperacaoSenhaSemRevelarConta.php`.
- `app/Actions/Fortify/ResetUserPassword.php`.
- `app/Http/Controllers/Gestao/ForgotPasswordController.php` e `ResetPasswordController.php`.
- `app/Http/Requests/Gestao/ResetPasswordRequest.php`.
- `app/Models/User.php` — `sendPasswordResetNotification` e `sendGestaoPasswordResetNotification`.
- `app/Listeners/RecordPasswordResetActivity.php`.
- `routes/gestao.php`.

## Não evidenciado
Não há recuperação por CPF, SMS ou GOV.BR. Não há, no código do portal desta história, o mesmo bloqueio de conta AD que o console aplica. Não há tela de redefinição no menu.

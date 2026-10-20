# HU-141 — Confirmar e-mail

## Épica
**EP01 — Identidade, Acesso e Segurança**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
A conta local confirma o e-mail por um link assinado enviado à fila. Sem essa confirmação, o portal autenticado e o login do console não seguem.

## História de Usuário
**Como** usuário que acabou de se cadastrar ou trocou o e-mail, **quero** confirmar o endereço por um link, **para** liberar o uso do portal e do console.

## Atores e permissões
O próprio usuário. `Features::emailVerification()` está ligado no Fortify, com prefixo `/portal` e guard `web`. A view de aviso é `auth/verify-email`. As rotas do portal em `routes/portal.php` usam o middleware `verified`. O login do console não usa esse middleware na rota: `LoginController::store` chama `hasVerifiedEmail()` depois da credencial correta.

Não há item de menu para a tela de confirmação.

## Fluxo evidenciado
1. `User` implementa `MustVerifyEmail` e substitui `sendEmailVerificationNotification`: cria `EmailLog` com status `na_fila` e notifica `VerifyEmailQueued` (fila). A URL assinada é capturada no disparo (`freezeUrlFor`), não no worker.
2. O e-mail pede a confirmação do endereço no Viabiliza. O texto informa a validade em minutos lida de `auth.verification.expire` (padrão 60 no fallback da notificação) e orienta a ignorar a mensagem se a pessoa não criou a conta.
3. A tela `auth/verify-email` é a view do Fortify enquanto o e-mail não está verificado. O reenvio é o fluxo de verificação do Fortify nesse prefixo.
4. Quando o e-mail é marcado como verificado, o evento `Verified` gera atividade `seguranca` / `email-confirmado` ("E-mail confirmado pelo usuário") em `RecordEmailVerifiedActivity`.
5. Troca de e-mail no perfil (`ProfileController` do portal e do console) zera `email_verified_at` e chama de novo `sendEmailVerificationNotification`.
6. Primeiro acesso AD grava `email_verified_at` na hora (`registrarPrimeiroAcessoAd`). Primeiro acesso GOV.BR chama `markEmailAsVerified()` quando o provedor declara o e-mail verificado (HU-142).
7. No console, credencial correta com e-mail não verificado responde: "Confirme seu e-mail institucional antes de acessar o console."

## Regras de negócio evidenciadas
- RN-001: usuário local precisa de `email_verified_at` para as rotas `verified` do portal e para concluir o login do console (`User`, middleware `verified`, `LoginController::store`).
- RN-002: alterar o e-mail desfaz a verificação e dispara outro envio (`Settings\ProfileController::update` e `Gestao\Conta\ProfileController::update`).
- RN-003: o envio é assíncrono e registrado em `EmailLog` (`User::sendEmailVerificationNotification`).
- RN-004: a confirmação gera atividade `email-confirmado` (`RecordEmailVerifiedActivity`).

## Validações
Não há Form Request desta história. A confirmação usa o link assinado da notificação `VerifyEmail` (URL produzida por `VerifyEmailQueued`). O cadastro e a troca de e-mail validam o formato e a unicidade nas histórias HU-140 e HU-144.

## Critérios de aceite
### CA-01 — Cadastro fica pendente de verificação
**Dado** um usuário criado por `CreateNewUser` sem `email_verified_at`, **quando** tenta uma rota do portal com middleware `verified`, **então** não entra no grupo autenticado até confirmar o e-mail.

### CA-02 — Console recusa e-mail pendente
**Dado** credencial correta de conta ativa com `acessar-gestao` e sem e-mail verificado, **quando** entra em `/gestao/login`, **então** a sessão não é criada e a mensagem pede a confirmação do e-mail institucional.

### CA-03 — Troca de e-mail pede nova confirmação
**Dado** um usuário que altera o e-mail no perfil do portal ou do console, **quando** o valor muda, **então** `email_verified_at` fica nulo e `sendEmailVerificationNotification` é chamada.

### CA-04 — Confirmação auditada
**Dado** o evento `Verified`, **quando** `RecordEmailVerifiedActivity` trata o evento, **então** a atividade registrada é `email-confirmado`.

## Divergências da redação anterior
- A história antiga descrevia o sistema confirmando e-mail dentro do fluxo genérico de viabilidade (GIS, REDESIM, LOUOS). O código é a verificação de e-mail do Fortify mais a checagem explícita no login do console.
- Conta criada pelo primeiro acesso AD ou pelo GOV.BR com e-mail já verificado no provedor não passa por este link.

## Evidências
- `config/fortify.php` — `Features::emailVerification()` e `FortifyServiceProvider::verifyEmailView`.
- `app/Models/User.php` — `MustVerifyEmail` e `sendEmailVerificationNotification`.
- `app/Notifications/VerifyEmailQueued.php`.
- `app/Listeners/RecordEmailVerifiedActivity.php`.
- `app/Http/Controllers/Gestao/LoginController.php` — `hasVerifiedEmail`.
- `routes/portal.php` — middleware `verified`.
- `app/Http/Controllers/Settings/ProfileController.php` e `app/Http/Controllers/Gestao/Conta/ProfileController.php`.

## Não evidenciado
Não há confirmação por código numérico digitado na tela, nem verificação por SMS. Não há rota de reenvio exclusiva do guard `gestao`; o recurso de verificação do Fortify está no prefixo do portal.

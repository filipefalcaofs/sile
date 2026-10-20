# HU-142 — Autenticar com GOV.BR

## Épica
**EP01 — Identidade, Acesso e Segurança**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
O cidadão entra no portal pelo Login Único. A identidade é o CPF do `sub`. Conta existente é vinculada; conta nova é criada de verdade. Com o interruptor desligado ou sem credenciais, as rotas recusam e avisam, sem simular sessão.

## História de Usuário
**Como** cidadão com conta GOV.BR, **quero** entrar no portal pelo Login Único, **para** usar o Viabiliza sem depender só de CPF e senha local.

## Atores e permissões
Visitante. Rotas públicas com middleware `guest`: GET `/portal/login/govbr` (`portal.govbr.redirect`) e GET `/portal/login/govbr/callback` (`portal.govbr.callback`).

Não há permissão Spatie. O botão na tela de login do Fortify só é montado quando `GovBr::loginAvailable()` é verdadeiro (`canLoginWithGovBr`). Não há item de menu.

Depois da sessão, valem as mesmas portas do portal (`verified` e `lgpd.accepted`). Conta nova marca o e-mail como verificado e ainda passa pelo termo, se houver termo vigente.

## Fluxo evidenciado
1. `GovBr::loginAvailable()` exige `features.govbr_login` ligado (`Settings::enabled('govbr_login')`) e `integrations.govbr.client_id` e `integrations.govbr.client_secret` preenchidos. O padrão do interruptor em `config/viabiliza.php` é desligado. Se indisponível, `redirect` e `callback` voltam a `login` com o erro: "O login com GOV.BR está indisponível no momento. Entre com e-mail e senha."
2. Disponibilidade ok: `Socialite::driver('govbr')->redirect()`. O provider `GovBrProvider` usa PKCE (`$usesPKCE = true`), escopos `openid`, `email`, `profile`, `govbr_confiabilidades` e `govbr_confiabilidades_idtoken`, grava `nonce` na sessão e pede autorização em `{base_url}/authorize`. A URL base vem de `integrations.govbr.base_url`.
3. No callback, `error` preenchido na query volta com: "A autorização no GOV.BR não foi concluída. Você pode tentar novamente ou entrar com e-mail e senha." `InvalidStateException` volta com sessão de autenticação expirada.
4. `GovBrProvider::user` exige `id_token` e `GovBrIdTokenValidator::validate` com client id, URL base e o nonce da sessão. O token é pedido com Authorization Basic; client id e segredo não vão no corpo.
5. `GovBrAuthService::authenticate` lê o CPF do `sub` (11 dígitos). Outro formato lança `GovBrAuthException`. O nível vem de `reliability_info.level` (`bronze`, `silver` → prata, `gold` → ouro); ausência vira bronze. O mínimo é `security.govbr.minimum_level` (padrão bronze). Abaixo do mínimo, `AuditService::logBlocked` registra o bloqueio (CPF mascarado) e a mensagem orienta a elevar o nível. Nenhuma sessão é criada.
6. CPF já cadastrado: conta inativa grava `access_logs` `inativada` no canal `portal` e responde "Sua conta está inativa. Procure o administrador do sistema." Conta ativa atualiza ou cria `gov_br_accounts` (nível, ids de confiabilidade, `linked_at` na primeira vez, `last_authenticated_at`) e segue. O e-mail não é usado para achar a conta.
7. CPF novo: exige e-mail presente e `email_verified` verdadeiro no provedor; senão, orienta a verificar o e-mail no gov.br ou criar conta com e-mail e senha. Se o e-mail já existe em outra conta, recusa sem vincular. Nome vazio recusa. No sucesso, cria usuário com nome, e-mail, CPF e senha aleatória, `markEmailAsVerified()`, papel `PerfilCidadao::nome()`, registro em `gov_br_accounts` e atividade `usuarios` / `cadastro-govbr`.
8. `Auth::login`, regeneração de sessão e atividade `acessos` / `login-govbr` com o nível. O destino é o `LoginResponse` do Fortify: URL pretendida em `/gestao` é descartada; o restante vai ao painel do portal.
9. Falhas de `GovBrAuthException` são logadas com o motivo técnico; a tela recebe só `userMessage`.

## Regras de negócio evidenciadas
- RN-001: sem interruptor e sem as duas credenciais, não há redirect ao provedor (`GovBr::loginAvailable`, `GovBrLoginController::unavailable`).
- RN-002: o vínculo de conta existente é só pelo CPF do `sub` (`GovBrAuthService::authenticate`).
- RN-003: o nível mínimo efetivo está em `security.govbr.minimum_level`, não em outra chave (`ensureMinimumLevel`).
- RN-004: conta inativa não autentica e gera o evento `inativada` (`ensureActive`).
- RN-005: primeiro acesso exige e-mail verificado no provedor, papel de cidadão e `email_verified_at` local (`register`).
- RN-006: e-mail do GOV.BR já usado por outro CPF impede a criação (`register`).
- RN-007: protocolo com `state` inválido ou sem conclusão no provedor não cria sessão (`GovBrProvider::user`, `GovBrLoginController::callback`).
- RN-008: sucesso e bloqueio por nível ficam auditados (`login-govbr`, `cadastro-govbr`, `logBlocked`). O `access_logs` de sucesso continua o listener genérico de `Login` (evento `login`, canal `portal`). Este controller não preenche `auth_origin`.

## Validações
Não há Form Request. As recusas são as de `GovBrAuthService` e as mensagens de `GovBrLoginController::failed`. O `sub` precisa ter 11 dígitos depois de remover não dígitos.

## Critérios de aceite
### CA-01 — Indisponível não autentica
**Dado** `features.govbr_login` desligado ou client id/segredo vazios, **quando** o visitante abre `/portal/login/govbr` ou o callback, **então** volta ao login com o aviso de indisponibilidade e nenhuma sessão é criada.

### CA-02 — CPF já cadastrado
**Dado** o interruptor e as credenciais ok, e um usuário ativo com o CPF do `sub`, **quando** o callback conclui, **então** a sessão `web` é criada, `gov_br_accounts` é gravado ou atualizado e a atividade `login-govbr` é registrada.

### CA-03 — Primeiro acesso
**Dado** que não existe usuário com aquele CPF, e o id token traz nome e e-mail verificado que não está em outra conta, **quando** o callback conclui, **então** a conta é criada com o papel de cidadão, e-mail marcado como verificado e atividade `cadastro-govbr`.

### CA-04 — Nível insuficiente ou conta inativa
**Dado** nível abaixo de `security.govbr.minimum_level`, ou conta local inativa com o mesmo CPF, **quando** o callback conclui a leitura do token, **então** a sessão não é criada e o cidadão recebe a mensagem de nível ou de conta inativa.

### CA-05 — E-mail de outra conta
**Dado** CPF ainda sem usuário e e-mail do GOV.BR já gravado em `users` com outro CPF, **quando** o callback tenta o primeiro acesso, **então** nenhuma conta é criada e a mensagem informa que o e-mail já está em uso.

## Divergências da redação anterior
- A chave do nível mínimo no código é `security.govbr.minimum_level`. A redação anterior citava `auth.govbr.minimum_level`.
- O interruptor é `features.govbr_login`. O padrão em `config/viabiliza.php` é desligado: nesse estado a rota recusa de forma explícita.
- As mensagens de falha do controller falam em "e-mail e senha", embora o login local do portal seja CPF e senha.
- O log de sucesso do GOV.BR na activity é `login-govbr`. O `access_logs` do listener de `Login` não recebe origem `govbr`, porque `GovBrLoginController` não define `auth_origin`.
- Não há, neste controller, logout no provedor (single logout). O logout do portal continua o do Fortify.

## Evidências
- `routes/portal.php` — `GovBrLoginController`.
- `app/Http/Controllers/Portal/GovBrLoginController.php`.
- `app/Services/GovBr/GovBr.php` — `loginAvailable`.
- `app/Services/GovBr/GovBrAuthService.php`.
- `app/Services/GovBr/GovBrProvider.php` — PKCE, nonce, id token.
- `app/Providers/AppServiceProvider.php` — `Socialite::extend('govbr', ...)`.
- `app/Providers/FortifyServiceProvider.php` — `canLoginWithGovBr` e `LoginResponse`.
- `config/viabiliza.php` — `features.govbr_login` e `integrations.govbr`.
- `database/seeders/ParameterSeeder.php` — chaves `features.govbr_login`, `integrations.govbr.client_id`, `integrations.govbr.client_secret`, `security.govbr.minimum_level`.

## Não evidenciado
Não há vínculo de conta pelo e-mail quando o CPF não confere. Não há elevação automática de nível. Não há single logout no GOV.BR. O credenciamento no ambiente real do Login Único não está neste código: sem client id e segredo configurados, `loginAvailable()` permanece falso.

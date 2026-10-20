# HU-001 — Autenticar usuário

## Épica
**EP01 — Identidade, Acesso e Segurança**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
O cidadão entra no portal com CPF e senha local. O servidor entra no console com e-mail ou usuário AD e senha, em sessão separada. Conta AD valida a credencial na API institucional da SEDUR e nunca cai para a senha local.

## História de Usuário
**Como** usuário com conta no Viabiliza, **quero** entrar no portal ou no console com a credencial do meu ambiente, **para** usar só o que a minha sessão e as minhas permissões permitem.

## Atores e permissões
- Portal: visitante. Rotas do Fortify em `/portal/login` (guard `web`). Depois do login, as telas exigem `auth:web`, `verified` e, no grupo autenticado, `lgpd.accepted`.
- Console: visitante em `/gestao/login` (`gestao.guest`). O POST é `LoginController::store`. O grupo interno exige `auth:gestao`, a permissão `acessar-gestao` e `lgpd.accepted`.
- As duas sessões são independentes. O logout do console (`LoginController::destroy`) encerra só o guard `gestao` e regenera o token; não invalida a sessão inteira.

Não há item de menu para o login. O cabeçalho de cada ambiente aponta o logout da própria sessão.

## Fluxo evidenciado
### Portal (CPF e senha local)
1. `Fortify::authenticateUsing` normaliza o CPF para dígitos e busca `users.cpf`.
2. Se a conta não existe ou `Hash::check` falha, devolve `null`. O evento `Failed` vira registro `falha` em `access_logs` (`RecordFailedLogin`), sem dizer se a conta existe.
3. Se a senha confere e `inactivated_at` está preenchido, grava o evento `inativada` e responde: "Sua conta está inativa. Procure o administrador do sistema."
4. Com credencial válida e conta ativa, a sessão do guard `web` é criada. `LoginResponse` descarta URL pretendida que comece com `/gestao` e redireciona para o destino pretendido ou `portal.dashboard`.
5. O limite de tentativas vem de `security.login.max_attempts` (padrão 5), chave CPF (só dígitos) + IP. No bloqueio, o evento `Lockout` grava `bloqueio` (`RecordLockout`).
6. Sucesso grava `access_logs.event = login`, canal `portal` (`RecordSuccessfulLogin`).

### Console (e-mail ou usuário AD)
1. `LoginRequest` exige `identifier` e `password`. O identificador é comparado em minúsculas com `email` ou `ad_username`.
2. Se não há conta e `SedurAuthSettings::enabled()` (`features.ad_login`) está ligado, e o identificador não contém espaço, `registrarPrimeiroAcessoAd` chama `SedurDirectoryAuthenticator::attempt`. Credencial inválida segue como falha genérica. API indisponível grava `ad-indisponivel` e responde que a autenticação institucional está indisponível.
3. Com a API aceitando o primeiro acesso, o sistema cria ou reaproveita o usuário por e-mail ou CPF, grava `auth_type = ad`, `ad_username`, senha local aleatória de 64 caracteres e `email_verified_at`. Se a conta ainda não pode `acessar-gestao`, concede essa permissão. Se a descoberta trouxer setor, `UserSectorAssignmentSync::syncFromDiscovery` tenta vincular; falha de sincronização grava `ad-sincronizacao-falhou` e não desfaz o login.
4. Conta já existente valida a credencial em `credencialValida`: local com `Hash::check`; AD de novo na API, sem usar o hash local. Com o interruptor AD desligado, conta AD recebe bloqueio auditado (`AuditService::logBlocked`) e a mensagem de autenticação corporativa desabilitada.
5. Depois da credencial correta, nesta ordem: conta inativa (evento `inativada` e mensagem de conta inativa); sem `acessar-gestao` (bloqueio auditado e "Esta conta não tem acesso ao console de gestão."); e-mail não verificado ("Confirme seu e-mail institucional antes de acessar o console.").
6. Sucesso limpa os limitadores, faz login no guard `gestao`, regenera o id da sessão e redireciona ao destino interno ou ao painel. Com o cabeçalho `X-Viabiliza-Login-Async`, devolve JSON `{ destino }` em vez de redirecionar na hora.
7. O limite por conta usa `security.login.max_attempts` (identificador + IP). O limite por IP usa `viabiliza.security.login.max_attempts_per_ip` (padrão 30). Os dois disparam `Lockout` e a mensagem `auth.throttle`.

## Regras de negócio evidenciadas
- RN-001: portal autentica por CPF; console autentica por e-mail ou `ad_username` (`FortifyServiceProvider`, `LoginController::store`).
- RN-002: conta AD (`User::isAdAccount`, `AuthType::Ad`) só valida em `DirectoryAuthenticator`; o comentário e o código de `credencialValida` não consultam `users.password`.
- RN-003: falha de credencial no console usa sempre `__('auth.failed')` ("As credenciais indicadas não coincidem com as registradas no sistema."), para conta inexistente, local ou AD (`LoginController::store`).
- RN-004: inatividade, falta de `acessar-gestao` e e-mail não verificado só são informados depois da credencial correta.
- RN-005: primeiro acesso AD persiste a conta e pode conceder `acessar-gestao`; a senha corporativa não é gravada (`registrarPrimeiroAcessoAd`).
- RN-006: login, falha, bloqueio, inativação e indisponibilidade/sincronização AD geram `access_logs` (`RecordSuccessfulLogin`, `RecordFailedLogin`, `RecordLockout` e os `AccessLog::create` do `LoginController`).

## Validações
Portal: campo de usuário do Fortify é `cpf`; a senha é a do formulário de login. Não há Form Request próprio.

Console, `LoginRequest`: `identifier` obrigatório, texto, máximo 255; `password` obrigatório, texto. O checkbox `remember` é lido com `boolean('remember')` e não entra nas regras.

## Critérios de aceite
### CA-01 — Cidadão entra com CPF
**Dado** um usuário ativo com CPF e senha local, **quando** envia o login do portal, **então** a sessão do guard `web` é criada, um `access_logs` de `login` no canal `portal` é gravado e o destino não é uma URL `/gestao`.

### CA-02 — Credencial errada não revela a conta
**Dado** um identificador inexistente ou uma senha incorreta no console, **quando** o POST de login é enviado, **então** a resposta é `auth.failed`, os contadores de tentativa sobem e o evento registrado é `falha`.

### CA-03 — Conta AD no console
**Dado** `features.ad_login` ligado e uma conta com `auth_type = ad`, **quando** o servidor informa `ad_username` e a senha aceita por `SedurDirectoryAuthenticator::attempt`, **então** a sessão `gestao` é criada sem `Hash::check` na senha local. Se a API estiver indisponível, o evento é `ad-indisponivel` e a sessão não é criada.

### CA-04 — Sem permissão de console
**Dado** uma credencial local correta de conta ativa e com e-mail verificado, sem a permissão `acessar-gestao`, **quando** tenta `/gestao/login`, **então** o login é recusado com a mensagem de conta sem acesso ao console e `AuditService::logBlocked` registra a tentativa.

### CA-05 — Primeiro acesso AD
**Dado** um identificador que ainda não existe em `users`, o interruptor AD ligado e a API devolvendo a pessoa, **quando** o servidor entra em `/gestao/login`, **então** a conta é gravada com `auth_type` AD, `ad_username` e permissão `acessar-gestao`, e a senha digitada não fica armazenada como senha local utilizável.

## Divergências da redação anterior
- Não existe um login único para portal e retaguarda. São guards, rotas e credenciais diferentes.
- O texto antigo mandava consultar GIS, REDESIM e o motor da LOUOS no fluxo de autenticação. Isso não ocorre.
- Login AD é cenário deste fluxo, não uma história separada. A senha AD não é conferida no hash local.
- O portal não autentica por e-mail.

## Evidências
- `app/Providers/FortifyServiceProvider.php` — `authenticateUsing`, `LoginResponse`, limite `security.login.max_attempts`.
- `app/Http/Controllers/Gestao/LoginController.php` — `store`, `credencialValida`, `registrarPrimeiroAcessoAd`, `destroy`.
- `app/Services/Auth/SedurDirectoryAuthenticator.php` — `attempt` (sessão, discovery e pessoa por CPF).
- `app/Services/Sedur/SedurAuthSettings.php` — `enabled()` lê `features.ad_login`.
- `app/Enums/AuthType.php` e `app/Models/User.php` — `isAdAccount`.
- `app/Listeners/RecordSuccessfulLogin.php`, `RecordFailedLogin.php`, `RecordLockout.php`.
- `routes/gestao.php` e `routes/portal.php`.
- `lang/pt_BR/auth.php` — textos `failed` e `throttle`.

## Não evidenciado
Não há segundo fator nem passkey nas features do Fortify (`config/fortify.php` lista registro, recuperação de senha, verificação de e-mail e atualização de senha). Não há autenticação AD no portal. O logout do portal não está neste controller.

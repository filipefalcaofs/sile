# HU-143 — Alterar senha

## Épica
**EP01 — Identidade, Acesso e Segurança**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
O usuário autenticado troca a própria senha informando a senha atual. No console, conta AD é recusada e orientada a alterar a senha na rede corporativa.

## História de Usuário
**Como** usuário autenticado com senha local, **quero** definir uma nova senha depois de confirmar a atual, **para** manter o acesso sem usar o link de recuperação.

## Atores e permissões
Usuário já autenticado. Não há permissão Spatie além do acesso ao ambiente.

- Portal: `auth:web`, `verified`, `lgpd.accepted` e `ResolveRepresentation`. Rotas `portal.conta.senha.show` (GET `/portal/conta/senha`) e `portal.conta.senha.update` (PUT). Controller `App\Http\Controllers\Settings\PasswordController`.
- Console: `auth:gestao`, `acessar-gestao`, `lgpd.accepted`. Rotas `gestao.conta.senha.show` e `gestao.conta.senha.update`. Controller `App\Http\Controllers\Gestao\Conta\PasswordController`.

No portal, a aba "Senha" fica no layout das telas de conta (`resources/js/layouts/settings-layout.tsx`), depois de abrir "Meus dados" em Minha conta (`/portal/conta/perfil`). No console, a rota existe, mas nenhum item de `gestao-nav.ts` nem a tela de perfil aponta para `/gestao/conta/senha`. O cabeçalho leva a `/gestao/conta/perfil`.

## Fluxo evidenciado
1. A tela mostra o texto de `PasswordPolicy::description()`.
2. No portal, `PasswordController::update` chama `UpdateUserPassword::update` com o usuário do guard `web` e os campos `current_password`, `password` e `password_confirmation`.
3. No console, se `isAdAccount()`, a atualização é recusada em `current_password` com: "Contas corporativas (AD) têm a senha gerenciada pela rede corporativa. Altere-a pelo portal de senhas da prefeitura."
4. `UpdateUserPassword` exige a senha atual (`current_password`) e a nova senha na política, com confirmação. A mensagem de senha atual divergente é a da regra: "The provided password does not match your current password." O erro fica no bag `updatePassword`.
5. Senha aceita: grava o hash e `AuditService::log` registra o log `seguranca` / `senha-alterada` ("Senha alterada pelo próprio usuário"). A resposta é `back()`, sem texto de status no controller. A tela do console exibe "Senha alterada com sucesso." quando o Inertia marca sucesso recente.

## Regras de negócio evidenciadas
- RN-001: a troca exige a senha atual (`UpdateUserPassword::update`).
- RN-002: a nova senha segue `PasswordPolicy` (tamanho, maiúsculas/minúsculas, números e símbolos conforme os parâmetros `security.password.*`).
- RN-003: no console, conta AD não altera senha local (`Gestao\Conta\PasswordController::update`). O controller do portal não faz essa checagem.
- RN-004: a alteração é auditada por `AuditService`, não pelo log fillable do model (a senha está em `logExcept` de `HasAuditoria`).

## Validações
Não há Form Request. `UpdateUserPassword` valida:

- `current_password`: obrigatório, texto, regra `current_password` (usuário do guard padrão da request).
- `password`: `passwordRules()` — obrigatório, `Password::default()`, `confirmed`.

## Critérios de aceite
### CA-01 — Troca no portal
**Dado** um cidadão autenticado no guard `web`, com e-mail verificado e termo aceito, **quando** informa a senha atual correta e uma nova senha válida em `/portal/conta/senha`, **então** o hash é atualizado e a atividade `senha-alterada` é registrada.

### CA-02 — Senha atual incorreta
**Dado** a senha atual digitada não confere, **quando** o formulário é enviado, **então** a validação recusa no bag `updatePassword` e a senha gravada não muda.

### CA-03 — Conta AD no console
**Dado** um servidor com `auth_type` AD autenticado no console, **quando** envia PUT `/gestao/conta/senha`, **então** a resposta aponta `current_password` para a mensagem da rede corporativa e `UpdateUserPassword` não roda.

## Divergências da redação anterior
- A redação antiga era o fluxo genérico de viabilidade (GIS, REDESIM, LOUOS, CNAE). A troca de senha não consulta esses serviços.
- São duas rotas e dois controllers. Só o console bloqueia conta AD.
- A tela do console não está no menu.

## Evidências
- `routes/portal.php` — prefixo `conta`, `PasswordController` de Settings.
- `routes/gestao.php` — prefixo `conta`, `Gestao\Conta\PasswordController`.
- `app/Actions/Fortify/UpdateUserPassword.php`.
- `app/Support/PasswordPolicy.php`.
- `resources/js/layouts/settings-layout.tsx` e `resources/js/pages/settings/password.tsx`.
- `resources/js/pages/gestao/conta/senha.tsx`.

## Não evidenciado
Não há link de navegação para a senha do console. Não há exigência de senha diferente da anterior além do que `Password::default()` já aplica. Não há invalidação das outras sessões no `update` lido.

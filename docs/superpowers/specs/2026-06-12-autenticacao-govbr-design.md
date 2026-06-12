# Autenticação GOV.BR no portal do cidadão — Design

Data: 2026-06-12 · Status: aprovado pelo usuário · HU: HU-151 · Fase: 3.1 (INSERTED)

## Decisões do usuário

| Decisão | Escolha |
|---|---|
| Credenciamento no Login Único | Ainda não existe — implementar completo com toggle desligado; validação contra staging real fica bloqueada como pendência |
| Convivência | GOV.BR convive com o login local (botão adicional, cadastro local permanece) |
| Nível mínimo de confiabilidade | Aceitar qualquer nível (bronze+), exigência parametrizável (`auth.govbr.minimum_level`) |
| Implementação OAuth | `laravel/socialite` + provider gov.br próprio; `firebase/php-jwt` para validar `id_token` (RS256 via JWK, exigência do roteiro oficial) |

Contexto: a Fase 1 havia adiado gov.br ("não consta nas HUs"); este pedido muda o escopo. O `sub` do Login Único é o CPF — e `users.cpf` já é obrigatório, único e validado (01-04), o que torna o vínculo de contas determinístico.

## Referência normativa/técnica

Roteiro de Integração do Login Único (acesso.gov.br/roteiro-tecnico, atualizado 2025-10-30):

- Authorization Code + **PKCE S256 obrigatório**, com `state` e `nonce`.
- Autorização: `{base}/authorize` · Token: `{base}/token` (Basic auth) · Chaves: `{base}/jwk` · Logout: `{base}/logout`.
- Staging: `https://sso.staging.acesso.gov.br` · Produção: `https://sso.acesso.gov.br`.
- Scopes: `openid email profile govbr_confiabilidades govbr_confiabilidades_idtoken`.
- `id_token` (RS256, validar contra JWK): claims `sub` (CPF), `name`, `email`, `email_verified`, `amr`, `nonce`; com o scope `govbr_confiabilidades_idtoken`, `reliability_info.level` (`bronze|silver|gold`) e `reliability_info.reliabilities[]` (selos) vêm no próprio token.
- **Ajuste pós-pesquisa (2026-06-12)**: a API separada de níveis (`/confiabilidades/v3/...`) é método **obsoleto** no roteiro ("novas integrações não devem utilizar"); os níveis são lidos exclusivamente do `id_token` e o parâmetro `integrations.govbr.api_base_url` foi removido do catálogo (não parametrizar o que não tem uso). `id_token` sem `reliability_info` é tratado como bronze (piso de qualquer conta gov.br) — fail-closed quando o mínimo parametrizado for prata/ouro.
- **Ajuste**: e-mail não verificado no gov.br faz o claim `email` nem ser emitido; primeiro acesso sem e-mail é bloqueado com orientação (conta local exige e-mail) — usuário existente (CPF encontrado) autentica normalmente.
- Token request usa `Authorization: Basic base64(client_id:client_secret)` + form body (grant_type, code, redirect_uri, code_verifier), conforme roteiro.

## Arquitetura

### Backend

1. **`App\Services\GovBr\GovBrProvider`** — Socialite `AbstractProvider` com PKCE habilitado, `nonce` em sessão (gerado no redirect, conferido no callback), endpoints derivados de `Settings::get('integrations.govbr.base_url')`. O `user()` decodifica o `id_token` do token response validando assinatura contra `{base}/jwk` (`firebase/php-jwt`, cache das chaves com TTL técnico em `config/sile.php`). Driver registrado via `Socialite::extend('govbr', ...)` lendo `Settings` em runtime — credenciais/URL mudam sem deploy.
2. **`App\Services\GovBr\GovBrAuthService`** — recebe o usuário Socialite e resolve o `User` local:
   - CPF (`sub`) encontra `User` → atualiza vínculo e autentica.
   - CPF inexistente → cria conta real: `name`/`email` do gov.br, `cpf` = sub, papel `cidadao`, senha aleatória (cast `hashed`), `email_verified_at = now()` somente se `email_verified` true (senão o fluxo `verified` existente cobra a verificação).
   - E-mail já em uso por usuário com **outro** CPF → `GovBrAuthException` comunicada — nunca vínculo automático.
   - Conta inativada → bloqueio com a mesma mensagem do login local + `access_log` evento `inativada` (paridade com `Fortify::authenticateUsing`).
   - Nível de confiabilidade abaixo de `auth.govbr.minimum_level` → bloqueio comunicado com orientação para elevar o nível no gov.br.
   - Tudo dentro de `DB::transaction`; `Auth::login` dispara o listener `RecordSuccessfulLogin` existente.
3. **`GovBrAccount`** (tabela `govbr_accounts`, 1:1 `users`): `user_id` unique, `reliability_level` (bronze|prata|ouro|null), `reliability_levels` json, `linked_at`, `last_authenticated_at`. `User` permanece enxuto.
4. **`Portal\GovBrLoginController`**: `redirect()` e `callback()`, rotas guest `GET /portal/login/govbr` (`portal.govbr.redirect`) e `GET /portal/login/govbr/callback` (`portal.govbr.callback`). Toggle desligado ou credenciais ausentes → redirect ao login com flash de indisponibilidade (degradação comunicada, nunca silenciosa). Erro/negação no IdP → mensagem amigável, falha auditada.
5. **Auditoria (RN-002)**: login gov.br grava `access_logs` evento `login` (listener existente) + `Context` de método `govbr`; o callback registra via `AuditService` (`log_name` `acessos`, event `login-govbr`, properties com nível e se a conta foi criada). Criação de conta auditada (`HasAuditoria` created + properties `origem: govbr`).

### Parâmetros (catálogo HU-014)

| Chave | Tipo | Default | Notas |
|---|---|---|---|
| `features.govbr_login` | boolean | `false` | toggle do botão e das rotas |
| `integrations.govbr.base_url` | string | `https://sso.staging.acesso.gov.br` | `requires_connection_test` (contrato RN-010, teste real na Fase 13) |
| `integrations.govbr.api_base_url` | string | `https://api.staging.acesso.gov.br` | API de confiabilidades |
| `integrations.govbr.client_id` | string | — | `sensitive` (criptografado) |
| `integrations.govbr.client_secret` | string | — | `sensitive` (criptografado) |
| `auth.govbr.minimum_level` | string | `bronze` | validação `in:bronze,prata,ouro` |

Constantes técnicas (timeout, TTL do cache JWK) em `config/sile.php`, fora do registry (precedente 02-02).

### Frontend

- `login.tsx` e `register.tsx`: botão "Entrar com gov.br" (identidade do guia oficial: fundo `#1351B4`, texto branco, "gov.br" em negrito), com divisor "ou" — renderizado apenas quando a prop `canLoginWithGovBr` é true (toggle ligado **e** credenciais preenchidas), passada por `Fortify::loginView`/`registerView`.
- Botão navega via `<a href>` (redirect externo de página inteira — não XHR Inertia).

### Fora de escopo (v1)

- Single Logout no SSO (logout permanece local; derrubar a sessão gov.br inteira do cidadão é indesejado para serviço municipal).
- Botão "testar conexão" na tela de parâmetros (contrato `requires_connection_test` registrado; implementação real na Fase 13, precedente cnpj_lookup).
- Login gov.br na retaguarda (`/gestao/login`) — exclusivo do portal do cidadão.

## Bloqueio externo registrado

Sem credenciamento da SEDUR no Login Único (Termo de Adesão junto à Secretaria de Governo Digital), a integração **não pode ser validada contra o staging real** — critério de conclusão de integração do projeto. A feature entra completa e desligada (`features.govbr_login = false`); pendência registrada em ROADMAP/STATE. Nunca adaptador falso.

## Testes (TDD — PHPUnit, CAs da HU-151)

Socialite mockado apenas na suíte (`Socialite::shouldReceive`); validação JWT testada em unit com par RSA gerado no teste. Cobertura: redirect monta URL correta (client_id/scopes/state/PKCE S256), toggle off bloqueia rotas e oculta botão, criação de conta nova (papel, CPF, e-mail verificado condicional, vínculo, auditoria), login de conta existente por CPF, conflito de e-mail, conta inativada, nível insuficiente parametrizado, state/nonce inválido falha sem sessão, props das telas.

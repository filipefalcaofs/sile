# Separação total das autenticações — Backoffice (console) × Portal

Data: 2026-06-30
Status: aprovada a abordagem em discussão; aguardando revisão final da spec

## 1. Contexto e problema

O Viabiliza tem dois ambientes que hoje **compartilham a mesma sessão de navegador**:

- **Portal do cidadão** — guard `web`, login por CPF via Fortify (prefixo `/portal`) e gov.br (HU-151).
- **Console da SEDUR (backoffice)** — guard `gestao`, login interno próprio (`/gestao/login`, `App\Http\Controllers\Gestao\LoginController`).

A separação por *guard* já existe (`config/auth.php` define `web` e `gestao` sobre a mesma tabela `users`) e há decisões registradas de 2026-06-12 nesse sentido (logout por guard, `gestao.guest`, `redirectGuestsTo` por contexto, descarte de `url.intended` de `/gestao` no `LoginResponse` do Fortify). Porém, os dois ambientes ainda **se confundem** em vários pontos:

1. No login do console, o link "Esqueceu a senha?" aponta para `/portal/forgot-password` (`resources/js/pages/auth/gestao-login.tsx:261`) — joga o servidor para o fluxo do portal.
2. Telas de conta (`/settings/profile`, `/settings/password`, `routes/settings.php`) e o termo LGPD (`/portal/termo-lgpd`, `routes/portal.php`) são servidas com `auth:web,gestao`. Com sessão ativa nos dois guards, o guard `web` tem precedência e o usuário acaba operando a conta do **portal** mesmo vindo do console.
3. As props compartilhadas do Inertia (`auth.user`, papéis, permissões, notificações) usam `$request->user()` sem guard fixo (`app/Http/Middleware/HandleInertiaRequests.php`). Resolve certo quando só um ambiente está logado, mas vaza a identidade do portal nas rotas multi-guard acima.
4. Console e portal usam **um único cookie de sessão** do domínio (`config/session.php`: nome único, `path=/`). Logo, expiração/invalidação atinge os dois juntos; não há independência no nível do navegador.

**Objetivo:** tornar as duas autenticações **totalmente separadas** — sessões/cookies independentes por ambiente (mesmo domínio), cada um com fluxo completo (login, recuperação de senha, conta, termo LGPD) e **sem nenhum link, rota ou identidade cruzada**.

## 2. Decisões de escopo (aprovadas pelo usuário)

1. **Nível de separação:** separação lógica/UX completa **+ sessões/cookies independentes por ambiente**, mantendo o mesmo domínio. (Subdomínio — isolamento nativo por host — foi considerado e descartado pelo impacto de infraestrutura.)
2. **Recuperação de senha do console:** **fluxo próprio**, isolado no guard `gestao`, por e-mail institucional, com telas e rotas sob `/gestao` (separado do portal).
3. **Telas de conta do console:** migram para dentro de `/gestao` (`/gestao/conta/perfil`, `/gestao/conta/senha`, `/gestao/termo-lgpd`). `/settings/*` e `/portal/termo-lgpd` passam a ser **exclusivos do portal** (guard `web`).
4. **Estratégia de isolamento de cookie:** **paths disjuntos** — portal sob `/portal`, console sob `/gestao`, cada um com cookie de sessão e CSRF de path próprio. Sem hacks no mecanismo de CSRF.

## 3. Princípio de design

Cada ambiente é uma **fronteira de autenticação fechada**: um usuário em `/gestao/*` só enxerga e opera a sessão do console; um usuário em `/portal/*` (e raiz) só enxerga a sessão do portal. O isolamento é garantido em três camadas, da mais forte para a mais fraca:

1. **Cookie de sessão por ambiente** (nome + path distintos) → as sessões são fisicamente independentes (driver `database`, uma linha por sessão).
2. **Path disjunto** (`/portal` × `/gestao`) → o cookie de um ambiente nem trafega no outro; elimina a colisão do cookie `XSRF-TOKEN` (nome fixo) e o vazamento de identidade.
3. **Rotas single-guard** → nenhuma rota usa mais `auth:web,gestao`; o guard do contexto é único e explícito.

Anti-fachada (regra do projeto): nada é simulado. Cada fluxo novo executa lógica real (reset de senha real via broker, auditoria real). A separação só muda fronteiras de sessão e organização de rotas — não inventa comportamento.

## 4. Núcleo técnico — isolamento de sessão

### 4.1 Middleware de ambiente de sessão

Novo middleware (`App\Http\Middleware\ConfigureEnvironmentSession`) registrado como **middleware GLOBAL** (prepend), rodando **antes do roteamento**. Por contexto de rota define a configuração de sessão do ambiente:

| Contexto | `session.cookie` | `session.path` |
|---|---|---|
| `gestao`, `gestao/*` | `sile_gestao_session` | `/gestao` |
| demais (portal) | `sile_portal_session` | `/portal` |

Responsabilidades do middleware:

- Ajustar `config(['session.cookie' => ..., 'session.path' => ...])`.
- Ajustar o **path/domínio padrão do `CookieJar`** (`cookie()->setDefaultPathAndDomain(...)`) para que cookies derivados (CSRF `XSRF-TOKEN` e "lembrar-me" `remember_*`) herdem o escopo do ambiente.

**Por que GLOBAL e não no grupo `web` (descoberto e comprovado por backtrace na Fase 1):** o Router instancia o controller da rota durante a *coleta* de middleware (`gatherRouteMiddleware` → `getController`), antes de executar a pilha. Os controllers do Fortify injetam o guard `web` no construtor, o que resolve o `session.store` — fixando o nome do cookie — **antes** de qualquer middleware do grupo `web`. Como middleware global roda antes do roteamento, a config vale a tempo para todas as rotas (inclusive as do Fortify). As rotas do console são closures e não disparavam esse caminho, o que mascarava o problema.

**Identidade por guard:** **não** se usa `Auth::shouldUse()` aqui — resolver o guard cedo recria exatamente o problema do `session.store`. A identidade do ambiente é resolvida por guard explícito nos consumidores (§7) e pelos middlewares `auth:web`/`auth:gestao` das rotas.

### 4.2 Rotas stateless

Para evitar sessões órfãs (o cookie de path `/portal` não trafega na raiz nem em `/up`):

- `/up` (health check) deve permanecer **sem sessão** (stateless).
- `Route::redirect('/', '/portal')` é um redirect puro; idealmente também sem sessão. Alternativas concretas (registro fora do grupo `web` ou short-circuit no middleware) ficam para o plano; é detalhe de implementação, não de design.

### 4.3 Verificação de viabilidade no plano

- Confirmar o ponto de inserção do middleware na ordem do grupo `web` (antes de `EncryptCookies`/`StartSession`).
- Confirmar o comportamento do `remember_*` cookie (path) e do `XSRF-TOKEN` (path) sob `setDefaultPathAndDomain`.
- Confirmar interação do `Auth::shouldUse()` global com o `PermissionMiddleware` do spatie (as permissões resolvem pelo `guard_name` do model, hoje `web`); cobrir por teste de regressão dos gates do console.

## 5. Mapa de rotas final por ambiente

### 5.1 Console — guard `gestao`, prefixo `/gestao`, cookie `sile_gestao_session` (path `/gestao`)

- `GET/POST /gestao/login`, `POST /gestao/logout` — já existem.
- `GET /gestao/forgot-password`, `POST /gestao/forgot-password` — **novo**.
- `GET /gestao/reset-password/{token}`, `POST /gestao/reset-password` — **novo**.
- `GET/PATCH /gestao/conta/perfil` — **novo** (substitui o uso de `/settings/profile` pelo console).
- `GET/PUT /gestao/conta/senha` — **novo** (substitui `/settings/password`).
- `GET/POST /gestao/termo-lgpd` — **novo** (termo do console).

Telas de login/forgot/reset sob `gestao.guest`; conta/termo sob `auth:gestao` (+ `lgpd.accepted` onde couber, exceto o próprio termo, para evitar loop).

### 5.2 Portal — guard `web`, prefixo `/portal`, cookie `sile_portal_session` (path `/portal`)

- Fortify (`/portal/login`, `/portal/forgot-password`, `/portal/reset-password`, registro, verificação) e gov.br — já estão em `/portal`.
- `GET/PATCH /portal/conta/perfil`, `GET/PUT /portal/conta/senha` — **migração** de `/settings/*` (controllers podem permanecer; muda prefixo/nome de rota e os links).
- `GET/POST /portal/termo-lgpd` — já existe; deixa de ser multi-guard (passa a `auth:web`).

### 5.3 Removido

- `routes/settings.php` multi-guard e o grupo `auth:web,gestao` do `termo-lgpd` em `routes/portal.php`. **Nenhuma rota** permanece com `auth:web,gestao`.

## 6. Fluxo de recuperação de senha do console (novo)

Controllers próprios em `App\Http\Controllers\Gestao` (ex.: `ForgotPasswordController`, `ResetPasswordController`), usando o broker `users` (mesma tabela `password_reset_tokens`) com **notification própria** (ex.: `GestaoResetPasswordNotification`) cuja URL aponta para `route('gestao.reset-password', token)`, em vez da do Fortify (portal).

- **Paridade de segurança com o login do console** (`LoginController`): throttle parametrizado (`security.login.max_attempts`, HU-014) com evento `Lockout` auditável; anti-oráculo (resposta neutra independentemente de o e-mail existir); auditoria de cada etapa (RN-002, via `AuditService`).
- Pré-condições coerentes com o login interno: a conta precisa existir, estar ativa (HU-012) e ter permissão de acesso ao console; sem revelar esse estado a quem não tem credencial (anti-oráculo).
- Após reset bem-sucedido: redireciona para `/gestao/login` com status (sem auto-login).
- Telas React: `resources/js/pages/auth/gestao-forgot-password.tsx` e `gestao-reset-password.tsx`, no padrão visual do console (reaproveitando componentes das telas equivalentes do portal).

## 7. Correções de vazamento de identidade

- `HandleInertiaRequests::share()` — resolve usuário/papéis/permissões/notificações pelo **guard do contexto** (consequência direta do `Auth::shouldUse()` do §4.1; remove a dependência do guard default ambíguo).
- `EnsureLgpdTermAccepted` — redireciona para o termo do **ambiente correto** (`gestao.termo-lgpd.show` em `/gestao`, senão `portal.termo-lgpd.show`).
- `EnsureUserIsActive` — simplifica para o guard do contexto (com paths disjuntos, o cookie do outro ambiente não trafega; não há mais necessidade de varrer os dois guards na mesma request).
- `bootstrap/app.php` — `redirectGuestsTo` (já por contexto) e `redirectUsersTo` revistos para apontar sempre ao painel do ambiente correto.

## 8. Telas e links (frontend)

- `resources/js/pages/auth/gestao-login.tsx` — "Esqueceu a senha?" passa a `/gestao/forgot-password`.
- `resources/js/components/app/app-header.tsx` — o `UserDropdown` recebe o link de conta por **prop** (`accountHref`), hoje fixo em `/settings/profile`; cada layout injeta o do seu ambiente (`/gestao/conta/perfil` ou `/portal/conta/perfil`). O `logoutHref` já é prop.
- `resources/js/layouts/settings-layout.tsx` — duplicado/parametrizado por ambiente (rotas de conta e "voltar ao painel" do ambiente), ou um layout de conta por ambiente; decisão fina no plano.
- Novas telas do console (forgot/reset, conta perfil/senha, termo-lgpd) reaproveitando os componentes equivalentes do portal.

## 9. Auditoria, segurança e LGPD

- **RN-002:** todos os fluxos novos auditados (login já é; forgot/reset, conta, aceite de termo do console via `AuditService`/`HasAuditoria`).
- **Segurança:** sem alteração em credenciais/segredos; reforço de isolamento de sessão e CSRF por ambiente reduz superfície de confusão. Acionar `especialista-seguranca` no plano (sessão, CSRF, endpoints de recuperação de senha, anti-oráculo, dado pessoal).
- **LGPD:** termo aceito e registrado por ambiente (`LegalTermAcceptance` já é por usuário; muda o ponto de captura/redirect). Acionar `especialista-acessibilidade` para as telas novas.

## 10. Estratégia de testes (TDD)

Feature tests (PHPUnit) — escrever o teste falhando antes de cada incremento:

- **Isolamento de cookie:** login no console emite `sile_gestao_session` (path `/gestao`); login no portal emite `sile_portal_session` (path `/portal`); nomes/paths distintos.
- **Independência:** logar/deslogar/expirar em um ambiente não afeta a sessão do outro.
- **Sem identidade cruzada:** estando logado nos dois, as props compartilhadas em `/gestao/*` trazem o usuário do console e em `/portal/*` o do portal.
- **Rotas single-guard:** acesso às rotas de conta/termo de um ambiente exige o guard daquele ambiente (o outro guard é rejeitado).
- **Recuperação de senha do console:** ponta a ponta — solicitar link (anti-oráculo, throttle/Lockout auditável), e-mail/notification com URL `/gestao/reset-password/{token}`, reset efetivo, redirecionamento ao login do console; auditoria registrada.
- **Redirecionamentos:** guest em rota protegida do console → `/gestao/login`; do portal → `/portal/login`; usuário logado em rota guest → painel do próprio ambiente.
- **CSRF:** ações POST válidas nos dois ambientes simultaneamente (sem 419).
- **Regressão de gates:** as permissões do console (`acessar-gestao`, `permission:*`) continuam funcionando após `Auth::shouldUse('gestao')`.

## 11. Fora de escopo

- Separação por subdomínio/host (descartada).
- Mudança de credenciais, criptografia ou política de senha.
- Verificação de e-mail do console como fluxo novo: mantém-se a exigência de e-mail verificado no login interno, apenas **neutralizando a mensagem** que hoje remete ao portal ("Confirme seu e-mail pelo portal"); a verificação de contas internas segue como responsabilidade do cadastro SEDUR (HU-012). Se exigir reenvio interno, vira pendência registrada — não fachada.

## 12. Critérios de aceite

1. Console e portal usam cookies de sessão distintos, com paths disjuntos (`/gestao` × `/portal`).
2. Nenhuma rota usa `auth:web,gestao`; cada rota é de um único guard.
3. Não há link/URL do console apontando para `/portal/*` (nem o inverso) nos fluxos de autenticação/conta.
4. O console possui fluxo próprio de recuperação de senha sob `/gestao`, com auditoria e paridade de segurança.
5. Telas de conta e termo LGPD do console vivem sob `/gestao`; `/settings/*` e `/portal/termo-lgpd` são exclusivos do portal.
6. As props compartilhadas nunca exibem a identidade de um ambiente no outro.
7. Toda a suíte de testes (incluindo os novos) passa; cada CA acima coberto por teste.

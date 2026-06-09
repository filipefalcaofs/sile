# Fase 1: Identidade, Acesso e Auditoria Transversal — Pesquisa

**Pesquisado em:** 2026-06-09
**Domínio:** Autenticação Laravel 13 + Inertia v3 + React 19; RBAC; auditoria transversal; LGPD
**Confiança:** ALTA

## Summary

A pesquisa respondeu as oito questões críticas do CONTEXT.md com verificação direta: dry-run do Composer contra o Laravel 13.15 instalado (prova definitiva de compatibilidade), documentação oficial 13.x, código-fonte do starter kit React oficial do Laravel e changelogs/upgrade guides dos pacotes Spatie.

**Decisão central — autenticação:** usar **Laravel Fortify ^1.37** (headless). É exatamente o que o starter kit React oficial do Laravel 13 usa (`laravel/react-starter-kit` requer `laravel/fortify ^1.37.2`), com views Inertia registradas via `Fortify::loginView()` etc. Fortify entrega de graça: registro, login com rate limiting, logout, reset de senha, confirmação de e-mail, alteração de senha — tudo disparando os eventos nativos de auth (`Login`, `Logout`, `Failed`, `Lockout`, `Registered`, `Verified`, `PasswordReset`) que alimentam a auditoria e o histórico de acessos. Implementação própria seria reinventar código testado pela comunidade sem ganho: os pontos de customização do SILE (política de senha parametrizada, rate limit parametrizado, redirect por perfil, auditoria) são todos extensíveis no Fortify.

**Decisão central — auditoria (RN-002):** **spatie/laravel-activitylog ^5.0** (v5 saiu em 2026-03-25; requer PHP 8.4+ e Laravel 12+ — compatível) com extensão SILE: migration publicada ganha colunas próprias (`ip_address`, `user_agent`, `channel`, `acting_for_user_id`, `result`, `rules_version`), model `Activity` próprio e **`LogActivityAction` customizada** (novidade da v5: action classes substituíveis via config) que enriquece TODO registro — de evento de model ou chamada manual — com origem, canal e "em nome de" num ponto único. Histórico de acessos (HU-010) fica em tabela dedicada `access_logs` alimentada por listeners dos eventos de auth (decisão fundamentada na seção de padrões).

**Recomendação primária:** instalar `laravel/fortify ^1.37`, `spatie/laravel-permission ^8.0`, `spatie/laravel-activitylog ^5.0` e `laravel-lang/common ^6.8` (dev); seguir os padrões do starter kit oficial para Fortify+Inertia; construir a infraestrutura de auditoria como primeira task (as demais HUs dependem dela).

## Standard Stack

Versões resolvidas por `composer require --dry-run` contra o projeto real (Laravel 13.15.0, PHP 8.5.4) em 2026-06-09 — compatibilidade comprovada, não estimada:

### Core (novas dependências de produção)

| Pacote | Versão resolvida | Propósito | Por quê |
|---|---|---|---|
| `laravel/fortify` | 1.37.2 (`^1.37`) | Backend headless de autenticação (registro, login, reset, verificação de e-mail, alteração de senha, throttling) | Padrão oficial: starter kits do Laravel 13 usam Fortify; views Inertia plugáveis; eventos nativos para auditoria |
| `spatie/laravel-permission` | 8.0.0 (`^8.0`) | Perfis e permissões granulares (RN-003) | Referência SIGVISA; middleware `role`/`permission`; v8 é a linha atual (suporta enums em `findByName`) |
| `spatie/laravel-activitylog` | 5.0.0 (`^5.0`) | Núcleo da auditoria transversal (RN-002) | Referência SIGVISA (`HasAuditoria`); v5 traz action classes substituíveis — ponto único de enriquecimento |

### Suporte (dev)

| Pacote | Versão | Propósito | Quando usar |
|---|---|---|---|
| `laravel-lang/common` | 6.8.0 (`^6.8`, `--dev`) | Traduções pt_BR de validação, auth, passwords, notificações nativas | Instalar uma vez, rodar `php artisan lang:add pt_BR`, commitar `lang/` |

### Alternativas consideradas (e rejeitadas)

| Em vez de | Poderia usar | Trade-off / por que rejeitar |
|---|---|---|
| Fortify | Controllers de auth próprios (starter kit antigo) | Controle total, porém reimplementa throttling, reset, verificação; mais código para manter e testar; starter kits oficiais abandonaram esse modelo no L13 |
| Fortify | `laravel/jetstream` | Traz frontend acoplado (Livewire/Inertia-Vue) — incompatível com o esqueleto React custom |
| activitylog estendido | Tabela de auditoria 100% própria | Perderia traits/observers prontos, scopes de consulta, limpeza (`clean_log`), e a v5 já permite custom model + custom action — extensão cobre RN-002 |
| `access_logs` dedicada | Auditoria filtrada (`log_name='acesso'`) | Ver justificativa na seção de padrões — login falho não tem causer e a consulta do usuário (HU-010) é hot-path |
| `laravel/wayfinder` (rotas tipadas TS) | — | Starter kit usa, mas exige composer + npm + plugin Vite extras; esqueleto não tem; Fase 1 usa rotas literais/nomeadas — adoção pode ser reavaliada depois |
| Pacote CPF (`laravellegends/pt-br-validator`) | Rule própria `ValidCpf` | Algoritmo de CPF é pequeno e estável; uma Rule com testes evita dependência nova (AGENTS.md: não mudar deps sem aprovação). Decisão final do planner |

**Instalação:**

```bash
composer require laravel/fortify spatie/laravel-permission spatie/laravel-activitylog
composer require laravel-lang/common --dev

php artisan fortify:install            # publica app/Actions/Fortify, FortifyServiceProvider, config, migrations
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-config"
php artisan lang:add pt_BR
php artisan migrate
```

Observações:
- `fortify:install` publica as actions em `app/Actions/Fortify` e registra o `FortifyServiceProvider` em `bootstrap/providers.php`. A migration de 2FA publicada é inofensiva com a feature desligada (Fortify 1.37 também puxa `laravel/passkeys` como dependência — feature fica desabilitada).
- Editar a migration publicada do activitylog ANTES de rodar (projeto novo, sem produção) para acrescentar as colunas SILE.
- `.env`: trocar `APP_LOCALE=pt_BR`, `APP_FAKER_LOCALE=pt_BR` (hoje está `en`/`en_US`).

## Architecture Patterns

### Estrutura recomendada

```
app/
├── Actions/Fortify/            # CreateNewUser, ResetUserPassword, UpdateUserPassword (publicadas)
├── Http/
│   ├── Controllers/
│   │   ├── Portal/             # ProcurationController, RepresentationController, AccessHistoryController...
│   │   ├── Gestao/             # DashboardController (mínimo nesta fase)
│   │   └── Settings/           # ProfileController (HU-007), padrão starter kit
│   ├── Middleware/
│   │   ├── HandleInertiaRequests.php      # (existe) + shared props auth/permissions/acting_for/flash
│   │   ├── SecurityHeaders.php            # referência SIGVISA
│   │   ├── EnsureLgpdTermAccepted.php     # HU-006
│   │   └── ResolveRepresentation.php      # HU-008/009 — valida procuração ativa a cada request
│   └── Requests/               # Form Requests (ProfileUpdateRequest, StoreProcurationRequest...)
├── Listeners/                  # RecordSuccessfulLogin, RecordFailedLogin, RecordLogout, RecordLockout (auto-descobertos)
├── Models/
│   ├── Activity.php            # estende Spatie, colunas SILE
│   ├── AccessLog.php           # HU-010
│   ├── LegalTerm.php / LegalTermAcceptance.php   # HU-006
│   ├── Procuration.php         # HU-008/009
│   └── User.php                # + HasRoles, HasActivity, MustVerifyEmail, HasAuditoria
├── Support/
│   ├── Audit/RecordActivityAction.php     # estende LogActivityAction (v5) — enriquecimento central
│   ├── Audit/AuditService.php             # log explícito em serviços (ação, resultado, versão de regras)
│   └── Settings.php                       # leitura de parâmetros (config-backed na Fase 1)
├── Concerns/HasAuditoria.php   # trait: LogsActivity + LogOptions padrão SILE
config/sile.php                 # parâmetros de negócio com defaults (política de senha, throttle, etc.)
routes/
├── web.php                     # público + require dos demais (padrão starter kit)
├── auth.php                    # rotas extras de auth se necessário (Fortify registra as suas)
├── portal.php                  # SILE Cidadão (auth + verified + lgpd)
└── gestao.php                  # SILE Gestão (auth + verified + lgpd + permission:acessar-gestao)
resources/js/
├── pages/
│   ├── auth/                   # login, register, forgot-password, reset-password, verify-email (nomes = Inertia::render)
│   ├── portal/                 # dashboard, procuracoes/, acessos/, termo-lgpd
│   ├── gestao/                 # dashboard (mínimo nesta fase)
│   └── settings/               # profile, security (padrão starter kit)
└── layouts/                    # auth-layout, portal-layout, gestao-layout
```

### Padrão 1: Fortify headless + Inertia (starter kit oficial)

**O quê:** Fortify define rotas e lógica; o `FortifyServiceProvider` registra as views como páginas Inertia e o rate limiter de login.
**Fonte:** `laravel/react-starter-kit` (main, 2026) — código real:

```php
// app/Providers/FortifyServiceProvider.php (padrão do starter kit, adaptado ao SILE)
Fortify::createUsersUsing(CreateNewUser::class);
Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
    'canResetPassword' => Features::enabled(Features::resetPasswords()),
    'status' => $request->session()->get('status'),
]));
Fortify::registerView(fn () => Inertia::render('auth/register', [
    'passwordRules' => Password::defaults()->toPasswordRulesString(),
]));
Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
    'status' => $request->session()->get('status'),
]));
Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
    'email' => $request->email,
    'token' => $request->route('token'),
]));
Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
    'status' => $request->session()->get('status'),
]));

// Rate limit de login PARAMETRIZADO (RN do SILE: nada hardcoded)
RateLimiter::for('login', function (Request $request) {
    $key = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());
    return Limit::perMinute(config('sile.security.login.max_attempts'))->by($key);
});
```

`config/fortify.php` — features para a Fase 1:

```php
'features' => [
    Features::registration(),        // HU-001
    Features::resetPasswords(),      // HU-003
    Features::emailVerification(),   // HU-005
    Features::updatePasswords(),     // HU-004 (PUT /user/password)
],
// 2FA e passkeys FORA: não constam nas HUs (não implementar — escopo)
```

**Login único, dois ambientes (HU-002):** um único guard `web` e uma única tela de login; o redirect pós-login é decidido por perfil via binding do contrato `LoginResponse`:

```php
// register() do FortifyServiceProvider
$this->app->instance(LoginResponse::class, new class implements LoginResponse {
    public function toResponse($request)
    {
        $user = $request->user();
        $target = $user->can('acessar-gestao') ? route('gestao.dashboard') : route('portal.dashboard');
        return redirect()->intended($target);
    }
});
```

Cidadão que tentar URL de gestão cai no middleware `permission:acessar-gestao` → 403 → registrado em auditoria (CA-04). Dois guards separados foram rejeitados: dobram a complexidade de sessão e o spatie/permission com múltiplos guards exige permissões duplicadas por guard.

### Padrão 2: Auditoria transversal (RN-002) sobre activitylog v5

**Mapeamento RN-002 → armazenamento** (decisão de schema para o planner):

| Campo RN-002 | Onde fica | Nativo/Custom |
|---|---|---|
| Usuário (ou serviço) | `causer_type`/`causer_id` (morph) | Nativo |
| Data/hora | `created_at` | Nativo |
| Origem | `ip_address`, `user_agent`, `channel` (portal/gestao/console) | **Colunas novas** |
| Ação executada | `description` + `event` + `log_name` | Nativo |
| Dados de entrada | `properties` (JSON, com redação de sensíveis) | Nativo |
| Mudanças de atributos | `attribute_changes` (JSON) | Nativo (novo na v5) |
| Resultado | `result` (string: `sucesso`/`bloqueado`/`falha`/`erro`) | **Coluna nova** |
| Erros/exceções | `properties['error']` | Nativo |
| Versão de regras | `rules_version` (string nullable, indexada) | **Coluna nova** (nasce agora para Fases 5/6) |
| "Em nome de" (procurador) | `acting_for_user_id` (FK users nullable) | **Coluna nova** (HU-008/009) |

**Enriquecimento central — v5 action class** (cobre model events E chamadas manuais num ponto só; API nova da v5, não existe na v4):

```php
// app/Support/Audit/RecordActivityAction.php
use Spatie\Activitylog\Actions\LogActivityAction;

class RecordActivityAction extends LogActivityAction
{
    protected function save(Model $activity): void
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            $activity->channel ??= 'console';
        } elseif (request()) {
            $activity->ip_address ??= request()->ip();
            $activity->user_agent ??= substr((string) request()->userAgent(), 0, 500);
            $activity->channel ??= request()->routeIs('gestao.*') ? 'gestao' : 'portal';
            $activity->acting_for_user_id ??= Context::get('acting_for_user_id');
        }
        $activity->result ??= 'sucesso';

        parent::save($activity);
    }
}
// config/activitylog.php → 'actions' => ['log_activity' => RecordActivityAction::class],
//                         → 'activity_model' => App\Models\Activity::class,
```

**Trait `HasAuditoria`** (nome de referência do SIGVISA) para models de domínio:

```php
// app/Concerns/HasAuditoria.php
trait HasAuditoria
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->logExcept(['password', 'remember_token']);
    }
}
```

**`AuditService`** para registro explícito em serviços/listeners (ação, resultado, versão de regras):

```php
activity('seguranca')
    ->causedBy($user)
    ->withProperties(['rota' => $request->path()])
    ->event('acesso-negado')
    ->log('Tentativa de acesso sem permissão');
// AuditService encapsula esse builder e seta result='bloqueado', rules_version etc.
```

**CA-04 (tentativa sem permissão registrada):** ponto único no exception handler — `Spatie\Permission\Exceptions\UnauthorizedException` e `Illuminate\Auth\Access\AuthorizationException` interceptadas em `bootstrap/app.php` (`->withExceptions()`), chamando `AuditService::logBlocked()` antes de renderizar o 403. Cobre todas as HUs de uma vez (nenhum controller precisa lembrar de auditar bloqueio).

**Cuidado com API v5 (breaking changes vs v4 — não usar exemplos antigos da web):**
- `tapActivity()` → agora `beforeActivityLogged(Activity $activity, string $eventName)`
- `$activity->changes()` → `$activity->attribute_changes`
- `getExtraProperty()` → `getProperty()`
- `CauserResolver::setCauser()` → `Activity::defaultCauser()`
- `dontSubmitEmptyLogs()` → `dontLogEmptyChanges()`
- Sistema de batch removido; trait `HasActivity` (LogsActivity + CausesActivity) reintroduzida — usar no `User`.

### Padrão 3: Histórico de acessos (HU-010) — tabela dedicada + listeners

**Decisão (era discricionária):** tabela `access_logs` dedicada, NÃO evento filtrado na `activity_log`. Justificativa:
1. Login falho não tem usuário autenticado (causer null) — na activity_log o registro ficaria órfão de morph; na tabela própria, `email` tentado + `user_id` nullable modelam isso naturalmente.
2. HU-010 é consulta hot-path do próprio usuário — índice `(user_id, created_at)` direto, sem filtrar morphs.
3. Retenção independente da trilha geral (Fase 12 define políticas distintas).

Schema: `id, user_id (nullable FK), email (string), event (string: login|logout|falha|bloqueio), ip_address (string 45), user_agent (string 500 nullable), channel (string), created_at`.

Listeners (auto-descobertos em `app/Listeners`, Laravel 13 não exige registro manual):

| Evento (`Illuminate\Auth\Events\*`) | Listener | Grava |
|---|---|---|
| `Login` | `RecordSuccessfulLogin` | `event=login`, user_id |
| `Logout` | `RecordLogout` | `event=logout`, user_id |
| `Failed` | `RecordFailedLogin` | `event=falha`, email tentado, user_id se existir |
| `Lockout` | `RecordLockout` | `event=bloqueio`, email tentado |
| `Registered` | (listener de auditoria) | activity `cadastro` (CA-02 HU-001) |
| `Verified` | (listener de auditoria) | activity `email-confirmado` (CA-02 HU-005) |
| `PasswordReset` | (listener de auditoria) | activity `senha-redefinida` (CA-02 HU-003) |

Fortify dispara todos esses eventos nativamente (pipeline padrão do guard de sessão + actions do Fortify) — nenhum hook extra necessário.

UI: página `portal/acessos` (próprios acessos, paginado); consulta de terceiros exige permissão `consultar-acessos-de-qualquer-conta` (admin) — rota na gestão com parâmetro de usuário.

### Padrão 4: Perfis e permissões (spatie/laravel-permission v8)

- `User` ganha `use HasRoles`.
- Middleware aliases em `bootstrap/app.php` (Laravel 13 não tem Kernel):

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->web(append: [SecurityHeaders::class, HandleInertiaRequests::class]);
    $middleware->alias([
        'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
        'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        'lgpd.accepted' => \App\Http\Middleware\EnsureLgpdTermAccepted::class,
    ]);
})
```

- **Papéis (decisão recomendada):** `cidadao`, `analista`, `gestor`, `administrador`. Contador e procurador NÃO são papéis: são cidadãos cujo poder vem de dados (procuração ativa, vínculo empresarial na Fase 3). Evita inconsistência papel × estado (procuração revogada não pode depender de remover papel). O formulário de cadastro pode coletar "tipo de usuário" como dado informativo do perfil.
- **Permissões seed da Fase 1 (mínimo):** `acessar-gestao`, `consultar-acessos-de-qualquer-conta`, `gerenciar-procuracoes-proprias` (cidadão), demais nascem nas fases seguintes. Seeder `RolesAndPermissionsSeeder` chama `app(PermissionRegistrar::class)->forgetCachedPermissions()` antes de criar.
- **Compartilhamento com Inertia** (`HandleInertiaRequests::share`):

```php
'auth' => [
    'user' => $request->user()?->only('id', 'name', 'email'),
    'roles' => $request->user()?->getRoleNames(),
    'permissions' => $request->user()?->getAllPermissions()->pluck('name'),
],
'actingFor' => fn () => app(CurrentRepresentation::class)->grantor()?->only('id', 'name'),
'flash' => ['status' => $request->session()->get('status')],
```

### Padrão 5: Procuração (HU-008/009) e "em nome de"

Modelagem mínima viável (sem cadastro empresarial — Fase 3):

```
procurations
├── id
├── grantor_user_id   FK users  (outorgante / representante legal)
├── attorney_user_id  FK users  (procurador — conta já existente, localizada por e-mail)
├── starts_at         timestamp (default now)
├── expires_at        timestamp nullable   (vigência)
├── revoked_at        timestamp nullable
├── revoked_by_user_id FK users nullable
└── timestamps
```

- Unicidade "uma procuração ativa por par (outorgante, procurador)": validação de aplicação (scope `active()`), índice simples `(grantor_user_id, attorney_user_id)` — índice único parcial via SQL cru foi rejeitado por portabilidade (testes rodam em SQLite `:memory:`, dev em Postgres).
- **Atuação "em nome de":** procurador escolhe representado (POST cria na sessão `acting_procuration_id`); middleware `ResolveRepresentation` roda em TODA request autenticada do portal: revalida a procuração (ativa, não expirada, não revogada) — efeito imediato da revogação (CA HU-009) —, popula `Context::add('acting_for_user_id', $grantorId)` (o `Context` do Laravel propaga para jobs em fila, beneficiando auditoria assíncrona futura) e compartilha `actingFor` com o React (banner "Atuando em nome de...").
- **Fase 3 sem retrabalho:** procuração permanece user→user; acesso às empresas do outorgante deriva dos vínculos empresariais dele (join na Fase 3). Se escopo por empresa for exigido, acrescenta-se `company_id` nullable por migration aditiva.

### Padrão 6: Termo LGPD versionado (HU-006)

```
legal_terms                       legal_term_acceptances
├── id                            ├── id
├── type      (ex.: 'lgpd')       ├── user_id        FK
├── version   (int)               ├── legal_term_id  FK
├── title                         ├── ip_address
├── content   (text)              ├── user_agent
├── published_at (nullable=draft) └── accepted_at + unique(user_id, legal_term_id)
└── timestamps
```

- Texto do termo NUNCA em código: seeder cria a versão 1 com o texto (dado administrável — Fase 2/HU-014 ganha UI de edição que publica novas versões).
- Middleware `lgpd.accepted` nas rotas autenticadas (portal e gestão), exceto rotas do próprio termo, verificação de e-mail e logout: usuário sem aceite da versão vigente → redirect para `portal/termo-lgpd` (página com conteúdo + aceite). Nova versão publicada → todos os usuários são re-apresentados ao termo automaticamente (o middleware compara contra a versão vigente).
- Aceite grava: quem, quando, qual versão, IP (CA-02) — e gera activity.
- Ordem de middlewares: `auth` → `verified` → `lgpd.accepted` (fluxo de primeiro acesso: cadastro → confirma e-mail → login → aceita termo → usa o sistema).

### Padrão 7: Parametrização sem UI (preparação para HU-014)

`config/sile.php` com defaults sensatos + wrapper fino:

```php
// config/sile.php
return [
    'security' => [
        'password' => ['min_length' => 8, 'require_mixed_case' => true, 'require_numbers' => true, 'require_symbols' => false],
        'login' => ['max_attempts' => 5],          // por minuto, por e-mail+IP
        'password_reset_expire' => 60,             // minutos (espelha auth.passwords.users.expire)
    ],
];
```

- Política de senha aplicada via `Password::defaults()` no `AppServiceProvider::boot()` lendo `config('sile.security.password.*')` — usada por Fortify no cadastro, reset e alteração (`Password::defaults()->toPasswordRulesString()` é compartilhável com o React para exibir os requisitos).
- Classe `App\Support\Settings::get('security.login.max_attempts')` delegando para `config()` na Fase 1 — na Fase 2 o backend vira banco (HU-014) sem tocar nos call sites. Indireção de uma classe, paga-se na Fase 2.

### Padrão 8: Rotas segregadas e SecurityHeaders

- Padrão do starter kit oficial: `routes/web.php` faz `require __DIR__.'/portal.php'` e `require __DIR__.'/gestao.php'` (mais simples que `then:` no `withRouting`).
- Grupos: portal → `Route::middleware(['auth', 'verified', 'lgpd.accepted'])->name('portal.')->prefix('portal')`; gestão → idem + `permission:acessar-gestao`, `->name('gestao.')->prefix('gestao')`.
- Nomes de rota com prefixo (`portal.*`/`gestao.*`) alimentam a detecção de `channel` na auditoria.
- `SecurityHeaders` (referência SIGVISA): `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` mínima — appended ao grupo web.

### Padrão 9: Frontend Inertia v3 + React 19

- Páginas auto-resolvidas de `resources/js/pages` (o `app.tsx` atual com `createInertiaApp({ strictMode: true })` já faz isso via `@inertiajs/vite`); `Inertia::render('auth/login')` ↔ `resources/js/pages/auth/login.tsx` — caminho exato, case-sensitive.
- Formulários: componente `<Form>` do v3 (recomendado pela skill `inertia-react-development`) ou `useForm` — telas de auth são forms simples, `<Form action="/login" method="post">` com render props (`errors`, `processing`).
- Navegação somente com `<Link>`; logout via `<Link href="/logout" method="post" as="button">`.
- Feedback pós-ação: `Inertia::flash('toast', ...)` (v3) ou session `status` compartilhada — starter kit usa ambos.
- Layouts persistentes por contexto (auth/portal/gestão) com textos 100% pt-BR escritos direto nos componentes (não há i18n client-side — UI é só pt-BR).
- Tailwind 4 já configurado via `@tailwindcss/vite`; seguir convenções da rule do repositório (rounded-lg/xl, dark mode com `dark:`, mobile-first).

### Anti-padrões a evitar

- **Rotas de auth manuais paralelas ao Fortify** (POST /login próprio): conflito de rotas e dois caminhos de autenticação — toda customização via extension points do Fortify.
- **Auditoria chamada manualmente em cada controller:** o mecanismo é transversal (trait + action custom + exception handler + listeners); chamada explícita só em serviços com semântica própria.
- **`Event::fake()` global em testes de auth:** mata os listeners de access log/auditoria que são o objeto do teste (CA-02). Usar `Event::fake([EventoEspecifico::class])` apenas para asserções de dispatch, ou assertar efeitos no banco.
- **Valores de negócio hardcoded** (tentativas de login, expiração de link, texto do termo): tudo em `config/sile.php`/seed.
- **Papel "procurador"** como role do spatie: estado de procuração é dado com vigência, não papel.

## Don't Hand-Roll

| Problema | Não construa | Use | Por quê |
|---|---|---|---|
| Login/logout/reset/verificação | Controllers de auth artesanais | Fortify 1.37 | Throttling, session fixation, hash de token de reset, eventos — tudo resolvido e auditado pela comunidade |
| Política de senha | Regex própria | `Password::defaults()` + config | Validador nativo com `min/mixedCase/numbers/symbols/uncompromised`; string de regras compartilhável com o frontend |
| Rate limit de login | Contador próprio em cache | `RateLimiter::for('login')` | Integrado ao pipeline do Fortify; dispara `Lockout` |
| RBAC | Tabelas role/permission próprias | spatie/laravel-permission 8 | Cache de permissões, middleware, Blade/Gate integration; padrão SIGVISA |
| Trilha de auditoria (núcleo) | Observer + tabela do zero | spatie/laravel-activitylog 5 estendido | Morphs subject/causer, scopes, limpeza, action classes substituíveis |
| Traduções de framework pt-BR | Escrever validation.php na mão | laravel-lang/common | 100% das mensagens nativas traduzidas e atualizáveis (`lang:update`) |
| Confirmação de e-mail | Token próprio | `MustVerifyEmail` + feature do Fortify | Rota assinada com hash, expiração, throttle de reenvio nativos |
| Dados de request em jobs | Passar IP manualmente | `Context` (Laravel 11+) | Propaga metadados (acting_for, ip) para filas automaticamente |

**Insight-chave:** a Fase 1 é 80% "cola" entre pacotes maduros. O código autoral de verdade é: colunas/action de auditoria, access_logs + listeners, procuração + representação, termo LGPD + middleware, seeds e as páginas React — exatamente onde os CAs das HUs apontam.

## Common Pitfalls

### Pitfall 1: `ViteException` nos feature tests
**O que acontece:** GET em rota Inertia renderiza `app.blade.php` com `@vite` → sem manifest, teste explode com "Unable to locate file in Vite manifest".
**Como evitar:** `$this->withoutVite()` no `setUp()` do `tests/TestCase.php` (base de todos) — não exige Node no CI de backend. Alternativa: `npm run build` antes da suíte.
**Sinal de alerta:** primeiro teste de tela falhando com exceção de Vite, não de asserção.

### Pitfall 2: cache de permissões do Spatie entre testes
**O que acontece:** permissões criadas num teste não aparecem noutro, ou `PermissionDoesNotExist` após `RefreshDatabase`.
**Como evitar:** no seeder/`setUp`, `app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();` após criar roles/permissions. Cache store em testes é `array` (phpunit.xml), o que reduz mas não elimina o problema dentro do mesmo processo.

### Pitfall 3: exemplos da web com API v4 do activitylog
**O que acontece:** quase todo tutorial usa `tapActivity`, `$activity->changes()`, `getExtraProperty()` — removidos/renomeados na v5 (2026-03).
**Como evitar:** seguir o UPGRADING.md/blog oficial (fontes abaixo); hooks corretos: `beforeActivityLogged()`, `attribute_changes`, `getProperty()`.

### Pitfall 4: `Event::fake()` engolindo a auditoria
**O que acontece:** teste de CA-02 (auditoria de login) com `Event::fake()` global passa em falso ou falha sem motivo — listeners nunca rodam.
**Como evitar:** para CAs de auditoria, assertar o efeito (`assertDatabaseHas('access_logs', ...)`); fakes só escopados.

### Pitfall 5: `#[Fillable]` do model User (Laravel 13)
**O que acontece:** o esqueleto usa atributos PHP `#[Fillable(['name','email','password'])]`; campos novos (cpf, phone) silenciosamente não preenchem em mass assignment.
**Como evitar:** atualizar o atributo `#[Fillable]` na mesma task que adicionar a coluna; teste de cadastro pega isso (TDD).

### Pitfall 6: esquecer `MustVerifyEmail`
**O que acontece:** middleware `verified` deixa todo mundo passar e Fortify não envia o e-mail de verificação.
**Como evitar:** `User implements MustVerifyEmail` (o import já existe comentado no esqueleto) + feature `emailVerification()` habilitada. Teste de HU-005 CA-01 cobre.

### Pitfall 7: revogação sem efeito imediato (CA HU-009)
**O que acontece:** validar procuração só no momento do "switch" deixa o procurador navegando em nome do outorgante após a revogação, até o logout.
**Como evitar:** `ResolveRepresentation` revalida a procuração ativa em TODA request; se revogada/expirada → limpa sessão, audita e redireciona com aviso.

### Pitfall 8: migrations não portáveis Postgres × SQLite
**O que acontece:** dev usa Postgres (porta 5433), testes usam SQLite `:memory:` — índice único parcial via `DB::statement` ou `jsonb` explícito quebram a suíte.
**Como evitar:** usar apenas recursos do Schema builder portáveis (`json()`, índices simples); unicidade condicional (procuração ativa) na camada de aplicação.

### Pitfall 9: locale esquecido em `en`
**O que acontece:** mensagens de validação/notificações saem em inglês (viola regra pt-BR do projeto).
**Como evitar:** `APP_LOCALE=pt_BR` + `APP_FAKER_LOCALE=pt_BR` no `.env` e `.env.example`, `lang/pt_BR` commitado (laravel-lang). Teste pode assertar mensagem pt-BR de validação.

### Pitfall 10: nomes de página Inertia divergentes
**O que acontece:** `Inertia::render('Auth/Login')` com arquivo `auth/login.tsx` → página em branco/erro de resolução (case-sensitive no Linux/CI, tolerante no macOS).
**Como evitar:** convenção única kebab/minúsculas (`auth/login`, `portal/dashboard`) — padrão do starter kit; `assertInertia(->component('auth/login'))` nos testes trava a convenção.

## Code Examples

Padrões verificados nas fontes oficiais:

### Teste de autenticação (starter kit oficial, PHPUnit 12 — base para os CAs)

```php
// Fonte: laravel/react-starter-kit tests/Feature/Auth/AuthenticationTest.php
public function test_users_can_authenticate_using_the_login_screen(): void
{
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
}

public function test_users_are_rate_limited(): void
{
    $user = User::factory()->create();
    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $response = $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);
    $response->assertTooManyRequests();
}
```

Extensão SILE para CA-02 (auditoria) e HU-010 no mesmo teste de login:

```php
public function test_login_gera_registro_no_historico_de_acessos(): void
{
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    $this->assertDatabaseHas('access_logs', [
        'user_id' => $user->id,
        'event' => 'login',
        'ip_address' => '127.0.0.1',
    ]);
}
```

### Asserções Inertia (docs oficiais v3)

```php
// Fonte: inertiajs.com/docs/v3/advanced/testing
use Inertia\Testing\AssertableInertia as Assert;

$this->get(route('login'))
    ->assertOk()
    ->assertInertia(fn (Assert $page) => $page
        ->component('auth/login')
        ->has('canResetPassword'));
```

### Listener de login (auto-descoberto)

```php
// app/Listeners/RecordSuccessfulLogin.php
use Illuminate\Auth\Events\Login;

class RecordSuccessfulLogin
{
    public function handle(Login $event): void
    {
        AccessLog::create([
            'user_id' => $event->user->getAuthIdentifier(),
            'email' => $event->user->email,
            'event' => 'login',
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 500),
            'channel' => request()->routeIs('gestao.*') ? 'gestao' : 'portal',
        ]);
    }
}
```

### Migration estendida do activitylog (editar a publicada antes de migrar)

```php
Schema::create('activity_log', function (Blueprint $table) {
    $table->bigIncrements('id');
    $table->string('log_name')->nullable()->index();
    $table->text('description');
    $table->nullableMorphs('subject', 'subject');
    $table->string('event')->nullable();
    $table->nullableMorphs('causer', 'causer');
    $table->json('properties')->nullable();
    $table->json('attribute_changes')->nullable();   // v5
    // ---- colunas SILE (RN-002) ----
    $table->string('ip_address', 45)->nullable();
    $table->string('user_agent', 500)->nullable();
    $table->string('channel', 20)->nullable();                       // portal|gestao|console
    $table->foreignId('acting_for_user_id')->nullable()->constrained('users'); // "em nome de"
    $table->string('result', 20)->nullable()->index();               // sucesso|bloqueado|falha|erro
    $table->string('rules_version', 50)->nullable()->index();        // Fases 5/6
    $table->timestamps();
});
```

### Model event logging (docs v5)

```php
// Fonte: freek.dev/3058 (oficial Spatie, 2026-03)
class NewsItem extends Model
{
    use LogsActivity;   // v5: getActivitylogOptions() é OPCIONAL para logging básico

    public function beforeActivityLogged(Activity $activity, string $eventName): void
    {
        $activity->properties = $activity->properties->merge(['contexto' => '...']);
    }
}
```

### Aceite LGPD — middleware

```php
// app/Http/Middleware/EnsureLgpdTermAccepted.php
public function handle(Request $request, Closure $next): Response
{
    $currentTerm = LegalTerm::current('lgpd');   // versão publicada vigente (cacheável)

    if ($currentTerm && ! $request->user()->hasAcceptedTerm($currentTerm)) {
        return redirect()->route('portal.termo-lgpd.show');
    }

    return $next($request);
}
```

## State of the Art

| Abordagem antiga | Abordagem atual | Quando mudou | Impacto na fase |
|---|---|---|---|
| Starter kits com controllers de auth próprios (Breeze/kits 2024) | Starter kits oficiais L13 100% sobre Fortify | Laravel 12→13 (2025/2026) | Fortify é o caminho oficial; copiar padrões do `react-starter-kit` |
| activitylog v4 (`tapActivity`, `changes()`, batches) | v5: action classes substituíveis, `attribute_changes`, `beforeActivityLogged` | 2026-03-25 | Enriquecimento central via action custom — design mais limpo p/ RN-002 |
| spatie/permission v6 (PHP 8.0+) | v7 (2026-02) → v8 (2026-05): modernização, enums em `findByName` | 2026 | Instalar direto na v8; docs v8 em spatie.be |
| Inertia v2 (axios, `Inertia::lazy`) | v3: XHR client próprio, `<Form>`, `useHttp`, `Inertia::flash`, `Inertia::optional` | Laracon 2025 | Usar `<Form>`/flash; eventos renomeados (`httpException`); `router.cancelAll()` |
| Páginas registradas manualmente no `createInertiaApp` | Auto-resolução via `@inertiajs/vite` | Inertia v3 | `app.tsx` de 3 linhas do esqueleto já está correto |

**Deprecado/fora de uso:**
- `app/Http/Kernel.php` — middleware/aliases vivem em `bootstrap/app.php` (L11+).
- `Inertia::lazy()` — removido no v3; usar `Inertia::optional()`.
- Registro manual de listeners no EventServiceProvider — auto-discovery em `app/Listeners` é o default.

## Validation Architecture

### Comandos

| Comando | O que prova |
|---|---|
| `php artisan test --compact` | Suíte completa (todos os CAs da fase) |
| `php artisan test --compact tests/Feature/Auth` | HU-001 a HU-005 (cadastro, login, senha, e-mail) |
| `php artisan test --compact --filter=Procuration` | HU-008/HU-009 |
| `php artisan test --compact --filter=Audit` | Infraestrutura RN-002 (CA-02 transversal) |
| `vendor/bin/pint --dirty --format agent` | Estilo PHP conforme projeto (obrigatório após editar PHP) |
| `npm run typecheck && npm run build` | Frontend compila sem erros de tipo |

### Grupos de teste e o que cada um prova

| Grupo (tests/Feature/) | HU | Prova |
|---|---|---|
| `Auth/RegistrationTest` | HU-001 | CA-01 cadastro cria usuário+papel cidadão e dispara verificação; CA-03 validação bloqueia dados faltantes (FA-01); CA-02 activity de cadastro registrada |
| `Auth/AuthenticationTest` | HU-002 | CA-01 login redireciona por perfil; CA-02 access_log de login; CA-03 credencial inválida não autentica + access_log `falha`; CA-04 cidadão em rota de gestão → 403 + activity `bloqueado`; lockout após N tentativas (parametrizado) |
| `Auth/PasswordResetTest` | HU-003 | CA-01 fluxo completo com `Notification::fake()` + `assertSentTo(ResetPassword)`; token inválido falha; activity de redefinição |
| `Settings/PasswordUpdateTest` | HU-004 | CA-01 senha atual exigida e nova política aplicada; CA-02 auditado; CA-03 senha atual errada bloqueia |
| `Auth/EmailVerificationTest` | HU-005 | CA-01 link assinado verifica; CA-03 hash inválido não verifica; não verificado não passa do middleware `verified` (acesso restrito) |
| `Lgpd/TermAcceptanceTest` | HU-006 | CA-01 aceite grava versão+IP; middleware redireciona sem aceite; nova versão publicada re-exige aceite; CA-02 auditado |
| `Settings/ProfileTest` | HU-007 | CA-01 consulta/atualização; e-mail alterado re-exige verificação; CA-02 activity com `attribute_changes`; CA-04 não autenticado bloqueado |
| `Procuration/LinkAttorneyTest` | HU-008 | CA-01 vínculo criado com vigência; CA-03 e-mail inexistente/duplicado bloqueado; CA-02 auditado; ação "em nome de" gera activity com `acting_for_user_id` |
| `Procuration/RevokeAttorneyTest` | HU-009 | CA-01 revogação imediata (request seguinte do procurador perde a representação); CA-04 terceiro não revoga procuração alheia + bloqueio auditado |
| `AccessHistory/AccessHistoryTest` | HU-010 | CA-01 usuário vê só os próprios acessos (login/logout/falhas com data/IP); admin vê de qualquer conta; CA-04 não-admin consultando terceiro → 403 auditado |
| `Audit/AuditInfrastructureTest` | RN-002 | Activity custom persiste ip/user_agent/channel/result/rules_version/acting_for; exception handler audita 403; trait HasAuditoria loga dirty-only sem campos sensíveis |

Convenções: classes PHPUnit 12 (`php artisan make:test --phpunit`), métodos `test_snake_case` em pt-BR descritivo, `RefreshDatabase`, factories com states (`User::factory()->unverified()`, `->analista()`, `Procuration::factory()->revoked()`), `Notification::fake()` para e-mails, `assertInertia` para componentes/props, `withoutVite()` no TestCase base.

### Critérios mensuráveis por HU (gabarito CA-01..CA-04)

Cada HU do EP01 tem os mesmos 4 CAs BDD; tradução mensurável:
- **CA-01 (execução com sucesso):** POST/GET na rota → status esperado (redirect/200) + efeito no banco (`assertDatabaseHas`/`assertAuthenticated`) + página Inertia correta.
- **CA-02 (auditoria obrigatória):** após a ação, existe registro em `activity_log` (ou `access_logs` para eventos de auth) com causer, ip_address, channel, description/event e result corretos.
- **CA-03 (bloqueio por inconsistência):** dados inválidos → `assertSessionHasErrors`/422, NENHUM efeito colateral no banco, mensagem pt-BR.
- **CA-04 (segurança de acesso):** sem permissão/anônimo → 403/redirect login E registro de auditoria com `result=bloqueado`.

### Validação manual no browser (smoke E2E)

Com `composer run dev` (já configurado no esqueleto — serve+queue+logs+vite):

1. `/cadastro` → criar conta → e-mail de verificação no log (`MAIL_MAILER=log`, conferir `storage/logs/laravel.log`) → abrir link → verificado.
2. `/entrar` (login) → primeiro acesso exige termo LGPD → aceitar → dashboard do portal.
3. Errar a senha 5x → mensagem de bloqueio temporário pt-BR.
4. `/esqueci-senha` → link no log → redefinir → logar com a nova.
5. Perfil: alterar nome (ok) e e-mail (volta a "não verificado").
6. Procuração: usuário A vincula B; logado como B, ativar representação (banner "em nome de A"); A revoga; próxima navegação de B perde a representação com aviso.
7. `/portal/acessos` → lista com logins/falhas, data/hora e IP.
8. Logado como cidadão, acessar `/gestao` → 403; logado como admin (seed), `/gestao` abre.
9. Conferir trilha: `php artisan tinker --execute 'App\Models\Activity::latest()->take(5)->get()'` mostra registros com ip/channel/result.

## Open Questions

1. **Campos do cadastro (HU-001): incluir CPF?**
   - O que sabemos: as HUs do EP01 usam template genérico e não listam campos; CPF será necessário no ecossistema (REDESIM/empresas, identificação de procurador).
   - O que falta: confirmação da SEDUR sobre campos mínimos.
   - Recomendação: incluir `cpf` (único, validado por Rule própria `ValidCpf` com testes) e `phone` desde já — barato agora, caro depois (re-coleta de dados). Planner decide; se incluir, atualizar `#[Fillable]`.

2. **Localização do procurador no vínculo (HU-008): e-mail ou CPF?**
   - Recomendação: buscar conta existente por e-mail (mais simples, sem expor CPF de terceiros). Procurador sem conta → mensagem orientando o cadastro prévio (sem convite por e-mail nesta fase — escopo mínimo).

3. **Texto oficial do termo LGPD (HU-006).**
   - Seed entra com texto provisório razoável (baseado na LGPD) marcado para substituição; o mecanismo de versão já resolve a troca pelo texto oficial da SEDUR sem código.

4. **2FA:** Fortify suporta, HUs não pedem. Fica desabilitado no `features` (toggle pronto para o futuro, custo zero agora). Não implementar UI.

5. **Wayfinder (rotas tipadas no TS):** starter kit oficial usa, esqueleto não tem. Fase 1 segue sem (paths literais/nomeados); reavaliar quando o volume de rotas crescer.

## Sources

### Primárias (confiança ALTA)
- **Composer dry-run no projeto real** (2026-06-09): `laravel/fortify` → 1.37.2; `spatie/laravel-permission` → 8.0.0; `spatie/laravel-activitylog` → 5.0.0; `laravel-lang/common` → 6.8.0 — resolução comprovada contra Laravel 13.15.0/PHP 8.5.4.
- [laravel.com/docs/13.x/fortify](https://laravel.com/docs/13.x/fortify) — instalação, features, views customizadas, `authenticateUsing`, rate limiting, guarda `web` para SPA.
- [laravel.com/docs/13.x/starter-kits](https://laravel.com/docs/13.x/starter-kits) — "All starter kits use Laravel Fortify"; tabela de rotas registradas pelo Fortify.
- [github.com/laravel/react-starter-kit](https://github.com/laravel/react-starter-kit) (branch main) — `FortifyServiceProvider` (views Inertia + RateLimiter), `routes/web.php`/`settings.php`, `ProfileController`, `HandleInertiaRequests`, `tests/Feature/Auth/AuthenticationTest.php`, `composer.json` (fortify ^1.37.2).
- [spatie.be/docs/laravel-activitylog/v5](https://spatie.be/docs/laravel-activitylog/v5/installation-and-setup) — instalação v5, config (`activity_model`, `actions`), custom model.
- [freek.dev/3058-whats-new-in-laravel-activitylog-v5](https://freek.dev/3058-whats-new-in-laravel-activitylog-v5) (Spatie oficial, 2026-03) — API v5 completa: action classes, `beforeActivityLogged`, buffering, `attribute_changes`.
- [github.com/spatie/laravel-activitylog UPGRADING.md](https://github.com/spatie/laravel-activitylog/blob/v5/UPGRADING.md) — breaking changes v4→v5 (tabela de renomeações).
- [spatie.be/docs/laravel-permission/v8](https://spatie.be/docs/laravel-permission/v8/installation-laravel) — instalação v8, `HasRoles`, publicação de config/migrations.
- [inertiajs.com/docs/v3/advanced/testing](https://inertiajs.com/docs/v3/advanced/testing) — `assertInertia`, `AssertableInertia`, `inertiaProps`.
- [laravel-lang.com](https://laravel-lang.com/packages-common.html) — `lang:add pt_BR`, `lang:update`, suporte a Laravel 13.
- Esqueleto local inspecionado: `composer.json`, `package.json`, `bootstrap/app.php`, `User.php` (atributos `#[Fillable]`), migrations, `vite.config.ts` (`@inertiajs/vite`), `app.blade.php` (`<x-inertia::app />`), `phpunit.xml` (SQLite `:memory:`), `.env` (Postgres 5433, Redis, locale `en`).
- Skills do repositório: `inertia-react-development` (Form/useForm/Link, pitfalls v3), `laravel-best-practices` (Form Requests, factories, `Context`, LazilyRefreshDatabase).

### Secundárias (confiança MÉDIA)
- Releases do spatie/laravel-permission (GitHub) — linha v7 (2026-02) → v8 (2026-05), renomeações de middleware namespace (`Middleware` singular desde v6).
- `docs/ANALISE-HUs-REUNIAO-SEDUR.md` seção 4B — módulos SIGVISA de referência (HasAuditoria, rotas segregadas, SecurityHeaders).

### Terciárias (confiança BAIXA — validar na execução)
- Comportamento exato do `Fortify::loginView` com nomes de rota `login.store` (visto nos testes do starter kit; confirmar nomes de rota gerados pela versão instalada com `php artisan route:list` após instalar).

## Metadata

**Confidence breakdown:**
- Stack/versões: **ALTA** — dry-run do Composer no projeto real + docs oficiais.
- Padrão Fortify+Inertia: **ALTA** — código-fonte do starter kit oficial do Laravel 13.
- Auditoria (activitylog v5): **ALTA** — docs v5 + blog oficial Spatie + upgrade guide; design das colunas SILE é autoral (decisão, não fato).
- Pitfalls: **ALTA/MÉDIA** — maioria verificada em docs/skills; itens de teste (Vite, cache de permissão) são conhecimento estabelecido do ecossistema.
- Nomes de rota do Fortify instalado: **BAIXA** — confirmar com `route:list` na primeira task.

**Nota sobre `search-docs` (Laravel Boost):** o MCP do Boost não estava disponível nesta sessão de pesquisa (servidores MCP ativos não o incluem). O pacote está instalado (`laravel/boost 2.4.10`); planner e executor DEVEM usar `search-docs` quando o MCP estiver ativo, conforme AGENTS.md.

**Data da pesquisa:** 2026-06-09
**Validade estimada:** 30 dias (stack estável; activitylog v5 e permission v8 recém-lançados — conferir patch releases ao instalar)

# Separação total das autenticações (Backoffice × Portal) — Plano de Implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Isolar por completo as autenticações do console SEDUR (guard `gestao`) e do portal do cidadão (guard `web`) — sessões/cookies independentes por ambiente no mesmo domínio, cada um com fluxo próprio (login, recuperação de senha, conta, termo LGPD) e sem nenhum link, rota ou identidade cruzada.

**Architecture:** Um middleware prependido ao grupo `web` define cookie de sessão + path + guard padrão por contexto de rota (`/gestao*` × resto), tornando as sessões fisicamente independentes (driver `database`). Paths disjuntos (`/gestao` × `/portal`) eliminam a colisão do cookie `XSRF-TOKEN`. Todas as rotas passam a ser single-guard; o console ganha fluxo próprio de senha/conta/termo sob `/gestao`.

**Tech Stack:** Laravel 13, Fortify (portal), Inertia v3 + React 19, PHPUnit, spatie/permission + spatie/activitylog.

**Spec:** `docs/superpowers/specs/2026-06-30-separacao-autenticacao-backoffice-portal-design.md`

---

## Premissas e ordem de execução

- TDD estrito: teste falhando primeiro, mínimo para passar, refatorar. Rodar o teste após cada passo.
- Após alterar PHP: `vendor/bin/pint --dirty --format agent`.
- Comando base de teste: `php artisan test --compact`. Filtro: `php artisan test --compact --filter=NomeDoTeste`.
- Commits frequentes (conventional commits pt-BR).
- Ordem das fases é sequencial (cada fase depende da anterior): F1 isolamento de sessão → F2 rotas single-guard e identidade → F3 fluxo de senha do console → F4 UI/limpeza.
- **Aviso de regressão de paths:** ao migrar URLs (`/settings/*` → `/portal/conta/*`), atualizar todos os testes existentes que batem nessas rotas (`tests/Feature/Settings/*`, `tests/Feature/Profile/*` se houver). Cada tarefa que muda rota lista os testes a ajustar.

---

## FASE 1 — Isolamento de sessão (núcleo técnico)

### Task 1: Middleware de ambiente de sessão  ✅ IMPLEMENTADA

> **Correção descoberta na execução:** o middleware é registrado como **global** (`$middleware->prepend(...)`), não no grupo `web`. O Router instancia o controller na coleta de middleware (`gatherRouteMiddleware` → `getController`) e o controller do Fortify injeta o guard `web`, resolvendo o `session.store` antes da pilha `web`; só um middleware global roda cedo o bastante. Também **não** se usa `Auth::shouldUse()` (recriava o mesmo problema). Os testes usam `config(['session.driver' => 'file'])` porque a suíte roda com `SESSION_DRIVER=array`, que não emite cookie de sessão.

**Files:**
- Create: `app/Http/Middleware/ConfigureEnvironmentSession.php`
- Modify: `bootstrap/app.php` (middleware **global** via `prepend`)
- Test: `tests/Feature/Auth/SessionIsolationTest.php`

- [ ] **Step 1: Escrever o teste falhando**

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_console_emite_cookie_de_sessao_proprio_com_path_gestao(): void
    {
        $response = $this->get('/gestao/login');

        $cookie = collect($response->headers->getCookies())
            ->firstWhere('name', 'sile_gestao_session');

        $this->assertNotNull($cookie, 'Cookie de sessão do console não foi emitido.');
        $this->assertSame('/gestao', $cookie->getPath());
    }

    public function test_portal_emite_cookie_de_sessao_proprio_com_path_portal(): void
    {
        $response = $this->get('/portal/login');

        $cookie = collect($response->headers->getCookies())
            ->firstWhere('name', 'sile_portal_session');

        $this->assertNotNull($cookie, 'Cookie de sessão do portal não foi emitido.');
        $this->assertSame('/portal', $cookie->getPath());
    }
}
```

- [ ] **Step 2: Rodar e confirmar a falha**

Run: `php artisan test --compact --filter=SessionIsolationTest`
Expected: FAIL — os cookies hoje têm nome único (`sile-session`/`Str::slug(APP_NAME)-session`) e path `/`.

- [ ] **Step 3: Criar o middleware**

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Isola a sessão por ambiente: o console (/gestao) e o portal usam cookies
 * de sessão distintos, com paths disjuntos. Roda ANTES do StartSession
 * (prepend no grupo web) e ajusta também o path/domínio padrão do CookieJar
 * para que o cookie CSRF (XSRF-TOKEN) e o "lembrar-me" herdem o escopo do
 * ambiente. Define o guard padrão do contexto para coerência de identidade.
 */
class ConfigureEnvironmentSession
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $isConsole = $request->is('gestao', 'gestao/*');

        $cookie = $isConsole ? 'sile_gestao_session' : 'sile_portal_session';
        $path = $isConsole ? '/gestao' : '/portal';

        config([
            'session.cookie' => $cookie,
            'session.path' => $path,
        ]);

        cookie()->setDefaultPathAndDomain(
            $path,
            config('session.domain'),
            (bool) config('session.secure'),
            config('session.same_site'),
        );

        Auth::shouldUse($isConsole ? 'gestao' : 'web');

        return $next($request);
    }
}
```

- [ ] **Step 4: Registrar o middleware no topo do grupo web**

Em `bootstrap/app.php`, no `withMiddleware`, adicionar o import e o prepend (antes do `web(append: [...])` existente):

```php
use App\Http\Middleware\ConfigureEnvironmentSession;

// ...

$middleware->web(prepend: [
    ConfigureEnvironmentSession::class,
]);
```

- [ ] **Step 5: Rodar e confirmar que passa**

Run: `php artisan test --compact --filter=SessionIsolationTest`
Expected: PASS.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Middleware/ConfigureEnvironmentSession.php bootstrap/app.php tests/Feature/Auth/SessionIsolationTest.php
git commit -m "feat: isola cookie de sessao por ambiente (console x portal)"
```

---

### Task 2: Independência das sessões (regressão dos testes de login existentes)

**Files:**
- Test: `tests/Feature/Auth/SessionIsolationTest.php` (adicionar)

- [ ] **Step 1: Adicionar testes de independência**

```php
    public function test_login_no_console_nao_cria_sessao_no_portal(): void
    {
        $admin = User::factory()->administrador()->create();

        $this->post('/gestao/login', ['email' => $admin->email, 'password' => 'password']);

        $this->assertAuthenticatedAs($admin, 'gestao');
        $this->assertGuest('web');
    }

    public function test_logout_do_console_nao_afeta_o_portal(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($admin, 'web');
        $this->actingAs($admin, 'gestao');

        $this->post('/gestao/logout')->assertRedirect(route('gestao.login'));

        $this->assertGuest('gestao');
        $this->assertAuthenticatedAs($admin, 'web');
    }
```

- [ ] **Step 2: Rodar a suíte de auth completa (regressão)**

Run: `php artisan test --compact tests/Feature/Auth`
Expected: PASS — `GestaoLoginTest`, `AuthenticationTest`, `PasswordResetTest`, `SessionExpiredTest` continuam verdes (confirma que o middleware não quebrou o login existente).

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Auth/SessionIsolationTest.php
git commit -m "test: cobre independencia das sessoes console x portal"
```

---

### Task 3: Rotas stateless (`/up` e `/`)

**Files:**
- Modify: `bootstrap/app.php` e/ou `routes/web.php`
- Test: `tests/Feature/Auth/SessionIsolationTest.php` (adicionar)

- [ ] **Step 1: Teste — health check não cria sessão**

```php
    public function test_health_check_nao_emite_cookie_de_sessao(): void
    {
        $response = $this->get('/up');

        $nomes = collect($response->headers->getCookies())->pluck('name');

        $this->assertFalse($nomes->contains('sile_portal_session'));
        $this->assertFalse($nomes->contains('sile_gestao_session'));
    }
```

- [ ] **Step 2: Rodar e observar**

Run: `php artisan test --compact --filter=test_health_check_nao_emite_cookie_de_sessao`
Expected: pode já PASSAR se `/up` for stateless por padrão no Laravel 12. Se FALHAR, aplicar o Step 3.

- [ ] **Step 3: Garantir `/up` stateless (apenas se o Step 2 falhar)**

Registrar o health check fora do grupo `web`. Em `bootstrap/app.php`, remover `health: '/up'` do `withRouting` e adicionar uma rota dedicada sem middleware de sessão (em `routes/web.php` o grupo web é aplicado; usar `withRouting(then: ...)` para registrar `/up` sem o grupo web):

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    commands: __DIR__.'/../routes/console.php',
    then: function () {
        Route::get('/up', fn () => response('', 200))->name('health');
    },
)
```

(Importar `use Illuminate\Support\Facades\Route;` no topo de `bootstrap/app.php`.) A raiz `/` permanece um `Route::redirect('/', '/portal')` puro; a sessão efêmera eventual em `/` é aceitável (redirect sem estado).

- [ ] **Step 4: Rodar e confirmar**

Run: `php artisan test --compact --filter=SessionIsolationTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add bootstrap/app.php routes/web.php tests/Feature/Auth/SessionIsolationTest.php
git commit -m "fix: mantem health check stateless apos isolamento de sessao"
```

---

## FASE 2 — Rotas single-guard e correção de identidade

### Task 4: Props compartilhadas resolvem o guard do contexto

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Test: `tests/Feature/Auth/IdentityIsolationTest.php`

- [ ] **Step 1: Escrever o teste falhando**

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class IdentityIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_console_mostra_identidade_do_servidor_nao_do_cidadao(): void
    {
        $servidor = User::factory()->administrador()->withAcceptedLgpdTerm()->create();
        $cidadao = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();

        // Ambos os guards autenticados na mesma sessão de teste.
        $this->actingAs($cidadao, 'web');
        $this->actingAs($servidor, 'gestao');

        $this->get('/gestao')
            ->assertInertia(fn (Assert $page) => $page->where('auth.user.id', $servidor->id));
    }

    public function test_portal_mostra_identidade_do_cidadao_nao_do_servidor(): void
    {
        $servidor = User::factory()->administrador()->withAcceptedLgpdTerm()->create();
        $cidadao = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();

        $this->actingAs($servidor, 'gestao');
        $this->actingAs($cidadao, 'web');

        $this->get('/portal/painel')
            ->assertInertia(fn (Assert $page) => $page->where('auth.user.id', $cidadao->id));
    }
}
```

- [ ] **Step 2: Rodar e confirmar a falha**

Run: `php artisan test --compact --filter=IdentityIsolationTest`
Expected: FAIL — `share()` usa `$request->user()` (guard default ambíguo).

- [ ] **Step 3: Resolver o guard por contexto no `share()`**

Em `app/Http/Middleware/HandleInertiaRequests.php`, substituir o bloco `auth`/`notificacoes` para resolver o usuário pelo guard do contexto:

```php
public function share(Request $request): array
{
    $guard = $request->is('gestao', 'gestao/*') ? 'gestao' : 'web';
    $user = $request->user($guard);

    return [
        ...parent::share($request),
        'auth' => [
            'user' => $user?->only('id', 'name', 'email'),
            'roles' => $user?->getRoleNames() ?? [],
            'permissions' => $user?->getAllPermissions()->pluck('name') ?? [],
        ],
        'actingFor' => fn () => app(CurrentRepresentation::class)->grantor()?->only('id', 'name'),
        'attendingFor' => fn () => app(CurrentRepresentation::class)->attendance()?->citizen?->only('id', 'name'),
        'notificacoes' => [
            'nao_lidas' => fn (): int => $user?->unreadNotifications()->count() ?? 0,
        ],
        'flash' => [
            'status' => $request->session()->get('status'),
            'error' => $request->session()->get('error'),
        ],
    ];
}
```

- [ ] **Step 4: Rodar e confirmar que passa**

Run: `php artisan test --compact --filter=IdentityIsolationTest`
Expected: PASS.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Middleware/HandleInertiaRequests.php tests/Feature/Auth/IdentityIsolationTest.php
git commit -m "fix: props compartilhadas resolvem identidade pelo guard do contexto"
```

---

### Task 5: Termo LGPD por ambiente (console) + redirect correto

**Files:**
- Create: `app/Http/Controllers/Gestao/TermoLgpdController.php`
- Modify: `app/Http/Middleware/EnsureLgpdTermAccepted.php`
- Modify: `routes/gestao.php` (rotas do termo), `routes/portal.php` (termo passa a `auth:web`)
- Create: `resources/js/pages/gestao/termo-lgpd.tsx`
- Test: `tests/Feature/Lgpd/TermoLgpdPorAmbienteTest.php`

- [ ] **Step 1: Teste falhando — console não é mandado para `/portal/termo-lgpd`**

```php
<?php

namespace Tests\Feature\Lgpd;

use App\Models\LegalTerm;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TermoLgpdPorAmbienteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        LegalTerm::factory()->create(['type' => 'lgpd', 'is_current' => true]);
    }

    public function test_servidor_sem_aceite_vai_para_o_termo_do_console(): void
    {
        $servidor = User::factory()->administrador()->create();

        $this->actingAs($servidor, 'gestao')
            ->get('/gestao')
            ->assertRedirect('/gestao/termo-lgpd');
    }

    public function test_cidadao_sem_aceite_vai_para_o_termo_do_portal(): void
    {
        $cidadao = User::factory()->cidadao()->create();

        $this->actingAs($cidadao, 'web')
            ->get('/portal/painel')
            ->assertRedirect('/portal/termo-lgpd');
    }
}
```

(Confirmar no `LegalTermFactory` os nomes reais dos campos `type`/`is_current`; ajustar o factory call se divergir.)

- [ ] **Step 2: Rodar e confirmar a falha**

Run: `php artisan test --compact --filter=TermoLgpdPorAmbienteTest`
Expected: FAIL — hoje o `EnsureLgpdTermAccepted` sempre redireciona para `portal.termo-lgpd.show`.

- [ ] **Step 3: Redirect por ambiente no middleware**

Em `app/Http/Middleware/EnsureLgpdTermAccepted.php`:

```php
public function handle(Request $request, Closure $next): Response
{
    $term = LegalTerm::current('lgpd');

    $guard = $request->is('gestao', 'gestao/*') ? 'gestao' : 'web';
    $user = $request->user($guard);

    if ($term !== null && $user !== null && ! $user->hasAcceptedTerm($term)) {
        $route = $guard === 'gestao' ? 'gestao.termo-lgpd.show' : 'portal.termo-lgpd.show';

        return redirect()->route($route);
    }

    return $next($request);
}
```

- [ ] **Step 4: Controller do termo do console**

```php
<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Models\LegalTerm;
use App\Models\LegalTermAcceptance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TermoLgpdController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $term = LegalTerm::current('lgpd');

        if ($term === null || $request->user('gestao')->hasAcceptedTerm($term)) {
            return redirect()->route('gestao.dashboard');
        }

        return Inertia::render('gestao/termo-lgpd', [
            'term' => $term->only('id', 'version', 'title', 'content'),
        ]);
    }

    public function accept(Request $request): RedirectResponse
    {
        $request->validate(['accepted' => ['accepted']], [], ['accepted' => 'aceite']);

        $term = LegalTerm::current('lgpd');

        if ($term === null) {
            return redirect()->route('gestao.dashboard');
        }

        LegalTermAcceptance::firstOrCreate(
            ['user_id' => $request->user('gestao')->id, 'legal_term_id' => $term->id],
            [
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'accepted_at' => now(),
            ],
        );

        return redirect()->route('gestao.dashboard')->with('status', 'Termo aceito com sucesso.');
    }
}
```

- [ ] **Step 5: Rotas do termo (console e portal single-guard)**

Em `routes/gestao.php`, dentro do grupo `auth:gestao` mas **fora** do gate `lgpd.accepted` (evita loop) — criar um grupo dedicado logo após o `gestao.logout`:

```php
Route::middleware('auth:gestao')->prefix('gestao')->name('gestao.')->group(function () {
    Route::get('termo-lgpd', [TermoLgpdController::class, 'show'])->name('termo-lgpd.show');
    Route::post('termo-lgpd', [TermoLgpdController::class, 'accept'])->name('termo-lgpd.accept');
});
```

(Importar `use App\Http\Controllers\Gestao\TermoLgpdController;`.)

Em `routes/portal.php`, trocar o grupo do termo de `auth:web,gestao` para `auth:web`:

```php
Route::middleware(['auth:web', 'verified'])
    ->prefix('portal')
    ->name('portal.')
    ->group(function () {
        Route::get('termo-lgpd', [LgpdTermController::class, 'show'])->name('termo-lgpd.show');
        Route::post('termo-lgpd', [LgpdTermController::class, 'accept'])->name('termo-lgpd.accept');
    });
```

E no `LgpdTermController` (portal), trocar `$request->user()` por `$request->user('web')` para coerência.

- [ ] **Step 6: Tela React do termo do console**

Criar `resources/js/pages/gestao/termo-lgpd.tsx` copiando `resources/js/pages/portal/termo-lgpd.tsx` e ajustando: `Form action="/gestao/termo-lgpd"`, textos/título para o contexto do servidor e o visual do console (mesma paleta navy do `gestao-login.tsx`). Sem links para `/portal/*`.

- [ ] **Step 7: Rodar e confirmar**

Run: `php artisan test --compact --filter=TermoLgpdPorAmbienteTest`
Expected: PASS.

- [ ] **Step 8: Pint + build + commit**

```bash
vendor/bin/pint --dirty --format agent
npm run build
git add app/Http/Controllers/Gestao/TermoLgpdController.php app/Http/Middleware/EnsureLgpdTermAccepted.php app/Http/Controllers/Portal/LgpdTermController.php routes/gestao.php routes/portal.php resources/js/pages/gestao/termo-lgpd.tsx tests/Feature/Lgpd/TermoLgpdPorAmbienteTest.php
git commit -m "feat: termo lgpd proprio por ambiente (console x portal)"
```

---

### Task 6: Conta do console sob `/gestao/conta`

**Files:**
- Create: `app/Http/Controllers/Gestao/Conta/ProfileController.php`, `app/Http/Controllers/Gestao/Conta/PasswordController.php`
- Modify: `routes/gestao.php`
- Create: `resources/js/pages/gestao/conta/perfil.tsx`, `resources/js/pages/gestao/conta/senha.tsx`, `resources/js/layouts/gestao-conta-layout.tsx`
- Test: `tests/Feature/Gestao/ContaConsoleTest.php`

- [ ] **Step 1: Teste falhando**

```php
<?php

namespace Tests\Feature\Gestao;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ContaConsoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_servidor_acessa_a_conta_do_console(): void
    {
        $servidor = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($servidor, 'gestao')
            ->get('/gestao/conta/perfil')
            ->assertOk();
    }

    public function test_cidadao_logado_so_no_portal_nao_acessa_conta_do_console(): void
    {
        $cidadao = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();

        $this->actingAs($cidadao, 'web')
            ->get('/gestao/conta/perfil')
            ->assertRedirect('/gestao/login');
    }

    public function test_servidor_altera_a_propria_senha_no_console(): void
    {
        $servidor = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($servidor, 'gestao')->put('/gestao/conta/senha', [
            'current_password' => 'password',
            'password' => 'NovaSenhaForte123',
            'password_confirmation' => 'NovaSenhaForte123',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('NovaSenhaForte123', $servidor->fresh()->password));
    }
}
```

- [ ] **Step 2: Rodar e confirmar a falha**

Run: `php artisan test --compact --filter=ContaConsoleTest`
Expected: FAIL — rotas `/gestao/conta/*` não existem.

- [ ] **Step 3: Controllers da conta do console**

`app/Http/Controllers/Gestao/Conta/ProfileController.php`:

```php
<?php

namespace App\Http\Controllers\Gestao\Conta;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user('gestao');

        return Inertia::render('gestao/conta/perfil', [
            'user' => $user->only('id', 'name', 'email', 'cpf', 'phone'),
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user('gestao');
        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($user->wasChanged('email')) {
            $user->sendEmailVerificationNotification();
        }

        return back()->with('status', 'Perfil atualizado com sucesso.');
    }
}
```

`app/Http/Controllers/Gestao/Conta/PasswordController.php`:

```php
<?php

namespace App\Http\Controllers\Gestao\Conta;

use App\Actions\Fortify\UpdateUserPassword;
use App\Http\Controllers\Controller;
use App\Support\PasswordPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('gestao/conta/senha', [
            'passwordRules' => PasswordPolicy::description(),
        ]);
    }

    public function update(Request $request, UpdateUserPassword $updater): RedirectResponse
    {
        $updater->update(
            $request->user('gestao'),
            $request->only('current_password', 'password', 'password_confirmation'),
        );

        return back();
    }
}
```

(Verificar: `ProfileUpdateRequest` valida unicidade de e-mail ignorando o próprio usuário via `$this->user()`. Em rota `auth:gestao`, `$this->user()` resolve o guard default; como o middleware de ambiente já fez `Auth::shouldUse('gestao')`, resolve o servidor. Cobrir por teste de unicidade se necessário.)

- [ ] **Step 4: Rotas da conta do console**

Em `routes/gestao.php`, dentro do grupo `['auth:gestao', 'permission:acessar-gestao', 'lgpd.accepted']` (o grupo principal já existente), adicionar:

```php
Route::prefix('conta')->name('conta.')->group(function () {
    Route::get('perfil', [\App\Http\Controllers\Gestao\Conta\ProfileController::class, 'edit'])->name('perfil.edit');
    Route::patch('perfil', [\App\Http\Controllers\Gestao\Conta\ProfileController::class, 'update'])->name('perfil.update');
    Route::get('senha', [\App\Http\Controllers\Gestao\Conta\PasswordController::class, 'show'])->name('senha.show');
    Route::put('senha', [\App\Http\Controllers\Gestao\Conta\PasswordController::class, 'update'])->name('senha.update');
});
```

(Preferir imports no topo em vez de FQN inline; FQN aqui só para clareza do passo.)

- [ ] **Step 5: Telas React da conta do console**

- `resources/js/layouts/gestao-conta-layout.tsx`: copiar `resources/js/layouts/settings-layout.tsx`, fixar `dashboardHref = '/gestao'`, `navItems` para `/gestao/conta/perfil` e `/gestao/conta/senha`.
- `resources/js/pages/gestao/conta/perfil.tsx` e `senha.tsx`: copiar de `resources/js/pages/settings/profile.tsx` e `password.tsx`, trocando o layout para `gestao-conta-layout` e os `action`/`href` para `/gestao/conta/*`.

- [ ] **Step 6: Rodar e confirmar**

Run: `php artisan test --compact --filter=ContaConsoleTest`
Expected: PASS.

- [ ] **Step 7: Pint + build + commit**

```bash
vendor/bin/pint --dirty --format agent
npm run build
git add app/Http/Controllers/Gestao/Conta routes/gestao.php resources/js/pages/gestao/conta resources/js/layouts/gestao-conta-layout.tsx tests/Feature/Gestao/ContaConsoleTest.php
git commit -m "feat: conta do console sob /gestao/conta"
```

---

### Task 7: Migrar conta do portal `/settings/*` → `/portal/conta/*` e remover multi-guard

**Files:**
- Modify: `routes/portal.php` (adicionar grupo conta), `routes/web.php` (remover `require settings.php`)
- Delete: `routes/settings.php`
- Modify: telas/links do portal que apontam para `/settings/*`
- Modify/Move: `tests/Feature/Settings/PasswordUpdateTest.php` (ajustar rotas)
- Test: ajustar/renomear conforme rota nova

- [ ] **Step 1: Atualizar o teste de senha do portal para a rota nova (falha esperada)**

Em `tests/Feature/Settings/PasswordUpdateTest.php`, trocar as chamadas de `/settings/password` por `/portal/conta/senha` e `auth` para guard `web`. Rodar para ver falhar (rota nova ainda não existe):

Run: `php artisan test --compact tests/Feature/Settings/PasswordUpdateTest.php`
Expected: FAIL.

- [ ] **Step 2: Adicionar grupo conta no portal**

Em `routes/portal.php`, dentro do grupo `['auth:web', 'verified']` + `['lgpd.accepted', ResolveRepresentation::class]` (onde vivem as rotas do portal autenticado), adicionar:

```php
Route::prefix('conta')->name('conta.')->group(function () {
    Route::get('perfil', [\App\Http\Controllers\Settings\ProfileController::class, 'edit'])->name('perfil.edit');
    Route::patch('perfil', [\App\Http\Controllers\Settings\ProfileController::class, 'update'])->name('perfil.update');
    Route::get('senha', [\App\Http\Controllers\Settings\PasswordController::class, 'show'])->name('senha.show');
    Route::put('senha', [\App\Http\Controllers\Settings\PasswordController::class, 'update'])->name('senha.update');
});
```

Nos controllers `Settings\ProfileController` e `Settings\PasswordController`, trocar `$request->user()` por `$request->user('web')`.

- [ ] **Step 3: Remover as rotas multi-guard de settings**

Em `routes/web.php`, remover a linha `require __DIR__.'/settings.php';`. Apagar `routes/settings.php`.

```bash
git rm routes/settings.php
```

- [ ] **Step 4: Atualizar telas/links do portal**

- `resources/js/pages/settings/profile.tsx` e `password.tsx`: mover para `resources/js/pages/portal/conta/perfil.tsx` e `senha.tsx` (ou ajustar os `action` para `/portal/conta/*`).
- `resources/js/layouts/settings-layout.tsx`: `navItems` e `dashboardHref` apontando para `/portal/conta/*` e `/portal/painel`.
- Buscar e atualizar qualquer `href="/settings/..."` remanescente.

- [ ] **Step 5: Rodar e confirmar**

Run: `php artisan test --compact tests/Feature/Settings/PasswordUpdateTest.php`
Expected: PASS.

- [ ] **Step 6: Verificar ausência total de rotas multi-guard**

Run: `php artisan route:list | rg "web,gestao|gestao,web"`
Expected: nenhuma linha.

- [ ] **Step 7: Pint + build + commit**

```bash
vendor/bin/pint --dirty --format agent
npm run build
git add -A
git commit -m "refactor: migra conta do portal para /portal/conta e remove rotas multi-guard"
```

---

### Task 8: Simplificar `EnsureUserIsActive` para o guard do contexto

**Files:**
- Modify: `app/Http/Middleware/EnsureUserIsActive.php`
- Test: `tests/Feature/Auth/SessionExpiredTest.php` ou novo — confirmar inativação por ambiente

- [ ] **Step 1: Teste — inativação derruba só o ambiente da request**

Adicionar a um teste novo `tests/Feature/Auth/InactiveUserPerEnvironmentTest.php`:

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InactiveUserPerEnvironmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_servidor_inativado_e_derrubado_no_console(): void
    {
        $servidor = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($servidor, 'gestao');
        $servidor->update(['inactivated_at' => now()]);

        $this->get('/gestao')->assertRedirect(route('gestao.login'));
        $this->assertGuest('gestao');
    }
}
```

- [ ] **Step 2: Rodar (pode já passar) — confirmar comportamento**

Run: `php artisan test --compact --filter=InactiveUserPerEnvironmentTest`
Expected: PASS ou FAIL; se PASS, manter a simplificação do Step 3 como limpeza coberta pela suíte.

- [ ] **Step 3: Simplificar o middleware**

```php
public function handle(Request $request, Closure $next): Response
{
    $guard = $request->is('gestao', 'gestao/*') ? 'gestao' : 'web';
    $user = Auth::guard($guard)->user();

    if ($user !== null && $user->isInactive()) {
        Auth::guard($guard)->logout();
        $request->session()->regenerateToken();

        $loginRoute = $guard === 'gestao' ? 'gestao.login' : 'login';

        return redirect()->route($loginRoute)->withErrors([
            'email' => __('Sua conta está inativa. Procure o administrador do sistema.'),
        ]);
    }

    return $next($request);
}
```

(Remover a constante `GUARDS` e a varredura dupla.)

- [ ] **Step 4: Rodar a suíte de auth completa**

Run: `php artisan test --compact tests/Feature/Auth`
Expected: PASS.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Middleware/EnsureUserIsActive.php tests/Feature/Auth/InactiveUserPerEnvironmentTest.php
git commit -m "refactor: inativacao resolve guard pelo contexto da request"
```

---

## FASE 3 — Recuperação de senha própria do console

### Task 9: Notification de reset do console (URL `/gestao/reset-password`)

**Files:**
- Create: `app/Notifications/GestaoResetPasswordQueued.php`
- Modify: `app/Models/User.php` (método `sendGestaoPasswordResetNotification`)
- Test: `tests/Feature/Auth/GestaoPasswordResetTest.php`

- [ ] **Step 1: Teste falhando — link aponta para `/gestao/reset-password/`**

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\GestaoResetPasswordQueued;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class GestaoPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_link_de_recuperacao_do_console_aponta_para_gestao(): void
    {
        Notification::fake();

        $servidor = User::factory()->administrador()->create();

        $this->post('/gestao/forgot-password', ['email' => $servidor->email]);

        Notification::assertSentTo($servidor, GestaoResetPasswordQueued::class, function (GestaoResetPasswordQueued $n) {
            return $n->resetUrl !== null && str_contains($n->resetUrl, '/gestao/reset-password/');
        });
    }
}
```

(Esta task cobre só a notification; as rotas vêm na Task 10. Para isolar, este teste falhará primeiro por rota inexistente — é esperado; ele fica verde ao fim da Task 10. Alternativamente, criar nesta task um teste unitário que instancia a notification e verifica `freezeUrlFor`.)

- [ ] **Step 2: Criar a notification**

```php
<?php

namespace App\Notifications;

class GestaoResetPasswordQueued extends ResetPasswordQueued
{
    /**
     * Congela a URL do console (guard gestao), não a do portal (Fortify).
     */
    public function freezeUrlFor(object $notifiable): void
    {
        $this->resetUrl = url(route('gestao.reset-password', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));
    }
}
```

- [ ] **Step 3: Método no User**

Em `app/Models/User.php`, adicionar:

```php
public function sendGestaoPasswordResetNotification($token): void
{
    $log = EmailLog::create([
        'recipient_email' => $this->email,
        'recipient_name' => $this->name,
        'notification_class' => GestaoResetPasswordQueued::class,
        'status' => 'na_fila',
        'queued_at' => now(),
    ]);

    $notification = new GestaoResetPasswordQueued($token);
    $notification->emailLogId = $log->id;
    $notification->freezeUrlFor($this);
    $this->notify($notification);
}
```

(Importar `use App\Notifications\GestaoResetPasswordQueued;`.)

- [ ] **Step 4: Pint (rotas vêm na Task 10; não rodar o teste de feature ainda)**

```bash
vendor/bin/pint --dirty --format agent
git add app/Notifications/GestaoResetPasswordQueued.php app/Models/User.php tests/Feature/Auth/GestaoPasswordResetTest.php
git commit -m "feat: notification de reset de senha propria do console"
```

---

### Task 10: Controllers e rotas de recuperação de senha do console

**Files:**
- Create: `app/Http/Controllers/Gestao/ForgotPasswordController.php`, `app/Http/Controllers/Gestao/ResetPasswordController.php`
- Create: `app/Http/Requests/Gestao/ResetPasswordRequest.php`
- Modify: `routes/gestao.php` (grupo `gestao.guest`)
- Create: `resources/js/pages/auth/gestao-forgot-password.tsx`, `resources/js/pages/auth/gestao-reset-password.tsx`
- Test: `tests/Feature/Auth/GestaoPasswordResetTest.php` (completar)

- [ ] **Step 1: Completar o teste de feature**

Adicionar ao `GestaoPasswordResetTest`:

```php
    public function test_pagina_esqueci_senha_do_console_renderiza(): void
    {
        $this->get('/gestao/forgot-password')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('auth/gestao-forgot-password'));
    }

    public function test_email_sem_acesso_ao_console_nao_recebe_link_anti_oraculo(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $cidadao = User::factory()->cidadao()->create();

        $this->post('/gestao/forgot-password', ['email' => $cidadao->email])
            ->assertSessionHas('status');

        \Illuminate\Support\Facades\Notification::assertNothingSent();
    }

    public function test_senha_do_console_e_redefinida_com_token_valido(): void
    {
        $servidor = User::factory()->administrador()->create();
        $token = \Illuminate\Support\Facades\Password::createToken($servidor);

        $this->post('/gestao/reset-password', [
            'token' => $token,
            'email' => $servidor->email,
            'password' => 'NovaSenhaForte123',
            'password_confirmation' => 'NovaSenhaForte123',
        ])->assertRedirect('/gestao/login');

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('NovaSenhaForte123', $servidor->fresh()->password));

        $this->assertDatabaseHas('activity_log', [
            'event' => 'senha-redefinida',
            'causer_id' => $servidor->id,
        ]);
    }
```

- [ ] **Step 2: Rodar e confirmar a falha**

Run: `php artisan test --compact --filter=GestaoPasswordResetTest`
Expected: FAIL — rotas/controllers inexistentes.

- [ ] **Step 3: ForgotPasswordController (anti-oráculo + restrição ao console)**

```php
<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ForgotPasswordController extends Controller
{
    public function show(Request $request): Response
    {
        return Inertia::render('auth/gestao-forgot-password', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'string', 'email']]);

        $user = User::query()->where('email', Str::lower($request->string('email')))->first();

        // Só contas ativas com acesso ao console recebem o link. Resposta
        // sempre genérica (anti-oráculo), espelhando o login interno.
        if ($user && ! $user->isInactive() && $user->can('acessar-gestao')) {
            Password::broker('users')->sendResetLink(
                ['email' => $user->email],
                fn (User $u, string $token) => $u->sendGestaoPasswordResetNotification($token),
            );
        }

        return back()->with('status', __('Se a conta tiver acesso ao console, enviamos as instruções de recuperação.'));
    }
}
```

- [ ] **Step 4: ResetPasswordRequest**

```php
<?php

namespace App\Http\Requests\Gestao;

use App\Actions\Fortify\PasswordValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    use PasswordValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required'],
            'email' => ['required', 'string', 'email'],
            'password' => $this->passwordRules(),
        ];
    }
}
```

- [ ] **Step 5: ResetPasswordController**

```php
<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class ResetPasswordController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        return Inertia::render('auth/gestao-reset-password', [
            'email' => $request->string('email')->toString(),
            'token' => $token,
        ]);
    }

    public function store(ResetPasswordRequest $request): RedirectResponse
    {
        $status = Password::broker('users')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
                event(new PasswordReset($user));
            },
        );

        return $status === Password::PasswordReset
            ? redirect()->route('gestao.login')->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
    }
}
```

- [ ] **Step 6: Rotas no grupo guest do console**

Em `routes/gestao.php`, no grupo `Route::middleware('gestao.guest')->prefix('gestao')->name('gestao.')`:

```php
Route::get('forgot-password', [ForgotPasswordController::class, 'show'])->name('forgot-password');
Route::post('forgot-password', [ForgotPasswordController::class, 'store'])->name('forgot-password.store');
Route::get('reset-password/{token}', [ResetPasswordController::class, 'show'])->name('reset-password');
Route::post('reset-password', [ResetPasswordController::class, 'store'])->name('reset-password.store');
```

(Importar os dois controllers. Nota: o `name('reset-password')` precisa bater com o usado na notification `route('gestao.reset-password', ...)`.)

- [ ] **Step 7: Telas React**

- `resources/js/pages/auth/gestao-forgot-password.tsx`: copiar `resources/js/pages/auth/forgot-password.tsx`, aplicar o visual do `gestao-login.tsx` (split BrandStage + form), `Form action="/gestao/forgot-password"`, link de volta para `/gestao/login`.
- `resources/js/pages/auth/gestao-reset-password.tsx`: copiar `resources/js/pages/auth/reset-password.tsx`, `Form action="/gestao/reset-password"`, visual do console.

- [ ] **Step 8: Rodar e confirmar**

Run: `php artisan test --compact --filter=GestaoPasswordResetTest`
Expected: PASS (inclui o teste da Task 9, agora com rota existente).

- [ ] **Step 9: Pint + build + commit**

```bash
vendor/bin/pint --dirty --format agent
npm run build
git add app/Http/Controllers/Gestao/ForgotPasswordController.php app/Http/Controllers/Gestao/ResetPasswordController.php app/Http/Requests/Gestao/ResetPasswordRequest.php routes/gestao.php resources/js/pages/auth/gestao-forgot-password.tsx resources/js/pages/auth/gestao-reset-password.tsx tests/Feature/Auth/GestaoPasswordResetTest.php
git commit -m "feat: fluxo proprio de recuperacao de senha do console"
```

---

## FASE 4 — UI e limpeza de cruzamentos

### Task 11: Link "Esqueceu a senha?" do console aponta para `/gestao`

**Files:**
- Modify: `resources/js/pages/auth/gestao-login.tsx:261`
- Test: `tests/Feature/Auth/GestaoLoginTest.php` (asserção de conteúdo) ou Inertia

- [ ] **Step 1: Trocar o link**

Em `resources/js/pages/auth/gestao-login.tsx`, linha ~261, trocar `href="/portal/forgot-password"` por `href="/gestao/forgot-password"`.

- [ ] **Step 2: Build + verificação manual de ausência de cruzamento**

Run: `rg "/portal/(forgot|reset|login)" resources/js/pages/auth/gestao-login.tsx`
Expected: nenhum resultado.

```bash
npm run build
git add resources/js/pages/auth/gestao-login.tsx
git commit -m "fix: link de recuperacao do console aponta para /gestao"
```

---

### Task 12: `app-header` recebe `accountHref` por ambiente

**Files:**
- Modify: `resources/js/components/app/app-header.tsx`, `resources/js/components/app/app-shell.tsx`
- Modify: `resources/js/layouts/gestao-layout.tsx`, `resources/js/layouts/portal-layout.tsx`

- [ ] **Step 1: Propagar `accountHref`**

- `app-header.tsx`: adicionar `accountHref: string` à `AppHeaderProps` e usar no `UserDropdown` (`href={accountHref}` no item "Configurações da conta", em vez de `/settings/profile`).
- `app-shell.tsx`: adicionar `accountHref?: string` à `AppShellProps` e repassar ao `AppHeader` (`accountHref={accountHref ?? '/portal/conta/perfil'}`).
- `gestao-layout.tsx`: passar `accountHref="/gestao/conta/perfil"` ao `AppShell`.
- `portal-layout.tsx`: passar `accountHref="/portal/conta/perfil"` ao `AppShell`.

- [ ] **Step 2: Verificar ausência de `/settings` no front**

Run: `rg "/settings/" resources/js`
Expected: nenhum resultado (todos migrados).

- [ ] **Step 3: Build + commit**

```bash
npm run build
git add resources/js/components/app/app-header.tsx resources/js/components/app/app-shell.tsx resources/js/layouts/gestao-layout.tsx resources/js/layouts/portal-layout.tsx
git commit -m "feat: link de conta no header por ambiente"
```

---

### Task 13: Neutralizar a mensagem de e-mail verificado do console

**Files:**
- Modify: `app/Http/Controllers/Gestao/LoginController.php:76-80`
- Test: `tests/Feature/Auth/GestaoLoginTest.php`

- [ ] **Step 1: Ajustar o teste de e-mail não verificado**

Em `GestaoLoginTest::test_email_nao_verificado_nao_loga_no_console`, acrescentar asserção da mensagem neutra:

```php
$response->assertSessionHasErrors([
    'email' => 'Confirme seu e-mail institucional antes de acessar o console.',
]);
```

- [ ] **Step 2: Rodar e confirmar a falha**

Run: `php artisan test --compact --filter=test_email_nao_verificado_nao_loga_no_console`
Expected: FAIL — a mensagem atual cita o portal.

- [ ] **Step 3: Trocar a mensagem**

Em `LoginController::store`, no bloco `hasVerifiedEmail`:

```php
if (! $user->hasVerifiedEmail()) {
    throw ValidationException::withMessages([
        'email' => __('Confirme seu e-mail institucional antes de acessar o console.'),
    ]);
}
```

- [ ] **Step 4: Rodar e confirmar**

Run: `php artisan test --compact --filter=GestaoLoginTest`
Expected: PASS.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Gestao/LoginController.php tests/Feature/Auth/GestaoLoginTest.php
git commit -m "fix: mensagem de e-mail nao verificado do console sem referencia ao portal"
```

---

### Task 14: Verificação final (suíte completa + CSRF nos dois ambientes)

**Files:**
- Test: `tests/Feature/Auth/CsrfPerEnvironmentTest.php`

- [ ] **Step 1: Teste de CSRF simultâneo**

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CsrfPerEnvironmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_cookie_xsrf_do_console_tem_path_gestao(): void
    {
        $cookie = collect($this->get('/gestao/login')->headers->getCookies())
            ->firstWhere('name', 'XSRF-TOKEN');

        $this->assertNotNull($cookie);
        $this->assertSame('/gestao', $cookie->getPath());
    }

    public function test_cookie_xsrf_do_portal_tem_path_portal(): void
    {
        $cookie = collect($this->get('/portal/login')->headers->getCookies())
            ->firstWhere('name', 'XSRF-TOKEN');

        $this->assertNotNull($cookie);
        $this->assertSame('/portal', $cookie->getPath());
    }
}
```

- [ ] **Step 2: Rodar e confirmar**

Run: `php artisan test --compact --filter=CsrfPerEnvironmentTest`
Expected: PASS (o XSRF-TOKEN herda `config('session.path')` ajustado pelo middleware).

- [ ] **Step 3: Suíte completa**

Run: `php artisan test --compact`
Expected: tudo verde. Corrigir regressões remanescentes (testes que ainda batam em `/settings/*` ou `auth:web,gestao`).

- [ ] **Step 4: Commit final**

```bash
git add tests/Feature/Auth/CsrfPerEnvironmentTest.php
git commit -m "test: valida cookie csrf por ambiente"
```

---

## Self-Review (cobertura do spec)

- **§2.1 sessões/cookies independentes** → Task 1, 2 (cookies distintos + independência).
- **§2.2 recuperação de senha própria do console** → Task 9, 10.
- **§2.3 telas de conta/termo sob /gestao** → Task 5 (termo), 6 (conta).
- **§2.4 paths disjuntos** → Task 1 (paths /gestao × /portal), Task 14 (CSRF por path).
- **§4 middleware de ambiente + stateless** → Task 1, 3.
- **§5 mapa de rotas / remoção multi-guard** → Task 5, 6, 7 (route:list sem web,gestao).
- **§6 fluxo de senha (anti-oráculo, broker, notification, auditoria via PasswordReset)** → Task 9, 10.
- **§7 correção de identidade (HandleInertiaRequests, EnsureLgpdTermAccepted, EnsureUserIsActive, redirects)** → Task 4, 5, 8.
- **§8 telas/links (gestao-login, app-header, layouts)** → Task 11, 12.
- **§9 auditoria/segurança/LGPD** → auditoria reusa listeners existentes (RecordPasswordResetActivity etc.); termo por ambiente (Task 5).
- **§10 testes** → cobertos em cada task + Task 14.
- **§11 fora de escopo / mensagem de verificação neutra** → Task 13.
- **§12 critérios de aceite** → 1-7 mapeados às tasks acima; CA7 (suíte verde) = Task 14 Step 3.

**Pendências de verificação durante a execução (não placeholders — checagens pontuais):**
- Nomes reais dos campos no `LegalTermFactory` (Task 5 Step 1).
- `/up` já stateless no Laravel 12 (Task 3 Step 2).
- `ProfileUpdateRequest` resolve o usuário correto sob `auth:gestao` (Task 6 Step 3).
- Acionar `especialista-seguranca` (sessão/CSRF/anti-oráculo do reset) e `especialista-acessibilidade` (telas novas) antes de marcar a fase como concluída, conforme `guardiao-entrega`.

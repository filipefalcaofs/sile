# Identificador único no login do console — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permitir que o servidor entre no console usando seu usuário AD ou que uma conta local entre usando o e-mail cadastrado, com orientação clara na tela e sem revelar o tipo da conta.

**Architecture:** O campo `email` do formulário é substituído por `identifier`. O `LoginController` localiza `User` por `ad_username` ou `email`, define a origem para auditoria e mantém a validação por `auth_type`: AD na API SEDUR; local por hash Laravel. A interface usa um único campo textual e explica os dois formatos aceitos.

**Tech Stack:** Laravel 13, PHPUnit 12, Inertia v3, React 19, Tailwind 4.

## Global Constraints

- A senha AD e o Bearer token nunca persistem nem entram em logs, exceções, sessão, cache ou props Inertia.
- Uma conta AD nunca valida a senha local; indisponibilidade da API AD falha de forma explícita.
- Falhas de identificador e senha retornam `__('auth.failed')`, sem revelar a existência ou o tipo da conta.
- O único campo da tela é “E-mail institucional ou usuário AD”; não criar seletor AD/local.
- UI, mensagens e nomes de testes em pt-BR; PHP com `vendor/bin/pint --dirty --format agent`.

---

### Task 1: Resolver o usuário por identificador único

**Files:**
- Modify: `app/Http/Requests/Gestao/LoginRequest.php`
- Modify: `app/Http/Controllers/Gestao/LoginController.php`
- Modify: `tests/Feature/Auth/GestaoLoginAdTest.php`
- Modify: `tests/Feature/Auth/GestaoLoginTest.php`

**Interfaces:**
- Consumes: `User::auth_type`, `User::ad_username`, `User::isAdAccount()`.
- Produces: request validada com `identifier` e `password`; login local por e-mail e AD por `ad_username`.

- [ ] **Step 1: Escrever testes que falham**

Em `tests/Feature/Auth/GestaoLoginAdTest.php`, trocar os payloads AD para `identifier` e acrescentar:

```php
public function test_usuario_ad_autentica_com_o_usuario_da_rede_corporativa(): void
{
    $this->fakeAdSucesso();
    $servidor = User::factory()->administrador()->ad('seuusuarioad')->create();

    $this->post('/gestao/login', [
        'identifier' => 'seuusuarioad',
        'password' => 'senha-corporativa',
    ])->assertRedirect('/gestao');

    $this->assertAuthenticatedAs($servidor, 'gestao');
    Http::assertSent(fn ($request) => $request['usuario'] === 'seuusuarioad');
}
```

Em `tests/Feature/Auth/GestaoLoginTest.php`, trocar todos os payloads por `identifier => $administrador->email` e acrescentar:

```php
public function test_conta_local_autentica_com_o_email_cadastrado(): void
{
    $administrador = User::factory()->administrador()->create();

    $this->post('/gestao/login', [
        'identifier' => $administrador->email,
        'password' => 'password',
    ])->assertRedirect('/gestao');

    $this->assertAuthenticatedAs($administrador, 'gestao');
}
```

- [ ] **Step 2: Confirmar RED**

Run: `php artisan test --compact tests/Feature/Auth/GestaoLoginAdTest.php tests/Feature/Auth/GestaoLoginTest.php`

Expected: FAIL porque `identifier` não é validado e o controller ainda busca somente por `email`.

- [ ] **Step 3: Implementar a resolução**

Em `app/Http/Requests/Gestao/LoginRequest.php`, substituir a regra:

```php
'email' => ['required', 'string', 'email'],
```

por:

```php
'identifier' => ['required', 'string', 'max:255'],
```

No `LoginController`, trocar a busca inicial:

```php
$identifier = Str::lower($request->validated('identifier'));

$user = User::query()
    ->where(function ($query) use ($identifier) {
        $query->where('email', $identifier)
            ->orWhere('ad_username', $identifier);
    })
    ->first();
```

Substituir todos os acessos a `$request->validated('email')` usados como credencial por `$request->validated('identifier')`; a chave da mensagem de validação continua sendo `identifier`.

No evento `Failed`, guardar somente:

```php
event(new Failed('gestao', $user, [
    'email' => $user?->email ?? $request->validated('identifier'),
    'origin' => $user === null ? null : ($user->isAdAccount() ? 'ad' : 'local'),
]));
```

Em `ensureIsNotRateLimited()`, devolver o erro no campo `identifier`. Em `throttleKey()`, trocar a chave para:

```php
return Str::transliterate(Str::lower($request->validated('identifier')).'|'.$request->ip());
```

Nos retornos de conta inativa, sem permissão, e-mail não verificado, toggle AD desligado e indisponibilidade, trocar a chave de erro `email` por `identifier`.

- [ ] **Step 4: Confirmar GREEN e regressão**

Run: `php artisan test --compact tests/Feature/Auth/GestaoLoginAdTest.php tests/Feature/Auth/GestaoLoginTest.php`

Expected: PASS.

- [ ] **Step 5: Formatar e commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Gestao/LoginRequest.php app/Http/Controllers/Gestao/LoginController.php tests/Feature/Auth/GestaoLoginAdTest.php tests/Feature/Auth/GestaoLoginTest.php
git commit -m "feat: aceita usuário AD ou e-mail local no login do console"
```

### Task 2: Adaptar a orientação e o campo da tela de login

**Files:**
- Modify: `resources/js/pages/auth/gestao-login.tsx`
- Test: `tests/Feature/Auth/GestaoLoginTest.php`

**Interfaces:**
- Consumes: campo HTTP `identifier` da Task 1.
- Produces: input textual único `name="identifier"` com mensagem de orientação sobre usuário AD e e-mail local.

- [ ] **Step 1: Escrever a asserção de interface que falha**

No teste que renderiza `GET /gestao/login`, acrescentar:

```php
->where('component', 'auth/gestao-login')
```

e confirmar no payload Inertia que a página não depende de nenhuma prop para decidir AD/local. A distinção deve existir apenas no texto estático e no campo enviado.

- [ ] **Step 2: Implementar o texto e o campo**

Em `resources/js/pages/auth/gestao-login.tsx`:

1. Trocar o texto introdutório por:

```tsx
Use o usuário da rede corporativa ou o e-mail da sua conta local para acessar o ambiente de gestão.
```

2. Trocar label por:

```tsx
E-mail institucional ou usuário AD
```

3. Trocar o input para:

```tsx
<input
    id="identifier"
    type="text"
    name="identifier"
    autoComplete="username"
    autoFocus
    required
    placeholder="nome@salvador.ba.gov.br ou seuusuarioad"
    aria-describedby="identifier-help"
    aria-invalid={errors.identifier ? 'true' : undefined}
    className={gestaoLoginInputClasses}
/>
<p id="identifier-help" className={`text-[12.5px] ${gestaoLoginMutedClasses}`}>
    Use o usuário da rede corporativa (ex.: seuusuarioad) ou o e-mail da conta local.
</p>
{errors.identifier && (
    <p className="text-[12.5px] text-error-600 dark:text-[oklch(72%_0.16_25)]">
        {errors.identifier}
    </p>
)}
```

4. Trocar o label da senha para:

```tsx
Senha da rede corporativa ou da conta local
```

5. Manter “Esqueceu?”; não condicionar o link ao conteúdo digitado, para não expor o tipo de autenticação.

- [ ] **Step 3: Verificar frontend e testes**

Run: `php artisan test --compact tests/Feature/Auth/GestaoLoginTest.php tests/Feature/Auth/GestaoLoginAdTest.php`

Expected: PASS.

Run: `npm run build`

Expected: build concluído sem erros.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/auth/gestao-login.tsx public/build tests/Feature/Auth/GestaoLoginTest.php
git commit -m "feat: orienta login por usuário AD ou e-mail local no console"
```

### Task 3: Verificação final

- [ ] **Step 1: Rodar a suíte de autenticação**

Run: `php artisan test --compact tests/Feature/Auth`

Expected: PASS.

- [ ] **Step 2: Conferir lint PHP**

Run: `vendor/bin/pint --dirty --format agent`

Expected: nenhum arquivo pendente de formatação.

- [ ] **Step 3: Revisar manualmente no navegador**

Abrir `/gestao/login` e confirmar:

1. O texto mostra os dois caminhos (“usuário da rede corporativa” e “e-mail da conta local”).
2. O input aceita `seuusuarioad` sem a validação nativa de e-mail.
3. O placeholder contém os dois exemplos.
4. A ajuda está associada ao campo por `aria-describedby`.
5. Erros aparecem abaixo do campo e não revelam se o identificador é AD ou local.

# Login AD do Console — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Autenticar servidores do console (`/gestao`) via AD usando a API institucional da SEDUR, com coexistência de contas locais, sessão sempre Laravel e auditoria de origem.

**Architecture:** O `Gestao\LoginController` delega a verificação de credencial a um contrato `DirectoryAuthenticator` (implementação `SedurDirectoryAuthenticator` sobre um `SedurAuthClient` HTTP no padrão `ReginHttpClient`). O vínculo é determinístico: `users.auth_type` (`ad`|`local`) + `users.ad_username`. Token Bearer e senha AD são transitórios (nunca persistidos/logados); a sessão resultante é a sessão Laravel do guard `gestao`. Spec: `docs/superpowers/specs/2026-09-24-login-ad-console-design.md`.

**Tech Stack:** Laravel 13, PHPUnit 12 (classes, não Pest), Inertia v3 + React 19, Spatie Permission, `Http::fake()` nos testes.

## Global Constraints

- TDD estrito: nenhum código de produção sem teste falhando antes (Red-Green-Refactor).
- Senha AD e token Bearer **nunca** em log, exceção, `access_logs`, sessão, cache ou props Inertia.
- HTTP sempre com verificação TLS (proibido `withoutVerifying()`); timeout via `config('viabiliza.integrations.sedur_auth.timeout')`.
- Anti-oráculo: credencial AD inválida, conta AD inexistente e senha local errada retornam a mesma mensagem `__('auth.failed')`.
- Sem fallback silencioso: conta `ad` NUNCA valida contra a senha local, mesmo com a API fora.
- `auth_type`/`ad_username` ficam **fora** do `$fillable` do User (precedente `inactivated_at`, decisão 02-03): gravação só por `forceFill` em fluxo autorizado + auditoria explícita via `AuditService`.
- Após alterar PHP: `vendor/bin/pint --dirty --format agent`.
- Mensagens de commit: conventional commits em pt-BR, minúsculas, sem ponto final.
- UI, mensagens e testes em pt-BR; código (classes, métodos, variáveis) em inglês.
- Limitação conhecida e aceita: há oráculo de *timing* entre conta vinculada ao AD (chamada HTTP à API) e conta local/inexistente (sem chamada). Aceito para console interno; não implementar atraso artificial.
- As chaves JSON das respostas da API (`token`, `nome`) são premissas a confirmar na homologação (escalonamento TI-SEDUR); estão isoladas uma por método no client para ajuste de uma linha.

---

### Task 1: Enum `AuthType` + colunas em `users` + factory

**Files:**
- Create: `app/Enums/AuthType.php`
- Create: `database/migrations/*_add_auth_columns_to_users_table.php` (via artisan)
- Modify: `app/Models/User.php`
- Modify: `database/factories/UserFactory.php`
- Test: `tests/Feature/Auth/AuthTypeUserTest.php`

**Interfaces:**
- Produces: `App\Enums\AuthType` (cases `Ad = 'ad'`, `Local = 'local'`, método `label(): string`); `User::isAdAccount(): bool`; cast `auth_type => AuthType::class`; colunas `users.auth_type` (string(10), default `'local'`, index) e `users.ad_username` (string(100), nullable, unique); state de factory `UserFactory::ad(?string $username = null)`.

- [ ] **Step 1: Escrever o teste que falha**

Criar com `php artisan make:test Auth/AuthTypeUserTest --phpunit --no-interaction` e completar:

```php
<?php

namespace Tests\Feature\Auth;

use App\Enums\AuthType;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AuthTypeUserTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_usuario_nasce_com_autenticacao_local_por_padrao(): void
    {
        $user = User::factory()->create();

        $this->assertSame(AuthType::Local, $user->auth_type);
        $this->assertNull($user->ad_username);
        $this->assertFalse($user->isAdAccount());
    }

    public function test_usuario_ad_tem_conta_vinculada_e_e_reconhecido(): void
    {
        $user = User::factory()->ad('seuusuarioad')->create();

        $this->assertSame(AuthType::Ad, $user->fresh()->auth_type);
        $this->assertSame('seuusuarioad', $user->ad_username);
        $this->assertTrue($user->isAdAccount());
    }

    public function test_ad_username_e_unico(): void
    {
        User::factory()->ad('seuusuarioad')->create();

        $this->expectException(\Illuminate\Database\QueryException::class);

        User::factory()->ad('seuusuarioad')->create();
    }
}
```

- [ ] **Step 2: Rodar e confirmar o RED**

Run: `php artisan test --compact tests/Feature/Auth/AuthTypeUserTest.php`
Expected: FAIL (coluna `auth_type` inexistente / erro de SQL).

- [ ] **Step 3: Implementar**

`php artisan make:enum Enums/AuthType --no-interaction` e completar `app/Enums/AuthType.php`:

```php
<?php

namespace App\Enums;

/**
 * Mecanismo de autenticação da conta no console: AD valida a credencial na
 * API institucional da SEDUR; Local valida contra o hash em users.password.
 * O vínculo AD é determinístico (ad_username) — nunca inferido por e-mail/CPF.
 */
enum AuthType: string
{
    case Ad = 'ad';
    case Local = 'local';

    public function label(): string
    {
        return match ($this) {
            self::Ad => 'Active Directory (rede corporativa)',
            self::Local => 'Local (senha do sistema)',
        };
    }
}
```

`php artisan make:migration "add auth columns to users table" --no-interaction` e completar a migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('auth_type', 10)->default('local')->index()->after('password');
            $table->string('ad_username', 100)->nullable()->unique()->after('auth_type');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['auth_type', 'ad_username']);
        });
    }
};
```

Em `app/Models/User.php`, adicionar o cast e o helper (sem tocar o `#[Fillable]` — `auth_type`/`ad_username` ficam fora dele por decisão de segurança, como `inactivated_at`):

```php
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'inactivated_at' => 'datetime',
            'password' => 'hashed',
            'auth_type' => AuthType::class,
        ];
    }

    public function isAdAccount(): bool
    {
        return $this->auth_type === AuthType::Ad;
    }
```

Adicionar `use App\Enums\AuthType;` aos imports do model.

Em `database/factories/UserFactory.php`, adicionar o state (e o import do enum):

```php
    /**
     * Conta vinculada ao AD corporativo (autenticação via API SEDUR).
     */
    public function ad(?string $username = null): static
    {
        return $this->state(fn (array $attributes) => [
            'auth_type' => \App\Enums\AuthType::Ad,
            'ad_username' => $username ?? fake()->userName(),
        ]);
    }
```

- [ ] **Step 4: Rodar e confirmar o GREEN**

Run: `php artisan test --compact tests/Feature/Auth/AuthTypeUserTest.php`
Expected: PASS (3 testes).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Enums/AuthType.php app/Models/User.php database/factories/UserFactory.php database/migrations tests/Feature/Auth/AuthTypeUserTest.php
git commit -m "feat: adiciona tipo de autenticação e conta AD ao cadastro de usuários"
```

---

### Task 2: Parâmetros e config da integração SEDUR

**Files:**
- Modify: `database/seeders/ParameterSeeder.php` (catálogo, após `features.govbr_login` e após o bloco `integrations.govbr.*`)
- Modify: `config/viabiliza.php` (`security.login` e `integrations`)
- Test: `tests/Feature/Seeders/DatabaseSeederTest.php` (contagem 89 → 91, dois pontos)

**Interfaces:**
- Produces: parâmetros `features.ad_login` (boolean, default `'1'`) e `integrations.sedur_auth.base_url` (string, default `https://api.sedur.salvador.ba.gov.br/k8s/prd`); config `viabiliza.integrations.sedur_auth.base_url|timeout` e `viabiliza.security.login.max_attempts_per_ip` (30).

- [ ] **Step 1: Ajustar o teste do seeder (RED)**

Em `tests/Feature/Seeders/DatabaseSeederTest.php`, nos dois pontos que afirmam a contagem de parâmetros, trocar `assertSame(89, Parameter::query()->count())` por `assertSame(91, Parameter::query()->count())` e acrescentar ao comentário de composição (acima da linha do primeiro assert):

```php
        // + 2 da autenticação AD do console (features.ad_login +
        // integrations.sedur_auth.base_url — 2026-09-24).
```

Run: `php artisan test --compact tests/Feature/Seeders/DatabaseSeederTest.php --filter=test_seed_completo_prepara_ambiente_de_desenvolvimento`
Expected: FAIL (esperava 91, encontrou 89).

- [ ] **Step 2: Implementar**

Em `database/seeders/ParameterSeeder.php`, dentro de `catalog()`, logo após a entrada `features.govbr_login`:

```php
            'features.ad_login' => [
                'group' => 'features',
                'type' => 'boolean',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Habilita a autenticação de servidores via AD (API institucional SEDUR) no console; desligar bloqueia o login das contas AD com aviso explícito',
            ],
```

E após o bloco `integrations.govbr.*` (após `integrations.govbr.client_secret`):

```php
            'integrations.sedur_auth.base_url' => [
                'group' => 'integracoes',
                'type' => 'string',
                'default_value' => 'https://api.sedur.salvador.ba.gov.br/k8s/prd',
                'validation_rules' => ['required', 'url'],
                'description' => 'URL base da API institucional de autenticação AD e gestão de pessoas da SEDUR (sem teste de conexão: a API exige a credencial do próprio usuário)',
            ],
```

Nota: sem `requires_connection_test` — não há conta de serviço; o endpoint exige usuário/senha do próprio servidor (escalonamento TI-SEDUR na spec §11).

Em `config/viabiliza.php`, ajustar `security.login` e adicionar a integração:

```php
        'login' => ['max_attempts' => 5, 'max_attempts_per_ip' => 30],
```

```php
        'sedur_auth' => [
            'base_url' => 'https://api.sedur.salvador.ba.gov.br/k8s/prd',
            'timeout' => 8,
        ],
```

(dentro de `'integrations' => [...]`, seguindo o comentário existente: constantes técnicas ficam no config, `base_url` é parametrizável.)

- [ ] **Step 3: Rodar e confirmar o GREEN**

Run: `php artisan test --compact tests/Feature/Seeders/DatabaseSeederTest.php`
Expected: PASS (arquivo inteiro — a contagem aparece em dois testes).

- [ ] **Step 4: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/seeders/ParameterSeeder.php config/viabiliza.php tests/Feature/Seeders/DatabaseSeederTest.php
git commit -m "feat: parametriza URL e interruptor da autenticação AD do console"
```

---

### Task 3: `SedurAuthClient` (HTTP) + settings + exceções

**Files:**
- Create: `app/Services/Sedur/SedurAuthSettings.php`
- Create: `app/Services/Sedur/SedurAuthUnavailableException.php`
- Create: `app/Services/Sedur/SedurAuthInvalidCredentialsException.php`
- Create: `app/Services/Sedur/SedurAuthClient.php`
- Test: `tests/Feature/Sedur/SedurAuthClientTest.php`

**Interfaces:**
- Consumes: `Settings::get()` (`app/Support/Settings.php`), config `viabiliza.integrations.sedur_auth.*` (Task 2).
- Produces: `SedurAuthSettings::baseUrl(): string`, `SedurAuthSettings::enabled(): bool`; `SedurAuthClient::autenticar(string $usuario, string $senha): string` (token) e `SedurAuthClient::pessoaPorCpf(string $token, string $cpf): array{nome: ?string}`; exceções `SedurAuthInvalidCredentialsException` (credencial rejeitada) e `SedurAuthUnavailableException` (indisponibilidade/erro da integração).

- [ ] **Step 1: Escrever o teste que falha**

`php artisan make:test Sedur/SedurAuthClientTest --phpunit --no-interaction` e completar:

```php
<?php

namespace Tests\Feature\Sedur;

use App\Services\Sedur\SedurAuthClient;
use App\Services\Sedur\SedurAuthInvalidCredentialsException;
use App\Services\Sedur\SedurAuthUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SedurAuthClientTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['viabiliza.integrations.sedur_auth.base_url' => 'https://sedur.test/k8s/prd']);
    }

    private function client(): SedurAuthClient
    {
        return app(SedurAuthClient::class);
    }

    public function test_autenticar_retorna_o_token_em_sucesso(): void
    {
        Http::fake(['*/ws-autenticacao/auth' => Http::response(['token' => 'tk-123'])]);

        $token = $this->client()->autenticar('seuusuarioad', 'senha-corporativa');

        $this->assertSame('tk-123', $token);

        Http::assertSent(fn ($request) => $request->url() === 'https://sedur.test/k8s/prd/ws-autenticacao/auth'
            && $request['usuario'] === 'seuusuarioad'
            && $request['senha'] === 'senha-corporativa');
    }

    public function test_credencial_rejeitada_lanca_excecao_especifica(): void
    {
        Http::fake(['*/ws-autenticacao/auth' => Http::response(['erro' => 'negado'], 401)]);

        $this->expectException(SedurAuthInvalidCredentialsException::class);

        $this->client()->autenticar('seuusuarioad', 'senha-errada');
    }

    public function test_erro_http_da_api_e_indisponibilidade(): void
    {
        Http::fake(['*/ws-autenticacao/auth' => Http::response('erro', 500)]);

        $this->expectException(SedurAuthUnavailableException::class);

        $this->client()->autenticar('seuusuarioad', 'senha-corporativa');
    }

    public function test_falha_de_conexao_e_indisponibilidade(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->expectException(SedurAuthUnavailableException::class);

        $this->client()->autenticar('seuusuarioad', 'senha-corporativa');
    }

    public function test_resposta_sem_token_e_indisponibilidade(): void
    {
        Http::fake(['*/ws-autenticacao/auth' => Http::response(['ok' => true])]);

        $this->expectException(SedurAuthUnavailableException::class);

        $this->client()->autenticar('seuusuarioad', 'senha-corporativa');
    }

    public function test_senha_nunca_aparece_na_mensagem_das_excecoes(): void
    {
        Http::fake(['*/ws-autenticacao/auth' => Http::response('senha-corporativa vazada no corpo', 500)]);

        try {
            $this->client()->autenticar('seuusuarioad', 'senha-corporativa');
            $this->fail('Esperava SedurAuthUnavailableException.');
        } catch (SedurAuthUnavailableException $e) {
            $this->assertStringNotContainsString('senha-corporativa', $e->getMessage());
        }
    }

    public function test_pessoa_por_cpf_retorna_apenas_o_nome(): void
    {
        Http::fake(['*/pessoaView/buscarPorCPF/*' => Http::response([
            'nome' => 'Filipe Falcao',
            'endereco' => 'Rua Exemplo, 1',
            'telefone' => '71999990000',
        ])]);

        $pessoa = $this->client()->pessoaPorCpf('tk-123', '12345678901');

        $this->assertSame(['nome' => 'Filipe Falcao'], $pessoa);

        Http::assertSent(fn ($request) => $request->url() === 'https://sedur.test/k8s/prd/ws-gp/view/pessoaView/buscarPorCPF/12345678901'
            && $request->hasHeader('Authorization', 'Bearer tk-123'));
    }

    public function test_pessoa_por_cpf_com_falha_e_indisponibilidade(): void
    {
        Http::fake(['*/pessoaView/buscarPorCPF/*' => Http::response('erro', 502)]);

        $this->expectException(SedurAuthUnavailableException::class);

        $this->client()->pessoaPorCpf('tk-123', '12345678901');
    }
}
```

- [ ] **Step 2: Rodar e confirmar o RED**

Run: `php artisan test --compact tests/Feature/Sedur/SedurAuthClientTest.php`
Expected: FAIL (classe `SedurAuthClient` inexistente).

- [ ] **Step 3: Implementar**

`app/Services/Sedur/SedurAuthSettings.php`:

```php
<?php

namespace App\Services\Sedur;

use App\Support\Settings;

/**
 * Configuração administrável da API institucional de autenticação AD / gestão
 * de pessoas da SEDUR (HU-014). A API não tem credencial de serviço: autentica
 * com a senha corporativa do próprio usuário, transitória — nada sensível aqui.
 */
class SedurAuthSettings
{
    public function baseUrl(): string
    {
        return rtrim((string) Settings::get(
            'integrations.sedur_auth.base_url',
            (string) config('viabiliza.integrations.sedur_auth.base_url', ''),
        ), '/');
    }

    public function enabled(): bool
    {
        return Settings::enabled('ad_login');
    }
}
```

`app/Services/Sedur/SedurAuthUnavailableException.php`:

```php
<?php

namespace App\Services\Sedur;

use RuntimeException;

/**
 * Indisponibilidade ou resposta inválida da API institucional SEDUR. A
 * mensagem nunca carrega senha, token ou corpo bruto da resposta.
 */
class SedurAuthUnavailableException extends RuntimeException {}
```

`app/Services/Sedur/SedurAuthInvalidCredentialsException.php`:

```php
<?php

namespace App\Services\Sedur;

use RuntimeException;

/**
 * A API institucional rejeitou a credencial corporativa (401/403). Distinta
 * de indisponibilidade para a auditoria separar "senha errada" de "API fora".
 */
class SedurAuthInvalidCredentialsException extends RuntimeException {}
```

`app/Services/Sedur/SedurAuthClient.php`:

```php
<?php

namespace App\Services\Sedur;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTP da API institucional SEDUR (ws-autenticacao + ws-gp). Segue o
 * padrão ReginHttpClient: senha e token nunca aparecem em exceção ou log, e a
 * verificação TLS é sempre exigida. O token Bearer é transitório — usado na
 * sequência auth → pessoaView da mesma request e descartado; nunca cacheado.
 *
 * Premissas de contrato a confirmar na homologação (escalonamento TI-SEDUR):
 * chave do token ("token") e chave do nome ("nome") — isoladas uma por método.
 */
class SedurAuthClient
{
    public function __construct(private SedurAuthSettings $settings) {}

    /**
     * Autentica a credencial corporativa e devolve o token Bearer transitório.
     *
     * @throws SedurAuthInvalidCredentialsException credencial rejeitada pela API
     * @throws SedurAuthUnavailableException API fora, erro HTTP ou contrato inválido
     */
    public function autenticar(string $usuario, string $senha): string
    {
        try {
            $response = Http::timeout($this->timeout())
                ->acceptJson()
                ->asJson()
                ->post($this->settings->baseUrl().'/ws-autenticacao/auth', [
                    'usuario' => $usuario,
                    'senha' => $senha,
                ]);
        } catch (ConnectionException) {
            throw new SedurAuthUnavailableException('API de autenticação institucional indisponível.');
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new SedurAuthInvalidCredentialsException('Credencial corporativa rejeitada.');
        }

        $token = $response->json('token');

        if (! $response->successful() || ! is_string($token) || $token === '') {
            throw new SedurAuthUnavailableException('Falha ao autenticar na API institucional.');
        }

        return $token;
    }

    /**
     * Busca a pessoa pelo CPF e extrai SOMENTE o nome (minimização LGPD): o
     * restante do payload é descartado na mesma request, sem persistência.
     *
     * @return array{nome: ?string}
     *
     * @throws SedurAuthUnavailableException API fora ou erro HTTP
     */
    public function pessoaPorCpf(string $token, string $cpf): array
    {
        try {
            $response = Http::timeout($this->timeout())
                ->acceptJson()
                ->withToken($token)
                ->get($this->settings->baseUrl().'/ws-gp/view/pessoaView/buscarPorCPF/'.$cpf);
        } catch (ConnectionException) {
            throw new SedurAuthUnavailableException('API de gestão de pessoas indisponível.');
        }

        if (! $response->successful()) {
            throw new SedurAuthUnavailableException('Falha ao consultar dados da pessoa.');
        }

        $nome = $response->json('nome');

        return ['nome' => is_string($nome) && $nome !== '' ? $nome : null];
    }

    private function timeout(): int
    {
        return (int) config('viabiliza.integrations.sedur_auth.timeout', 8);
    }
}
```

- [ ] **Step 4: Rodar e confirmar o GREEN**

Run: `php artisan test --compact tests/Feature/Sedur/SedurAuthClientTest.php`
Expected: PASS (8 testes).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Sedur tests/Feature/Sedur/SedurAuthClientTest.php
git commit -m "feat: adiciona cliente HTTP da API institucional de autenticação SEDUR"
```

---

### Task 4: Contrato `DirectoryAuthenticator` + implementação SEDUR

**Files:**
- Create: `app/Services/Auth/DirectoryAuthenticator.php`
- Create: `app/Services/Auth/DirectoryAuthResult.php`
- Create: `app/Services/Auth/SedurDirectoryAuthenticator.php`
- Modify: `app/Providers/AppServiceProvider.php` (binding em `register()`)
- Test: `tests/Feature/Auth/SedurDirectoryAuthenticatorTest.php`

**Interfaces:**
- Consumes: `SedurAuthClient` (Task 3).
- Produces: `DirectoryAuthenticator::attempt(string $adUsername, string $password, ?string $cpf = null): DirectoryAuthResult`; `DirectoryAuthResult` com `public ?string $nome` e `public bool $sincronizacaoFalhou`. Binding container: `DirectoryAuthenticator::class → SedurDirectoryAuthenticator::class`.

- [ ] **Step 1: Escrever o teste que falha**

`php artisan make:test Auth/SedurDirectoryAuthenticatorTest --phpunit --no-interaction` e completar:

```php
<?php

namespace Tests\Feature\Auth;

use App\Services\Auth\DirectoryAuthenticator;
use App\Services\Sedur\SedurAuthInvalidCredentialsException;
use App\Services\Sedur\SedurAuthUnavailableException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SedurDirectoryAuthenticatorTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['viabiliza.integrations.sedur_auth.base_url' => 'https://sedur.test/k8s/prd']);
    }

    private function authenticator(): DirectoryAuthenticator
    {
        return app(DirectoryAuthenticator::class);
    }

    public function test_sucesso_retorna_nome_sincronizado_da_pessoa(): void
    {
        Http::fake([
            '*/ws-autenticacao/auth' => Http::response(['token' => 'tk-1']),
            '*/pessoaView/buscarPorCPF/*' => Http::response(['nome' => 'Filipe Falcao']),
        ]);

        $resultado = $this->authenticator()->attempt('seuusuarioad', 'senha', '12345678901');

        $this->assertSame('Filipe Falcao', $resultado->nome);
        $this->assertFalse($resultado->sincronizacaoFalhou);
    }

    public function test_sem_cpf_nao_consulta_pessoa_view(): void
    {
        Http::fake(['*/ws-autenticacao/auth' => Http::response(['token' => 'tk-1'])]);

        $resultado = $this->authenticator()->attempt('seuusuarioad', 'senha');

        $this->assertNull($resultado->nome);
        Http::assertSentCount(1);
    }

    public function test_credencial_invalida_propaga_excecao_especifica(): void
    {
        Http::fake(['*/ws-autenticacao/auth' => Http::response([], 401)]);

        $this->expectException(SedurAuthInvalidCredentialsException::class);

        $this->authenticator()->attempt('seuusuarioad', 'senha-errada', '12345678901');
    }

    public function test_indisponibilidade_no_auth_propaga_indisponibilidade(): void
    {
        Http::fake(['*/ws-autenticacao/auth' => Http::response([], 500)]);

        $this->expectException(SedurAuthUnavailableException::class);

        $this->authenticator()->attempt('seuusuarioad', 'senha', '12345678901');
    }

    public function test_falha_na_pessoa_view_nao_derruba_a_autenticacao(): void
    {
        Http::fake([
            '*/ws-autenticacao/auth' => Http::response(['token' => 'tk-1']),
            '*/pessoaView/buscarPorCPF/*' => Http::response([], 502),
        ]);

        $resultado = $this->authenticator()->attempt('seuusuarioad', 'senha', '12345678901');

        $this->assertNull($resultado->nome);
        $this->assertTrue($resultado->sincronizacaoFalhou);
    }
}
```

- [ ] **Step 2: Rodar e confirmar o RED**

Run: `php artisan test --compact tests/Feature/Auth/SedurDirectoryAuthenticatorTest.php`
Expected: FAIL (contrato inexistente).

- [ ] **Step 3: Implementar**

`app/Services/Auth/DirectoryAuthenticator.php`:

```php
<?php

namespace App\Services\Auth;

use App\Services\Sedur\SedurAuthInvalidCredentialsException;
use App\Services\Sedur\SedurAuthUnavailableException;

/**
 * Validador de identidade corporativa (diretório institucional). O Laravel
 * permanece soberano sobre sessão, autorização e auditoria — este contrato
 * apenas responde "a credencial corporativa é válida?" e, quando há CPF,
 * traz o nome oficial para sincronização cadastral.
 */
interface DirectoryAuthenticator
{
    /**
     * @throws SedurAuthInvalidCredentialsException credencial rejeitada pelo diretório
     * @throws SedurAuthUnavailableException diretório indisponível
     */
    public function attempt(string $adUsername, string $password, ?string $cpf = null): DirectoryAuthResult;
}
```

`app/Services/Auth/DirectoryAuthResult.php`:

```php
<?php

namespace App\Services\Auth;

/**
 * Resultado da validação no diretório: nome oficial (quando sincronizado) e
 * flag de falha da sincronização complementar — a identidade já estava
 * validada pelo /auth, então falha no pessoaView não derruba o login.
 */
final readonly class DirectoryAuthResult
{
    public function __construct(
        public ?string $nome,
        public bool $sincronizacaoFalhou = false,
    ) {}
}
```

`app/Services/Auth/SedurDirectoryAuthenticator.php`:

```php
<?php

namespace App\Services\Auth;

use App\Services\Sedur\SedurAuthClient;
use App\Services\Sedur\SedurAuthUnavailableException;

/**
 * DirectoryAuthenticator sobre a API institucional SEDUR. O token Bearer é
 * transitório: autentica, consulta a pessoa do PRÓPRIO usuário (minimização
 * LGPD) e é descartado ao fim da chamada.
 */
class SedurDirectoryAuthenticator implements DirectoryAuthenticator
{
    public function __construct(private SedurAuthClient $client) {}

    public function attempt(string $adUsername, string $password, ?string $cpf = null): DirectoryAuthResult
    {
        $token = $this->client->autenticar($adUsername, $password);

        if ($cpf === null || $cpf === '') {
            return new DirectoryAuthResult(nome: null);
        }

        try {
            $pessoa = $this->client->pessoaPorCpf($token, $cpf);
        } catch (SedurAuthUnavailableException) {
            return new DirectoryAuthResult(nome: null, sincronizacaoFalhou: true);
        }

        return new DirectoryAuthResult(nome: $pessoa['nome']);
    }
}
```

Em `app/Providers/AppServiceProvider.php`, método `register()`, adicionar:

```php
        $this->app->bind(
            \App\Services\Auth\DirectoryAuthenticator::class,
            \App\Services\Auth\SedurDirectoryAuthenticator::class,
        );
```

- [ ] **Step 4: Rodar e confirmar o GREEN**

Run: `php artisan test --compact tests/Feature/Auth/SedurDirectoryAuthenticatorTest.php`
Expected: PASS (5 testes).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Auth app/Providers/AppServiceProvider.php tests/Feature/Auth/SedurDirectoryAuthenticatorTest.php
git commit -m "feat: adiciona contrato de autenticação em diretório com implementação SEDUR"
```

---

### Task 5: Origem da autenticação em `access_logs`

**Files:**
- Create: `database/migrations/*_add_origin_to_access_logs_table.php` (via artisan)
- Modify: `app/Models/AccessLog.php` (fillable)
- Modify: `app/Listeners/RecordSuccessfulLogin.php`
- Modify: `app/Listeners/RecordFailedLogin.php`
- Test: `tests/Feature/Auth/AccessLogOriginTest.php`

**Interfaces:**
- Produces: coluna `access_logs.origin` (string(10), nullable); convenção — o emissor define a origem via atributo de request `auth_origin` (login com sucesso) ou chave `origin` nas credenciais do evento `Failed`. Valores: `'ad'`, `'local'`; `null` = legado/portal.

- [ ] **Step 1: Escrever o teste que falha**

`php artisan make:test Auth/AccessLogOriginTest --phpunit --no-interaction` e completar:

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AccessLogOriginTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_login_registra_a_origem_definida_na_request(): void
    {
        $user = User::factory()->create();

        request()->attributes->set('auth_origin', 'ad');

        event(new Login('gestao', $user, false));

        $this->assertDatabaseHas('access_logs', [
            'user_id' => $user->id,
            'event' => 'login',
            'origin' => 'ad',
        ]);
    }

    public function test_login_sem_origem_definida_grava_nulo(): void
    {
        $user = User::factory()->create();

        event(new Login('gestao', $user, false));

        $this->assertDatabaseHas('access_logs', [
            'user_id' => $user->id,
            'event' => 'login',
            'origin' => null,
        ]);
    }

    public function test_falha_registra_a_origem_presente_nas_credenciais(): void
    {
        event(new Failed('gestao', null, ['email' => 'servidor@sedur.ba.gov.br', 'origin' => 'ad']));

        $this->assertDatabaseHas('access_logs', [
            'event' => 'falha',
            'origin' => 'ad',
        ]);
    }

    public function test_falha_sem_origem_nas_credenciais_grava_nulo(): void
    {
        event(new Failed('gestao', null, ['email' => 'servidor@sedur.ba.gov.br']));

        $this->assertDatabaseHas('access_logs', [
            'event' => 'falha',
            'origin' => null,
        ]);
    }
}
```

- [ ] **Step 2: Rodar e confirmar o RED**

Run: `php artisan test --compact tests/Feature/Auth/AccessLogOriginTest.php`
Expected: FAIL (coluna `origin` inexistente).

- [ ] **Step 3: Implementar**

`php artisan make:migration "add origin to access logs table" --no-interaction`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_logs', function (Blueprint $table) {
            $table->string('origin', 10)->nullable()->after('channel');
        });
    }

    public function down(): void
    {
        Schema::table('access_logs', function (Blueprint $table) {
            $table->dropColumn('origin');
        });
    }
};
```

`app/Models/AccessLog.php` — fillable passa a:

```php
#[Fillable(['user_id', 'email', 'event', 'ip_address', 'user_agent', 'channel', 'origin'])]
```

`app/Listeners/RecordSuccessfulLogin.php` — adicionar a linha ao `AccessLog::create([...])`:

```php
            'channel' => request()->routeIs('gestao.*') ? 'gestao' : 'portal',
            // Origem da credencial (ad/local) definida pelo fluxo de login;
            // null no portal e em logins anteriores à feature.
            'origin' => request()->attributes->get('auth_origin'),
```

`app/Listeners/RecordFailedLogin.php` — adicionar ao `AccessLog::create([...])`:

```php
            'channel' => request()->routeIs('gestao.*') ? 'gestao' : 'portal',
            'origin' => $event->credentials['origin'] ?? null,
```

- [ ] **Step 4: Rodar e confirmar o GREEN**

Run: `php artisan test --compact tests/Feature/Auth/AccessLogOriginTest.php`
Expected: PASS (4 testes).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/AccessLog.php app/Listeners database/migrations tests/Feature/Auth/AccessLogOriginTest.php
git commit -m "feat: registra origem da credencial (AD ou local) no histórico de acessos"
```

---

### Task 6: Login híbrido no `LoginController` (núcleo)

**Files:**
- Modify: `app/Http/Controllers/Gestao/LoginController.php`
- Test: `tests/Feature/Auth/GestaoLoginAdTest.php`
- Regression: `tests/Feature/Auth/GestaoLoginTest.php` (deve continuar verde sem alteração)

**Interfaces:**
- Consumes: `DirectoryAuthenticator` (Task 4), `SedurAuthSettings` (Task 3), `User::isAdAccount()` (Task 1), convenção `auth_origin`/`origin` (Task 5), config `viabiliza.security.login.max_attempts_per_ip` (Task 2).
- Produces: fluxo final do login do console; eventos de auditoria `ad-indisponivel` e `ad-sincronizacao-falhou` em `access_logs`.

- [ ] **Step 1: Escrever os testes que falham**

`php artisan make:test Auth/GestaoLoginAdTest --phpunit --no-interaction` e completar:

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Login híbrido do console: contas AD validam na API institucional SEDUR,
 * contas locais no hash local. Sessão resultante é sempre Laravel (guard
 * gestao); a senha AD e o token Bearer são transitórios.
 */
class GestaoLoginAdTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        config(['viabiliza.integrations.sedur_auth.base_url' => 'https://sedur.test/k8s/prd']);
    }

    private function fakeAdSucesso(string $nome = 'Nome Sincronizado'): void
    {
        Http::fake([
            '*/ws-autenticacao/auth' => Http::response(['token' => 'tk-fake']),
            '*/pessoaView/buscarPorCPF/*' => Http::response([
                'nome' => $nome,
                'endereco' => 'Rua Exemplo, 1',
                'telefone' => '71999990000',
            ]),
        ]);
    }

    public function test_usuario_ad_autentica_pela_api_e_sincroniza_o_nome(): void
    {
        $this->fakeAdSucesso();

        $servidor = User::factory()->administrador()->ad('seuusuarioad')->create([
            'name' => 'Nome Antigo',
        ]);

        $response = $this->post('/gestao/login', [
            'email' => $servidor->email,
            'password' => 'senha-corporativa',
        ]);

        $response->assertRedirect('/gestao');
        $this->assertAuthenticatedAs($servidor, 'gestao');

        Http::assertSent(fn ($request) => $request->url() === 'https://sedur.test/k8s/prd/ws-autenticacao/auth'
            && $request['usuario'] === 'seuusuarioad'
            && $request['senha'] === 'senha-corporativa');

        // Sincronização com minimização: só o nome muda; telefone e demais
        // campos do payload são descartados.
        $this->assertSame('Nome Sincronizado', $servidor->fresh()->name);
        $this->assertSame($servidor->phone, $servidor->fresh()->phone);

        $this->assertDatabaseHas('access_logs', [
            'user_id' => $servidor->id,
            'event' => 'login',
            'channel' => 'gestao',
            'origin' => 'ad',
        ]);
    }

    public function test_credencial_ad_invalida_tem_mensagem_identica_a_de_senha_local(): void
    {
        Http::fake(['*/ws-autenticacao/auth' => Http::response([], 401)]);

        $servidorAd = User::factory()->administrador()->ad('seuusuarioad')->create();
        $servidorLocal = User::factory()->administrador()->create();

        $respostaAd = $this->post('/gestao/login', [
            'email' => $servidorAd->email,
            'password' => 'senha-errada',
        ]);

        $respostaLocal = $this->post('/gestao/login', [
            'email' => $servidorLocal->email,
            'password' => 'senha-errada',
        ]);

        $respostaAd->assertSessionHasErrors(['email' => __('auth.failed')]);
        $respostaLocal->assertSessionHasErrors(['email' => __('auth.failed')]);

        $this->assertGuest('gestao');

        $this->assertDatabaseHas('access_logs', [
            'email' => $servidorAd->email,
            'event' => 'falha',
            'origin' => 'ad',
        ]);
    }

    public function test_api_ad_indisponivel_nao_cai_para_a_senha_local(): void
    {
        Http::fake(['*/ws-autenticacao/auth' => Http::response('erro', 500)]);

        // A conta AD tem um hash local (legado da criação); a senha local
        // correta NÃO pode autenticar — não há fallback silencioso.
        $servidor = User::factory()->administrador()->ad('seuusuarioad')->create();

        $response = $this->post('/gestao/login', [
            'email' => $servidor->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'Autenticação institucional indisponível no momento. Tente novamente em instantes.',
        ]);

        $this->assertGuest('gestao');

        $this->assertDatabaseHas('access_logs', [
            'email' => $servidor->email,
            'event' => 'ad-indisponivel',
            'channel' => 'gestao',
        ]);
    }

    public function test_integracao_ad_desligada_bloqueia_com_aviso_e_auditoria(): void
    {
        config(['viabiliza.features.ad_login' => false]);
        Http::fake();

        $servidor = User::factory()->administrador()->ad('seuusuarioad')->create();

        $response = $this->post('/gestao/login', [
            'email' => $servidor->email,
            'password' => 'senha-corporativa',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'A autenticação corporativa está desabilitada. Procure o administrador do sistema.',
        ]);

        $this->assertGuest('gestao');
        Http::assertNothingSent();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
        ]);
    }

    public function test_usuario_local_nao_chama_a_api_ad(): void
    {
        Http::fake();

        $servidor = User::factory()->administrador()->create();

        $this->post('/gestao/login', [
            'email' => $servidor->email,
            'password' => 'password',
        ])->assertRedirect('/gestao');

        Http::assertNothingSent();

        $this->assertDatabaseHas('access_logs', [
            'user_id' => $servidor->id,
            'event' => 'login',
            'origin' => 'local',
        ]);
    }

    public function test_throttle_bloqueia_antes_de_chamar_a_api_ad(): void
    {
        config(['viabiliza.security.login.max_attempts' => 3]);
        Http::fake(['*/ws-autenticacao/auth' => Http::response([], 401)]);

        $servidor = User::factory()->administrador()->ad('seuusuarioad')->create();

        foreach (range(1, 3) as $tentativa) {
            $this->post('/gestao/login', [
                'email' => $servidor->email,
                'password' => 'senha-errada',
            ]);
        }

        $this->post('/gestao/login', [
            'email' => $servidor->email,
            'password' => 'senha-errada',
        ])->assertSessionHasErrors('email');

        // A 4ª tentativa foi barrada pelo throttle ANTES de bater na API —
        // o VIABILIZA não vira proxy de força bruta contra o AD institucional.
        Http::assertSentCount(3);
    }

    public function test_usuario_ad_sem_permissao_no_console_e_negado_e_auditado(): void
    {
        $this->fakeAdSucesso();

        $cidadao = User::factory()->cidadao()->ad('seuusuarioad')->create();

        $this->post('/gestao/login', [
            'email' => $cidadao->email,
            'password' => 'senha-corporativa',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('gestao');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);
    }

    public function test_usuario_ad_inativo_e_negado(): void
    {
        $this->fakeAdSucesso();

        $servidor = User::factory()->administrador()->ad('seuusuarioad')->inactive()->create();

        $this->post('/gestao/login', [
            'email' => $servidor->email,
            'password' => 'senha-corporativa',
        ])->assertSessionHasErrors([
            'email' => 'Sua conta está inativa. Procure o administrador do sistema.',
        ]);

        $this->assertGuest('gestao');
    }

    public function test_falha_na_sincronizacao_nao_derruba_o_login_e_fica_auditada(): void
    {
        Http::fake([
            '*/ws-autenticacao/auth' => Http::response(['token' => 'tk-fake']),
            '*/pessoaView/buscarPorCPF/*' => Http::response([], 502),
        ]);

        $servidor = User::factory()->administrador()->ad('seuusuarioad')->create();

        $this->post('/gestao/login', [
            'email' => $servidor->email,
            'password' => 'senha-corporativa',
        ])->assertRedirect('/gestao');

        $this->assertAuthenticatedAs($servidor, 'gestao');

        $this->assertDatabaseHas('access_logs', [
            'user_id' => $servidor->id,
            'event' => 'ad-sincronizacao-falhou',
            'channel' => 'gestao',
        ]);
    }

    public function test_senha_ad_nao_aparece_em_nenhum_registro_de_acesso(): void
    {
        $this->fakeAdSucesso();

        $servidor = User::factory()->administrador()->ad('seuusuarioad')->create();

        $this->post('/gestao/login', [
            'email' => $servidor->email,
            'password' => 'SuperSecreta123',
        ]);

        foreach (DB::table('access_logs')->get() as $log) {
            $this->assertStringNotContainsString('SuperSecreta123', (string) json_encode($log));
        }
    }
}
```

- [ ] **Step 2: Rodar e confirmar o RED**

Run: `php artisan test --compact tests/Feature/Auth/GestaoLoginAdTest.php`
Expected: FAIL (fluxo AD inexistente — contas `ad` tentam senha local).

- [ ] **Step 3: Implementar o login híbrido**

Reescrever `app/Http/Controllers/Gestao/LoginController.php`:

```php
<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\LoginRequest;
use App\Models\AccessLog;
use App\Models\User;
use App\Services\Auth\DirectoryAuthenticator;
use App\Services\Sedur\SedurAuthInvalidCredentialsException;
use App\Services\Sedur\SedurAuthSettings;
use App\Services\Sedur\SedurAuthUnavailableException;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Autenticação do console em guard próprio (gestao): sessão independente
 * do portal do cidadão. Login híbrido — contas com auth_type=ad validam a
 * credencial na API institucional SEDUR (DirectoryAuthenticator); contas
 * locais validam o hash em users.password. Em ambos os casos a sessão é
 * Laravel e valem as mesmas proteções: throttle parametrizado (HU-014) por
 * conta e por IP com Lockout auditável, anti-oráculo, conta inativada
 * (HU-012) e tentativa sem permissão bloqueada e registrada (HU-002 CA-04).
 * Sem fallback: conta AD nunca valida contra a senha local.
 */
class LoginController extends Controller
{
    public function __construct(
        private DirectoryAuthenticator $directory,
        private SedurAuthSettings $sedurAuth,
    ) {}

    public function store(LoginRequest $request): RedirectResponse
    {
        $this->ensureIsNotRateLimited($request);

        $user = User::query()
            ->where('email', Str::lower($request->validated('email')))
            ->first();

        // Anti-oráculo: o estado da conta (inativa, sem permissão) só é
        // revelado com credencial correta, e a falha de credencial tem a
        // mesma mensagem para conta local, AD ou inexistente.
        if (! $user || ! $this->credencialValida($request, $user)) {
            RateLimiter::hit($this->throttleKey($request), 60);
            RateLimiter::hit($this->ipThrottleKey($request), 60);

            event(new Failed('gestao', $user, [
                'email' => $request->validated('email'),
                'origin' => $user === null ? null : ($user->isAdAccount() ? 'ad' : 'local'),
            ]));

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        if ($user->isInactive()) {
            AccessLog::create([
                'user_id' => $user->id,
                'email' => $user->email,
                'event' => 'inativada',
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'channel' => 'gestao',
                'origin' => $user->isAdAccount() ? 'ad' : 'local',
            ]);

            throw ValidationException::withMessages([
                'email' => __('Sua conta está inativa. Procure o administrador do sistema.'),
            ]);
        }

        if (! $user->can('acessar-gestao')) {
            app(AuditService::class)->logBlocked('seguranca', 'Tentativa de login no console sem permissão', [
                'email' => $user->email,
                'user_id' => $user->id,
            ]);

            throw ValidationException::withMessages([
                'email' => __('Esta conta não tem acesso ao console de gestão.'),
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => __('Confirme seu e-mail institucional antes de acessar o console.'),
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));
        RateLimiter::clear($this->ipThrottleKey($request));

        Auth::guard('gestao')->login($user, $request->boolean('remember'));

        // Novo ID de sessão contra fixation, preservando os dados existentes
        // (inclusive um eventual login do portal no guard web).
        $request->session()->regenerate();

        return redirect()->intended(route('gestao.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('gestao')->logout();

        // Logout apenas do guard da gestão: invalidar a sessão inteira
        // derrubaria também a sessão do portal (cookie único por domínio).
        $request->session()->regenerateToken();

        return redirect()->route('gestao.login');
    }

    /**
     * Verifica a credencial pelo mecanismo da conta. A senha digitada é
     * transitória: repassada ao diretório (conta AD) ou ao hash local, e
     * descartada ao fim da request — nunca persistida nem logada.
     */
    private function credencialValida(LoginRequest $request, User $user): bool
    {
        if (! $user->isAdAccount()) {
            $request->attributes->set('auth_origin', 'local');

            return Hash::check($request->validated('password'), $user->password);
        }

        $request->attributes->set('auth_origin', 'ad');

        if (! $this->sedurAuth->enabled()) {
            app(AuditService::class)->logBlocked('seguranca', 'Login AD bloqueado: integração desabilitada (features.ad_login)', [
                'email' => $user->email,
                'user_id' => $user->id,
            ]);

            throw ValidationException::withMessages([
                'email' => __('A autenticação corporativa está desabilitada. Procure o administrador do sistema.'),
            ]);
        }

        try {
            $resultado = $this->directory->attempt(
                (string) $user->ad_username,
                $request->validated('password'),
                $user->cpf,
            );
        } catch (SedurAuthInvalidCredentialsException) {
            return false;
        } catch (SedurAuthUnavailableException) {
            AccessLog::create([
                'user_id' => $user->id,
                'email' => $user->email,
                'event' => 'ad-indisponivel',
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'channel' => 'gestao',
                'origin' => 'ad',
            ]);

            throw ValidationException::withMessages([
                'email' => __('Autenticação institucional indisponível no momento. Tente novamente em instantes.'),
            ]);
        }

        if ($resultado->sincronizacaoFalhou) {
            AccessLog::create([
                'user_id' => $user->id,
                'email' => $user->email,
                'event' => 'ad-sincronizacao-falhou',
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'channel' => 'gestao',
                'origin' => 'ad',
            ]);
        }

        // Sincronização cadastral com minimização: somente o nome oficial.
        // name está no fillable — a auditoria HasAuditoria registra a mudança.
        if ($resultado->nome !== null && $resultado->nome !== $user->name) {
            $user->update(['name' => $resultado->nome]);
        }

        return true;
    }

    protected function ensureIsNotRateLimited(LoginRequest $request): void
    {
        $this->ensureIpIsNotRateLimited($request);

        $max = (int) Settings::get('security.login.max_attempts');

        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), $max)) {
            return;
        }

        event(new Lockout($request));

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => (int) ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Limite por IP (além do por conta): contém password spraying — muitas
     * contas, uma senha — que a chave email|ip não pega. Sem ele, o VIABILIZA
     * vira proxy de força bruta contra o AD institucional.
     */
    private function ensureIpIsNotRateLimited(LoginRequest $request): void
    {
        $maxPorIp = (int) config('viabiliza.security.login.max_attempts_per_ip', 30);

        if (! RateLimiter::tooManyAttempts($this->ipThrottleKey($request), $maxPorIp)) {
            return;
        }

        event(new Lockout($request));

        $seconds = RateLimiter::availableIn($this->ipThrottleKey($request));

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => (int) ceil($seconds / 60),
            ]),
        ]);
    }

    protected function throttleKey(LoginRequest $request): string
    {
        return Str::transliterate(Str::lower($request->validated('email')).'|'.$request->ip());
    }

    protected function ipThrottleKey(LoginRequest $request): string
    {
        return 'gestao-login-ip|'.$request->ip();
    }
}
```

- [ ] **Step 4: Rodar e confirmar o GREEN + regressão**

Run: `php artisan test --compact tests/Feature/Auth/GestaoLoginAdTest.php`
Expected: PASS (10 testes).

Run: `php artisan test --compact tests/Feature/Auth/`
Expected: PASS — `GestaoLoginTest`, `GestaoPasswordResetTest`, `SessionIsolationTest`, `AuthenticationTest`, `AccessLogOriginTest`, `AuthTypeUserTest`, `SedurDirectoryAuthenticatorTest` todos verdes (regressão do fluxo local intacto).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Gestao/LoginController.php tests/Feature/Auth/GestaoLoginAdTest.php
git commit -m "feat: autentica contas AD do console via API institucional SEDUR"
```

---

### Task 7: Gestão do vínculo AD no cadastro de usuários

**Files:**
- Create: `app/Http/Requests/Gestao/UpdateUserAuthTypeRequest.php`
- Modify: `app/Http/Controllers/Gestao/UserManagementController.php` (action `updateAuthType` + props do `index`)
- Modify: `routes/gestao.php` (rota no grupo `permission:manter-usuarios`)
- Modify: `resources/js/pages/gestao/usuarios/index.tsx` (modal "Autenticação")
- Test: `tests/Feature/Gestao/UserAuthTypeTest.php`

**Interfaces:**
- Consumes: `AuthType` (Task 1), padrão `UpdateUserRoleRequest`/`updateRole` existentes.
- Produces: rota `PUT /gestao/usuarios/{user}/autenticacao` (nome `gestao.usuarios.autenticacao.update`); props `auth_type` e `ad_username` no item da listagem; evento de auditoria `usuarios`/`autenticacao-alterada`.

- [ ] **Step 1: Escrever os testes que falham**

`php artisan make:test Gestao/UserAuthTypeTest --phpunit --no-interaction` e completar:

```php
<?php

namespace Tests\Feature\Gestao;

use App\Enums\AuthType;
use App\Models\User;
use App\Notifications\GestaoResetPasswordQueued;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserAuthTypeTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function gestorLogado(): User
    {
        $gestor = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($gestor, 'gestao');

        return $gestor;
    }

    public function test_gestor_vincula_conta_ao_ad_e_senha_local_deixa_de_valer(): void
    {
        $this->gestorLogado();
        $servidor = User::factory()->analista()->create();

        $response = $this->put("/gestao/usuarios/{$servidor->id}/autenticacao", [
            'auth_type' => 'ad',
            'ad_username' => 'seuusuarioad',
        ]);

        $response->assertSessionHasNoErrors();

        $servidor->refresh();
        $this->assertSame(AuthType::Ad, $servidor->auth_type);
        $this->assertSame('seuusuarioad', $servidor->ad_username);
        $this->assertFalse(Hash::check('password', $servidor->password));

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'usuarios',
            'event' => 'autenticacao-alterada',
        ]);
    }

    public function test_conta_ad_exige_ad_username(): void
    {
        $this->gestorLogado();
        $servidor = User::factory()->analista()->create();

        $this->put("/gestao/usuarios/{$servidor->id}/autenticacao", [
            'auth_type' => 'ad',
        ])->assertSessionHasErrors('ad_username');
    }

    public function test_ad_username_precisa_ser_unico(): void
    {
        $this->gestorLogado();
        User::factory()->ad('seuusuarioad')->create();
        $outro = User::factory()->analista()->create();

        $this->put("/gestao/usuarios/{$outro->id}/autenticacao", [
            'auth_type' => 'ad',
            'ad_username' => 'seuusuarioad',
        ])->assertSessionHasErrors('ad_username');
    }

    public function test_conta_local_nao_aceita_ad_username(): void
    {
        $this->gestorLogado();
        $servidor = User::factory()->analista()->create();

        $this->put("/gestao/usuarios/{$servidor->id}/autenticacao", [
            'auth_type' => 'local',
            'ad_username' => 'seuusuarioad',
        ])->assertSessionHasErrors('ad_username');
    }

    public function test_conversao_para_local_envia_link_para_definir_nova_senha(): void
    {
        Notification::fake();
        $this->gestorLogado();
        $servidor = User::factory()->analista()->ad('seuusuarioad')->create();

        $this->put("/gestao/usuarios/{$servidor->id}/autenticacao", [
            'auth_type' => 'local',
        ])->assertSessionHasNoErrors();

        $servidor->refresh();
        $this->assertSame(AuthType::Local, $servidor->auth_type);
        $this->assertNull($servidor->ad_username);

        Notification::assertSentTo($servidor, GestaoResetPasswordQueued::class);
    }

    public function test_mesma_configuracao_nao_troca_senha_nem_envia_email(): void
    {
        Notification::fake();
        $this->gestorLogado();
        $servidor = User::factory()->analista()->ad('seuusuarioad')->create();
        $hashAntes = $servidor->password;

        $this->put("/gestao/usuarios/{$servidor->id}/autenticacao", [
            'auth_type' => 'ad',
            'ad_username' => 'seuusuarioad',
        ])->assertSessionHasNoErrors();

        $this->assertSame($hashAntes, $servidor->fresh()->password);
        Notification::assertNothingSent();
    }

    public function test_usuario_sem_permissao_nao_altera_autenticacao(): void
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();
        $this->actingAs($analista, 'gestao');

        $servidor = User::factory()->analista()->create();

        $this->put("/gestao/usuarios/{$servidor->id}/autenticacao", [
            'auth_type' => 'ad',
            'ad_username' => 'seuusuarioad',
        ])->assertForbidden();
    }
}
```

- [ ] **Step 2: Rodar e confirmar o RED**

Run: `php artisan test --compact tests/Feature/Gestao/UserAuthTypeTest.php`
Expected: FAIL (rota inexistente → 404/405).

- [ ] **Step 3: Implementar backend**

`app/Http/Requests/Gestao/UpdateUserAuthTypeRequest.php`:

```php
<?php

namespace App\Http\Requests\Gestao;

use App\Enums\AuthType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateUserAuthTypeRequest extends FormRequest
{
    /**
     * A autorização é o middleware permission:manter-usuarios da rota.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'auth_type' => ['required', new Enum(AuthType::class)],
            'ad_username' => [
                'required_if:auth_type,ad',
                'prohibited_if:auth_type,local',
                'nullable',
                'string',
                'max:100',
                'regex:/^[a-zA-Z0-9._-]+$/',
                Rule::unique('users', 'ad_username')->ignore($this->route('user')),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'auth_type' => 'tipo de autenticação',
            'ad_username' => 'conta AD',
        ];
    }
}
```

Em `app/Http/Controllers/Gestao/UserManagementController.php`:

No mapa do `->through(fn (User $user) => [...])` do `index()`, adicionar:

```php
                'auth_type' => $user->auth_type->value,
                'ad_username' => $user->ad_username,
```

Nova action (imports novos: `App\Enums\AuthType`, `App\Http\Requests\Gestao\UpdateUserAuthTypeRequest`, `App\Notifications\GestaoResetPasswordQueued` não é necessário aqui — o método do User cuida —, `Illuminate\Support\Facades\Password`, `Illuminate\Support\Str`):

```php
    /**
     * Define o mecanismo de autenticação da conta (HU-012 estendida): AD
     * valida na API institucional SEDUR; local valida o hash em users.password.
     * Na troca de mecanismo a credencial anterior é inutilizada (hash
     * aleatório) — conta convertida para local define a nova senha pelo
     * fluxo de recuperação do console. auth_type/ad_username ficam fora do
     * fillable (precedente inactivated_at): forceFill + auditoria explícita.
     */
    public function updateAuthType(UpdateUserAuthTypeRequest $request, User $user): RedirectResponse
    {
        $tipo = AuthType::from($request->validated('auth_type'));
        $adUsername = $tipo === AuthType::Ad
            ? Str::lower((string) $request->validated('ad_username'))
            : null;

        $tipoAnterior = $user->auth_type;

        $mudou = $tipoAnterior !== $tipo || $user->ad_username !== $adUsername;

        if (! $mudou) {
            return back()->with('status', 'Tipo de autenticação atualizado.');
        }

        $anterior = ['auth_type' => $tipoAnterior->value, 'ad_username' => $user->ad_username];

        $atributos = ['auth_type' => $tipo, 'ad_username' => $adUsername];

        if ($tipoAnterior !== $tipo) {
            // Troca de mecanismo: a credencial anterior deixa de ser
            // utilizável (o cast 'hashed' transforma em hash aleatório).
            $atributos['password'] = Str::random(64);
        }

        $user->forceFill($atributos)->save();

        if ($tipoAnterior === AuthType::Ad && $tipo === AuthType::Local) {
            Password::broker('users')->sendResetLink(
                ['email' => $user->email],
                fn (User $u, string $token) => $u->sendGestaoPasswordResetNotification($token),
            );
        }

        app(AuditService::class)->log(
            'usuarios',
            'autenticacao-alterada',
            "Autenticação de {$user->email} alterada para {$tipo->label()}",
            [
                'target_user_id' => $user->id,
                'anterior' => $anterior,
                'novo' => ['auth_type' => $tipo->value, 'ad_username' => $adUsername],
            ],
            $user,
            personalData: true,
        );

        return back()->with('status', 'Tipo de autenticação atualizado.');
    }
```

Em `routes/gestao.php`, dentro do grupo `Route::middleware('permission:manter-usuarios')`:

```php
            Route::put('usuarios/{user}/autenticacao', [UserManagementController::class, 'updateAuthType'])->name('usuarios.autenticacao.update');
```

- [ ] **Step 4: Rodar os testes do backend**

Run: `php artisan test --compact tests/Feature/Gestao/UserAuthTypeTest.php`
Expected: PASS (7 testes).

- [ ] **Step 5: Implementar o modal na listagem**

Em `resources/js/pages/gestao/usuarios/index.tsx`:

Interface `UserItem` — adicionar:

```tsx
    auth_type: 'ad' | 'local';
    ad_username: string | null;
```

Import do input de texto (já existem `Select`, `Label`, `Modal`, `Button`):

```tsx
import Input from '@/components/form/input';
```

Novo componente (ao lado de `RoleModal`):

```tsx
function AuthTypeModal({ user, onClose }: { user: UserItem; onClose: () => void }) {
    const [authType, setAuthType] = useState<'ad' | 'local'>(user.auth_type);
    const [adUsername, setAdUsername] = useState(user.ad_username ?? '');

    return (
        <Modal isOpen onClose={onClose} className="m-4 max-w-[507px] p-6 lg:p-8">
            <h4 className="text-lg font-semibold text-gray-800 dark:text-white/90">Tipo de autenticação</h4>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Defina como {user.name} ({user.email}) entra no console.
            </p>

            <Form action={`/gestao/usuarios/${user.id}/autenticacao`} method="put" onSuccess={onClose} className="mt-6">
                {({ errors, processing }) => (
                    <div className="flex flex-col gap-5">
                        <div>
                            <Label htmlFor={`auth-type-${user.id}`}>Autenticação</Label>
                            <Select
                                id={`auth-type-${user.id}`}
                                name="auth_type"
                                value={authType}
                                onChange={(value) => setAuthType(value as 'ad' | 'local')}
                                options={[
                                    { value: 'local', label: 'Local (senha do sistema)' },
                                    { value: 'ad', label: 'Active Directory (rede corporativa)' },
                                ]}
                            />
                            {errors.auth_type && <p className="mt-1.5 text-theme-xs text-error-500">{errors.auth_type}</p>}
                        </div>

                        {authType === 'ad' && (
                            <div>
                                <Label htmlFor={`ad-username-${user.id}`}>Conta AD</Label>
                                <Input
                                    id={`ad-username-${user.id}`}
                                    name="ad_username"
                                    value={adUsername}
                                    onChange={(event) => setAdUsername(event.target.value)}
                                    placeholder="ex.: seuusuarioad"
                                    autoComplete="off"
                                />
                                {errors.ad_username && (
                                    <p className="mt-1.5 text-theme-xs text-error-500">{errors.ad_username}</p>
                                )}
                            </div>
                        )}

                        {authType !== user.auth_type && (
                            <p className="text-theme-xs text-gray-500 dark:text-gray-400">
                                {authType === 'ad'
                                    ? 'A senha local deixará de valer; o acesso passa a ser validado na rede corporativa.'
                                    : 'O usuário receberá um e-mail para definir a nova senha local.'}
                            </p>
                        )}

                        <div className="flex items-center justify-end gap-3">
                            <Button size="sm" variant="outline" onClick={onClose} disabled={processing}>
                                Cancelar
                            </Button>
                            <Button size="sm" type="submit" disabled={processing}>
                                {processing ? 'Salvando...' : 'Salvar'}
                            </Button>
                        </div>
                    </div>
                )}
            </Form>
        </Modal>
    );
}
```

No componente `UsersIndex`: novo state `const [authTypeUser, setAuthTypeUser] = useState<UserItem | null>(null);`, nova ação na coluna `actions` (antes de "Alterar papel"):

```tsx
                        <TableAction tone="neutral" onClick={() => setAuthTypeUser(user)}>
                            Autenticação
                        </TableAction>
```

E no final, ao lado do `RoleModal`:

```tsx
            {authTypeUser && <AuthTypeModal user={authTypeUser} onClose={() => setAuthTypeUser(null)} />}
```

- [ ] **Step 6: Verificar o build do frontend**

Run: `npm run build`
Expected: build sem erros de TypeScript.

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Gestao/UpdateUserAuthTypeRequest.php app/Http/Controllers/Gestao/UserManagementController.php routes/gestao.php resources/js/pages/gestao/usuarios/index.tsx public/build tests/Feature/Gestao/UserAuthTypeTest.php
git commit -m "feat: permite vincular conta AD no cadastro de usuários do console"
```

(`public/build` entra no commit: o Dockerfile de produção não roda `npm run build` — regra docker-publish do projeto.)

---

### Task 8: Recuperação e troca de senha bloqueadas para contas AD

**Files:**
- Modify: `app/Http/Controllers/Gestao/ForgotPasswordController.php`
- Modify: `app/Http/Controllers/Gestao/ResetPasswordController.php`
- Modify: `app/Http/Controllers/Gestao/Conta/PasswordController.php`
- Test: `tests/Feature/Auth/GestaoAdPasswordResetTest.php`

**Interfaces:**
- Consumes: `User::isAdAccount()` (Task 1).
- Produces: conta AD não recebe link de recuperação do console, não redefine senha por token e não altera senha em `/gestao/conta/senha` — a senha corporativa é gerida fora do VIABILIZA.

- [ ] **Step 1: Escrever os testes que falham**

`php artisan make:test Auth/GestaoAdPasswordResetTest --phpunit --no-interaction` e completar:

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\GestaoResetPasswordQueued;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class GestaoAdPasswordResetTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_conta_ad_nao_recebe_link_de_recuperacao_e_resposta_permanece_generica(): void
    {
        Notification::fake();

        $servidorAd = User::factory()->administrador()->ad('seuusuarioad')->create();
        $servidorLocal = User::factory()->administrador()->create();

        $respostaAd = $this->post('/gestao/forgot-password', ['email' => $servidorAd->email]);
        $respostaLocal = $this->post('/gestao/forgot-password', ['email' => $servidorLocal->email]);

        // Anti-oráculo: a resposta não revela que a conta é AD.
        $respostaAd->assertSessionHas('status', 'Se a conta tiver acesso ao console, enviamos as instruções de recuperação.');
        $respostaLocal->assertSessionHas('status', 'Se a conta tiver acesso ao console, enviamos as instruções de recuperação.');

        Notification::assertNotSentTo($servidorAd, GestaoResetPasswordQueued::class);
        Notification::assertSentTo($servidorLocal, GestaoResetPasswordQueued::class);
    }

    public function test_conta_ad_nao_redefine_senha_mesmo_com_token_valido(): void
    {
        $servidor = User::factory()->administrador()->ad('seuusuarioad')->create();
        $hashAntes = $servidor->password;

        $token = Password::broker('users')->createToken($servidor);

        $this->post('/gestao/reset-password', [
            'email' => $servidor->email,
            'token' => $token,
            'password' => 'NovaSenha123',
            'password_confirmation' => 'NovaSenha123',
        ])->assertSessionHasErrors('email');

        $this->assertSame($hashAntes, $servidor->fresh()->password);
        $this->assertFalse(Hash::check('NovaSenha123', $servidor->fresh()->password));
    }

    public function test_conta_ad_nao_altera_senha_pela_area_da_conta(): void
    {
        $servidor = User::factory()->administrador()->ad('seuusuarioad')->withAcceptedLgpdTerm()->create();

        $this->actingAs($servidor, 'gestao');

        $this->put('/gestao/conta/senha', [
            'current_password' => 'qualquer',
            'password' => 'NovaSenha123',
            'password_confirmation' => 'NovaSenha123',
        ])->assertSessionHasErrors('current_password');

        $this->assertFalse(Hash::check('NovaSenha123', $servidor->fresh()->password));
    }
}
```

- [ ] **Step 2: Rodar e confirmar o RED**

Run: `php artisan test --compact tests/Feature/Auth/GestaoAdPasswordResetTest.php`
Expected: FAIL (conta AD ainda recebe link / redefine senha).

- [ ] **Step 3: Implementar**

`app/Http/Controllers/Gestao/ForgotPasswordController.php` — ajustar a condição (anti-oráculo preservado: a resposta genérica não muda):

```php
        // Só contas ativas, locais e com acesso ao console recebem o link; a
        // resposta é sempre a mesma para não revelar a existência/estado da
        // conta. Conta AD tem a senha gerida pela rede corporativa.
        if ($user && ! $user->isInactive() && ! $user->isAdAccount() && $user->can('acessar-gestao')) {
```

`app/Http/Controllers/Gestao/ResetPasswordController.php` — guarda dentro da closure do broker (defesa em profundidade: tokens emitidos antes da conversão para AD deixam de valer):

```php
        $status = Password::broker('users')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                if ($user->isAdAccount()) {
                    throw ValidationException::withMessages([
                        'email' => __('Contas corporativas (AD) têm a senha gerenciada pela rede corporativa.'),
                    ]);
                }

                $user->forceFill(['password' => Hash::make($password)])->save();

                event(new PasswordReset($user));
            },
        );
```

(adicionar `use Illuminate\Validation\ValidationException;` aos imports.)

`app/Http/Controllers/Gestao/Conta/PasswordController.php`:

```php
    public function update(Request $request, UpdateUserPassword $updater): RedirectResponse
    {
        $user = $request->user('gestao');

        if ($user->isAdAccount()) {
            throw ValidationException::withMessages([
                'current_password' => __('Contas corporativas (AD) têm a senha gerenciada pela rede corporativa. Altere-a pelo portal de senhas da prefeitura.'),
            ]);
        }

        $updater->update(
            $user,
            $request->only('current_password', 'password', 'password_confirmation'),
        );

        return back();
    }
```

(adicionar `use Illuminate\Validation\ValidationException;` aos imports.)

- [ ] **Step 4: Rodar e confirmar o GREEN + regressão**

Run: `php artisan test --compact tests/Feature/Auth/GestaoAdPasswordResetTest.php tests/Feature/Auth/GestaoPasswordResetTest.php`
Expected: PASS — novos testes verdes e suíte de reset existente intacta.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Gestao/ForgotPasswordController.php app/Http/Controllers/Gestao/ResetPasswordController.php app/Http/Controllers/Gestao/Conta/PasswordController.php tests/Feature/Auth/GestaoAdPasswordResetTest.php
git commit -m "feat: bloqueia recuperação e troca de senha local para contas AD"
```

---

### Task 9: Registrar decisões e pendências externas no STATE.md

**Files:**
- Modify: `.planning/STATE.md`

**Interfaces:**
- Consumes: spec `docs/superpowers/specs/2026-09-24-login-ad-console-design.md` §11.

- [ ] **Step 1: Ler o formato atual e acrescentar a entrada**

Ler `.planning/STATE.md` (o arquivo já tem modificações não commitadas no working tree — **acrescentar**, nunca sobrescrever) e adicionar entrada datada seguindo o padrão das existentes:

```markdown
## 2026-09-24 — Autenticação AD do console (API institucional SEDUR)

Decisões:
- Console híbrido: `users.auth_type` (ad|local) + `users.ad_username` (unique); vínculo determinístico, sem inferência por e-mail/CPF.
- AD só valida identidade; sessão, autorização (Spatie) e auditoria seguem Laravel (guard gestao). Sem fallback de conta AD para senha local.
- Token Bearer da API SEDUR é transitório (descartado ao fim da request de login); senha AD nunca persistida/logada.
- `pessoaView` somente com o CPF do próprio usuário autenticado (minimização LGPD); consulta a terceiros pelo console BLOQUEADA.
- Spec: docs/superpowers/specs/2026-09-24-login-ad-console-design.md

Pendências externas (bloqueios):
- TI-SEDUR: escopo/TTL do token do /auth; ambiente de homologação da API e credenciais de teste. A integração só se conclui com validação contra homologação real, com evidência da chamada registrada.
- DPO: base legal do tratamento de nome/CPF de servidores; janela de retenção de access_logs.
- SEDUR: política de desprovisionamento (hoje: validação a cada login + inativação manual; sincronização proativa depende de a API oferecer consulta de status funcional).
```

- [ ] **Step 2: Commit**

```bash
git add .planning/STATE.md
git commit -m "docs: registra decisões e bloqueios externos da autenticação AD"
```

---

### Task 10: Verificação final (suíte completa)

- [ ] **Step 1: Suíte de autenticação e gestão completa**

Run: `php artisan test --compact tests/Feature/Auth tests/Feature/Gestao tests/Feature/Seeders`
Expected: PASS em todos os arquivos.

- [ ] **Step 2: Suíte geral**

Run: `php artisan test --compact`
Expected: PASS. Se algum teste fora do escopo quebrar por causa da migration (ex.: factories/seeders que serializam User), corrigir o ponto quebrado — nunca remover o teste.

- [ ] **Step 3: Verificação anti-fachada (guardiao-entrega)**

Acionar o subagent `guardiao-entrega` com o diff da branch para o veredito final: lógica real de ponta a ponta, auditoria, parametrização, TDD com evidência fresca, pareceres de segurança incorporados. Veredito REPROVADO bloqueia a conclusão.

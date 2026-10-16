# Cidadão em tabela própria — Plano de implementação

> **Para agentes:** SUB-SKILL OBRIGATÓRIA: usar superpowers:subagent-driven-development (recomendado) ou superpowers:executing-plans para executar tarefa a tarefa. Os passos usam checkbox (`- [ ]`).

**Objetivo:** separar a conta do cidadão (portal) da conta do servidor (retaguarda) em tabelas, models, guards, providers e brokers de senha distintos.

**Arquitetura:** nova tabela `cidadaos` (model `Cidadao`) com os `id` das contas migradas preservados. Colunas só de cidadão trocam o alvo da chave estrangeira e são renomeadas para `*_cidadao_id`; colunas mistas viram par `*_type` + `*_id`; colunas só de servidor não mudam. Tudo é entregue numa única versão, com migrations em ordem.

**Stack:** Laravel 13, PHP 8.5, Fortify, Inertia v3 + React 19, PHPUnit 12, PostgreSQL (produção) e SQLite em memória (testes), Pint.

**Spec:** `docs/superpowers/specs/2026-10-15-cidadaos-tabela-separada-design.md`

## Global Constraints

- Base: `softwell/main` @ `dede710f`, VERSION 0.77.2, branch `feat/cidadaos-tabela-separada`, worktree `.claude/worktrees/cidadaos`. Não mexer na árvore principal.
- Config do sistema é `config/sile.php` (chave `sile.*`) nesta base. Não usar `viabiliza.*`.
- CPF e e-mail únicos dentro de cada tabela, não entre elas.
- `users` passa a ter só servidores; o papel `cidadao` deixa de existir.
- Colunas só de cidadão: renomear `*_user_id` → `*_cidadao_id`. Colunas mistas: `*_type` guarda o nome completo da classe; sem `enforceMorphMap`.
- Código e comentários em português; sem marca de IA em código, commit ou PR; commits em português, sem trailer.
- Testes são classes PHPUnit (`php artisan make:test --phpunit`); rodar `php artisan test --compact <alvo>`; ao final de cada tarefa rodar `vendor/bin/pint --dirty --format agent`.
- Nenhuma tela, texto ou regra de negócio visível ao cidadão muda.
- Não publicar nada. Publicação (Softwell/SEMIT) é decisão posterior, só depois do ensaio em cópia do banco.

## Review Focus

1. **Mesmo CPF e e-mail nas duas tabelas:** servidor com CPF X e cidadão com CPF X convivem; cadastro, login e recuperação de senha de cada ambiente ignoram a outra tabela (Tarefa 3).
2. **Cidadão criado no balcão, sem senha:** não consegue entrar; ao se cadastrar com o mesmo CPF assume o registro e vê o histórico; CPF que já tem senha continua recusado (Tarefa 3).
3. **Servidor referenciado numa coluna de cidadão** (o ator de fallback de `ReginProcessoProtocolador`): a migração cria o espelho sem senha em vez de falhar na chave estrangeira (Tarefas 5 e 9).
4. **Sessões existentes:** sessão de cidadão aberta antes do deploy continua válida (`id` preservado) e sessão de servidor não autentica no portal (Tarefa 3).
5. **Notificações e auditoria antigas:** notificação com `notifiable_type = App\Models\User` de um cidadão continua aparecendo na central do portal depois da migração (Tarefa 10).

---

## Mapa de arquivos

| Arquivo | Responsabilidade |
|---|---|
| `app/Models/Cidadao.php` | Identidade do portal (Authenticatable, MustVerifyEmail) |
| `database/factories/CidadaoFactory.php` | Factory com estados `unverified`, `inactive`, `semSenha`, `withAcceptedLgpdTerm` |
| `app/Support/Migration/IdentidadeSeparada.php` | Helpers de migration: espelhar órfãos, retargetar FK, converter em morph, acertar sequência |
| `app/Actions/Fortify/CreateNewCidadao.php` (+ `UpdateCidadao*`, `ResetCidadaoPassword`) | Ações do Fortify sobre `cidadaos` |
| `app/Http/Controllers/Gestao/CidadaoManagementController.php` | Lista e inativação de contas de cidadão na gestão |
| `tests/Feature/Cidadao/*` | Testes novos desta mudança |
| `tests/Feature/Cidadao/UsersForeignKeysTest.php` | Portão final: só colunas de servidor apontam para `users` |

---

### Task 0: Ambiente da worktree e linha de base

**Files:**
- Nenhum arquivo versionado.

- [ ] **Step 1: Instalar dependências reais na worktree (nunca symlink de `vendor`)**

```bash
cd /Users/filipefalcao/Downloads/fabrica/sile/.claude/worktrees/cidadaos
composer install --no-interaction --prefer-dist
cp .env.example .env && php artisan key:generate --no-interaction
```

Motivo: `vendor` por symlink faz o autoload resolver para o código da árvore principal e os testes rodam o código errado.

- [ ] **Step 2: Registrar a linha de base da suíte**

Run: `php artisan test --compact 2>&1 | tail -5`
Expected: suíte verde (anotar total e pulados). Se houver falhas, anotá-las: são anteriores a esta mudança e não podem ser atribuídas a ela depois.

- [ ] **Step 3: Pré-voo no banco real (executar no pod, via tinker, antes da Tarefa 12)**

Guardar o resultado nas notas da execução; ele valida as premissas da migração:

```php
// contas de servidor com histórico de cidadão em colunas "só cidadão"
foreach ([['viability_requests','requester_user_id'],['company_user','user_id'],['procurations','grantor_user_id'],['procurations','attorney_user_id'],['procurations','revoked_by_user_id'],['assisted_attendances','citizen_user_id'],['viability_queries','user_id'],['service_declaration_acceptances','user_id'],['gov_br_accounts','user_id']] as [$t,$c]) {
    $n = DB::table($t)->whereNotNull($c)->whereIn($c, DB::table('users')->where('auth_type','ad')->select('id'))->count();
    echo "$t.$c com conta AD: $n\n";
}
echo 'contas AD com papel cidadao: '.DB::table('users')->where('auth_type','ad')->whereExists(fn($q)=>$q->from('model_has_roles')->join('roles','roles.id','=','model_has_roles.role_id')->whereColumn('model_has_roles.model_id','users.id')->where('roles.name','cidadao'))->count()."\n";
```

Sem commit nesta tarefa.

---

### Task 1: Portão final — só colunas de servidor apontam para `users`

**Files:**
- Create: `tests/Feature/Cidadao/UsersForeignKeysTest.php`

**Interfaces:**
- Produces: o teste vermelho que só fica verde ao fim da Tarefa 12. Ele é o critério de pronto do projeto.

- [ ] **Step 1: Escrever o teste**

```php
<?php

namespace Tests\Feature\Cidadao;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Portão da separação cidadão x servidor: nenhuma coluna de cidadão pode apontar
 * para `users`, e toda chave para `users` precisa estar classificada como de servidor.
 */
class UsersForeignKeysTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @var list<string> tabela.coluna que, por decisão, apontam para users (servidor). */
    private const SERVIDOR = [
        'viability_requests.assigned_user_id',
        'analysis_records.analyst_user_id',
        'viability_decisions.decided_by_user_id',
        'inspections.vistoriador_user_id',
        'assisted_attendances.attendant_user_id',
        'tvl_documents.generated_by_user_id',
        'indeferimento_documents.generated_by_user_id',
        'fine_mesh_referrals.referred_by_user_id',
        'fine_mesh_referrals.resolved_by_user_id',
        'inspection_referrals.encaminhado_por_user_id',
        'inspection_referrals.analista_origem_user_id',
        'inspection_attachments.uploaded_by_user_id',
        'analysis_pendencies.requested_by_user_id',
        'analysis_pendencies.cancelled_by_user_id',
        'analysis_status_transitions.actor_user_id',
        'access_requests.decided_by_user_id',
        'access_requests.user_id',
        'sector_user.user_id',
        'user_sector_assignments.user_id',
        'user_sector_assignments.changed_by_user_id',
        'rule_versions.created_by',
        'rule_versions.published_by',
        'parameter_proposals.created_by',
        'parameter_proposals.reviewed_by',
        'ai_suggestions.created_by_user_id',
        'abuse_alerts.resolved_by_user_id',
        'predictive_anomalies.resolved_by_user_id',
        'export_files.user_id',
        'passkeys.user_id',
    ];

    public function test_so_colunas_de_servidor_apontam_para_users(): void
    {
        $encontradas = [];

        foreach (Schema::getTableListing(schemaQualified: false) as $tabela) {
            foreach (Schema::getForeignKeys($tabela) as $fk) {
                if ($fk['foreign_table'] === 'users') {
                    foreach ($fk['columns'] as $coluna) {
                        $encontradas[] = "{$tabela}.{$coluna}";
                    }
                }
            }
        }

        sort($encontradas);
        $esperadas = self::SERVIDOR;
        sort($esperadas);

        $this->assertSame($esperadas, $encontradas);
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `php artisan test --compact tests/Feature/Cidadao/UsersForeignKeysTest.php`
Expected: FAIL, com a diferença listando as colunas de cidadão (`company_user.user_id`, `viability_requests.requester_user_id`, ...). Se faltar alguma coluna de servidor na lista acima (o teste reclama de uma FK que não esperava), classificar a coluna lendo o código que a grava e ajustar a constante antes de seguir.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Cidadao/UsersForeignKeysTest.php
git commit -m "test: portão que exige só colunas de servidor apontando para users"
```

---

### Task 2: Tabela, model e factory do cidadão

**Files:**
- Create: `database/migrations/2026_10_16_000100_create_cidadaos_table.php`
- Create: `app/Models/Cidadao.php`
- Create: `database/factories/CidadaoFactory.php`
- Test: `tests/Feature/Cidadao/CidadaoModelTest.php`

**Interfaces:**
- Produces: `App\Models\Cidadao` (`Authenticatable`, `MustVerifyEmail`, `Notifiable`, `CausesActivity`, `HasAuditoria`), métodos `isInactive(): bool`, `hasAcceptedTerm(LegalTerm): bool`, `termAcceptances(): MorphMany`, `govBrAccount(): HasOne`, `sendEmailVerificationNotification()`, `sendPasswordResetNotification($token)`, `routeNotificationForWhatsapp()`, `temSenha(): bool`.
- Produces: `CidadaoFactory` com estados `unverified()`, `inactive()`, `semSenha()`, `withAcceptedLgpdTerm()`.

- [ ] **Step 1: Escrever o teste que falha**

```php
<?php

namespace Tests\Feature\Cidadao;

use App\Models\Cidadao;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CidadaoModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cria_cidadao_pela_factory(): void
    {
        $cidadao = Cidadao::factory()->create();

        $this->assertDatabaseHas('cidadaos', ['id' => $cidadao->id, 'cpf' => $cidadao->cpf]);
        $this->assertTrue($cidadao->temSenha());
        $this->assertFalse($cidadao->isInactive());
    }

    public function test_cpf_e_email_de_servidor_nao_colidem_com_cidadao(): void
    {
        $servidor = User::factory()->create();

        $cidadao = Cidadao::factory()->create([
            'cpf' => $servidor->cpf,
            'email' => $servidor->email,
        ]);

        $this->assertSame($servidor->cpf, $cidadao->cpf);
    }

    public function test_cpf_repetido_entre_cidadaos_e_recusado(): void
    {
        $primeiro = Cidadao::factory()->create();

        $this->expectException(QueryException::class);

        Cidadao::factory()->create(['cpf' => $primeiro->cpf]);
    }

    public function test_cidadao_sem_senha_nao_tem_senha(): void
    {
        $cidadao = Cidadao::factory()->semSenha()->create();

        $this->assertFalse($cidadao->temSenha());
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `php artisan test --compact tests/Feature/Cidadao/CidadaoModelTest.php`
Expected: FAIL com `Class "App\Models\Cidadao" not found`.

- [ ] **Step 3: Migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cidadaos', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('cpf', 11)->unique();
            $table->string('email')->unique();
            $table->string('phone', 20)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->rememberToken();
            $table->timestamp('inactivated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cidadao_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cidadao_password_reset_tokens');
        Schema::dropIfExists('cidadaos');
    }
};
```

- [ ] **Step 4: Model**

```php
<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use App\Notifications\ResetPasswordQueued;
use App\Notifications\VerifyEmailQueued;
use Database\Factories\CidadaoFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Spatie\Activitylog\Models\Concerns\CausesActivity;

/**
 * Conta do cidadão no portal. Identidade independente do servidor da retaguarda
 * (`User`): tabela, guard `web`, provider e broker de senha próprios.
 */
#[Fillable(['name', 'email', 'cpf', 'phone', 'password'])]
#[Hidden(['password', 'remember_token'])]
class Cidadao extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<CidadaoFactory> */
    use CausesActivity, HasAuditoria, HasFactory, Notifiable;

    protected $table = 'cidadaos';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'inactivated_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isInactive(): bool
    {
        return $this->inactivated_at !== null;
    }

    /** Registro criado no balcão nasce sem senha e só vira conta quando a pessoa se cadastra. */
    public function temSenha(): bool
    {
        return $this->password !== null;
    }

    public function sendEmailVerificationNotification(): void
    {
        $log = EmailLog::create([
            'recipient_email' => $this->email,
            'recipient_name' => $this->name,
            'notification_class' => VerifyEmailQueued::class,
            'status' => 'na_fila',
            'queued_at' => now(),
        ]);

        $notification = new VerifyEmailQueued;
        $notification->emailLogId = $log->id;
        $notification->freezeUrlFor($this);
        $this->notify($notification);
    }

    public function sendPasswordResetNotification($token): void
    {
        $log = EmailLog::create([
            'recipient_email' => $this->email,
            'recipient_name' => $this->name,
            'notification_class' => ResetPasswordQueued::class,
            'status' => 'na_fila',
            'queued_at' => now(),
        ]);

        $notification = new ResetPasswordQueued($token);
        $notification->emailLogId = $log->id;
        $notification->freezeUrlFor($this);
        $this->notify($notification);
    }

    public function routeNotificationForWhatsapp(?Notification $notification = null): ?string
    {
        return $this->phone;
    }

    /**
     * @return MorphMany<LegalTermAcceptance, $this>
     */
    public function termAcceptances(): MorphMany
    {
        return $this->morphMany(LegalTermAcceptance::class, 'accepted_by');
    }

    public function govBrAccount(): HasOne
    {
        return $this->hasOne(GovBrAccount::class, 'cidadao_id');
    }

    public function hasAcceptedTerm(LegalTerm $term): bool
    {
        return $this->termAcceptances()->where('legal_term_id', $term->id)->exists();
    }
}
```

Observação: `termAcceptances()` e `govBrAccount()` dependem das Tarefas 6 e 7 para funcionar; os testes desta tarefa não os usam.

- [ ] **Step 5: Factory**

```php
<?php

namespace Database\Factories;

use App\Models\Cidadao;
use App\Models\LegalTerm;
use App\Models\LegalTermAcceptance;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Cidadao>
 */
class CidadaoFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'cpf' => $this->generateCpf(),
            'phone' => fake()->numerify('(71) 9####-####'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    private function generateCpf(): string
    {
        $n = [];

        for ($i = 0; $i < 9; $i++) {
            $n[] = random_int(0, 9);
        }

        for ($t = 9; $t < 11; $t++) {
            $d = 0;

            for ($c = 0; $c < $t; $c++) {
                $d += $n[$c] * (($t + 1) - $c);
            }

            $n[$t] = ((10 * $d) % 11) % 10;
        }

        return implode('', $n);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => ['email_verified_at' => null]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['inactivated_at' => now()]);
    }

    /** Registro criado pelo servidor no atendimento presencial: sem senha e sem e-mail verificado. */
    public function semSenha(): static
    {
        return $this->state(fn (array $attributes) => [
            'password' => null,
            'email_verified_at' => null,
            'remember_token' => null,
        ]);
    }

    public function withAcceptedLgpdTerm(): static
    {
        return $this->afterCreating(function (Cidadao $cidadao) {
            $term = LegalTerm::current('lgpd')
                ?? LegalTerm::factory()->published()->create(['type' => 'lgpd', 'version' => 1]);

            LegalTermAcceptance::create([
                'accepted_by_type' => Cidadao::class,
                'accepted_by_id' => $cidadao->id,
                'legal_term_id' => $term->id,
                'ip_address' => '127.0.0.1',
                'accepted_at' => now(),
            ]);
        });
    }
}
```

`withAcceptedLgpdTerm()` usa o par morph que a Tarefa 6 cria; até lá, o teste desta tarefa não chama esse estado.

- [ ] **Step 6: Rodar e ver passar**

Run: `php artisan test --compact tests/Feature/Cidadao/CidadaoModelTest.php`
Expected: PASS (4 testes).

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_10_16_000100_create_cidadaos_table.php app/Models/Cidadao.php database/factories/CidadaoFactory.php tests/Feature/Cidadao/CidadaoModelTest.php
git commit -m "feat: tabela, model e factory do cidadão"
```

---

### Task 3: Autenticação do portal sobre `cidadaos`

**Files:**
- Modify: `config/auth.php` (providers e passwords)
- Modify: `config/fortify.php:12` (`'passwords' => 'cidadaos'`)
- Create: `app/Actions/Fortify/CreateNewCidadao.php`, `UpdateCidadaoPassword.php`, `ResetCidadaoPassword.php`, `UpdateCidadaoProfileInformation.php`
- Modify: `app/Providers/FortifyServiceProvider.php:94-97,134-159`
- Modify: `app/Http/Controllers/Auth/ForgotPasswordController.php`, `ResetPasswordController.php` (conferir qual ambiente cada um serve)
- Modify: `app/Services/GovBr/GovBrAuthService.php`, `app/Http/Controllers/Portal/GovBrLoginController.php`
- Test: `tests/Feature/Cidadao/AutenticacaoCidadaoTest.php`

**Interfaces:**
- Consumes: `Cidadao`, `CidadaoFactory` (Tarefa 2).
- Produces: guard `web` → provider `cidadaos` → `Cidadao`; broker `cidadaos` (tabela `cidadao_password_reset_tokens`); `CreateNewCidadao::create(array $input): Cidadao`.

- [ ] **Step 1: Escrever os testes que falham**

```php
<?php

namespace Tests\Feature\Cidadao;

use App\Models\Cidadao;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AutenticacaoCidadaoTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function dadosCadastro(array $sobrescrever = []): array
    {
        return array_merge([
            'name' => 'Maria da Silva',
            'email' => 'maria@example.com',
            'cpf' => '52998224725',
            'phone' => '(71) 99999-1234',
            'password' => 'Senha1234',
            'password_confirmation' => 'Senha1234',
        ], $sobrescrever);
    }

    public function test_servidor_com_o_mesmo_cpf_e_email_consegue_criar_conta_de_cidadao(): void
    {
        User::factory()->create(['cpf' => '52998224725', 'email' => 'maria@example.com']);

        $this->post('/portal/register', $this->dadosCadastro())->assertRedirect();

        $this->assertDatabaseHas('cidadaos', ['cpf' => '52998224725']);
        $this->assertSame(1, User::query()->where('cpf', '52998224725')->count());
    }

    public function test_cpf_ja_cadastrado_como_cidadao_com_senha_e_recusado(): void
    {
        Cidadao::factory()->create(['cpf' => '52998224725']);

        $this->post('/portal/register', $this->dadosCadastro())
            ->assertSessionHasErrors('cpf');
    }

    public function test_cadastro_assume_o_registro_criado_no_balcao(): void
    {
        $balcao = Cidadao::factory()->semSenha()->create([
            'cpf' => '52998224725',
            'email' => 'balcao@example.com',
        ]);

        $this->post('/portal/register', $this->dadosCadastro())->assertRedirect();

        $this->assertSame(1, Cidadao::query()->where('cpf', '52998224725')->count());
        $balcao->refresh();
        $this->assertTrue($balcao->temSenha());
        $this->assertSame('maria@example.com', $balcao->email);
    }

    public function test_registro_do_balcao_nao_autentica_sem_senha(): void
    {
        Cidadao::factory()->semSenha()->create(['cpf' => '52998224725']);

        $this->post('/portal/login', ['cpf' => '529.982.247-25', 'password' => 'qualquer'])
            ->assertSessionHasErrors();
        $this->assertGuest('web');
    }

    public function test_login_do_portal_so_autentica_cidadao(): void
    {
        $servidor = User::factory()->create(['cpf' => '52998224725']);

        $this->post('/portal/login', ['cpf' => '529.982.247-25', 'password' => 'password'])
            ->assertSessionHasErrors();
        $this->assertGuest('web');

        Cidadao::factory()->create(['cpf' => '52998224725']);

        $this->post('/portal/login', ['cpf' => '529.982.247-25', 'password' => 'password']);
        $this->assertAuthenticated('web');
        $this->assertNotSame($servidor::class, auth('web')->user()::class);
    }

    public function test_login_da_gestao_so_autentica_servidor(): void
    {
        Cidadao::factory()->create(['cpf' => '52998224725', 'email' => 'maria@example.com']);

        $this->post('/gestao/login', ['email' => 'maria@example.com', 'password' => 'password'])
            ->assertSessionHasErrors();
        $this->assertGuest('gestao');
    }

    public function test_recuperacao_de_senha_do_portal_ignora_servidor(): void
    {
        User::factory()->create(['email' => 'so-servidor@example.com']);

        $status = Password::broker('cidadaos')->sendResetLink(['email' => 'so-servidor@example.com']);

        $this->assertSame(Password::INVALID_USER, $status);
    }

    public function test_sessao_de_cidadao_existente_continua_valida_no_painel(): void
    {
        $cidadao = Cidadao::factory()->withAcceptedLgpdTerm()->create();

        $this->actingAs($cidadao, 'web')->get('/portal/painel')->assertOk();
    }

    public function test_sessao_de_servidor_nao_autentica_no_portal(): void
    {
        $servidor = User::factory()->create();

        $this->actingAs($servidor, 'gestao')->get('/portal/painel')->assertRedirect(route('login'));
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `php artisan test --compact tests/Feature/Cidadao/AutenticacaoCidadaoTest.php`
Expected: FAIL (cadastro recusa CPF de servidor; login do portal autentica servidor).

- [ ] **Step 3: `config/auth.php`**

Em `providers`, acrescentar o provider dos cidadãos e trocar o do guard `web`:

```php
    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'cidadaos',
        ],

        // Retaguarda: contas de servidor em `users`, sessão independente do portal.
        'gestao' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],

        'cidadaos' => [
            'driver' => 'eloquent',
            'model' => Cidadao::class,
        ],
    ],
```

Importar `use App\Models\Cidadao;` no topo, ao lado de `use App\Models\User;`. Em `passwords`:

```php
    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],

        'cidadaos' => [
            'provider' => 'cidadaos',
            'table' => 'cidadao_password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],
```

Em `config/fortify.php`, trocar `'passwords' => 'users'` por `'passwords' => 'cidadaos'`.

- [ ] **Step 4: Ações do Fortify**

`app/Actions/Fortify/CreateNewCidadao.php`:

```php
<?php

namespace App\Actions\Fortify;

use App\Models\Cidadao;
use App\Rules\TelefoneComDdd;
use App\Rules\ValidCpf;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewCidadao implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Cria a conta do cidadão ou assume o registro criado pelo servidor no
     * atendimento presencial (mesmo CPF, ainda sem senha).
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): Cidadao
    {
        $input['cpf'] = preg_replace('/\D/', '', (string) ($input['cpf'] ?? ''));

        $balcao = Cidadao::query()->where('cpf', $input['cpf'])->whereNull('password')->first();

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('cidadaos', 'email')->ignore($balcao?->id),
            ],
            'cpf' => [
                'required', 'string', new ValidCpf,
                Rule::unique('cidadaos', 'cpf')->ignore($balcao?->id),
            ],
            'phone' => ['required', 'string', 'max:20', new TelefoneComDdd],
            'password' => $this->passwordRules(),
        ], [], [
            'name' => 'nome',
            'cpf' => 'CPF',
            'phone' => 'telefone',
            'password' => 'senha',
        ])->validate();

        $dados = [
            'name' => $input['name'],
            'email' => $input['email'],
            'cpf' => $input['cpf'],
            'phone' => $input['phone'] ?? null,
            'password' => $input['password'],
        ];

        if ($balcao !== null) {
            $balcao->forceFill($dados)->save();

            return $balcao;
        }

        return Cidadao::create($dados);
    }
}
```

As demais ações são cópias das de `User` trocando o tipo para `Cidadao`:

```bash
cd app/Actions/Fortify
for a in UpdateUserPassword:UpdateCidadaoPassword ResetUserPassword:ResetCidadaoPassword UpdateUserProfileInformation:UpdateCidadaoProfileInformation; do
  de=${a%%:*}; para=${a##*:}
  sed -e "s/class $de/class $para/" -e 's/App\\Models\\User/App\\Models\\Cidadao/' -e 's/\bUser \$user/Cidadao $user/g' -e "s/unique:users/unique:cidadaos/g" -e "s/Rule::unique('users')/Rule::unique('cidadaos')/g" $de.php > $para.php
done
grep -n "User\b" UpdateCidadaoPassword.php ResetCidadaoPassword.php UpdateCidadaoProfileInformation.php
```

O `grep` final precisa voltar vazio (ou só comentários). Ajustar à mão o que sobrar.

- [ ] **Step 5: `FortifyServiceProvider`**

Trocar as quatro linhas de `Fortify::...Using` (`:94-97`) e o `authenticateUsing` (`:134-159`):

```php
        Fortify::createUsersUsing(CreateNewCidadao::class);
        Fortify::updateUserProfileInformationUsing(UpdateCidadaoProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateCidadaoPassword::class);
        Fortify::resetUserPasswordsUsing(ResetCidadaoPassword::class);
```

```php
        Fortify::authenticateUsing(function (Request $request) {
            $cpf = preg_replace('/\D/', '', (string) $request->input('cpf'));

            $cidadao = Cidadao::query()->where('cpf', $cpf)->first();

            if (! $cidadao || ! $cidadao->temSenha() || ! Hash::check($request->password, $cidadao->password)) {
                return null;
            }

            if ($cidadao->isInactive()) {
                AccessLog::create([
                    'actor_type' => Cidadao::class,
                    'actor_id' => $cidadao->id,
                    'email' => $cidadao->email,
                    'event' => 'inativada',
                    'ip_address' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 500),
                    'channel' => 'portal',
                ]);

                throw ValidationException::withMessages([
                    'cpf' => __('Sua conta está inativa. Procure o administrador do sistema.'),
                ]);
            }

            return $cidadao;
        });
```

Trocar os `use` correspondentes (`CreateNewCidadao`, `ResetCidadaoPassword`, `UpdateCidadaoPassword`, `UpdateCidadaoProfileInformation`, `Cidadao`; remover `User`). `AccessLog` passa a ter `actor_type`/`actor_id` na Tarefa 6; até lá o `create` acima falha só no ramo de conta inativa, coberto pelo teste da Tarefa 6.

- [ ] **Step 6: Controllers de senha e gov.br**

Run: `grep -n "Password::\|broker\|User::" app/Http/Controllers/Auth/*.php app/Http/Controllers/Portal/GovBrLoginController.php app/Services/GovBr/GovBrAuthService.php`

Regras: `ForgotPasswordController` e `ResetPasswordController` da **gestão** usam `Password::broker('users')`; o fluxo do portal (Fortify) já usa o broker `cidadaos` via `config/fortify.php`. `GovBrAuthService` resolve o CPF contra `Cidadao` (não `User`), cria `Cidadao` no primeiro acesso e deixa de chamar `PerfilCidadao::nome()` (não há mais papel).

- [ ] **Step 7: Rodar e ver passar**

Run: `php artisan test --compact tests/Feature/Cidadao/AutenticacaoCidadaoTest.php`
Expected: PASS (9 testes). O teste `withAcceptedLgpdTerm` e o ramo de conta inativa só passam depois da Tarefa 6; até lá rodar os demais com `--filter`.

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add config/auth.php config/fortify.php app/Actions/Fortify app/Providers/FortifyServiceProvider.php app/Services/GovBr app/Http/Controllers tests/Feature/Cidadao/AutenticacaoCidadaoTest.php
git commit -m "feat: autenticação do portal sobre a tabela de cidadãos"
```

Estado esperado ao fim desta tarefa: a suíte completa fica **vermelha** nos testes do portal. É a etapa do cutover: as Tarefas 4 a 10 devolvem o verde por área. Cada tarefa lista os diretórios de teste que ela precisa deixar verdes.

---

### Task 4: Converter os testes do portal para `Cidadao`

**Files:**
- Modify: ~75 arquivos em `tests/` que usam `User::factory()->cidadao()` (lista por `grep -rl "cidadao()" tests`)
- Modify: `database/seeders/RolesAndPermissionsSeeder.php` (continua criando o papel até a Tarefa 12)

**Interfaces:**
- Consumes: `CidadaoFactory` (Tarefa 2).
- Produces: testes que criam o usuário do portal como `Cidadao`.

- [ ] **Step 1: Conversão mecânica (script descartável, não versionar)**

```bash
python3 - <<'EOF'
import re, subprocess, pathlib
arquivos = subprocess.run(["grep","-rl","cidadao()","tests"],capture_output=True,text=True).stdout.split()
for f in arquivos:
    p = pathlib.Path(f); s = p.read_text(); o = s
    s = re.sub(r"User::factory\(\)->cidadao\(\)", "Cidadao::factory()", s)
    if s != o and "use App\\Models\\Cidadao;" not in s:
        s = s.replace("use App\\Models\\User;", "use App\\Models\\Cidadao;\nuse App\\Models\\User;", 1) \
            if "use App\\Models\\User;" in s else s.replace("\nuse ", "\nuse App\\Models\\Cidadao;\nuse ", 1)
    if s != o:
        p.write_text(s); print("convertido", f)
EOF
vendor/bin/pint --dirty --format agent
grep -rn "cidadao()" tests | head
```

O `grep` final lista o que a regex não pegou (por exemplo `User::factory()->withAcceptedLgpdTerm()->cidadao()`): converter à mão para `Cidadao::factory()->withAcceptedLgpdTerm()`.

- [ ] **Step 2: Quais testes dependem do papel**

Run: `grep -rn "assignRole('cidadao')\|hasRole('cidadao')\|PerfilCidadao" tests`
Cada ocorrência vira criação de `Cidadao`; `ManageParametersTest` ganha o ajuste da Tarefa 12 (parâmetro `portal.perfil_cidadao`).

- [ ] **Step 3: Commit**

```bash
git add tests
git commit -m "test: usuários do portal nos testes passam a ser Cidadao"
```

---

### Task 5: Empresas e Gov.br — `company_user` e `gov_br_accounts`

**Files:**
- Create: `app/Support/Migration/IdentidadeSeparada.php`
- Create: `database/migrations/2026_10_16_000300_copia_cidadaos_de_users.php`
- Create: `database/migrations/2026_10_16_000400_retarget_company_user_e_gov_br_accounts.php`
- Modify: `app/Models/CompanyUser.php`, `app/Models/Company.php`, `app/Models/GovBrAccount.php`, `app/Policies/CompanyPolicy.php`, `app/Http/Controllers/Portal/{CompanyController,CompanyLinkController,HistoricoConsultaController,RenovacaoController}.php`, `app/Http/Requests/Portal/EndCompanyLinkRequest.php`, `app/Services/RedesimImportService.php`, `database/factories/{CompanyUserFactory,GovBrAccountFactory}.php`, `database/seeders/{CompanySeeder,AuditoriaDevSeeder}.php`
- Test: `tests/Feature/Cidadao/MigracaoCopiaCidadaosTest.php`; diretórios que precisam voltar a verde: `tests/Feature/Companies`, `tests/Feature/Auth/GovBr*`

**Interfaces:**
- Produces: `IdentidadeSeparada::espelharOrfaos(string $tabela, string $coluna): void`, `::retargetar(string $tabela, string $antiga, string $nova, bool $nullable, string $onDelete): void`, `::paraMorph(string $tabela, string $antiga, string $base): void`, `::acertarSequencia(): void`.
- Produces: `company_user.cidadao_id`, `gov_br_accounts.cidadao_id` → `cidadaos.id`.

- [ ] **Step 1: Teste da cópia de contas (falha)**

```php
<?php

namespace Tests\Feature\Cidadao;

use App\Models\Cidadao;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MigracaoCopiaCidadaosTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_copia_so_contas_que_nao_sao_de_servidor_preservando_o_id(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $cidadao = User::factory()->create();
        $analista = User::factory()->analista()->create();
        $ad = User::factory()->ad()->create();

        $migracao = require database_path('migrations/2026_10_16_000300_copia_cidadaos_de_users.php');
        $migracao->up();

        $this->assertDatabaseHas('cidadaos', ['id' => $cidadao->id, 'cpf' => $cidadao->cpf]);
        $this->assertDatabaseMissing('cidadaos', ['id' => $analista->id]);
        $this->assertDatabaseMissing('cidadaos', ['id' => $ad->id]);
        $this->assertSame($cidadao->password, DB::table('cidadaos')->where('id', $cidadao->id)->value('password'));
    }

    public function test_espelho_de_servidor_nasce_sem_senha_e_com_o_mesmo_id(): void
    {
        $servidor = User::factory()->analista()->create();

        \App\Support\Migration\IdentidadeSeparada::espelharServidor($servidor->id);

        $this->assertDatabaseHas('cidadaos', ['id' => $servidor->id, 'password' => null]);
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `php artisan test --compact tests/Feature/Cidadao/MigracaoCopiaCidadaosTest.php`
Expected: FAIL (arquivo de migration e classe inexistentes). Atenção: no SQLite de teste, `users` já tem as contas criadas pela factory depois da migration inicial; a migration de cópia roda no `up()` manual do teste, e também uma vez no `migrate` do `RefreshDatabase` com `users` vazia.

- [ ] **Step 3: Helper de migration**

```php
<?php

namespace App\Support\Migration;

use App\Models\Cidadao;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Operações compartilhadas pelas migrations da separação cidadão x servidor.
 * Os `id` das contas migradas são preservados, então as colunas "só cidadão"
 * mantêm os valores e só trocam o alvo da chave estrangeira.
 */
final class IdentidadeSeparada
{
    private const COLUNAS = [
        'id', 'name', 'cpf', 'email', 'phone', 'email_verified_at', 'password',
        'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
        'remember_token', 'inactivated_at', 'created_at', 'updated_at',
    ];

    /**
     * Contas que não são de servidor: não AD e sem perfil com `acessar-gestao`
     * (mesmo critério de `User::scopeUsuarioPortal`).
     */
    public static function consultaContasDeCidadao(): \Illuminate\Database\Query\Builder
    {
        return DB::table('users')
            ->select(self::COLUNAS)
            ->where('auth_type', '!=', 'ad')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('model_has_roles')
                    ->join('role_has_permissions', 'role_has_permissions.role_id', '=', 'model_has_roles.role_id')
                    ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                    ->whereColumn('model_has_roles.model_id', 'users.id')
                    ->where('model_has_roles.model_type', User::class)
                    ->where('permissions.name', 'acessar-gestao');
            });
    }

    public static function copiarContasDeCidadao(): void
    {
        DB::table('cidadaos')->insertUsing(self::COLUNAS, self::consultaContasDeCidadao());
        self::acertarSequencia();
    }

    /** Servidor que aparece como requerente/outorgante: ganha espelho sem senha, mesmo id. */
    public static function espelharServidor(int $userId): void
    {
        if (DB::table('cidadaos')->where('id', $userId)->exists()) {
            return;
        }

        $colunas = array_diff(self::COLUNAS, ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']);

        DB::table('cidadaos')->insertUsing(
            $colunas,
            DB::table('users')->select($colunas)->where('id', $userId),
        );
    }

    public static function espelharOrfaos(string $tabela, string $coluna): void
    {
        DB::table($tabela)
            ->whereNotNull($coluna)
            ->whereNotIn($coluna, DB::table('cidadaos')->select('id'))
            ->distinct()
            ->pluck($coluna)
            ->each(fn (int $id) => self::espelharServidor($id));

        self::acertarSequencia();
    }

    public static function acertarSequencia(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            "SELECT setval(pg_get_serial_sequence('cidadaos','id'), COALESCE((SELECT MAX(id) FROM cidadaos), 1), (SELECT MAX(id) IS NOT NULL FROM cidadaos))"
        );
    }

    /** Troca `$antiga` (FK para users) por `$nova` (FK para cidadaos), mantendo os valores. */
    public static function retargetar(string $tabela, string $antiga, string $nova, bool $nullable, string $onDelete = 'cascade'): void
    {
        self::espelharOrfaos($tabela, $antiga);

        Schema::table($tabela, fn (Blueprint $t) => $t->dropForeign([$antiga]));
        Schema::table($tabela, fn (Blueprint $t) => $t->renameColumn($antiga, $nova));
        Schema::table($tabela, function (Blueprint $t) use ($nova, $onDelete) {
            $t->foreign($nova)->references('id')->on('cidadaos')->onDelete($onDelete);
        });
    }

    /** Converte `$antiga` (FK para users) no par `{$base}_type` + `{$base}_id`, sem FK. */
    public static function paraMorph(string $tabela, string $antiga, string $base): void
    {
        Schema::table($tabela, function (Blueprint $t) use ($base) {
            $t->string("{$base}_type", 60)->nullable();
        });

        DB::table($tabela)->whereNotNull($antiga)
            ->whereIn($antiga, DB::table('cidadaos')->select('id'))
            ->update(["{$base}_type" => Cidadao::class]);

        DB::table($tabela)->whereNotNull($antiga)->whereNull("{$base}_type")
            ->update(["{$base}_type" => User::class]);

        Schema::table($tabela, fn (Blueprint $t) => $t->dropForeign([$antiga]));
        Schema::table($tabela, fn (Blueprint $t) => $t->renameColumn($antiga, "{$base}_id"));
        Schema::table($tabela, fn (Blueprint $t) => $t->index(["{$base}_type", "{$base}_id"]));
    }
}
```

- [ ] **Step 4: Migration de cópia**

```php
<?php

use App\Support\Migration\IdentidadeSeparada;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        IdentidadeSeparada::copiarContasDeCidadao();
    }

    public function down(): void
    {
        DB::table('cidadaos')->delete();
    }
};
```

A cópia mantém as linhas em `users`; a remoção acontece na Tarefa 12, depois de todas as colunas serem retargetadas.

- [ ] **Step 5: Migration das duas tabelas**

```php
<?php

use App\Support\Migration\IdentidadeSeparada;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        IdentidadeSeparada::retargetar('company_user', 'user_id', 'cidadao_id', false);
        IdentidadeSeparada::retargetar('gov_br_accounts', 'user_id', 'cidadao_id', false);
    }

    public function down(): void
    {
        // Reversão consolidada na migration final (Tarefa 12).
    }
};
```

- [ ] **Step 6: Código**

Trocar nas classes listadas (e conferir com o `grep` abaixo):

- `CompanyUser`: `#[Fillable([... 'cidadao_id' ...])]`, `belongsTo(Cidadao::class, 'cidadao_id')`; método da relação renomeado de `user()` para `cidadao()`.
- `Company`: relações `links()`/`users()` e `scopeForUser` passam a `Cidadao` e `cidadao_id`; renomear `forUser(User $u)` para `forCidadao(Cidadao $c)`.
- `GovBrAccount`: `#[Fillable(['cidadao_id', ...])]`, `belongsTo(Cidadao::class, 'cidadao_id')`.
- `CompanyPolicy`: parâmetro `Cidadao $cidadao`.
- Controllers e requests: `$request->user()` já é `Cidadao`; ajustar chamadas `forUser`/`user_id`/`->user` para os novos nomes.
- Factories e seeders: `user_id` → `cidadao_id`; `User::factory()` → `Cidadao::factory()`.

```bash
grep -rn "company_user\|CompanyUser\|forUser\|gov_br\|GovBrAccount\|govBrAccount" app database/factories database/seeders routes resources/js | grep -n "user_id\|User\b\|->user\b"
```

Deve voltar vazio.

- [ ] **Step 7: Rodar**

Run: `php artisan test --compact tests/Feature/Cidadao/MigracaoCopiaCidadaosTest.php tests/Feature/Companies tests/Feature/Auth/GovBrAccountTest.php tests/Feature/Auth/GovBrLoginTest.php`
Expected: PASS. Falhas restantes nessas pastas por causa de requerente ou procuração aparecem aqui e só se resolvem nas Tarefas 7 e 8; separar o que é desta tarefa do que não é.

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A app database tests
git commit -m "feat: empresas e gov.br apontam para cidadãos"
```

---

### Task 6: Colunas mistas viram par `*_type` + `*_id`

**Files:**
- Create: `database/migrations/2026_10_16_000350_converte_colunas_mistas_em_morph.php` (roda **antes** da 000400)
- Modify: `app/Models/{ViabilityRequest,ViabilityRequestDocument,ViabilityRequestTransition,AccessLog,LegalTermAcceptance,Communication}.php`, `app/Models/User.php` e `Cidadao.php` (relações inversas)
- Modify: `app/Listeners/{RecordSuccessfulLogin,RecordFailedLogin,RecordLockout,RecordLogout,RegistrarTrilhaProtocolo,AuditModelsPruned}.php`, `app/Http/Controllers/{Gestao/AccessHistoryController,Gestao/AuditoriaController,Portal/AccessHistoryController,Portal/LgpdTermController,Gestao/TermoLgpdController,Portal/SolicitacaoDocumentoController}.php`, `app/Services/{Auditoria/AuditTrailQueryService,Relatorios/TrilhaProcessoService,Solicitacao/*,Comunicacao/NotificationDispatcher,Lgpd/LgpdMonitorService}.php`, `app/Http/Middleware/EnsureLgpdTermAccepted.php`, factories correspondentes
- Test: `tests/Feature/Cidadao/ColunasMistasTest.php`; diretórios: `tests/Feature/AccessHistory`, `tests/Feature/Lgpd`, `tests/Feature/Comunicacao`, `tests/Feature/Auth`

**Interfaces:**
- Produces: pares `created_by`, `cancelled_by` (em `viability_requests`), `uploaded_by` (`viability_request_documents`), `actor` (`viability_request_transitions` e `access_logs`), `accepted_by` (`legal_term_acceptances`), `recipient` (`communications`); cada um com `{base}_type` (string 60) e `{base}_id`, e `MorphTo` `{base}()` no model.
- Consumes: `IdentidadeSeparada::paraMorph` (Tarefa 5).

- [ ] **Step 1: Teste (falha)**

```php
<?php

namespace Tests\Feature\Cidadao;

use App\Models\AccessLog;
use App\Models\Cidadao;
use App\Models\LegalTermAcceptance;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ColunasMistasTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_access_log_guarda_o_tipo_de_quem_agiu(): void
    {
        $cidadao = Cidadao::factory()->create();
        $servidor = User::factory()->create();

        $logCidadao = AccessLog::factory()->create(['actor_type' => Cidadao::class, 'actor_id' => $cidadao->id]);
        $logServidor = AccessLog::factory()->create(['actor_type' => User::class, 'actor_id' => $servidor->id]);

        $this->assertTrue($logCidadao->actor->is($cidadao));
        $this->assertTrue($logServidor->actor->is($servidor));
    }

    public function test_cidadao_e_servidor_com_o_mesmo_id_nao_se_confundem(): void
    {
        $servidor = User::factory()->create();
        $cidadao = Cidadao::factory()->create(['id' => $servidor->id]);

        $aceite = LegalTermAcceptance::factory()->create([
            'accepted_by_type' => Cidadao::class,
            'accepted_by_id' => $cidadao->id,
        ]);

        $this->assertInstanceOf(Cidadao::class, $aceite->acceptedBy);
    }
}
```

Run: `php artisan test --compact tests/Feature/Cidadao/ColunasMistasTest.php`
Expected: FAIL (colunas e relações inexistentes). Se `LegalTermAcceptanceFactory` não existir, criar com `php artisan make:factory LegalTermAcceptanceFactory --model=LegalTermAcceptance --no-interaction`.

- [ ] **Step 2: Migration**

```php
<?php

use App\Support\Migration\IdentidadeSeparada;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** Ordem obrigatória: o tipo é decidido pelo conteúdo de `cidadaos` antes de qualquer espelho. */
    public function up(): void
    {
        IdentidadeSeparada::paraMorph('viability_requests', 'created_by_user_id', 'created_by');
        IdentidadeSeparada::paraMorph('viability_requests', 'cancelled_by_user_id', 'cancelled_by');
        IdentidadeSeparada::paraMorph('viability_request_documents', 'uploaded_by_user_id', 'uploaded_by');
        IdentidadeSeparada::paraMorph('viability_request_transitions', 'actor_user_id', 'actor');
        IdentidadeSeparada::paraMorph('access_logs', 'user_id', 'actor');
        IdentidadeSeparada::paraMorph('legal_term_acceptances', 'user_id', 'accepted_by');
        IdentidadeSeparada::paraMorph('communications', 'recipient_user_id', 'recipient');
    }

    public function down(): void
    {
        // Reversão consolidada na migration final (Tarefa 12).
    }
};
```

Conferir antes, nas migrations originais, se alguma dessas colunas é `NOT NULL` ou tem índice único composto que cita a coluna antiga (`legal_term_acceptances` tem unique `[user_id, legal_term_id]`): nesse caso, derrubar o unique antes de renomear e recriá-lo como `unique([accepted_by_type, accepted_by_id, legal_term_id])` dentro de `paraMorph` ou logo após a chamada.

- [ ] **Step 3: Models e escritores**

Em cada model, trocar `belongsTo(User::class, 'x_user_id')` por `morphTo('x')`, com `#[Fillable]` atualizado, por exemplo:

```php
public function createdBy(): MorphTo
{
    return $this->morphTo('created_by');
}
```

Nos escritores (`create([... 'created_by_user_id' => $user->id])`), gravar o par:

```php
'created_by_type' => $ator::class,
'created_by_id' => $ator->id,
```

onde `$ator` é o `Authenticatable` do request (`Cidadao` no portal, `User` na gestão). `EnsureLgpdTermAccepted` já escolhe o guard; passa a consultar `hasAcceptedTerm` do `Cidadao` ou do `User`, e `User::termAcceptances()` vira `morphMany(LegalTermAcceptance::class, 'accepted_by')`.

```bash
grep -rn "created_by_user_id\|cancelled_by_user_id\|uploaded_by_user_id\|'actor_user_id'\|recipient_user_id\|'user_id'" app database/factories database/seeders resources/js | grep -v "analysis_\|inspection\|tvl\|indeferimento\|sector\|fine_mesh\|abuse\|predictive\|export_files\|access_requests\|parameter\|rule_versions\|ai_suggestions"
```

Deve voltar vazio ao fim da tarefa.

- [ ] **Step 4: Rodar**

Run: `php artisan test --compact tests/Feature/Cidadao/ColunasMistasTest.php tests/Feature/AccessHistory tests/Feature/Lgpd tests/Feature/Comunicacao tests/Feature/Auth`
Expected: PASS. Inclui o ramo de conta inativa de `AutenticacaoCidadaoTest` (Tarefa 3).

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A app database tests
git commit -m "feat: colunas mistas guardam o tipo de quem agiu"
```

---

### Task 7: Procurações e representação

**Files:**
- Create: `database/migrations/2026_10_16_000410_retarget_procurations.php`
- Modify: `app/Models/Procuration.php`, `app/Policies/ProcurationPolicy.php`, `app/Http/Controllers/Portal/{ProcurationController,RepresentationController,DashboardController}.php`, `app/Http/Requests/StoreProcurationRequest.php`, `app/Http/Middleware/{ResolveRepresentation,HandleInertiaRequests}.php`, `app/Support/Representation/CurrentRepresentation.php`, `database/factories/ProcurationFactory.php`
- Test: diretório `tests/Feature/Procuration` e `tests/Feature/Cidadao/RepresentacaoTest.php`

**Interfaces:**
- Produces: `procurations.grantor_cidadao_id`, `attorney_cidadao_id`, `revoked_by_cidadao_id` → `cidadaos.id`; `CurrentRepresentation::grantor(): ?Cidadao`; contexto de auditoria `acting_for_cidadao_id`.

- [ ] **Step 1: Teste (falha)**

```php
<?php

namespace Tests\Feature\Cidadao;

use App\Models\Cidadao;
use App\Models\Procuration;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RepresentacaoTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_procuracao_liga_dois_cidadaos(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $outorgante = Cidadao::factory()->create();
        $procurador = Cidadao::factory()->create();

        $procuracao = Procuration::factory()->create([
            'grantor_cidadao_id' => $outorgante->id,
            'attorney_cidadao_id' => $procurador->id,
        ]);

        $this->assertTrue($procuracao->grantor->is($outorgante));
        $this->assertTrue($procuracao->attorney->is($procurador));
    }
}
```

- [ ] **Step 2: Migration**

```php
<?php

use App\Support\Migration\IdentidadeSeparada;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        IdentidadeSeparada::retargetar('procurations', 'grantor_user_id', 'grantor_cidadao_id', false);
        IdentidadeSeparada::retargetar('procurations', 'attorney_user_id', 'attorney_cidadao_id', false);
        IdentidadeSeparada::retargetar('procurations', 'revoked_by_user_id', 'revoked_by_cidadao_id', true, 'set null');
    }

    public function down(): void
    {
        // Reversão consolidada na migration final (Tarefa 12).
    }
};
```

O índice composto `[grantor_user_id, attorney_user_id]` acompanha o renome no PostgreSQL; no SQLite o Laravel recria a tabela com o nome novo. Conferir com `Schema::getIndexes('procurations')` no teste se houver dúvida.

- [ ] **Step 3: Código**

- `Procuration`: `#[Fillable(['grantor_cidadao_id','attorney_cidadao_id','starts_at','expires_at','revoked_at','revoked_by_cidadao_id'])]`; `grantor()`, `attorney()`, `revokedBy()` com `belongsTo(Cidadao::class, '..._cidadao_id')`.
- `ProcurationController::destroy`: `'revoked_by_cidadao_id' => $request->user()->id`.
- `ResolveRepresentation`: `$procuration->attorney_cidadao_id !== $request->user()?->id`; `Context::add('acting_for_cidadao_id', $procuration->grantor_cidadao_id)`.
- `CurrentRepresentation::grantor(): ?Cidadao`; `attendance()`/`citizen()` fica para a Tarefa 9.
- `HandleInertiaRequests`: `actingFor` continua `->only('id', 'name')` do `Cidadao`.
- `ProcurationPolicy`, `StoreProcurationRequest`, factories: trocar tipos e nomes.

```bash
grep -rn "grantor_user_id\|attorney_user_id\|revoked_by_user_id\|acting_for_user_id" app database/factories database/seeders resources/js tests
```

Deve voltar vazio, exceto `acting_for_user_id` em `activity_log`, que é tratado na Tarefa 10.

- [ ] **Step 4: Rodar**

Run: `php artisan test --compact tests/Feature/Cidadao/RepresentacaoTest.php tests/Feature/Procuration`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A app database tests
git commit -m "feat: procurações e representação entre cidadãos"
```

---

### Task 8: Solicitações — requerente, consultas e declarações

**Files:**
- Create: `database/migrations/2026_10_16_000420_retarget_solicitacoes_consultas_declaracoes.php`
- Modify: `app/Models/{ViabilityRequest,ViabilityQuery,ServiceDeclarationAcceptance}.php`, `app/Policies/ViabilityRequestPolicy.php`, `app/Http/Controllers/Portal/{SolicitacaoController,ConsultaViabilidadeController,HistoricoConsultaController}.php`, `app/Http/Controllers/Gestao/{ProcessoBuscaController,EnviarParaAnaliseController,MalhaFinaCaixaController}.php`, `app/Console/Commands/{AlertarVencimentosCommand,SolicitacaoProtocolarCommand}.php`, `app/Listeners/{NotificarPendencia,NotificarResultadoExpresso}.php`, `app/Services/{Abuso/Detectors/CondicionanteEvasaoDetector,Abuso/Detectors/InscricaoAtividadesIncompativeisDetector,Analise/ProcessoQueryService,EscritorioVirtual/DesvincularInscricaoService,Painel/PainelCidadaoService,Renovacao/RenovacaoProtocoloService}.php`, `resources/js/pages/gestao/abuso/index.tsx`, factories e seeders de dev
- Test: `tests/Feature/Cidadao/SolicitacaoRequerenteTest.php`; diretórios: `tests/Feature/Solicitacao`, `tests/Feature/Renovacao`, `tests/Feature/Portal`, `tests/Feature/Dashboard`, `tests/Feature/Viabilidade`, `tests/Feature/Documentos`, `tests/Feature/Processo`

**Interfaces:**
- Produces: `viability_requests.requester_cidadao_id` (NOT NULL, → `cidadaos`), `viability_queries.cidadao_id` (nullable, set null), `service_declaration_acceptances.cidadao_id`; `ViabilityRequest::requester(): BelongsTo<Cidadao>`.

- [ ] **Step 1: Teste (falha)**

```php
<?php

namespace Tests\Feature\Cidadao;

use App\Models\Cidadao;
use App\Models\User;
use App\Models\ViabilityRequest;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SolicitacaoRequerenteTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_requerente_e_cidadao_e_criador_pode_ser_servidor(): void
    {
        $cidadao = Cidadao::factory()->create();
        $servidor = User::factory()->create();

        $solicitacao = ViabilityRequest::factory()->create([
            'requester_cidadao_id' => $cidadao->id,
            'created_by_type' => User::class,
            'created_by_id' => $servidor->id,
        ]);

        $this->assertTrue($solicitacao->requester->is($cidadao));
        $this->assertTrue($solicitacao->createdBy->is($servidor));
    }
}
```

- [ ] **Step 2: Migration**

```php
<?php

use App\Support\Migration\IdentidadeSeparada;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        IdentidadeSeparada::retargetar('viability_requests', 'requester_user_id', 'requester_cidadao_id', false, 'restrict');
        IdentidadeSeparada::retargetar('viability_queries', 'user_id', 'cidadao_id', true, 'set null');
        IdentidadeSeparada::retargetar('service_declaration_acceptances', 'user_id', 'cidadao_id', false);
    }

    public function down(): void
    {
        // Reversão consolidada na migration final (Tarefa 12).
    }
};
```

A migration original de `service_declaration_acceptances` e de `viability_requests` define o comportamento de exclusão; conferir com `grep -n "user" database/migrations/2026_09_28_170600_create_service_declaration_acceptances_table.php` e ajustar o quinto argumento se não for `cascade`.

- [ ] **Step 3: Código**

- `ViabilityRequest::requester()`: `belongsTo(Cidadao::class, 'requester_cidadao_id')`; `#[Fillable]` atualizado; escopos que filtram por `requester_user_id` passam a `requester_cidadao_id`.
- `ViabilityRequestPolicy`: métodos recebem `Cidadao|User $ator`. Cidadão vê a própria solicitação e a do outorgante da procuração ativa; servidor segue as permissões do Spatie. Separar pela classe:

```php
public function view(Cidadao|User $ator, ViabilityRequest $solicitacao): bool
{
    if ($ator instanceof User) {
        return $ator->can('consultar-processos');
    }

    return $solicitacao->requester_cidadao_id === $ator->id
        || app(CurrentRepresentation::class)->grantor()?->id === $solicitacao->requester_cidadao_id;
}
```

Conferir o nome da permissão real no `ViabilityRequestPolicy` atual e manter a regra existente do lado do servidor; o trecho acima só mostra a divisão por classe.

- `ProcessoQueryService`, detectores de abuso, comandos, listeners e `PainelCidadaoService`: `requester_user_id` → `requester_cidadao_id`; joins com `users` para nome do requerente passam a `cidadaos`.
- `resources/js/pages/gestao/abuso/index.tsx`: renomear o campo da resposta se ele expõe `requester_user_id`.
- `ReginProcessoProtocolador`: ver Tarefa 9.

```bash
grep -rn "requester_user_id\|->requester\b" app database/factories database/seeders resources/js tests | grep -v "requester_cidadao_id"
```

Deve voltar só chamadas à relação `->requester` (válidas).

- [ ] **Step 4: Rodar**

Run: `php artisan test --compact tests/Feature/Cidadao/SolicitacaoRequerenteTest.php tests/Feature/Solicitacao tests/Feature/Renovacao tests/Feature/Portal tests/Feature/Dashboard tests/Feature/Viabilidade tests/Feature/Documentos tests/Feature/Processo`
Expected: PASS, exceto o que depende do atendimento presencial e do REGIN (Tarefa 9).

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A app database resources tests
git commit -m "feat: solicitações, consultas e declarações apontam para cidadãos"
```

---

### Task 9: Atendimento presencial, contingência e recebimento REGIN

**Files:**
- Create: `database/migrations/2026_10_16_000430_retarget_atendimento_presencial.php`
- Modify: `app/Models/AssistedAttendance.php`, `app/Http/Controllers/Gestao/{AssistedAttendanceController,ContingenciaController}.php`, `app/Http/Middleware/ResolveAssistedAttendance.php`, `app/Support/Representation/CurrentRepresentation.php`, `app/Services/Regin/ReginProcessoProtocolador.php:87-88,134-135,229-268,1054-1060`, `database/factories/AssistedAttendanceFactory.php`
- Test: `tests/Feature/Cidadao/AtendimentoBalcaoTest.php`; diretórios: `tests/Feature/Regin`, `tests/Feature/Solicitacao/AtendimentoPresencialTest.php`, `ContingenciaTest.php`, `ConsultarCpfAtendimentoTest.php`

**Interfaces:**
- Produces: `assisted_attendances.citizen_cidadao_id` → `cidadaos`; `AssistedAttendance::citizen(): BelongsTo<Cidadao>`; `Cidadao::criarRegistroDeBalcao(string $cpf, string $nome, ?string $email, ?string $telefone): Cidadao`.
- Consumes: `Cidadao::semSenha()` semântica (Tarefa 2).

- [ ] **Step 1: Teste (falha)**

```php
<?php

namespace Tests\Feature\Cidadao;

use App\Models\Cidadao;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AtendimentoBalcaoTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_registro_de_balcao_nasce_sem_senha(): void
    {
        $cidadao = Cidadao::criarRegistroDeBalcao('52998224725', 'Maria da Silva', 'maria@example.com', '(71) 99999-1234');

        $this->assertFalse($cidadao->temSenha());
        $this->assertNull($cidadao->email_verified_at);
    }

    public function test_registro_de_balcao_reaproveita_o_cidadao_existente_pelo_cpf(): void
    {
        $existente = Cidadao::factory()->create(['cpf' => '52998224725']);

        $cidadao = Cidadao::criarRegistroDeBalcao('52998224725', 'Outro Nome', 'outro@example.com', null);

        $this->assertTrue($cidadao->is($existente));
        $this->assertSame($existente->name, $cidadao->fresh()->name);
    }

    public function test_email_em_uso_recebe_endereco_tecnico_unico(): void
    {
        Cidadao::factory()->create(['email' => 'repetido@example.com']);

        $cidadao = Cidadao::criarRegistroDeBalcao('52998224725', 'Maria', 'repetido@example.com', null);

        $this->assertSame('52998224725@solicitante.viabiliza.local', $cidadao->email);
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `php artisan test --compact tests/Feature/Cidadao/AtendimentoBalcaoTest.php`
Expected: FAIL com `Call to undefined method App\Models\Cidadao::criarRegistroDeBalcao()`.

- [ ] **Step 3: Implementar em `Cidadao`**

```php
    /**
     * Registro criado pelo servidor (atendimento presencial, contingência ou
     * recebimento da JUCEB). Sem senha: a pessoa assume o registro ao se
     * cadastrar no portal com o mesmo CPF. CPF já cadastrado é reaproveitado.
     */
    public static function criarRegistroDeBalcao(string $cpf, string $nome, ?string $email, ?string $telefone): self
    {
        $existente = self::query()->where('cpf', $cpf)->first();

        if ($existente !== null) {
            return $existente;
        }

        $emailEmUso = $email !== null && self::query()->where('email', $email)->exists();

        $cidadao = new self([
            'name' => $nome,
            'cpf' => $cpf,
            'email' => ($email === null || $emailEmUso) ? $cpf.'@solicitante.viabiliza.local' : $email,
            'phone' => $telefone,
        ]);
        $cidadao->save();

        return $cidadao;
    }
```

- [ ] **Step 4: Migration**

```php
<?php

use App\Support\Migration\IdentidadeSeparada;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        IdentidadeSeparada::retargetar('assisted_attendances', 'citizen_user_id', 'citizen_cidadao_id', false);
    }

    public function down(): void
    {
        // Reversão consolidada na migration final (Tarefa 12).
    }
};
```

- [ ] **Step 5: Código**

- `AssistedAttendance`: `#[Fillable(['attendant_user_id','citizen_cidadao_id', ...])]`; `citizen()` → `belongsTo(Cidadao::class, 'citizen_cidadao_id')`; `attendant()` segue `User`.
- `AssistedAttendanceController` e `ContingenciaController`: onde hoje criam ou buscam `User` por CPF, usar `Cidadao::criarRegistroDeBalcao(...)`; `created_by` grava o servidor (`created_by_type = User::class`).
- `ResolveAssistedAttendance`: `Context::add('acting_for_cidadao_id', $attendance->citizen_cidadao_id)`.
- `ReginProcessoProtocolador`:
  - `:229-268` (`resolverSolicitante`): trocar `User::query()` por `Cidadao::query()` e o `create` por `Cidadao::criarRegistroDeBalcao($cpf, $nome, $email, $telefone)`.
  - `:87-88` e `:134-135`: `requester_cidadao_id => $solicitante->id`; `created_by_type`/`created_by_id` recebem o ator de sistema (servidor).
  - `:229` e `:1054-1060`: o "ator" de fallback `User::query()->orderBy('id')->first()` continua sendo `User` (servidor) e passa a ir **só** em `created_by_*`; nunca em `requester_cidadao_id`.

- [ ] **Step 6: Rodar**

Run: `php artisan test --compact tests/Feature/Cidadao/AtendimentoBalcaoTest.php tests/Feature/Regin tests/Feature/Solicitacao/AtendimentoPresencialTest.php tests/Feature/Solicitacao/ContingenciaTest.php tests/Feature/Solicitacao/ConsultarCpfAtendimentoTest.php`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A app database tests
git commit -m "feat: atendimento presencial, contingência e REGIN criam registro de cidadão"
```

---

### Task 10: Notificações, auditoria e `acting_for`

**Files:**
- Create: `database/migrations/2026_10_16_000450_retipa_notificacoes_e_auditoria.php`
- Modify: `app/Models/Activity.php`, `app/Support/Audit/RecordActivityAction.php`, `app/Http/Controllers/NotificationCenterController.php`, `app/Services/Comunicacao/NotificationDispatcher.php`, `app/Support/Audit/AuditService.php`
- Test: `tests/Feature/Cidadao/NotificacoesAuditoriaTest.php`; diretórios: `tests/Feature/Audit`, `tests/Feature/Auditoria`, `tests/Feature/Comunicacao`, `tests/Feature/Dashboard`

**Interfaces:**
- Produces: `activity_log.acting_for_cidadao_id` (nullable, sem FK); `notifications.notifiable_type` e `activity_log.causer_type`/`subject_type` dos cidadãos migrados passam a `App\Models\Cidadao`.

- [ ] **Step 1: Teste (falha)**

```php
<?php

namespace Tests\Feature\Cidadao;

use App\Models\Cidadao;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NotificacoesAuditoriaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_notificacao_antiga_do_cidadao_passa_a_ser_dele(): void
    {
        $servidor = User::factory()->create();
        $cidadao = Cidadao::factory()->create(['id' => $servidor->id + 1000]);

        DB::table('notifications')->insert([
            ['id' => (string) \Illuminate\Support\Str::uuid(), 'type' => 'x', 'notifiable_type' => User::class, 'notifiable_id' => $cidadao->id, 'data' => '{}', 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) \Illuminate\Support\Str::uuid(), 'type' => 'x', 'notifiable_type' => User::class, 'notifiable_id' => $servidor->id, 'data' => '{}', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $migracao = require database_path('migrations/2026_10_16_000450_retipa_notificacoes_e_auditoria.php');
        $migracao->up();

        $this->assertSame(1, $cidadao->notifications()->count());
        $this->assertSame(1, $servidor->notifications()->count());
    }
}
```

O teste cria o servidor e o cidadão com ids diferentes (o do cidadão é `$servidor->id + 1000`) e espera que só as notificações do `id` presente em `cidadaos` com senha sejam retipadas. A notificação de `User` com o `id` do cidadão precisa existir no teste: ela representa a linha antiga de antes da migração.

- [ ] **Step 2: Migration**

```php
<?php

use App\Models\Cidadao;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $ids = fn () => DB::table('cidadaos')->whereNotNull('password')->select('id');

        DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->whereIn('notifiable_id', $ids())
            ->update(['notifiable_type' => Cidadao::class]);

        DB::table('activity_log')
            ->where('causer_type', User::class)
            ->whereIn('causer_id', $ids())
            ->update(['causer_type' => Cidadao::class]);

        DB::table('activity_log')
            ->where('subject_type', User::class)
            ->whereIn('subject_id', $ids())
            ->update(['subject_type' => Cidadao::class]);

        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropForeign(['acting_for_user_id']);
            $table->renameColumn('acting_for_user_id', 'acting_for_cidadao_id');
        });
    }

    public function down(): void
    {
        // Reversão consolidada na migration final (Tarefa 12).
    }
};
```

`whereNotNull('password')` distingue contas copiadas (têm senha) dos espelhos de servidor (sem senha), que **não** devem ser retipados.

- [ ] **Step 3: Código**

`Activity`/`RecordActivityAction`: coluna e chave de contexto `acting_for_cidadao_id`; `NotificationCenterController` e `NotificationDispatcher` usam `Cidadao` como destinatário do portal e `User` na gestão.

- [ ] **Step 4: Rodar**

Run: `php artisan test --compact tests/Feature/Cidadao/NotificacoesAuditoriaTest.php tests/Feature/Audit tests/Feature/Auditoria tests/Feature/Comunicacao tests/Feature/Dashboard`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A app database tests
git commit -m "feat: notificações e auditoria dos cidadãos passam ao novo tipo"
```

---

### Task 11: Cidadãos na gestão

**Files:**
- Create: `app/Http/Controllers/Gestao/CidadaoManagementController.php`
- Modify: `app/Http/Controllers/Gestao/UserManagementController.php:28-95`, `routes/gestao.php:304-308`, `app/Services/Relatorios/Export/Sources/UsuariosReportSource.php`, `resources/js/pages/gestao/usuarios/index.tsx`
- Test: `tests/Feature/Cidadao/GestaoCidadaosTest.php`; diretório `tests/Feature/Users`

**Interfaces:**
- Produces: aba `portal` de `/gestao/usuarios` lista `Cidadao`; rota `gestao.cidadaos.inativacao.update` (`PUT gestao/cidadaos/{cidadao}/inativacao`) → `CidadaoManagementController::toggleActivation`.

- [ ] **Step 1: Teste (falha)**

```php
<?php

namespace Tests\Feature\Cidadao;

use App\Models\Cidadao;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GestaoCidadaosTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_aba_portal_lista_cidadaos(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();
        Cidadao::factory()->create(['name' => 'Maria Cidadã']);

        $this->actingAs($admin, 'gestao')
            ->get('/gestao/usuarios?tab=portal')
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/usuarios/index')
                ->where('users.data.0.name', 'Maria Cidadã'));
    }

    public function test_inativar_cidadao(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();
        $cidadao = Cidadao::factory()->create();

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/cidadaos/{$cidadao->id}/inativacao")
            ->assertRedirect();

        $this->assertTrue($cidadao->fresh()->isInactive());
    }
}
```

Conferir se `User::factory()->withAcceptedLgpdTerm()` ainda existe no `UserFactory` com o par morph (ajustar `LegalTermAcceptance::create` para `accepted_by_type`/`accepted_by_id` — Tarefa 6).

- [ ] **Step 2: Rodar e ver falhar**

Run: `php artisan test --compact tests/Feature/Cidadao/GestaoCidadaosTest.php`
Expected: FAIL (aba `portal` ainda consulta `User::usuarioPortal()`; rota inexistente).

- [ ] **Step 3: Controller**

```php
<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Models\Cidadao;
use App\Support\Audit\AuditService;
use Illuminate\Http\RedirectResponse;

class CidadaoManagementController extends Controller
{
    /**
     * Alterna a situação da conta do cidadão. Auditoria explícita: `inactivated_at`
     * fica fora do fillable. Acesso a dado pessoal de terceiro (LGPD HU-102).
     */
    public function toggleActivation(Cidadao $cidadao): RedirectResponse
    {
        $reativando = $cidadao->isInactive();

        $cidadao->forceFill(['inactivated_at' => $reativando ? null : now()])->save();

        app(AuditService::class)->log(
            'usuarios',
            $reativando ? 'cidadao-reativado' : 'cidadao-inativado',
            'Conta de '.$cidadao->email.($reativando ? ' reativada' : ' inativada'),
            ['target_cidadao_id' => $cidadao->id],
            $cidadao,
            personalData: true,
        );

        return back()->with('status', 'Situação da conta atualizada.');
    }
}
```

Rota em `routes/gestao.php`, dentro do mesmo grupo `permission:manter-usuarios`:

```php
Route::put('cidadaos/{cidadao}/inativacao', [CidadaoManagementController::class, 'toggleActivation'])->name('cidadaos.inativacao.update');
```

- [ ] **Step 4: Aba `portal` no `UserManagementController::index`**

Para `tab === 'portal'`, consultar `Cidadao::query()` com a mesma busca e paginação, mapeando `roles => []`, `auth_type => 'local'`, `ad_username => null`; `counts.portal => Cidadao::query()->count()`; `counts.gestao => User::query()->equipeSedur()->count()`. A aba `gestao` continua em `User::equipeSedur()`. No front (`index.tsx`), o botão de inativar das linhas da aba `portal` envia para `gestao.cidadaos.inativacao.update`; papel e tipo de autenticação não aparecem nessa aba. `UsuariosReportSource` faz a mesma divisão por aba. Remover `User::scopeUsuarioPortal` depois que o `grep -rn "usuarioPortal" app` voltar vazio.

- [ ] **Step 5: Rodar**

Run: `php artisan test --compact tests/Feature/Cidadao/GestaoCidadaosTest.php tests/Feature/Users`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A app routes resources tests
git commit -m "feat: gestão lista e inativa contas de cidadão"
```

---

### Task 12: Migração final — remove cidadãos de `users` e fecha o portão

**Files:**
- Create: `database/migrations/2026_10_16_000900_remove_cidadaos_de_users.php`
- Modify: `database/seeders/RolesAndPermissionsSeeder.php`, `app/Support/PerfilCidadao.php` (remover), `app/Support/RoleProtection.php`, `database/seeders/ParameterSeeder.php` (parâmetro `portal.perfil_cidadao`), `tests/Feature/Parameters/ManageParametersTest.php`, `app/Models/User.php` (remover `scopeUsuarioPortal`, `govBrAccount`, `termAcceptances` de cidadão)
- Test: `tests/Feature/Cidadao/MigracaoFinalTest.php`, `tests/Feature/Cidadao/UsersForeignKeysTest.php` (Tarefa 1)

**Interfaces:**
- Consumes: todas as migrations anteriores.
- Produces: `users` só com servidores; papel `cidadao` removido.

- [ ] **Step 1: Teste da migração final (falha)**

```php
<?php

namespace Tests\Feature\Cidadao;

use App\Models\Cidadao;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MigracaoFinalTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_remove_de_users_so_as_contas_ja_copiadas_com_senha(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $copiado = User::factory()->create();
        $servidor = User::factory()->analista()->create();
        Cidadao::factory()->create(['id' => $copiado->id, 'cpf' => $copiado->cpf, 'email' => $copiado->email]);

        $migracao = require database_path('migrations/2026_10_16_000900_remove_cidadaos_de_users.php');
        $migracao->up();

        $this->assertDatabaseMissing('users', ['id' => $copiado->id]);
        $this->assertDatabaseHas('users', ['id' => $servidor->id]);
    }

    public function test_falha_com_mensagem_se_coluna_de_servidor_aponta_para_cidadao(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $usuario = User::factory()->create();
        Cidadao::factory()->create(['id' => $usuario->id, 'cpf' => $usuario->cpf, 'email' => $usuario->email]);
        DB::table('sector_user')->insert(['user_id' => $usuario->id, 'sector_id' => \App\Models\Sector::factory()->create()->id]);

        $this->expectExceptionMessage('sector_user.user_id');

        (require database_path('migrations/2026_10_16_000900_remove_cidadaos_de_users.php'))->up();
    }
}
```

- [ ] **Step 2: Migration**

```php
<?php

use App\Models\Cidadao;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Colunas de servidor: nenhuma pode apontar para uma conta que sai de `users`. */
    private const SERVIDOR = [
        ['viability_requests', 'assigned_user_id'], ['analysis_records', 'analyst_user_id'],
        ['viability_decisions', 'decided_by_user_id'], ['inspections', 'vistoriador_user_id'],
        ['assisted_attendances', 'attendant_user_id'], ['tvl_documents', 'generated_by_user_id'],
        ['indeferimento_documents', 'generated_by_user_id'], ['fine_mesh_referrals', 'referred_by_user_id'],
        ['fine_mesh_referrals', 'resolved_by_user_id'], ['inspection_referrals', 'encaminhado_por_user_id'],
        ['inspection_referrals', 'analista_origem_user_id'], ['inspection_attachments', 'uploaded_by_user_id'],
        ['analysis_pendencies', 'requested_by_user_id'], ['analysis_pendencies', 'cancelled_by_user_id'],
        ['analysis_status_transitions', 'actor_user_id'], ['access_requests', 'decided_by_user_id'],
        ['access_requests', 'user_id'], ['sector_user', 'user_id'], ['user_sector_assignments', 'user_id'],
        ['user_sector_assignments', 'changed_by_user_id'], ['rule_versions', 'created_by'],
        ['rule_versions', 'published_by'], ['parameter_proposals', 'created_by'],
        ['parameter_proposals', 'reviewed_by'], ['ai_suggestions', 'created_by_user_id'],
        ['abuse_alerts', 'resolved_by_user_id'], ['predictive_anomalies', 'resolved_by_user_id'],
        ['export_files', 'user_id'], ['passkeys', 'user_id'],
    ];

    public function up(): void
    {
        $copiados = fn () => DB::table('cidadaos')->whereNotNull('password')->select('id');

        foreach (self::SERVIDOR as [$tabela, $coluna]) {
            if (! Schema::hasTable($tabela)) {
                continue;
            }

            $conflitos = DB::table($tabela)->whereIn($coluna, $copiados())
                ->whereIn($coluna, DB::table('users')->select('id'))->count();

            if ($conflitos > 0) {
                throw new RuntimeException("Coluna de servidor {$tabela}.{$coluna} aponta para {$conflitos} conta(s) de cidadão; revisar antes de migrar.");
            }
        }

        DB::table('model_has_roles')->where('model_type', User::class)
            ->whereIn('model_id', $copiados())->delete();

        DB::table('users')->whereIn('id', $copiados())->delete();

        $roleId = DB::table('roles')->where('name', 'cidadao')->value('id');

        if ($roleId !== null) {
            DB::table('role_has_permissions')->where('role_id', $roleId)->delete();
            DB::table('model_has_roles')->where('role_id', $roleId)->delete();
            DB::table('roles')->where('id', $roleId)->delete();
        }
    }

    public function down(): void
    {
        DB::table('users')->insertUsing(
            ['id', 'name', 'cpf', 'email', 'phone', 'email_verified_at', 'password', 'two_factor_secret',
                'two_factor_recovery_codes', 'two_factor_confirmed_at', 'remember_token', 'inactivated_at',
                'created_at', 'updated_at'],
            DB::table('cidadaos')->whereNotNull('password')->select(
                'id', 'name', 'cpf', 'email', 'phone', 'email_verified_at', 'password', 'two_factor_secret',
                'two_factor_recovery_codes', 'two_factor_confirmed_at', 'remember_token', 'inactivated_at',
                'created_at', 'updated_at'),
        );
    }
};
```

O `down()` devolve as contas e não desfaz o retarget das colunas: a reversão completa só é suportada restaurando o backup do banco, e o ensaio da Tarefa 13 prova isso. Registrar essa limitação no corpo da migration como comentário PHPDoc.

- [ ] **Step 3: Remover o papel e o perfil**

- `RolesAndPermissionsSeeder`: tirar a criação do papel `cidadao`.
- `PerfilCidadao`, `RoleProtection` (referência ao papel), parâmetro `portal.perfil_cidadao` no `ParameterSeeder`, `ManageParametersTest`: remover, conferindo com `grep -rn "perfil_cidadao\|PerfilCidadao\|'cidadao'" app database tests config` até voltar vazio.
- `User`: remover `scopeUsuarioPortal` (se a Tarefa 11 ainda não removeu), `govBrAccount()` e a parte de `termAcceptances()` que atende cidadão.

- [ ] **Step 4: Rodar o portão e a migração final**

Run: `php artisan test --compact tests/Feature/Cidadao`
Expected: PASS, inclusive `UsersForeignKeysTest`, que estava vermelho desde a Tarefa 1.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A app database tests config
git commit -m "feat: remove contas de cidadão de users e o papel cidadao"
```

---

### Task 13: Varredura final e ensaio em cópia do banco

**Files:**
- Modify: `database/seeders/{DemonstracaoClienteSeeder,SolicitacaoDevSeeder,AnaliseDevSeeder,AuditoriaDevSeeder,ComunicacaoDevSeeder,ExpressoDevSeeder,RelatoriosDevSeeder,CompanySeeder,SectorSeeder}.php`, `resources/js/**` (tipos que citem `*_user_id` de cidadão)
- Nenhum arquivo novo.

- [ ] **Step 1: Varreduras que precisam voltar vazias**

```bash
grep -rn "requester_user_id\|grantor_user_id\|attorney_user_id\|citizen_user_id\|revoked_by_user_id\|recipient_user_id\|acting_for_user_id\|created_by_user_id\|cancelled_by_user_id\|uploaded_by_user_id" app database/factories database/seeders resources/js routes tests | grep -v "assigned_user_id"
grep -rn "assignRole('cidadao')\|->cidadao()\|User::factory()->withAcceptedLgpdTerm()" app database tests | grep -v "Gestao\|gestao"
```

Cada ocorrência restante é corrigida na tarefa de origem da coluna (consultar a coluna na tabela do spec).

- [ ] **Step 2: Suíte completa e formatação**

Run: `php artisan test --compact 2>&1 | tail -8` e `vendor/bin/pint --format agent`
Expected: mesma contagem de testes da linha de base da Tarefa 0 mais os testes novos, **sem falhas**; pulados iguais aos da linha de base.

- [ ] **Step 3: Frontend**

Run: `npm ci && npm run types && npm run build`
Expected: sem erro de tipo. `public/build` entra no commit (o Dockerfile não roda o build).

- [ ] **Step 4: Ensaio em cópia do banco**

No ambiente de homologação, em cópia do banco (nunca no banco vivo), executar:

```bash
php artisan migrate --force
php artisan tinker --execute '
echo "cidadaos: ".DB::table("cidadaos")->count()."\n";
echo "users: ".DB::table("users")->count()."\n";
echo "solicitacoes sem requerente: ".DB::table("viability_requests")->whereNotIn("requester_cidadao_id", DB::table("cidadaos")->select("id"))->count()."\n";
echo "empresas sem vinculo valido: ".DB::table("company_user")->whereNotIn("cidadao_id", DB::table("cidadaos")->select("id"))->count()."\n";
echo "access_logs sem tipo: ".DB::table("access_logs")->whereNotNull("actor_id")->whereNull("actor_type")->count()."\n";
'
```

Expected: contagens antes e depois conferem (`cidadaos` + `users` ≥ total anterior de `users`), e as três últimas linhas são `0`. Se a migration final recusar com `Coluna de servidor ...`, o dado precisa de decisão do usuário antes de seguir: não contornar.

- [ ] **Step 5: Login real na cópia**

Com um CPF de cidadão existente, entrar em `/portal/login` e abrir "Minhas solicitações" e "Minhas empresas"; com um usuário da retaguarda, entrar em `/gestao/login`. Registrar o resultado.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "chore: varredura final da separação entre cidadão e servidor"
```

Não publicar. Publicação segue a skill `publicar-softwell-semit` e começa por `homologa`, com o portal em modo manutenção durante a migração (`php artisan down` antes do `migrate`, `php artisan up` depois do teste de login). Antes de migrar em homologação e em produção, fazer backup do banco.

---

## Auto-revisão contra a spec

- **Cobertura:** tabela e model (T2), guards/providers/brokers (T3), três grupos de colunas (T5–T9 só cidadão; T6 mistas; só servidor ficam, protegidas pelo portão T1/T12), notificações e auditoria (T10), migração de dados (T5 cópia, T6/T8/T9 espelhos e tipos, T12 remoção), gestão (T11), testes e rollout (T13).
- **Pendências da spec resolvidas:** `revoked_by_user_id` é só de cidadão (T7); `ReginProcessoProtocolador` usa `criarRegistroDeBalcao` e o ator de fallback só vai em `created_by_*` (T9); contas de servidor com histórico de cidadão viram espelho sem senha (T5, helper `espelharServidor`). A contagem de contas AD com papel `cidadao` no banco real sai do pré-voo da T0, passo 3.
- **Tipos e nomes:** `criarRegistroDeBalcao`, `temSenha`, `termAcceptances` (MorphMany, base `accepted_by`), `IdentidadeSeparada::{retargetar,paraMorph,espelharOrfaos,espelharServidor,acertarSequencia}`, colunas `*_cidadao_id` e `{base}_type`/`{base}_id` aparecem com a mesma grafia em todas as tarefas.
- **Estado vermelho declarado:** a suíte completa fica vermelha entre o fim da T3 e o fim da T9; cada tarefa fecha com os diretórios de teste que ela devolve a verde, e a T13 exige a suíte inteira verde.

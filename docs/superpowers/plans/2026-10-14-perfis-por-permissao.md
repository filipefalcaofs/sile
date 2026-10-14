# Perfis governados pela tabela Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fazer a tabela `roles` ser a única fonte dos perfis: o código decide por permissão e não por nome de perfil, `analista` e `gestor` saem, e a análise de processos fica com Coordenador, Subcoordenador, Chefe de setor e Analista Terceirizado.

**Architecture:** Cinco pontos do código que hoje nomeiam perfil passam a usar permissão (`forcar-transicao-analise`, `receber-escalonamento-sla`), um parâmetro (`portal.perfil_cidadao`) ou uma coluna (`roles.protegido`). O seeder vira estado inicial aditivo e uma migração de dados remove `analista`/`gestor` reatribuindo as contas.

**Tech Stack:** Laravel 13, PHP 8.5, spatie/laravel-permission, PHPUnit 12, Inertia React (sem mudança de front).

**Spec:** `docs/superpowers/specs/2026-10-14-perfis-por-permissao-design.md`

## Global Constraints

- Testes em classes PHPUnit (`php artisan make:test --phpunit`), nunca Pest.
- Após alterar PHP: `vendor/bin/pint --dirty --format agent`.
- Rodar o teste alterado com `php artisan test --compact --filter=<nome>`; suíte completa só na Task 6.
- Chaves de parâmetro e nomes de permissão exatamente como na spec: `portal.perfil_cidadao`, `forcar-transicao-analise`, `receber-escalonamento-sla`, coluna `roles.protegido`.
- Perfis de análise: `Analista Terceirizado`, `Coordenador`, `Subcoordenador`, `Chefe de setor`.
- Commits sem coautoria nem menção a IA/ferramenta; autor é o `git config` do usuário. Comentários mínimos, só quando o motivo não é óbvio.
- O seeder continua **aditivo** (`firstOrCreate` + `givePermissionTo`); nunca `syncPermissions` nem apagar perfil.
- Os nomes dos states do `UserFactory` (`analista()`, `gestor()`) ficam, para não tocar ~450 usos; só mudam os perfis que atribuem.

## Review Focus

- Perfil do cidadão apontado pelo parâmetro não pode ser excluído nem renomeado, senão o autocadastro quebra (`RoleDoesNotExist`). Coberto na Task 4.
- Escalonamento sem nenhum perfil com `receber-escalonamento-sla` não notifica ninguém e deve registrar aviso no log. Coberto na Task 3.
- Usuário com `analista` **e** `gestor`, ou que já tem o perfil de destino, não pode ficar duplicado nem perder acesso na migração. Coberto na Task 5.
- Migração rodando duas vezes ou em banco sem os perfis antigos (testes, ambiente novo) deve ser no-op. Coberto na Task 5.
- `administrador` sem `analisar-processos` recebe 403 ao concluir análise e não perde as demais permissões. Coberto nas Tasks 2 e 5.

---

### Task 1: Coluna `protegido` no lugar de `Roles::STRUCTURAL`

**Files:**
- Create: `database/migrations/2026_10_14_090000_add_protegido_to_roles_table.php`
- Create: `app/Support/PerfilCidadao.php`
- Create: `app/Support/RoleProtection.php`
- Modify: `app/Http/Controllers/Gestao/RoleController.php` (imports, `index` linha 52, `update` linha 95, `destroy` linhas 117-121)
- Modify: `app/Http/Requests/Gestao/UpdateRoleRequest.php` (import e linha 58)
- Modify: `database/seeders/RolesAndPermissionsSeeder.php` (cidadao e administrador nascem protegidos)
- Test: `tests/Feature/Roles/ManageRolesTest.php`

**Interfaces:**
- Produces: `App\Support\PerfilCidadao::nome(): string` (lê `Settings::get('portal.perfil_cidadao', 'cidadao')`); `App\Support\RoleProtection::isProtected(Spatie\Permission\Models\Role $role): bool`; coluna `roles.protegido` boolean default `false`.

- [ ] **Step 1: Escrever os testes que falham**

Acrescentar em `tests/Feature/Roles/ManageRolesTest.php`, antes de `test_exclusao_bloqueada_com_usuarios_vinculados`:

```php
    public function test_perfil_protegido_nao_pode_ser_renomeado_nem_excluido(): void
    {
        $admin = $this->admin();
        $protegido = Role::create(['name' => 'auditoria-externa', 'guard_name' => 'web', 'protegido' => true]);

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/perfis/{$protegido->id}", ['name' => 'outro-nome'])
            ->assertSessionHasErrors('name');

        $this->actingAs($admin, 'gestao')
            ->delete("/gestao/perfis/{$protegido->id}")
            ->assertSessionHasErrors('role');

        $this->assertDatabaseHas('roles', ['name' => 'auditoria-externa', 'protegido' => true]);
    }

    public function test_perfil_comum_pode_ser_renomeado(): void
    {
        $admin = $this->admin();
        $comum = Role::create(['name' => 'perfil-comum', 'guard_name' => 'web']);

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/perfis/{$comum->id}", ['name' => 'perfil-renomeado', 'permissions' => []])
            ->assertRedirect();

        $this->assertSame('perfil-renomeado', $comum->fresh()->name);
    }

    public function test_listagem_marca_protegido_no_campo_structural(): void
    {
        $this->actingAs($this->admin(), 'gestao')
            ->get('/gestao/perfis')
            ->assertInertia(fn (Assert $page) => $page
                ->where('roles', fn ($roles) => collect($roles)->firstWhere('name', 'administrador')['structural'] === true
                    && collect($roles)->firstWhere('name', 'apoio')['structural'] === false));
    }
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `php artisan test --compact --filter=ManageRolesTest`
Expected: FAIL (coluna `protegido` inexistente).

- [ ] **Step 3: Implementar**

`database/migrations/2026_10_14_090000_add_protegido_to_roles_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('protegido')->default(false)->after('guard_name');
        });

        DB::table('roles')->whereIn('name', ['cidadao', 'administrador'])->update(['protegido' => true]);
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('protegido');
        });
    }
};
```

`app/Support/PerfilCidadao.php`:

```php
<?php

namespace App\Support;

final class PerfilCidadao
{
    /** Perfil atribuído no autocadastro e no login gov.br (parâmetro portal.perfil_cidadao). */
    public static function nome(): string
    {
        return (string) Settings::get('portal.perfil_cidadao', 'cidadao');
    }
}
```

`app/Support/RoleProtection.php`:

```php
<?php

namespace App\Support;

use Spatie\Permission\Models\Role;

final class RoleProtection
{
    public static function isProtected(Role $role): bool
    {
        return (bool) $role->protegido || $role->name === PerfilCidadao::nome();
    }
}
```

`RoleController`: trocar `use App\Support\Roles;` por `use App\Support\RoleProtection;`; em `index` a linha `'structural' => in_array($role->name, Roles::STRUCTURAL, true),` vira `'structural' => RoleProtection::isProtected($role),`; em `update` e `destroy` trocar `in_array($role->name, Roles::STRUCTURAL, true)` por `RoleProtection::isProtected($role)`; mensagem do `destroy` vira `__('Perfis protegidos do sistema não podem ser excluídos.')`. Ajustar o docblock de `index` ("O flag structural comunica à tela quais perfis são protegidos").

`UpdateRoleRequest`: trocar o import e a condição por `RoleProtection::isProtected($role) && $this->input('name') !== $role->name`; mensagem `__('Perfis protegidos do sistema não podem ser renomeados.')`.

`RolesAndPermissionsSeeder` (provisório; a Task 2 reescreve o arquivo): em `Role::firstOrCreate(['name' => 'cidadao', 'guard_name' => 'web'])` e `Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web'])` acrescentar o segundo argumento `['protegido' => true]`.

- [ ] **Step 4: Rodar e ver passar**

Run: `php artisan test --compact --filter=ManageRolesTest`
Expected: PASS. Se algum teste antigo assertar as mensagens "Papéis estruturais…", atualizar para o novo texto (`grep -rn "estruturais do sistema" tests`).

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_10_14_090000_add_protegido_to_roles_table.php app/Support/PerfilCidadao.php app/Support/RoleProtection.php app/Http/Controllers/Gestao/RoleController.php app/Http/Requests/Gestao/UpdateRoleRequest.php database/seeders/RolesAndPermissionsSeeder.php tests/Feature/Roles/ManageRolesTest.php
git commit -m "refactor: perfil protegido vira coluna da tabela de perfis"
```

---

### Task 2: Permissões novas, perfis de análise e seeder sem catálogo fechado

**Files:**
- Modify (reescrever): `database/seeders/RolesAndPermissionsSeeder.php`
- Modify: `app/Support/PermissionCatalog.php` (após a entrada `analisar-processos`)
- Modify: `database/factories/UserFactory.php` (states `analista()` e `gestor()`)
- Delete: `app/Support/Roles.php`
- Test: `tests/Feature/Authorization/RolesAndPermissionsSeederTest.php`, `tests/Feature/Seeders/DatabaseSeederTest.php` (linhas ~341-347), `tests/Feature/Seeders/DemonstracaoClienteSeederTest.php` (linhas ~84-91), `tests/Feature/Users/ManageUsersTest.php` (linhas ~183-204), `tests/Feature/Roles/ManageRolesTest.php` (linha 129 e a contagem `->has('roles', 21)` passa a 19)

**Interfaces:**
- Consumes: coluna `roles.protegido` (Task 1).
- Produces: permissões `forcar-transicao-analise` e `receber-escalonamento-sla`; perfis `Analista Terceirizado`, `Coordenador`, `Subcoordenador`, `Chefe de setor` com permissões de análise; `UserFactory::analista()` atribui `Analista Terceirizado` e `UserFactory::gestor()` atribui `Coordenador`.

- [ ] **Step 1: Escrever os testes que falham**

Em `RolesAndPermissionsSeederTest.php` acrescentar:

```php
    public function test_so_os_perfis_de_analise_concluem_processos(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (['Analista Terceirizado', 'Coordenador', 'Subcoordenador', 'Chefe de setor'] as $nome) {
            $this->assertTrue(Role::findByName($nome, 'web')->hasPermissionTo('analisar-processos'), $nome);
        }

        foreach (['administrador', 'apoio', 'cidadao'] as $nome) {
            $this->assertFalse(Role::findByName($nome, 'web')->hasPermissionTo('analisar-processos'), $nome);
        }
    }

    public function test_chefias_recebem_extras_e_analista_terceirizado_nao(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $extras = [
            'distribuir-processos',
            'analisar-malha-fina',
            'consultar-relatorios',
            'relatorios.produtividade.nominal',
            'forcar-transicao-analise',
            'receber-escalonamento-sla',
        ];

        foreach (['Coordenador', 'Subcoordenador', 'Chefe de setor'] as $nome) {
            foreach ($extras as $permissao) {
                $this->assertTrue(Role::findByName($nome, 'web')->hasPermissionTo($permissao), "{$nome} / {$permissao}");
            }
        }

        foreach ($extras as $permissao) {
            $this->assertFalse(Role::findByName('Analista Terceirizado', 'web')->hasPermissionTo($permissao), $permissao);
        }
    }

    public function test_analista_e_gestor_nao_existem_mais_e_protegidos_vem_marcados(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertDatabaseMissing('roles', ['name' => 'analista']);
        $this->assertDatabaseMissing('roles', ['name' => 'gestor']);
        $this->assertDatabaseHas('roles', ['name' => 'cidadao', 'protegido' => true]);
        $this->assertDatabaseHas('roles', ['name' => 'administrador', 'protegido' => true]);
        $this->assertDatabaseHas('roles', ['name' => 'apoio', 'protegido' => false]);
    }

    public function test_reseed_nao_apaga_perfil_criado_pela_interface(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Role::create(['name' => 'perfil-da-tela', 'guard_name' => 'web']);

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertDatabaseHas('roles', ['name' => 'perfil-da-tela']);
    }
```

Localizar `test_seeder_remove_perfil_fora_do_catalogo` (linha ~498) e **invertê-lo**: renomear para `test_seeder_preserva_perfil_fora_do_seed` e trocar a asserção final para `assertDatabaseHas`. Remover os testes que dependem de `Roles::LEGACY`/`Roles::catalog()` (`grep -n "Roles::" tests`).

- [ ] **Step 2: Rodar e ver falhar**

Run: `php artisan test --compact --filter=RolesAndPermissionsSeederTest`
Expected: FAIL (perfis de análise sem permissões; `analista` ainda existe).

- [ ] **Step 3: Implementar**

Reescrever `database/seeders/RolesAndPermissionsSeeder.php` por inteiro:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /** Conjunto de análise: o que cada perfil que trabalha processos precisa. */
    private const array PERMISSOES_ANALISE = [
        'acessar-gestao',
        'consultar-cnaes',
        'consultar-territorio',
        'consultar-louos',
        'consultar-solicitacoes',
        'analisar-processos',
        'reprocessar-processos',
        'encaminhar-malha-fina',
        'emitir-tvl',
        'enviar-tvl-analise',
        'preencher-ficha-vistoria',
    ];

    /** Acrescidas às chefias de análise. */
    private const array PERMISSOES_CHEFIA = [
        'distribuir-processos',
        'analisar-malha-fina',
        'consultar-relatorios',
        'relatorios.produtividade.nominal',
        'forcar-transicao-analise',
        'receber-escalonamento-sla',
    ];

    private const array PERFIS_ANALISE = ['Analista Terceirizado'];

    private const array PERFIS_CHEFIA = ['Coordenador', 'Subcoordenador', 'Chefe de setor'];

    /**
     * Perfis herdados do sistema antigo da SEDUR: nascem sem permissão e o
     * administrador configura cada um na tela de Perfis.
     */
    private const array PERFIS_SEM_PERMISSAO = [
        'Analista Servidor',
        'Analista Vistoriador',
        'Atendimento Sedur',
        'Diretor',
        'Fiscal',
        'Gerente',
        'GR',
        'Padrão',
        'Secretário',
        'SEFAZ',
        'Servidor',
        'Vistoriador',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'acessar-gestao',
            'consultar-acessos-de-qualquer-conta',
            'gerenciar-procuracoes-proprias',
            'manter-cnaes',
            'manter-gatilhos-risco',
            'manter-usuarios',
            'manter-perfis',
            'manter-parametros',
            'consultar-cnaes',
            'monitorar-emails',
            'consultar-territorio',
            'manter-territorio',
            'consultar-louos',
            'manter-louos',
            'registrar-contingencia',
            'atendimento-presencial',
            'consultar-solicitacoes',
            'manter-tipos-servico',
            'manter-tipos-imovel',
            'manter-requisitos-documentais',
            'analisar-processos',
            'distribuir-processos',
            'emitir-tvl',
            'encaminhar-malha-fina',
            'analisar-malha-fina',
            'reprocessar-processos',
            'enviar-tvl-analise',
            'preencher-ficha-vistoria',
            'manter-setores',
            'consultar-auditoria',
            'monitorar-lgpd',
            'gerenciar-alertas-abuso',
            'manter-config-email',
            'manter-config-ia',
            'consultar-relatorios',
            'relatorios.produtividade.nominal',
            'forcar-transicao-analise',
            'receber-escalonamento-sla',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Atribuição aditiva (givePermissionTo, nunca sync): re-seed em produção
        // não pode remover permissões ajustadas pelo administrador via interface (HU-013).
        $this->perfil('cidadao', protegido: true)
            ->givePermissionTo(['gerenciar-procuracoes-proprias']);

        foreach (self::PERFIS_ANALISE as $nome) {
            $this->perfil($nome)->givePermissionTo(self::PERMISSOES_ANALISE);
        }

        foreach (self::PERFIS_CHEFIA as $nome) {
            $this->perfil($nome)->givePermissionTo([...self::PERMISSOES_ANALISE, ...self::PERMISSOES_CHEFIA]);
        }

        // Apoio (tramitação): distribui os processos da caixa do setor para um
        // analista específico, sem analisar — a tramitação é dele, a análise não.
        $this->perfil('apoio')->givePermissionTo([
            'acessar-gestao',
            'consultar-cnaes',
            'consultar-territorio',
            'consultar-louos',
            'consultar-solicitacoes',
            'distribuir-processos',
        ]);

        $this->perfil('administrador', protegido: true)->givePermissionTo([
            'acessar-gestao',
            'consultar-acessos-de-qualquer-conta',
            'manter-cnaes',
            'manter-gatilhos-risco',
            'manter-usuarios',
            'manter-perfis',
            'manter-parametros',
            'consultar-cnaes',
            'monitorar-emails',
            'consultar-territorio',
            'manter-territorio',
            'consultar-louos',
            'manter-louos',
            'registrar-contingencia',
            'atendimento-presencial',
            'consultar-solicitacoes',
            'manter-tipos-servico',
            'manter-tipos-imovel',
            'manter-requisitos-documentais',
            'reprocessar-processos',
            'distribuir-processos',
            'emitir-tvl',
            'encaminhar-malha-fina',
            'analisar-malha-fina',
            'enviar-tvl-analise',
            'preencher-ficha-vistoria',
            'manter-setores',
            'consultar-auditoria',
            'monitorar-lgpd',
            'gerenciar-alertas-abuso',
            'manter-config-email',
            'manter-config-ia',
            'consultar-relatorios',
            'relatorios.produtividade.nominal',
        ]);

        foreach (self::PERFIS_SEM_PERMISSAO as $nome) {
            $this->perfil($nome);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function perfil(string $nome, bool $protegido = false): Role
    {
        $role = Role::firstOrCreate(['name' => $nome, 'guard_name' => 'web'], ['protegido' => $protegido]);

        if ($protegido && ! $role->protegido) {
            $role->forceFill(['protegido' => true])->save();
        }

        return $role;
    }
}
```

`PermissionCatalog.php`: logo após a entrada de `analisar-processos`, inserir:

```php
            [
                'name' => 'forcar-transicao-analise',
                'label' => 'Forçar transição da situação da análise',
                'description' => 'Muda a situação da análise para qualquer estado permitido pela máquina de estados, mesmo fora das opções manuais.',
                'group' => 'Análise técnica',
            ],
            [
                'name' => 'receber-escalonamento-sla',
                'label' => 'Receber escalonamentos de prazo e alertas de TLL',
                'description' => 'Recebe os avisos de processo com prazo de análise vencido e de exercício sem tabela TLL vigente.',
                'group' => 'Análise técnica',
            ],
```

`UserFactory.php`: `analista()` passa a `assignRole('Analista Terceirizado')` (docblock "Assign the Analista Terceirizado role") e `gestor()` a `assignRole('Coordenador')` (docblock "Assign the Coordenador role, a chefia de análise"). Apagar `app/Support/Roles.php`.

Demais testes: aplicar o mapeamento `analista` → `Analista Terceirizado` e `gestor` → `Coordenador` nas asserções de `RolesAndPermissionsSeederTest`, `DatabaseSeederTest`, `DemonstracaoClienteSeederTest`, `ManageUsersTest` e `ManageRolesTest`. Se uma asserção exigir que o antigo `gestor` **tenha** `registrar-contingencia`, `atendimento-presencial`, `manter-tipos-servico`, `manter-tipos-imovel`, `manter-gatilhos-risco` ou `manter-requisitos-documentais`, trocar o perfil verificado por `administrador`. Asserções de que `gestor`/`analista` **não** têm uma permissão continuam valendo com os novos nomes. Contagem de perfis em `ManageRolesTest`: 21 → 19.

- [ ] **Step 4: Rodar e ver passar**

Run: `php artisan test --compact tests/Feature/Authorization/RolesAndPermissionsSeederTest.php tests/Feature/Seeders/DatabaseSeederTest.php tests/Feature/Seeders/DemonstracaoClienteSeederTest.php tests/Feature/Users/ManageUsersTest.php tests/Feature/Roles/ManageRolesTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/seeders/RolesAndPermissionsSeeder.php app/Support/PermissionCatalog.php database/factories/UserFactory.php tests
git rm app/Support/Roles.php
git commit -m "feat: perfis de análise da SEDUR com permissões próprias e seeder aditivo"
```

---

### Task 3: `force` e escalonamentos por permissão

**Files:**
- Create: `app/Services/Comunicacao/DestinatariosEscalonamento.php`
- Modify: `app/Http/Controllers/Gestao/ProcessoController.php` (docblock ~linha 260 e linha 273)
- Modify: `app/Console/Commands/EscalonarSlaCommand.php` (docblock, linha 49, chamada e método `destinatarios` na linha 112)
- Modify: `app/Console/Commands/AlertarExercicioTllCommand.php` (linhas 47-51)
- Modify: `config/sile.php` (remover linha 273)
- Test: `tests/Feature/Analise/AnalysisStatusEndpointTest.php`, `tests/Feature/Comunicacao/EscalonarSlaCommandTest.php`, `tests/Feature/Analise/AlertarExercicioTllCommandTest.php`, `tests/Feature/Seeders/ParameterSeederTest.php` (linhas 628-629)

**Interfaces:**
- Consumes: permissões `forcar-transicao-analise` e `receber-escalonamento-sla` (Task 2).
- Produces: `App\Services\Comunicacao\DestinatariosEscalonamento::usuarios(): Illuminate\Database\Eloquent\Collection<int, User>`, ordenado por id; registra `Log::warning` quando vazio.

- [ ] **Step 1: Escrever os testes que falham**

Em `AnalysisStatusEndpointTest.php`, trocar o `gestor` do teste `test_gestor_com_force_ignora_subconjunto_manual` (linhas 111-115) por usuário com permissão direta, sem depender de perfil:

```php
    public function test_quem_tem_permissao_de_forcar_ignora_subconjunto_manual(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $papel = Role::create(['name' => 'perfil-livre', 'guard_name' => 'web']);
        $papel->givePermissionTo(['acessar-gestao', 'analisar-processos', 'forcar-transicao-analise']);
        $usuario = User::factory()->create();
        $usuario->assignRole('perfil-livre');
```

e usar `$usuario` no restante (`actingAs($usuario, 'gestao')`); adicionar `use Spatie\Permission\Models\Role;`. Renomear `test_force_de_nao_gestor_e_ignorado` para `test_force_sem_a_permissao_e_ignorado` (o corpo, com `User::factory()->analista()`, continua válido: Analista Terceirizado não tem a permissão).

Em `EscalonarSlaCommandTest.php` acrescentar:

```php
    public function test_escalonamento_segue_a_permissao_e_nao_o_nome_do_perfil(): void
    {
        Role::create(['name' => 'perfil-livre', 'guard_name' => 'web'])
            ->givePermissionTo('receber-escalonamento-sla');
        $destinatario = User::factory()->create();
        $destinatario->assignRole('perfil-livre');
        $naoDestinatario = User::factory()->analista()->create();

        $usuarios = app(DestinatariosEscalonamento::class)->usuarios();

        $this->assertTrue($usuarios->contains('id', $destinatario->id));
        $this->assertFalse($usuarios->contains('id', $naoDestinatario->id));
    }
```


Teste do cenário "ninguém tem a permissão" (mesmo arquivo):

```php
    public function test_sem_destinatario_registra_aviso_no_log(): void
    {
        Role::query()->get()->each(fn (Role $role) => $role->revokePermissionTo('receber-escalonamento-sla'));
        Log::spy();

        $usuarios = app(DestinatariosEscalonamento::class)->usuarios();

        $this->assertTrue($usuarios->isEmpty());
        Log::shouldHaveReceived('warning')->once();
    }
```

Imports: `App\Services\Comunicacao\DestinatariosEscalonamento`, `Illuminate\Support\Facades\Log`, `Spatie\Permission\Models\Role`. O `setUp` do arquivo já semeia os perfis.

Em `ParameterSeederTest.php` (linhas 628-629) remover as duas linhas que referenciam `notificacoes.escalonamento.gestor_role` e a de `config(...)` que espera `'gestor'`; no lugar:

```php
        $this->assertNull(config('sile.notificacoes.escalonamento.gestor_role'));
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `php artisan test --compact --filter="AnalysisStatusEndpointTest|EscalonarSlaCommandTest|AlertarExercicioTllCommandTest|ParameterSeederTest"`
Expected: FAIL (classe `DestinatariosEscalonamento` inexistente; `force` ainda por nome).

- [ ] **Step 3: Implementar**

`app/Services/Comunicacao/DestinatariosEscalonamento.php`:

```php
<?php

namespace App\Services\Comunicacao;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Quem recebe escalonamento de prazo e alerta de TLL: todo usuário cujo perfil
 * tem a permissão receber-escalonamento-sla (o administrador define o perfil).
 */
class DestinatariosEscalonamento
{
    public const string PERMISSAO = 'receber-escalonamento-sla';

    /**
     * @return Collection<int, User>
     */
    public function usuarios(): Collection
    {
        $usuarios = User::query()
            ->whereHas('roles.permissions', fn ($query) => $query->where('name', self::PERMISSAO))
            ->orderBy('id')
            ->get();

        if ($usuarios->isEmpty()) {
            Log::warning('Escalonamento sem destinatário: nenhum perfil tem a permissão '.self::PERMISSAO.'.');
        }

        return $usuarios;
    }
}
```

`ProcessoController` linha 273: `$force = $request->boolean('force') && $request->user()->can('forcar-transicao-analise');`; no docblock trocar "A guarda é ignorada quando o usuário é gestor E pede o override" por "…quando o usuário tem forcar-transicao-analise E pede o override".

`EscalonarSlaCommand`: injetar `DestinatariosEscalonamento $destinatarios` em `handle(NotificationDispatcher $dispatcher, AnalysisSlaService $sla, DestinatariosEscalonamento $destinatariosEscalonamento)`; remover a linha `$gestorRole = ...`; a chamada vira `$this->destinatarios($tratamentoFaixa, $processo, $destinatariosEscalonamento)`; o método vira:

```php
    private function destinatarios(string $tratamento, ViabilityRequest $processo, DestinatariosEscalonamento $escalonamento): array
    {
        return match ($tratamento) {
            'notificar_analista' => array_values(array_filter([$processo->assignedTo])),
            'notificar_gestor' => $escalonamento->usuarios()->all(),
            default => [],
        };
    }
```

Atualizar o docblock da classe: "escala a quem tem a permissão receber-escalonamento-sla".

`AlertarExercicioTllCommand`: `handle(AuditService $audit, DestinatariosEscalonamento $escalonamento)`; trocar as linhas 47-51 por `$gestores = $escalonamento->usuarios();`; remover `use App\Models\User;` se ficar sem uso; importar `App\Services\Comunicacao\DestinatariosEscalonamento`.

`config/sile.php`: remover a linha `'gestor_role' => 'gestor',`.

- [ ] **Step 4: Rodar e ver passar**

Run: `php artisan test --compact --filter="AnalysisStatusEndpointTest|EscalonarSlaCommandTest|AlertarExercicioTllCommandTest|ParameterSeederTest"`
Expected: PASS (os testes existentes com `User::factory()->gestor()` passam porque `Coordenador` tem a permissão).

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Comunicacao/DestinatariosEscalonamento.php app/Http/Controllers/Gestao/ProcessoController.php app/Console/Commands config/sile.php tests
git commit -m "refactor: force e escalonamentos passam a depender de permissão"
```

---

### Task 4: Perfil do cidadão vem do parâmetro

**Files:**
- Modify: `database/seeders/ParameterSeeder.php` (catálogo, junto dos parâmetros `seguranca.*`)
- Modify: `app/Actions/Fortify/CreateNewUser.php` (linha 48)
- Modify: `app/Services/GovBr/GovBrAuthService.php` (linha 198)
- Test: `tests/Feature/Auth/RegistrationTest.php`, `tests/Feature/Auth/GovBrLoginTest.php`, `tests/Feature/Seeders/ParameterSeederTest.php`, `tests/Feature/Roles/ManageRolesTest.php`

**Interfaces:**
- Consumes: `PerfilCidadao::nome()` e `RoleProtection::isProtected()` (Task 1).
- Produces: parâmetro `portal.perfil_cidadao` (grupo `seguranca`, tipo `string`, padrão `cidadao`, regra `exists:roles,name`).

- [ ] **Step 1: Escrever os testes que falham**

`RegistrationTest.php`:

```php
    public function test_cadastro_usa_o_perfil_definido_no_parametro(): void
    {
        Role::create(['name' => 'municipe', 'guard_name' => 'web']);
        config(['sile.portal.perfil_cidadao' => 'municipe']);

        $this->post('/portal/register', $this->validPayload());

        $user = User::firstWhere('email', 'maria@example.com');
        $this->assertTrue($user->hasRole('municipe'));
        $this->assertFalse($user->hasRole('cidadao'));
    }
```

(importar `Spatie\Permission\Models\Role`). Em `GovBrLoginTest.php`, o teste `test_callback_cria_conta_real_no_primeiro_acesso` já cobre o padrão `cidadao`; acrescentar um irmão:

```php
    public function test_callback_usa_o_perfil_definido_no_parametro(): void
    {
        Role::create(['name' => 'municipe', 'guard_name' => 'web']);
        config(['sile.portal.perfil_cidadao' => 'municipe']);
        $this->enableGovBr();
        $this->mockSocialiteUser($this->govBrClaims());

        $this->get('/portal/login/govbr/callback?code=abc&state=xyz');

        $this->assertTrue(User::query()->where('cpf', self::VALID_CPF)->first()->hasRole('municipe'));
    }
```

`ParameterSeederTest.php`:

```php
    public function test_parametro_do_perfil_do_cidadao(): void
    {
        $this->seed(ParameterSeeder::class);

        $parametro = Parameter::query()->where('key', 'portal.perfil_cidadao')->first();

        $this->assertNotNull($parametro);
        $this->assertSame('seguranca', $parametro->group);
        $this->assertSame('string', $parametro->type);
        $this->assertSame('cidadao', $parametro->typedValue());
        $this->assertContains('exists:roles,name', $parametro->validation_rules);
    }
```

`ManageRolesTest.php`:

```php
    public function test_perfil_apontado_pelo_parametro_do_cidadao_nao_pode_ser_excluido_nem_renomeado(): void
    {
        $admin = $this->admin();
        $municipe = Role::create(['name' => 'municipe', 'guard_name' => 'web']);
        config(['sile.portal.perfil_cidadao' => 'municipe']);

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/perfis/{$municipe->id}", ['name' => 'outro'])
            ->assertSessionHasErrors('name');

        $this->actingAs($admin, 'gestao')
            ->delete("/gestao/perfis/{$municipe->id}")
            ->assertSessionHasErrors('role');
    }
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `php artisan test --compact --filter="RegistrationTest|GovBrLoginTest|ParameterSeederTest|ManageRolesTest"`
Expected: FAIL nos novos (o código ainda atribui `cidadao`; parâmetro inexistente).

- [ ] **Step 3: Implementar**

`ParameterSeeder::catalog()`, após `security.login.max_attempts`:

```php
            'portal.perfil_cidadao' => [
                'group' => 'seguranca',
                'type' => 'string',
                'default_value' => 'cidadao',
                'validation_rules' => ['required', 'string', 'exists:roles,name'],
                'description' => 'Perfil atribuído no autocadastro e no login gov.br do cidadão',
            ],
```

`CreateNewUser` e `GovBrAuthService`: `assignRole('cidadao')` → `assignRole(PerfilCidadao::nome())`, com `use App\Support\PerfilCidadao;`.

- [ ] **Step 4: Rodar e ver passar**

Run: `php artisan test --compact --filter="RegistrationTest|GovBrLoginTest|ParameterSeederTest|ManageRolesTest"`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/seeders/ParameterSeeder.php app/Actions/Fortify/CreateNewUser.php app/Services/GovBr/GovBrAuthService.php tests
git commit -m "feat: perfil do cidadão vem do parâmetro portal.perfil_cidadao"
```

---

### Task 5: Migração de dados (remove `analista` e `gestor`)

**Files:**
- Create: `database/migrations/2026_10_14_100000_remove_perfis_analista_e_gestor.php`
- Test: `tests/Feature/Authorization/RemovePerfisAnalistaGestorMigrationTest.php`

**Interfaces:**
- Consumes: `RolesAndPermissionsSeeder` (Task 2), coluna `protegido` (Task 1).
- Produces: banco sem os perfis `analista`/`gestor`; contas reatribuídas; `administrador` sem `analisar-processos`.

- [ ] **Step 1: Escrever o teste que falha**

`php artisan make:test --phpunit Authorization/RemovePerfisAnalistaGestorMigrationTest`, conteúdo:

```php
<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RemovePerfisAnalistaGestorMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function executar(): void
    {
        $migration = require database_path('migrations/2026_10_14_100000_remove_perfis_analista_e_gestor.php');
        $migration->up();
    }

    private function cenarioLegado(): void
    {
        Permission::create(['name' => 'analisar-processos', 'guard_name' => 'web']);
        Role::create(['name' => 'analista', 'guard_name' => 'web'])->givePermissionTo('analisar-processos');
        Role::create(['name' => 'gestor', 'guard_name' => 'web'])->givePermissionTo('analisar-processos');
        Role::create(['name' => 'administrador', 'guard_name' => 'web', 'protegido' => true])
            ->givePermissionTo('analisar-processos');
    }

    public function test_reatribui_contas_e_remove_os_perfis_antigos(): void
    {
        $this->cenarioLegado();
        $analista = User::factory()->create();
        $analista->assignRole('analista');
        $gestor = User::factory()->create();
        $gestor->assignRole('gestor');

        $this->executar();

        $this->assertTrue($analista->fresh()->hasRole('Analista Terceirizado'));
        $this->assertTrue($gestor->fresh()->hasRole('Coordenador'));
        $this->assertDatabaseMissing('roles', ['name' => 'analista']);
        $this->assertDatabaseMissing('roles', ['name' => 'gestor']);
    }

    public function test_conta_com_os_dois_perfis_ou_ja_com_destino_nao_duplica(): void
    {
        $this->cenarioLegado();
        $ambos = User::factory()->create();
        $ambos->assignRole(['analista', 'gestor']);
        $jaDestino = User::factory()->create();
        $jaDestino->assignRole(['gestor', 'Coordenador']);

        $this->executar();

        $this->assertEqualsCanonicalizing(['Analista Terceirizado', 'Coordenador'], $ambos->fresh()->getRoleNames()->all());
        $this->assertSame(['Coordenador'], $jaDestino->fresh()->getRoleNames()->all());
    }

    public function test_administrador_perde_so_analisar_processos_e_chefias_ganham_as_permissoes(): void
    {
        $this->cenarioLegado();
        Role::findByName('administrador', 'web')->givePermissionTo(
            Permission::create(['name' => 'manter-usuarios', 'guard_name' => 'web'])
        );

        $this->executar();

        $administrador = Role::findByName('administrador', 'web');
        $this->assertFalse($administrador->hasPermissionTo('analisar-processos'));
        $this->assertTrue($administrador->hasPermissionTo('manter-usuarios'));

        foreach (['Coordenador', 'Subcoordenador', 'Chefe de setor', 'Analista Terceirizado'] as $nome) {
            $this->assertTrue(Role::findByName($nome, 'web')->hasPermissionTo('analisar-processos'), $nome);
        }
        $this->assertTrue(Role::findByName('Coordenador', 'web')->hasPermissionTo('forcar-transicao-analise'));
    }

    public function test_sem_perfis_antigos_nao_faz_nada_e_rodar_duas_vezes_e_seguro(): void
    {
        $antes = Role::query()->count();

        $this->executar();
        $this->assertSame($antes, Role::query()->count());

        $this->cenarioLegado();
        $this->executar();
        $this->executar();

        $this->assertDatabaseMissing('roles', ['name' => 'analista']);
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `php artisan test --compact --filter=RemovePerfisAnalistaGestorMigrationTest`
Expected: FAIL (arquivo de migração inexistente).

- [ ] **Step 3: Implementar**

`database/migrations/2026_10_14_100000_remove_perfis_analista_e_gestor.php`:

```php
<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const array DESTINO = [
        'analista' => 'Analista Terceirizado',
        'gestor' => 'Coordenador',
    ];

    public function up(): void
    {
        $antigos = Role::query()->whereIn('name', array_keys(self::DESTINO))->get();

        // Banco novo (testes, ambiente limpo): nada a migrar.
        if ($antigos->isEmpty()) {
            return;
        }

        // Cria os perfis de destino e concede as permissões da análise antes de mover as contas.
        app(RolesAndPermissionsSeeder::class)->run();

        DB::transaction(function () use ($antigos): void {
            foreach ($antigos as $antigo) {
                $destino = Role::findByName(self::DESTINO[$antigo->name], 'web');

                $vinculos = DB::table('model_has_roles')->where('role_id', $antigo->id)->get();

                foreach ($vinculos as $vinculo) {
                    DB::table('model_has_roles')->insertOrIgnore([
                        'role_id' => $destino->id,
                        'model_type' => $vinculo->model_type,
                        'model_id' => $vinculo->model_id,
                    ]);
                }

                DB::table('model_has_roles')->where('role_id', $antigo->id)->delete();
                $antigo->delete();
            }

            $administrador = Role::query()->where('name', 'administrador')->where('guard_name', 'web')->first();
            $analisar = Permission::query()->where('name', 'analisar-processos')->where('guard_name', 'web')->first();

            if ($administrador !== null && $analisar !== null) {
                $administrador->revokePermissionTo($analisar);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Irreversível: as contas já foram reatribuídas aos perfis de análise da SEDUR.
    }
};
```

- [ ] **Step 4: Rodar e ver passar**

Run: `php artisan test --compact --filter=RemovePerfisAnalistaGestorMigrationTest`
Expected: PASS. Se `insertOrIgnore` não ignorar por falta de chave única, usar `DB::table('model_has_roles')->where(...)->exists()` antes de inserir.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_10_14_100000_remove_perfis_analista_e_gestor.php tests/Feature/Authorization/RemovePerfisAnalistaGestorMigrationTest.php
git commit -m "feat: migração remove os perfis analista e gestor e reatribui as contas"
```

---

### Task 6: Seeders de desenvolvimento, suíte completa e administrador sem análise

**Files:**
- Modify: `database/seeders/AnaliseDevSeeder.php`, `AuditoriaDevSeeder.php`, `ComunicacaoDevSeeder.php`, `CompanySeeder.php`, `SectorSeeder.php` (somente onde atribuem `analista`/`gestor` por nome: `grep -nE "assignRole|syncRoles|'analista'|'gestor'" database/seeders`)
- Modify: demais testes que a suíte apontar
- Test: `tests/Feature/Analise/AdministradorNaoConcluiAnaliseTest.php`

**Interfaces:**
- Consumes: tudo das Tasks 1-5.

- [ ] **Step 1: Escrever o teste que falha**

`php artisan make:test --phpunit Analise/AdministradorNaoConcluiAnaliseTest`:

```php
<?php

namespace Tests\Feature\Analise;

use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AdministradorNaoConcluiAnaliseTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_administrador_recebe_403_ao_concluir_analise(): void
    {
        $administrador = User::factory()->administrador()->withAcceptedLgpdTerm()->create();
        $processo = ViabilityRequest::factory()->create();

        $this->actingAs($administrador, 'gestao')
            ->post("/gestao/processos/{$processo->id}/ficha/concluir-processo", ['resultado' => 'deferido'])
            ->assertForbidden();
    }
}
```

- [ ] **Step 2: Rodar e ver falhar/passar**

Run: `php artisan test --compact --filter=AdministradorNaoConcluiAnaliseTest`
Expected: PASS (a rota `processos.ficha.concluir-processo` está no grupo `permission:analisar-processos` e o seeder da Task 2 já retirou a permissão do administrador). Se retornar 404, conferir a URL com `php artisan route:list --name=ficha.concluir-processo`.

- [ ] **Step 3: Atualizar seeders de desenvolvimento**

Nos seeders listados, trocar `assignRole('analista')` por `assignRole('Analista Terceirizado')` e `assignRole('gestor')` por `assignRole('Coordenador')` (ou usar os states do factory).

- [ ] **Step 4: Rodar a suíte completa e triar**

Run: `php artisan test --compact`
Expected: PASS. Falhas esperadas e tratamento:
- teste usa `->gestor()` e exige uma das seis permissões que só o `gestor` tinha (`registrar-contingencia`, `atendimento-presencial`, `manter-tipos-servico`, `manter-tipos-imovel`, `manter-gatilhos-risco`, `manter-requisitos-documentais`): trocar para `->administrador()`;
- teste usa `->administrador()` para analisar/concluir processo: trocar para `->analista()` ou `->gestor()`;
- teste ainda cita `hasRole('analista')`/`findByName('analista')`: aplicar o mapeamento da Task 2.
Reportar a lista de arquivos ajustados.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/seeders tests
git commit -m "test: seeders e testes passam a usar os perfis de análise da SEDUR"
```

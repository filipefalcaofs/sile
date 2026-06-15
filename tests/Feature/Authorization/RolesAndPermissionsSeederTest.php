<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesAndPermissionsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_cria_papeis_e_permissoes_da_fase(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (['cidadao', 'analista', 'gestor', 'administrador'] as $role) {
            $this->assertSame($role, Role::findByName($role, 'web')->name);
        }

        $permissions = [
            'acessar-gestao',
            'consultar-acessos-de-qualquer-conta',
            'gerenciar-procuracoes-proprias',
            'manter-cnaes',
            'manter-usuarios',
            'manter-perfis',
            'manter-parametros',
            'consultar-cnaes',
            'consultar-risco',
            'manter-risco',
            'consultar-louos',
            'manter-louos',
            'registrar-contingencia',
            'atendimento-presencial',
            'consultar-solicitacoes',
            'manter-tipos-servico',
            'manter-requisitos-documentais',
        ];

        foreach ($permissions as $permission) {
            $this->assertSame($permission, Permission::findByName($permission, 'web')->name);
        }
    }

    public function test_atribuicoes_de_permissao_por_papel(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $administrador = Role::findByName('administrador', 'web');
        $this->assertTrue($administrador->hasPermissionTo('acessar-gestao'));
        $this->assertTrue($administrador->hasPermissionTo('consultar-acessos-de-qualquer-conta'));

        foreach (['manter-cnaes', 'manter-usuarios', 'manter-perfis', 'manter-parametros', 'consultar-cnaes'] as $permission) {
            $this->assertTrue($administrador->hasPermissionTo($permission));
        }

        $analista = Role::findByName('analista', 'web');
        $this->assertTrue($analista->hasPermissionTo('acessar-gestao'));
        $this->assertTrue($analista->hasPermissionTo('consultar-cnaes'));
        $this->assertFalse($analista->hasPermissionTo('manter-cnaes'));

        $gestor = Role::findByName('gestor', 'web');
        $this->assertTrue($gestor->hasPermissionTo('acessar-gestao'));
        $this->assertTrue($gestor->hasPermissionTo('consultar-cnaes'));
        $this->assertFalse($gestor->hasPermissionTo('manter-cnaes'));

        $cidadao = Role::findByName('cidadao', 'web');
        $this->assertTrue($cidadao->hasPermissionTo('gerenciar-procuracoes-proprias'));
        $this->assertFalse($cidadao->hasPermissionTo('acessar-gestao'));
        $this->assertFalse($cidadao->hasPermissionTo('consultar-cnaes'));
    }

    public function test_papeis_recebem_permissao_consultar_territorio(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(
            'consultar-territorio',
            Permission::findByName('consultar-territorio', 'web')->name,
        );

        foreach (['administrador', 'analista', 'gestor'] as $role) {
            $this->assertTrue(
                Role::findByName($role, 'web')->hasPermissionTo('consultar-territorio'),
            );
        }

        $this->assertFalse(
            Role::findByName('cidadao', 'web')->hasPermissionTo('consultar-territorio'),
        );
    }

    public function test_papeis_recebem_permissoes_de_risco(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (['consultar-risco', 'manter-risco'] as $permission) {
            $this->assertSame(
                $permission,
                Permission::findByName($permission, 'web')->name,
            );
        }

        // Consulta da tabela de risco: analista, gestor e administrador (espelha CNAEs).
        foreach (['administrador', 'analista', 'gestor'] as $role) {
            $this->assertTrue(
                Role::findByName($role, 'web')->hasPermissionTo('consultar-risco'),
            );
        }

        // Manutenção (publicação versionada e condicionantes): só o administrador.
        $this->assertTrue(Role::findByName('administrador', 'web')->hasPermissionTo('manter-risco'));
        $this->assertFalse(Role::findByName('analista', 'web')->hasPermissionTo('manter-risco'));
        $this->assertFalse(Role::findByName('gestor', 'web')->hasPermissionTo('manter-risco'));
        $this->assertFalse(Role::findByName('cidadao', 'web')->hasPermissionTo('consultar-risco'));
    }

    public function test_papeis_recebem_permissoes_de_louos(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (['consultar-louos', 'manter-louos'] as $permission) {
            $this->assertSame(
                $permission,
                Permission::findByName($permission, 'web')->name,
            );
        }

        // Consulta dos Quadros vigentes: analista, gestor e administrador (espelha CNAEs/risco).
        foreach (['administrador', 'analista', 'gestor'] as $role) {
            $this->assertTrue(
                Role::findByName($role, 'web')->hasPermissionTo('consultar-louos'),
            );
        }

        // Manutenção (publicação versionada dos Quadros): só o administrador.
        $this->assertTrue(Role::findByName('administrador', 'web')->hasPermissionTo('manter-louos'));
        $this->assertFalse(Role::findByName('analista', 'web')->hasPermissionTo('manter-louos'));
        $this->assertFalse(Role::findByName('gestor', 'web')->hasPermissionTo('manter-louos'));
        $this->assertFalse(Role::findByName('cidadao', 'web')->hasPermissionTo('consultar-louos'));
    }

    public function test_papeis_recebem_permissoes_de_solicitacao(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $solicitacao = [
            'registrar-contingencia',
            'atendimento-presencial',
            'consultar-solicitacoes',
            'manter-tipos-servico',
            'manter-requisitos-documentais',
        ];

        foreach ($solicitacao as $permission) {
            $this->assertSame(
                $permission,
                Permission::findByName($permission, 'web')->name,
            );
        }

        // Administrador parametriza tudo; gestor opera e parametriza os cadastros da fase.
        foreach (['administrador', 'gestor'] as $role) {
            foreach ($solicitacao as $permission) {
                $this->assertTrue(
                    Role::findByName($role, 'web')->hasPermissionTo($permission),
                );
            }
        }

        // Analista só consulta os processos no backoffice (não registra contingência).
        $analista = Role::findByName('analista', 'web');
        $this->assertTrue($analista->hasPermissionTo('consultar-solicitacoes'));
        $this->assertFalse($analista->hasPermissionTo('registrar-contingencia'));

        // Cidadão opera as próprias solicitações por policy, sem permissão nomeada.
        $this->assertFalse(
            Role::findByName('cidadao', 'web')->hasPermissionTo('consultar-solicitacoes'),
        );
    }

    public function test_estados_da_factory_atribuem_papel(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertTrue(User::factory()->cidadao()->create()->hasRole('cidadao'));
        $this->assertTrue(User::factory()->analista()->create()->hasRole('analista'));
        $this->assertTrue(User::factory()->gestor()->create()->hasRole('gestor'));
        $this->assertTrue(User::factory()->administrador()->create()->hasRole('administrador'));
    }

    public function test_papeis_recebem_permissoes_da_analise_tecnica(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $novas = [
            'analisar-processos',
            'distribuir-processos',
            'emitir-tvl',
            'encaminhar-malha-fina',
            'manter-setores',
        ];

        foreach ($novas as $permission) {
            $this->assertSame(
                $permission,
                Permission::findByName($permission, 'web')->name,
            );
        }

        // Analista analisa, provoca malha fina e emite o TVL interno; não
        // distribui processos nem administra setores (atribuições do gestor).
        $analista = Role::findByName('analista', 'web');
        foreach (['analisar-processos', 'encaminhar-malha-fina', 'emitir-tvl'] as $permission) {
            $this->assertTrue($analista->hasPermissionTo($permission));
        }
        $this->assertFalse($analista->hasPermissionTo('distribuir-processos'));
        $this->assertFalse($analista->hasPermissionTo('manter-setores'));

        // Gestor distribui, administra setores e também analisa/emite TVL.
        $gestor = Role::findByName('gestor', 'web');
        foreach ($novas as $permission) {
            $this->assertTrue($gestor->hasPermissionTo($permission));
        }

        // Administrador recebe as cinco (administra tudo).
        $administrador = Role::findByName('administrador', 'web');
        foreach ($novas as $permission) {
            $this->assertTrue($administrador->hasPermissionTo($permission));
        }

        // Cidadão não recebe nenhuma permissão de backoffice da análise.
        $cidadao = Role::findByName('cidadao', 'web');
        foreach ($novas as $permission) {
            $this->assertFalse($cidadao->hasPermissionTo($permission));
        }
    }

    public function test_papeis_recebem_permissoes_de_auditoria_e_compliance(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $novas = ['consultar-auditoria', 'monitorar-lgpd', 'gerenciar-alertas-abuso'];

        foreach ($novas as $permission) {
            $this->assertSame(
                $permission,
                Permission::findByName($permission, 'web')->name,
            );
        }

        // Consultar a trilha (HU-097..101) e gerenciar alertas de abuso (HU-149):
        // gestor e administrador.
        foreach (['gestor', 'administrador'] as $role) {
            $this->assertTrue(Role::findByName($role, 'web')->hasPermissionTo('consultar-auditoria'));
            $this->assertTrue(Role::findByName($role, 'web')->hasPermissionTo('gerenciar-alertas-abuso'));
        }

        // Painel LGPD (HU-102; DPO/admin): só o administrador.
        $this->assertTrue(Role::findByName('administrador', 'web')->hasPermissionTo('monitorar-lgpd'));
        $this->assertFalse(Role::findByName('gestor', 'web')->hasPermissionTo('monitorar-lgpd'));

        // Analista NÃO recebe nenhuma das três (default gestor/admin; sem papel
        // auditor dedicado — decisão CONTEXT).
        $analista = Role::findByName('analista', 'web');
        foreach ($novas as $permission) {
            $this->assertFalse($analista->hasPermissionTo($permission));
        }

        // Cidadão tampouco recebe qualquer permissão de auditoria/compliance.
        $cidadao = Role::findByName('cidadao', 'web');
        foreach ($novas as $permission) {
            $this->assertFalse($cidadao->hasPermissionTo($permission));
        }
    }

    public function test_seeder_e_idempotente(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(4, Role::query()->count());
        $this->assertSame(27, Permission::query()->count());
    }

    public function test_seeder_aditivo_preserva_ajustes_feitos_pela_interface(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        Role::findByName('analista', 'web')->givePermissionTo(
            Permission::firstOrCreate(['name' => 'permissao-extra-ui', 'guard_name' => 'web']),
        );

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertTrue(Role::findByName('analista', 'web')->hasPermissionTo('permissao-extra-ui'));
    }
}

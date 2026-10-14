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
        Role::create(['name' => 'Coordenador', 'guard_name' => 'web']);
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

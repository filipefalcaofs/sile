<?php

namespace Tests\Feature\Cidadao;

use App\Models\User;
use App\Support\Migration\IdentidadeSeparada;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MigracaoCopiaCidadaosTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_copia_so_contas_que_nao_sao_de_servidor_preservando_o_id(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $cidadao = User::factory()->create();
        $comPapel = User::factory()->papelCidadaoLegado()->create();
        $analista = User::factory()->analista()->create();
        $ad = User::factory()->ad()->create();

        $migracao = require database_path('migrations/2026_10_16_000300_copia_cidadaos_de_users.php');
        $migracao->up();

        $this->assertDatabaseHas('cidadaos', ['id' => $cidadao->id, 'cpf' => $cidadao->cpf]);
        $this->assertDatabaseHas('cidadaos', ['id' => $comPapel->id, 'cpf' => $comPapel->cpf]);
        $this->assertDatabaseMissing('cidadaos', ['id' => $analista->id]);
        $this->assertDatabaseMissing('cidadaos', ['id' => $ad->id]);
        $this->assertSame($cidadao->password, DB::table('cidadaos')->where('id', $cidadao->id)->value('password'));
    }

    public function test_servidor_so_com_perfil_herdado_sem_permissao_nao_e_copiado(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $fiscal = User::factory()->create();
        $fiscal->assignRole('Fiscal');
        $fiscalComPapelCidadao = User::factory()->papelCidadaoLegado()->create();
        $fiscalComPapelCidadao->assignRole('Fiscal');

        IdentidadeSeparada::copiarContasDeCidadao();

        $this->assertSame(0, DB::table('cidadaos')->count());
    }

    public function test_acessar_gestao_como_permissao_direta_marca_servidor(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $servidor = User::factory()->create();
        $servidor->givePermissionTo('acessar-gestao');

        IdentidadeSeparada::copiarContasDeCidadao();

        $this->assertDatabaseMissing('cidadaos', ['id' => $servidor->id]);
    }

    public function test_perfil_do_parametro_portal_perfil_cidadao_conta_como_perfil_de_cidadao(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        DB::table('parameters')->updateOrInsert(['key' => 'portal.perfil_cidadao'], [
            'group' => 'seguranca', 'type' => 'string', 'value' => 'Contribuinte', 'default_value' => 'cidadao',
            'validation_rules' => '[]', 'description' => 'Perfil do cidadão',
        ]);
        $contribuinte = User::factory()->create();
        $contribuinte->assignRole(Role::findOrCreate('Contribuinte', 'web'));
        $comPapelCidadao = User::factory()->papelCidadaoLegado()->create();
        $semPerfil = User::factory()->create();
        $fiscal = User::factory()->create();
        $fiscal->assignRole('Fiscal');

        IdentidadeSeparada::copiarContasDeCidadao();

        $this->assertSame(
            [$contribuinte->id, $comPapelCidadao->id, $semPerfil->id],
            DB::table('cidadaos')->orderBy('id')->pluck('id')->all(),
        );
    }

    public function test_sem_parametro_vale_o_default_value_do_perfil(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        DB::table('parameters')->updateOrInsert(['key' => 'portal.perfil_cidadao'], [
            'group' => 'seguranca', 'type' => 'string', 'value' => null, 'default_value' => 'Contribuinte',
            'validation_rules' => '[]', 'description' => 'Perfil do cidadão',
        ]);
        $contribuinte = User::factory()->create();
        $contribuinte->assignRole(Role::findOrCreate('Contribuinte', 'web'));

        IdentidadeSeparada::copiarContasDeCidadao();

        $this->assertDatabaseHas('cidadaos', ['id' => $contribuinte->id]);
    }

    public function test_espelho_de_servidor_nasce_sem_senha_e_com_o_mesmo_id(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $servidor = User::factory()->analista()->create();

        IdentidadeSeparada::espelharServidor($servidor->id);

        $this->assertDatabaseHas('cidadaos', ['id' => $servidor->id, 'password' => null]);
    }

    public function test_acertar_sequencia_nao_falha_fora_do_postgres(): void
    {
        // Só verificável em PostgreSQL real (setval); aqui basta não falhar.
        $this->expectNotToPerformAssertions();

        IdentidadeSeparada::acertarSequencia();
    }

    public function test_down_da_migration_de_copia_nao_apaga_cidadaos(): void
    {
        $cidadao = User::factory()->create();
        IdentidadeSeparada::copiarContasDeCidadao();

        $migracao = require database_path('migrations/2026_10_16_000300_copia_cidadaos_de_users.php');
        $migracao->down();

        $this->assertDatabaseHas('cidadaos', ['id' => $cidadao->id]);
    }
}

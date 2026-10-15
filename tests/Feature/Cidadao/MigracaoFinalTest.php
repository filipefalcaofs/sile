<?php

namespace Tests\Feature\Cidadao;

use App\Models\Cidadao;
use App\Models\Sector;
use App\Models\User;
use App\Support\Migration\IdentidadeSeparada;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MigracaoFinalTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const MIGRATION = 'migrations/2026_10_16_000900_remove_cidadaos_de_users.php';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_remove_de_users_so_as_contas_ja_copiadas_com_senha(): void
    {
        $copiado = User::factory()->papelCidadaoLegado()->create();
        $copiado->givePermissionTo('gerenciar-procuracoes-proprias');
        $naoCopiado = User::factory()->create();
        $servidor = User::factory()->analista()->create();
        $servidorAd = User::factory()->ad()->papelCidadaoLegado()->create();
        $servidorComEspelho = User::factory()->analista()->create();
        $servidorQueAssumiuEspelho = User::factory()->analista()->create();

        IdentidadeSeparada::copiarContasDeCidadao();
        DB::table('cidadaos')->where('id', $naoCopiado->id)->delete();
        IdentidadeSeparada::espelharServidor($servidorComEspelho->id);
        IdentidadeSeparada::espelharServidor($servidorQueAssumiuEspelho->id);
        DB::table('cidadaos')->where('id', $servidorQueAssumiuEspelho->id)->update(['password' => 'hash-do-portal']);

        $this->rodarMigracao();

        $this->assertSame(
            [$naoCopiado->id, $servidor->id, $servidorAd->id, $servidorComEspelho->id, $servidorQueAssumiuEspelho->id],
            DB::table('users')->orderBy('id')->pluck('id')->all(),
        );
        $this->assertSame(
            [$copiado->id, $servidorComEspelho->id, $servidorQueAssumiuEspelho->id],
            DB::table('cidadaos')->orderBy('id')->pluck('id')->all(),
        );
        $this->assertNull(DB::table('cidadaos')->where('id', $servidorComEspelho->id)->value('password'));
        $this->assertSame(0, DB::table('model_has_roles')->where('model_id', $copiado->id)->count());
        $this->assertSame(0, DB::table('model_has_permissions')->where('model_id', $copiado->id)->count());
        $this->assertTrue($servidor->fresh()->hasRole('Analista Terceirizado'));
        $this->assertSame([], $servidorAd->fresh()->getRoleNames()->all());
    }

    public function test_remove_o_papel_cidadao_e_suas_permissoes(): void
    {
        User::factory()->papelCidadaoLegado()->create();
        $papel = DB::table('roles')->where('name', 'cidadao')->value('id');
        DB::table('role_has_permissions')->insert([
            'role_id' => $papel,
            'permission_id' => Permission::findByName('gerenciar-procuracoes-proprias', 'web')->id,
        ]);

        $this->rodarMigracao();

        $this->assertSame([], DB::table('roles')->where('name', 'cidadao')->pluck('id')->all());
        $this->assertSame(0, DB::table('role_has_permissions')->where('role_id', $papel)->count());
        $this->assertSame(0, DB::table('model_has_roles')->where('role_id', $papel)->count());
        $this->assertTrue(DB::table('roles')->where('name', 'administrador')->exists());
    }

    public function test_falha_com_mensagem_se_coluna_de_servidor_aponta_para_cidadao(): void
    {
        $usuario = User::factory()->create();
        IdentidadeSeparada::copiarContasDeCidadao();
        DB::table('sector_user')->insert(['user_id' => $usuario->id, 'sector_id' => Sector::factory()->create()->id]);

        try {
            $this->rodarMigracao();
            $this->fail('A migration deveria recusar a remoção.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('sector_user.user_id (1)', $e->getMessage());
        }

        $this->assertSame([$usuario->id], DB::table('users')->pluck('id')->all());
    }

    public function test_conflito_e_detectado_em_qualquer_chave_para_users(): void
    {
        $usuario = User::factory()->create();
        $outro = User::factory()->create();
        IdentidadeSeparada::copiarContasDeCidadao();
        DB::table('regin_simulacao_execucoes')->insert([
            ['codigo' => 'a', 'relatorio' => '{}', 'user_id' => $usuario->id],
            ['codigo' => 'b', 'relatorio' => '{}', 'user_id' => $outro->id],
        ]);
        DB::table('passkeys')->insert([
            'user_id' => $usuario->id, 'name' => 'chave', 'credential_id' => 'c1', 'credential' => '{}',
        ]);

        try {
            $this->rodarMigracao();
            $this->fail('A migration deveria recusar a remoção.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('regin_simulacao_execucoes.user_id (2)', $e->getMessage());
            $this->assertStringContainsString('passkeys.user_id (1)', $e->getMessage());
        }

        $this->assertSame(2, DB::table('users')->count());
    }

    public function test_cidadao_novo_com_id_de_servidor_nao_remove_o_servidor(): void
    {
        $servidor = User::factory()->analista()->create();
        Cidadao::factory()->create(['id' => $servidor->id]);

        $this->rodarMigracao();

        $this->assertTrue(DB::table('users')->where('id', $servidor->id)->exists());
    }

    public function test_down_nao_altera_nada(): void
    {
        $copiado = User::factory()->create();
        IdentidadeSeparada::copiarContasDeCidadao();
        $migracao = require database_path(self::MIGRATION);
        $migracao->up();

        $migracao->down();

        $this->assertFalse(DB::table('users')->where('id', $copiado->id)->exists());
        $this->assertTrue(DB::table('cidadaos')->where('id', $copiado->id)->exists());
    }

    private function rodarMigracao(): void
    {
        (require database_path(self::MIGRATION))->up();
    }
}

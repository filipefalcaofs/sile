<?php

namespace Tests\Feature\Cidadao;

use App\Models\AiSuggestion;
use App\Models\Cidadao;
use App\Models\Sector;
use App\Models\User;
use App\Support\Migration\IdentidadeSeparada;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
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

    public function test_servidor_so_com_perfil_herdado_sem_permissao_nao_e_apagado(): void
    {
        $fiscal = User::factory()->create();
        $fiscal->assignRole('Fiscal');
        DB::table('sector_user')->insert(['user_id' => $fiscal->id, 'sector_id' => Sector::factory()->create()->id]);
        $diretoComGestao = User::factory()->create();
        $diretoComGestao->givePermissionTo('acessar-gestao');

        IdentidadeSeparada::copiarContasDeCidadao();
        $this->rodarMigracao();

        $this->assertSame([$fiscal->id, $diretoComGestao->id], DB::table('users')->orderBy('id')->pluck('id')->all());
        $this->assertSame(0, DB::table('cidadaos')->count());
        $this->assertTrue($fiscal->fresh()->hasRole('Fiscal'));
        $this->assertTrue($diretoComGestao->fresh()->hasDirectPermission('acessar-gestao'));
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
        $usuario = User::factory()->papelCidadaoLegado()->create();
        IdentidadeSeparada::copiarContasDeCidadao();
        DB::table('sector_user')->insert(['user_id' => $usuario->id, 'sector_id' => Sector::factory()->create()->id]);

        try {
            $this->rodarMigracao();
            $this->fail('A migration deveria recusar a remoção.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('sector_user.user_id (1)', $e->getMessage());
        }

        $this->assertSame([$usuario->id], DB::table('users')->pluck('id')->all());
        $this->assertNadaFoiApagado($usuario);
    }

    public function test_referencia_de_cidadao_copiado_em_coluna_set_null_e_anulada(): void
    {
        $cidadao = User::factory()->papelCidadaoLegado()->create();
        $servidor = User::factory()->analista()->create();
        IdentidadeSeparada::copiarContasDeCidadao();
        $doCidadao = AiSuggestion::factory()->create(['created_by_user_id' => $cidadao->id]);
        $doServidor = AiSuggestion::factory()->create(['created_by_user_id' => $servidor->id]);

        $this->rodarMigracao();

        $this->assertNull($doCidadao->fresh()->created_by_user_id);
        $this->assertSame($servidor->id, $doServidor->fresh()->created_by_user_id);
        $this->assertFalse(DB::table('users')->where('id', $cidadao->id)->exists());
    }

    public function test_conflito_sem_set_null_aborta_sem_anular_as_demais_colunas(): void
    {
        $cidadao = User::factory()->papelCidadaoLegado()->create();
        IdentidadeSeparada::copiarContasDeCidadao();
        $sugestao = AiSuggestion::factory()->create(['created_by_user_id' => $cidadao->id]);
        DB::table('sector_user')->insert(['user_id' => $cidadao->id, 'sector_id' => Sector::factory()->create()->id]);

        try {
            $this->rodarMigracao();
            $this->fail('A migration deveria recusar a remoção.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('sector_user.user_id (1)', $e->getMessage());
        }

        $this->assertSame($cidadao->id, $sugestao->fresh()->created_by_user_id);
    }

    public function test_conflito_e_detectado_em_qualquer_chave_para_users(): void
    {
        $usuario = User::factory()->papelCidadaoLegado()->create();
        $outro = User::factory()->create();
        IdentidadeSeparada::copiarContasDeCidadao();
        DB::table('passkeys')->insert([
            ['user_id' => $usuario->id, 'name' => 'chave', 'credential_id' => 'c1', 'credential' => '{}'],
            ['user_id' => $outro->id, 'name' => 'outra', 'credential_id' => 'c2', 'credential' => '{}'],
        ]);
        DB::table('sector_user')->insert(['user_id' => $usuario->id, 'sector_id' => Sector::factory()->create()->id]);

        try {
            $this->rodarMigracao();
            $this->fail('A migration deveria recusar a remoção.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('passkeys.user_id (2)', $e->getMessage());
            $this->assertStringContainsString('sector_user.user_id (1)', $e->getMessage());
        }

        $this->assertSame(2, DB::table('users')->count());
        $this->assertNadaFoiApagado($usuario);
    }

    public function test_cidadao_novo_com_id_de_servidor_nao_remove_o_servidor(): void
    {
        $servidor = User::factory()->analista()->create();
        Cidadao::factory()->create(['id' => $servidor->id]);

        $this->rodarMigracao();

        $this->assertTrue(DB::table('users')->where('id', $servidor->id)->exists());
    }

    public function test_conta_com_mesmo_id_mas_outro_cpf_em_cidadaos_nao_e_copia_e_fica_em_users(): void
    {
        $usuario = User::factory()->papelCidadaoLegado()->create();
        $usuario->givePermissionTo('gerenciar-procuracoes-proprias');
        Cidadao::factory()->create(['id' => $usuario->id, 'cpf' => '52998224725']);
        $copia = User::factory()->papelCidadaoLegado()->create();
        Cidadao::factory()->create(['id' => $copia->id, 'cpf' => $copia->cpf, 'email' => $copia->email]);

        $this->rodarMigracao();

        $this->assertSame([$usuario->id], DB::table('users')->pluck('id')->all());
        $this->assertSame(1, DB::table('model_has_permissions')->where('model_type', User::class)->where('model_id', $usuario->id)->count());
        $this->assertSame(0, DB::table('model_has_permissions')->where('model_id', $copia->id)->count());
        $this->assertTrue(DB::table('cidadaos')->where('id', $copia->id)->where('cpf', $copia->cpf)->exists());
    }

    public function test_conta_com_mesmo_id_mas_outro_cpf_mantem_os_papeis(): void
    {
        $usuario = User::factory()->create();
        $papel = Role::findOrCreate('perfil-legado', 'web');
        $usuario->assignRole($papel);
        Cidadao::factory()->create(['id' => $usuario->id, 'cpf' => '52998224725']);

        $this->rodarMigracao();

        $this->assertTrue(DB::table('users')->where('id', $usuario->id)->exists());
        $this->assertTrue(DB::table('model_has_roles')
            ->where('model_type', User::class)->where('model_id', $usuario->id)->where('role_id', $papel->id)->exists());
    }

    public function test_apaga_tokens_de_redefinicao_das_contas_copiadas(): void
    {
        $copiado = User::factory()->papelCidadaoLegado()->create();
        $servidor = User::factory()->analista()->create();
        IdentidadeSeparada::copiarContasDeCidadao();
        DB::table('password_reset_tokens')->insert([
            ['email' => $copiado->email, 'token' => 'a', 'created_at' => now()],
            ['email' => $servidor->email, 'token' => 'b', 'created_at' => now()],
        ]);

        $this->rodarMigracao();

        $this->assertSame([$servidor->email], DB::table('password_reset_tokens')->pluck('email')->all());
    }

    public function test_solicitante_do_regin_antigo_fica_sem_senha_e_conta_normal_mantem(): void
    {
        $solicitante = User::factory()->create(['email' => '52998224725'.Cidadao::SUFIXO_EMAIL_TECNICO]);
        $normal = User::factory()->papelCidadaoLegado()->create();
        IdentidadeSeparada::copiarContasDeCidadao();

        $this->rodarMigracao();

        $this->assertFalse(DB::table('users')->where('id', $solicitante->id)->exists());
        $this->assertNull(DB::table('cidadaos')->where('id', $solicitante->id)->value('password'));
        $this->assertNotNull(DB::table('cidadaos')->where('id', $normal->id)->value('password'));
        $this->assertFalse(DB::table('users')->where('id', $normal->id)->exists());
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

    /** A checagem de conflito vem antes de qualquer delete: vínculos e o papel `cidadao` seguem intactos. */
    private function assertNadaFoiApagado(User $usuario): void
    {
        $papel = DB::table('roles')->where('name', 'cidadao')->value('id');

        $this->assertNotNull($papel);
        $this->assertTrue(DB::table('model_has_roles')
            ->where('model_type', User::class)->where('model_id', $usuario->id)->where('role_id', $papel)->exists());
    }

    private function rodarMigracao(): void
    {
        (require database_path(self::MIGRATION))->up();
    }
}

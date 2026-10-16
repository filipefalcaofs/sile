<?php

namespace Tests\Feature\Cidadao;

use App\Models\Sector;
use App\Models\User;
use App\Support\Migration\IdentidadeSeparada;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class MigracaoPreflightTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const MIGRATION = 'migrations/2026_10_16_000050_preflight_separacao_cidadao_servidor.php';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_banco_vazio_passa(): void
    {
        $this->rodarMigracao();

        $this->assertSame(0, DB::table('users')->count());
    }

    public function test_sem_conflito_passa(): void
    {
        $servidor = User::factory()->analista()->create();
        $fiscal = User::factory()->create();
        $fiscal->assignRole('Fiscal');
        User::factory()->papelCidadaoLegado()->create();
        $setor = Sector::factory()->create();
        DB::table('sector_user')->insert([
            ['user_id' => $servidor->id, 'sector_id' => $setor->id],
            ['user_id' => $fiscal->id, 'sector_id' => $setor->id],
        ]);

        $this->rodarMigracao();

        $this->assertSame(3, DB::table('users')->count());
    }

    public function test_conflito_em_coluna_de_servidor_aborta_antes_de_qualquer_mudanca(): void
    {
        $cidadao = User::factory()->papelCidadaoLegado()->create();
        $semPerfil = User::factory()->create();
        $setor = Sector::factory()->create();
        DB::table('sector_user')->insert(['user_id' => $cidadao->id, 'sector_id' => $setor->id]);
        DB::table('regin_simulacao_execucoes')->insert([
            ['codigo' => 'a', 'relatorio' => '{}', 'user_id' => $cidadao->id],
            ['codigo' => 'b', 'relatorio' => '{}', 'user_id' => $semPerfil->id],
        ]);
        $papeisAntes = DB::table('model_has_roles')->count();

        try {
            $this->rodarMigracao();
            $this->fail('O pré-voo deveria recusar a migração.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('sector_user.user_id (1)', $e->getMessage());
            $this->assertStringContainsString('regin_simulacao_execucoes.user_id (2)', $e->getMessage());
        }

        $this->assertSame([$cidadao->id, $semPerfil->id], DB::table('users')->orderBy('id')->pluck('id')->all());
        $this->assertSame($papeisAntes, DB::table('model_has_roles')->count());
        $this->assertTrue(DB::table('roles')->where('name', 'cidadao')->exists());
        $this->assertSame(0, DB::table('cidadaos')->count());
        $this->assertSame(1, DB::table('sector_user')->count());
    }

    /** No schema antigo as colunas de cidadão e as mistas ainda apontam para users e podem citar cidadãos. */
    public function test_colunas_de_cidadao_do_schema_antigo_nao_contam(): void
    {
        Schema::table('viability_queries', function (Blueprint $t) {
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
        });
        $cidadao = User::factory()->create();
        DB::table('viability_queries')->insert([
            'user_id' => $cidadao->id, 'entry_type' => 'endereco', 'input' => '{}', 'result' => '{}',
            'rules_versions' => '{}', 'created_at' => now(),
        ]);
        $this->assertSame(1, DB::table('viability_queries')->where('user_id', $cidadao->id)->count());
        $this->assertTrue(collect(Schema::getForeignKeys('viability_queries'))
            ->contains(fn (array $fk) => $fk['foreign_table'] === 'users' && $fk['columns'] === ['user_id']));
        DB::table('sector_user')->insert(['user_id' => $cidadao->id, 'sector_id' => Sector::factory()->create()->id]);

        try {
            $this->rodarMigracao();
            $this->fail('O pré-voo deveria recusar a migração.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('sector_user.user_id (1)', $e->getMessage());
            $this->assertStringNotContainsString('viability_queries', $e->getMessage());
        }
    }

    /** A lista de colunas convertidas casa com as migrations: nenhuma delas sobra no schema novo. */
    public function test_colunas_convertidas_nao_existem_mais_no_schema_novo(): void
    {
        foreach (IdentidadeSeparada::COLUNAS_CONVERTIDAS as $coluna) {
            [$tabela, $nome] = explode('.', $coluna);
            $this->assertTrue(Schema::hasTable($tabela), $coluna);
            $this->assertFalse(Schema::hasColumn($tabela, $nome), $coluna);
        }
    }

    private function rodarMigracao(): void
    {
        (require database_path(self::MIGRATION))->up();
    }
}

<?php

namespace Tests\Feature\Cidadao;

use App\Models\AiSuggestion;
use App\Models\Sector;
use App\Models\User;
use App\Models\ViabilityRequest;
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
        DB::table('passkeys')->insert([
            ['user_id' => $cidadao->id, 'name' => 'a', 'credential_id' => 'c1', 'credential' => '{}'],
            ['user_id' => $semPerfil->id, 'name' => 'b', 'credential_id' => 'c2', 'credential' => '{}'],
        ]);
        $papeisAntes = DB::table('model_has_roles')->count();

        try {
            $this->rodarMigracao();
            $this->fail('O pré-voo deveria recusar a migração.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('sector_user.user_id (1)', $e->getMessage());
            $this->assertStringContainsString('passkeys.user_id (2)', $e->getMessage());
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

    public function test_referencia_de_cidadao_em_coluna_set_null_e_anulada_e_a_de_servidor_fica(): void
    {
        $cidadao = User::factory()->papelCidadaoLegado()->create();
        $servidor = User::factory()->analista()->create();
        $solicitacao = ViabilityRequest::factory()->create();
        $sugestaoDoCidadao = AiSuggestion::factory()->create(['created_by_user_id' => $cidadao->id]);
        $sugestaoDoServidor = AiSuggestion::factory()->create(['created_by_user_id' => $servidor->id]);
        $this->inserirTransicao($solicitacao->id, $cidadao->id);
        $this->inserirTransicao($solicitacao->id, $servidor->id);

        $this->rodarMigracao();

        $this->assertNull($sugestaoDoCidadao->fresh()->created_by_user_id);
        $this->assertSame($servidor->id, $sugestaoDoServidor->fresh()->created_by_user_id);
        $this->assertSame(1, DB::table('analysis_status_transitions')->whereNull('actor_user_id')->count());
        $this->assertSame(1, DB::table('analysis_status_transitions')->where('actor_user_id', $servidor->id)->count());
    }

    public function test_conflito_em_coluna_sem_set_null_aborta_sem_anular_nada(): void
    {
        $cidadao = User::factory()->papelCidadaoLegado()->create();
        $sugestao = AiSuggestion::factory()->create(['created_by_user_id' => $cidadao->id]);
        DB::table('sector_user')->insert(['user_id' => $cidadao->id, 'sector_id' => Sector::factory()->create()->id]);

        try {
            $this->rodarMigracao();
            $this->fail('O pré-voo deveria recusar a migração.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('sector_user.user_id (1)', $e->getMessage());
            $this->assertStringNotContainsString('ai_suggestions', $e->getMessage());
        }

        $this->assertSame($cidadao->id, $sugestao->fresh()->created_by_user_id);
    }

    public function test_conta_de_integracao_nao_conta_como_cidada_e_mantem_a_referencia(): void
    {
        $integracao = User::factory()->create(['email' => User::EMAIL_INTEGRACAO_REGIN]);
        $sugestao = AiSuggestion::factory()->create(['created_by_user_id' => $integracao->id]);

        $this->rodarMigracao();

        $this->assertSame($integracao->id, $sugestao->fresh()->created_by_user_id);
    }

    private function inserirTransicao(int $solicitacaoId, int $atorId): void
    {
        DB::table('analysis_status_transitions')->insert([
            'viability_request_id' => $solicitacaoId, 'to_status' => 'x', 'actor_user_id' => $atorId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function rodarMigracao(): void
    {
        (require database_path(self::MIGRATION))->up();
    }
}

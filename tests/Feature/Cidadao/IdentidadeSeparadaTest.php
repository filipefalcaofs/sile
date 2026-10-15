<?php

namespace Tests\Feature\Cidadao;

use App\Models\Cidadao;
use App\Models\User;
use App\Support\Migration\IdentidadeSeparada;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class IdentidadeSeparadaTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const TABELA = 'rascunho_identidade';

    private function criarRascunho(bool $autorNullable = false): void
    {
        Schema::create(self::TABELA, function (Blueprint $t) use ($autorNullable) {
            $t->id();
            $coluna = $t->foreignId('user_id');
            if ($autorNullable) {
                $coluna->nullable();
            }
            $coluna->constrained('users')->cascadeOnDelete();
            $t->string('assunto');
            $t->unique(['user_id', 'assunto']);
            $t->index(['assunto', 'user_id']);
        });
    }

    /** @return array<string, array{columns: list<string>, unique: bool}> */
    private function indices(string $tabela): array
    {
        $indices = [];
        foreach (Schema::getIndexes($tabela) as $indice) {
            if (! $indice['primary']) {
                $indices[$indice['name']] = ['columns' => $indice['columns'], 'unique' => $indice['unique']];
            }
        }

        return $indices;
    }

    public function test_retargetar_recria_indices_com_nomes_padrao_sobre_a_coluna_nova(): void
    {
        $this->criarRascunho();
        $cidadao = User::factory()->create();
        DB::table(self::TABELA)->insert(['user_id' => $cidadao->id, 'assunto' => 'a']);
        IdentidadeSeparada::copiarContasDeCidadao();

        IdentidadeSeparada::retargetar(self::TABELA, 'user_id', 'cidadao_id', false);

        $indices = $this->indices(self::TABELA);
        $this->assertSame(['cidadao_id', 'assunto'], $indices['rascunho_identidade_cidadao_id_assunto_unique']['columns']);
        $this->assertTrue($indices['rascunho_identidade_cidadao_id_assunto_unique']['unique']);
        $this->assertSame(['assunto', 'cidadao_id'], $indices['rascunho_identidade_assunto_cidadao_id_index']['columns']);
        $this->assertCount(2, $indices);
        $this->assertDatabaseHas(self::TABELA, ['cidadao_id' => $cidadao->id]);

        Schema::table(self::TABELA, function (Blueprint $t) {
            $t->dropUnique(['cidadao_id', 'assunto']);
            $t->dropIndex(['assunto', 'cidadao_id']);
        });
        $this->assertSame([], $this->indices(self::TABELA));
    }

    public function test_renomear_sem_chave_derruba_a_fk_para_users_e_mantem_indices(): void
    {
        $this->criarRascunho(true);
        $usuario = User::factory()->create();
        DB::table(self::TABELA)->insert(['user_id' => $usuario->id, 'assunto' => 'a']);

        IdentidadeSeparada::renomearSemChave(self::TABELA, 'user_id', 'cidadao_id');

        $this->assertSame([], Schema::getForeignKeys(self::TABELA));
        $this->assertTrue(Schema::hasColumn(self::TABELA, 'cidadao_id'));
        $this->assertFalse(Schema::hasColumn(self::TABELA, 'user_id'));
        $indices = $this->indices(self::TABELA);
        $this->assertSame(['cidadao_id', 'assunto'], $indices['rascunho_identidade_cidadao_id_assunto_unique']['columns']);
        $this->assertSame(['assunto', 'cidadao_id'], $indices['rascunho_identidade_assunto_cidadao_id_index']['columns']);
        $this->assertDatabaseHas(self::TABELA, ['cidadao_id' => $usuario->id]);
        $this->assertDatabaseMissing('cidadaos', ['id' => $usuario->id]);
    }

    public function test_revogar_procuracoes_de_servidores_so_mantem_as_entre_cidadaos(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Schema::create('procuracoes_antigas', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('grantor_user_id');
            $t->unsignedBigInteger('attorney_user_id');
            $t->timestamp('revoked_at')->nullable();
        });
        $c1 = User::factory()->cidadao()->create();
        $c2 = User::factory()->cidadao()->create();
        $servidor = User::factory()->analista()->create();
        $jaRevogada = now()->subDay()->startOfSecond();
        DB::table('procuracoes_antigas')->insert([
            ['grantor_user_id' => $c1->id, 'attorney_user_id' => $c2->id, 'revoked_at' => null],
            ['grantor_user_id' => $c1->id, 'attorney_user_id' => $servidor->id, 'revoked_at' => null],
            ['grantor_user_id' => $servidor->id, 'attorney_user_id' => $c2->id, 'revoked_at' => null],
            ['grantor_user_id' => $servidor->id, 'attorney_user_id' => $c1->id, 'revoked_at' => $jaRevogada],
        ]);

        IdentidadeSeparada::revogarProcuracoesDeServidores('procuracoes_antigas');

        $linhas = DB::table('procuracoes_antigas')->orderBy('id')->get();
        $this->assertNull($linhas[0]->revoked_at);
        $this->assertNotNull($linhas[1]->revoked_at);
        $this->assertNotNull($linhas[2]->revoked_at);
        $this->assertSame($jaRevogada->toDateTimeString(), (string) $linhas[3]->revoked_at);
    }

    public function test_retargetar_recusa_set_null_em_coluna_obrigatoria(): void
    {
        $this->criarRascunho();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('set null');

        IdentidadeSeparada::retargetar(self::TABELA, 'user_id', 'cidadao_id', false, 'set null');
    }

    public function test_para_morph_inclui_o_tipo_nos_indices(): void
    {
        $this->criarRascunho();

        IdentidadeSeparada::paraMorph(self::TABELA, 'user_id', 'autor');

        $indices = $this->indices(self::TABELA);
        $this->assertSame(['autor_type', 'autor_id', 'assunto'], $indices['rascunho_identidade_autor_type_autor_id_assunto_unique']['columns']);
        $this->assertTrue($indices['rascunho_identidade_autor_type_autor_id_assunto_unique']['unique']);
        $this->assertSame(['assunto', 'autor_type', 'autor_id'], $indices['rascunho_identidade_assunto_autor_type_autor_id_index']['columns']);
        $this->assertCount(2, $indices);

        Schema::table(self::TABELA, function (Blueprint $t) {
            $t->dropUnique(['autor_type', 'autor_id', 'assunto']);
            $t->dropIndex(['assunto', 'autor_type', 'autor_id']);
        });
        $this->assertSame([], $this->indices(self::TABELA));
    }

    public function test_para_morph_sem_indices_cria_indice_do_par(): void
    {
        Schema::create(self::TABELA, function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users');
        });

        IdentidadeSeparada::paraMorph(self::TABELA, 'user_id', 'autor');

        $this->assertSame(['autor_type', 'autor_id'], array_values($this->indices(self::TABELA))[0]['columns']);
    }

    public function test_para_morph_classifica_pela_consulta_e_nao_pelo_conteudo_de_cidadaos(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->criarRascunho(true);
        $cidadao = User::factory()->cidadao()->create();
        $servidor = User::factory()->analista()->create();
        DB::table(self::TABELA)->insert([
            ['user_id' => $cidadao->id, 'assunto' => 'c'],
            ['user_id' => $servidor->id, 'assunto' => 's'],
            ['user_id' => null, 'assunto' => 'n'],
        ]);
        IdentidadeSeparada::espelharServidor($servidor->id);

        IdentidadeSeparada::paraMorph(self::TABELA, 'user_id', 'autor');

        $this->assertDatabaseHas(self::TABELA, ['assunto' => 'c', 'autor_type' => Cidadao::class]);
        $this->assertDatabaseHas(self::TABELA, ['assunto' => 's', 'autor_type' => User::class]);
        $this->assertDatabaseHas(self::TABELA, ['assunto' => 'n', 'autor_type' => null]);
    }

    public function test_para_morph_herda_a_obrigatoriedade_da_coluna(): void
    {
        $this->criarRascunho(false);
        IdentidadeSeparada::paraMorph(self::TABELA, 'user_id', 'autor');
        $tipo = collect(Schema::getColumns(self::TABELA))->firstWhere('name', 'autor_type');
        $this->assertFalse($tipo['nullable']);

        Schema::drop(self::TABELA);
        $this->criarRascunho(true);
        IdentidadeSeparada::paraMorph(self::TABELA, 'user_id', 'autor');
        $tipo = collect(Schema::getColumns(self::TABELA))->firstWhere('name', 'autor_type');
        $this->assertTrue($tipo['nullable']);
    }

    public function test_tabela_real_de_aceites_migrada_tem_o_tipo_no_unique(): void
    {
        $indices = $this->indices('legal_term_acceptances');

        $this->assertSame(
            ['accepted_by_type', 'accepted_by_id', 'legal_term_id'],
            $indices['legal_term_acceptances_accepted_by_type_accepted_by_id_legal_term_id_unique']['columns'],
        );
        $this->assertFalse(Schema::hasColumn('legal_term_acceptances', 'user_id'));
    }

    public function test_espelhar_orfaos_espelha_so_os_ausentes_e_e_idempotente(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->criarRascunho(true);
        $a = User::factory()->analista()->create();
        $b = User::factory()->analista()->create();
        $semVinculo = User::factory()->analista()->create();
        DB::table(self::TABELA)->insert([
            ['user_id' => $a->id, 'assunto' => '1'],
            ['user_id' => $a->id, 'assunto' => '2'],
            ['user_id' => $b->id, 'assunto' => '3'],
            ['user_id' => null, 'assunto' => '4'],
        ]);
        IdentidadeSeparada::espelharServidor($b->id);

        IdentidadeSeparada::espelharOrfaos(self::TABELA, 'user_id');
        IdentidadeSeparada::espelharOrfaos(self::TABELA, 'user_id');

        $this->assertSame(1, DB::table('cidadaos')->where('id', $a->id)->count());
        $this->assertSame(1, DB::table('cidadaos')->where('id', $b->id)->count());
        $this->assertDatabaseMissing('cidadaos', ['id' => $semVinculo->id]);
    }
}

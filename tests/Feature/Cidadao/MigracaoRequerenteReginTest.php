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
use Tests\TestCase;

/**
 * O REGIN antigo gravava o primeiro servidor como requerente de todo recebimento sem
 * solicitante. Na separação essas solicitações vão para o registro técnico, não para
 * um espelho assumível do servidor.
 */
class MigracaoRequerenteReginTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const TABELA = 'solicitacoes_antigas';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        Schema::create(self::TABELA, function (Blueprint $t) {
            $t->id();
            $t->foreignId('requester_user_id')->constrained('users')->restrictOnDelete();
            $t->string('origin');
            $t->string('assunto');
            $t->index('requester_user_id');
        });
    }

    public function test_solicitacao_regin_de_servidor_vai_para_o_registro_tecnico(): void
    {
        $servidor = User::factory()->analista()->create();
        $cidadao = User::factory()->papelCidadaoLegado()->create();
        DB::table(self::TABELA)->insert([
            ['requester_user_id' => $servidor->id, 'origin' => 'regin', 'assunto' => 'regin-servidor'],
            ['requester_user_id' => $servidor->id, 'origin' => 'portal', 'assunto' => 'portal-servidor'],
            ['requester_user_id' => $cidadao->id, 'origin' => 'regin', 'assunto' => 'regin-cidadao'],
            ['requester_user_id' => $cidadao->id, 'origin' => 'portal', 'assunto' => 'portal-cidadao'],
        ]);
        IdentidadeSeparada::copiarContasDeCidadao();

        IdentidadeSeparada::retargetarRequerentes(self::TABELA);

        $tecnico = DB::table('cidadaos')->where('cpf', Cidadao::CPF_TECNICO)->first();
        $this->assertNotNull($tecnico);
        $this->assertNull($tecnico->password);
        $this->assertSame(Cidadao::CPF_TECNICO.Cidadao::SUFIXO_EMAIL_TECNICO, $tecnico->email);
        $this->assertSame(Cidadao::NOME_TECNICO, $tecnico->name);

        $this->assertSame(
            [
                'portal-cidadao' => $cidadao->id,
                'portal-servidor' => $servidor->id,
                'regin-cidadao' => $cidadao->id,
                'regin-servidor' => (int) $tecnico->id,
            ],
            DB::table(self::TABELA)->orderBy('assunto')->pluck('requester_cidadao_id', 'assunto')->map(fn ($id) => (int) $id)->all(),
        );
        $this->assertNull(DB::table('cidadaos')->where('id', $servidor->id)->value('password'));
        $this->assertSame(0, DB::table(self::TABELA)->where('requester_cidadao_id', $servidor->id)->where('origin', 'regin')->count());
        $this->assertSame('cidadaos', Schema::getForeignKeys(self::TABELA)[0]['foreign_table']);
        $this->assertSame((int) $tecnico->id, Cidadao::tecnicoDoRegin()->id);
    }

    public function test_servidor_que_so_aparece_no_regin_nao_ganha_espelho(): void
    {
        $servidor = User::factory()->analista()->create();
        DB::table(self::TABELA)->insert([
            ['requester_user_id' => $servidor->id, 'origin' => 'regin', 'assunto' => 'a'],
        ]);

        IdentidadeSeparada::retargetarRequerentes(self::TABELA);

        $this->assertDatabaseMissing('cidadaos', ['id' => $servidor->id]);
        $this->assertSame(1, DB::table('cidadaos')->count());
    }

    public function test_registro_tecnico_existente_e_reaproveitado(): void
    {
        $existente = Cidadao::tecnicoDoRegin();
        $servidor = User::factory()->analista()->create();
        DB::table(self::TABELA)->insert([
            ['requester_user_id' => $servidor->id, 'origin' => 'regin', 'assunto' => 'a'],
        ]);

        IdentidadeSeparada::retargetarRequerentes(self::TABELA);

        $this->assertSame(1, DB::table('cidadaos')->where('cpf', Cidadao::CPF_TECNICO)->count());
        $this->assertSame($existente->id, (int) DB::table(self::TABELA)->value('requester_cidadao_id'));
    }

    public function test_sem_solicitacao_regin_nao_cria_registro_tecnico(): void
    {
        $cidadao = User::factory()->papelCidadaoLegado()->create();
        DB::table(self::TABELA)->insert([
            ['requester_user_id' => $cidadao->id, 'origin' => 'portal', 'assunto' => 'a'],
        ]);
        IdentidadeSeparada::copiarContasDeCidadao();

        IdentidadeSeparada::retargetarRequerentes(self::TABELA);

        $this->assertDatabaseMissing('cidadaos', ['cpf' => Cidadao::CPF_TECNICO]);
    }
}

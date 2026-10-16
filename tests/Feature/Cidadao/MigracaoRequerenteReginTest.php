<?php

namespace Tests\Feature\Cidadao;

use App\Models\User;
use App\Services\Regin\ReginProcessoProtocolador;
use App\Support\Migration\IdentidadeSeparada;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * O REGIN antigo gravava um servidor (o primeiro, ou a conta da integração) como requerente
 * do recebimento sem solicitante. Na separação essas solicitações ficam sem requerente (NULL),
 * como o tronco já grava hoje, e o servidor não ganha espelho assumível.
 */
class MigracaoRequerenteReginTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const TABELA = 'solicitacoes_antigas';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        // Schema do tronco: requerente opcional (2026_10_15_192821), FK sem cascata.
        Schema::create(self::TABELA, function (Blueprint $t) {
            $t->id();
            $t->foreignId('requester_user_id')->nullable()->constrained('users');
            $t->string('origin');
            $t->string('assunto');
            $t->index('requester_user_id');
        });
    }

    public function test_solicitacao_regin_de_servidor_fica_sem_requerente(): void
    {
        $servidor = User::factory()->analista()->create();
        $cidadao = User::factory()->papelCidadaoLegado()->create();
        DB::table(self::TABELA)->insert([
            ['requester_user_id' => $servidor->id, 'origin' => 'regin', 'assunto' => 'regin-servidor'],
            ['requester_user_id' => $servidor->id, 'origin' => 'portal', 'assunto' => 'portal-servidor'],
            ['requester_user_id' => $cidadao->id, 'origin' => 'regin', 'assunto' => 'regin-cidadao'],
            ['requester_user_id' => $cidadao->id, 'origin' => 'portal', 'assunto' => 'portal-cidadao'],
            ['requester_user_id' => null, 'origin' => 'regin', 'assunto' => 'regin-nulo'],
        ]);
        IdentidadeSeparada::copiarContasDeCidadao();

        IdentidadeSeparada::retargetarRequerentes(self::TABELA);

        $this->assertSame(
            [
                'portal-cidadao' => $cidadao->id,
                'portal-servidor' => $servidor->id,
                'regin-cidadao' => $cidadao->id,
                'regin-nulo' => null,
                'regin-servidor' => null,
            ],
            DB::table(self::TABELA)->orderBy('assunto')->pluck('requester_cidadao_id', 'assunto')
                ->map(fn ($id) => $id === null ? null : (int) $id)->all(),
        );
        // O espelho do servidor existe só pela solicitação do portal, sem senha.
        $this->assertNull(DB::table('cidadaos')->where('id', $servidor->id)->value('password'));
        $this->assertDatabaseMissing('cidadaos', ['cpf' => '00000000000']);

        $chave = Schema::getForeignKeys(self::TABELA)[0];
        $this->assertSame('cidadaos', $chave['foreign_table']);
        $this->assertSame(['requester_cidadao_id'], $chave['columns']);
        $this->assertTrue(collect(Schema::getColumns(self::TABELA))->firstWhere('name', 'requester_cidadao_id')['nullable']);
    }

    public function test_servidor_que_so_aparece_no_regin_nao_ganha_espelho(): void
    {
        $servidor = User::factory()->analista()->create();
        DB::table(self::TABELA)->insert([
            ['requester_user_id' => $servidor->id, 'origin' => 'regin', 'assunto' => 'a'],
        ]);

        IdentidadeSeparada::retargetarRequerentes(self::TABELA);

        $this->assertDatabaseMissing('cidadaos', ['id' => $servidor->id]);
        $this->assertSame(0, DB::table('cidadaos')->count());
        $this->assertNull(DB::table(self::TABELA)->value('requester_cidadao_id'));
    }

    public function test_conta_da_integracao_como_requerente_do_regin_fica_nula_e_nao_vira_cidadao(): void
    {
        $integracao = app(ReginProcessoProtocolador::class)->integracao();
        DB::table(self::TABELA)->insert([
            ['requester_user_id' => $integracao->id, 'origin' => 'regin', 'assunto' => 'a'],
        ]);
        IdentidadeSeparada::copiarContasDeCidadao();

        IdentidadeSeparada::retargetarRequerentes(self::TABELA);

        $this->assertNull(DB::table(self::TABELA)->value('requester_cidadao_id'));
        $this->assertSame(0, DB::table('cidadaos')->count());
    }
}

<?php

namespace Tests\Feature\Cidadao;

use App\Models\Cidadao;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Regin\ReginProcessoProtocolador;
use App\Support\Migration\IdentidadeSeparada;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A conta da integração JUCEB/REGIN (e outras contas de sistema) fica em `users` sem perfil e
 * não é AD: pela regra geral seria "conta de cidadão". A separação não pode copiá-la para
 * `cidadaos` nem apagá-la de `users`, e o que ela criou continua com autor do tipo User.
 */
class MigracaoContasDeSistemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const TABELA = 'rascunho_regin';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        config(['viabiliza.tramitacao.atores_ocultos.emails' => ['Suporte@Exemplo.test']]);
    }

    public function test_contas_de_sistema_nao_sao_contas_de_cidadao(): void
    {
        $integracao = app(ReginProcessoProtocolador::class)->integracao();
        $invalida = User::factory()->create(['email' => 'robo@sistema.invalid']);
        $suporte = User::factory()->create(['email' => 'suporte@exemplo.test']);
        $cidadao = User::factory()->create();

        $this->assertSame(
            [$cidadao->id],
            IdentidadeSeparada::consultaContasDeCidadao()->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all(),
        );
        $this->assertNotContains($integracao->id, IdentidadeSeparada::consultaContasDeCidadao()->pluck('id')->all());
        $this->assertNotContains($invalida->id, IdentidadeSeparada::consultaContasDeCidadao()->pluck('id')->all());
        $this->assertNotContains($suporte->id, IdentidadeSeparada::consultaContasDeCidadao()->pluck('id')->all());
    }

    public function test_copia_e_remocao_mantem_a_conta_da_integracao_em_users(): void
    {
        $integracao = app(ReginProcessoProtocolador::class)->integracao();
        $cidadao = User::factory()->create();

        IdentidadeSeparada::copiarContasDeCidadao();
        (require database_path('migrations/2026_10_16_000900_remove_cidadaos_de_users.php'))->up();

        $this->assertDatabaseMissing('cidadaos', ['email' => User::EMAIL_INTEGRACAO_REGIN]);
        $this->assertDatabaseMissing('cidadaos', ['id' => $integracao->id, 'cpf' => $integracao->cpf]);
        $this->assertDatabaseHas('users', ['id' => $integracao->id, 'email' => User::EMAIL_INTEGRACAO_REGIN]);
        $this->assertDatabaseMissing('users', ['id' => $cidadao->id]);
        $this->assertDatabaseHas('cidadaos', ['id' => $cidadao->id]);
        $this->assertTrue(app(ReginProcessoProtocolador::class)->integracao()->is($integracao));
    }

    public function test_preflight_nao_acusa_coluna_de_servidor_que_cita_a_conta_da_integracao(): void
    {
        $integracao = app(ReginProcessoProtocolador::class)->integracao();
        ViabilityRequest::factory()->create(['assigned_user_id' => $integracao->id]);

        $this->assertSame([], IdentidadeSeparada::colunasDeServidorQueCitam(
            IdentidadeSeparada::consultaContasDeCidadao()->select('users.id'),
        ));

        (require database_path('migrations/2026_10_16_000050_preflight_separacao_cidadao_servidor.php'))->up();
    }

    public function test_solicitacao_regin_criada_pela_integracao_fica_com_autor_user(): void
    {
        $integracao = app(ReginProcessoProtocolador::class)->integracao();
        $cidadao = User::factory()->create();
        Schema::create(self::TABELA, function (Blueprint $t) {
            $t->id();
            $t->foreignId('created_by_user_id')->constrained('users');
            $t->string('assunto');
        });
        DB::table(self::TABELA)->insert([
            ['created_by_user_id' => $integracao->id, 'assunto' => 'regin'],
            ['created_by_user_id' => $cidadao->id, 'assunto' => 'portal'],
        ]);

        IdentidadeSeparada::paraMorph(self::TABELA, 'created_by_user_id', 'created_by');

        $this->assertSame(
            ['portal' => Cidadao::class, 'regin' => User::class],
            DB::table(self::TABELA)->orderBy('assunto')->pluck('created_by_type', 'assunto')->all(),
        );
        $this->assertSame($integracao->id, (int) DB::table(self::TABELA)->where('assunto', 'regin')->value('created_by_id'));
    }
}

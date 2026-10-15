<?php

namespace Tests\Feature\Cidadao;

use App\Models\User;
use App\Support\Migration\IdentidadeSeparada;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MigracaoCopiaCidadaosTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_copia_so_contas_que_nao_sao_de_servidor_preservando_o_id(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $cidadao = User::factory()->create();
        $analista = User::factory()->analista()->create();
        $ad = User::factory()->ad()->create();

        $migracao = require database_path('migrations/2026_10_16_000300_copia_cidadaos_de_users.php');
        $migracao->up();

        $this->assertDatabaseHas('cidadaos', ['id' => $cidadao->id, 'cpf' => $cidadao->cpf]);
        $this->assertDatabaseMissing('cidadaos', ['id' => $analista->id]);
        $this->assertDatabaseMissing('cidadaos', ['id' => $ad->id]);
        $this->assertSame($cidadao->password, DB::table('cidadaos')->where('id', $cidadao->id)->value('password'));
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
        IdentidadeSeparada::acertarSequencia();

        $this->assertTrue(true);
    }
}

<?php

namespace Tests\Feature\Cidadao;

use App\Models\AccessLog;
use App\Models\Cidadao;
use App\Models\LegalTermAcceptance;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ColunasMistasTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_access_log_guarda_o_tipo_de_quem_agiu(): void
    {
        $cidadao = Cidadao::factory()->create();
        $servidor = User::factory()->create();

        $logCidadao = AccessLog::factory()->create(['actor_type' => Cidadao::class, 'actor_id' => $cidadao->id]);
        $logServidor = AccessLog::factory()->create(['actor_type' => User::class, 'actor_id' => $servidor->id]);

        $this->assertTrue($logCidadao->actor->is($cidadao));
        $this->assertTrue($logServidor->actor->is($servidor));
    }

    public function test_cidadao_e_servidor_com_o_mesmo_id_nao_se_confundem(): void
    {
        $servidor = User::factory()->create();
        $cidadao = Cidadao::factory()->create(['id' => $servidor->id]);

        $aceite = LegalTermAcceptance::factory()->create([
            'accepted_by_type' => Cidadao::class,
            'accepted_by_id' => $cidadao->id,
        ]);

        $this->assertInstanceOf(Cidadao::class, $aceite->acceptedBy);
    }
}

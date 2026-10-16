<?php

namespace Tests\Feature\Cidadao;

use App\Models\Cidadao;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CidadaoModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cria_cidadao_pela_factory(): void
    {
        $cidadao = Cidadao::factory()->create();

        $this->assertDatabaseHas('cidadaos', ['id' => $cidadao->id, 'cpf' => $cidadao->cpf]);
        $this->assertTrue($cidadao->temSenha());
        $this->assertFalse($cidadao->isInactive());
    }

    public function test_cpf_e_email_de_servidor_nao_colidem_com_cidadao(): void
    {
        $servidor = User::factory()->create();

        $cidadao = Cidadao::factory()->create([
            'cpf' => $servidor->cpf,
            'email' => $servidor->email,
        ]);

        $this->assertSame($servidor->cpf, $cidadao->cpf);
    }

    public function test_cpf_repetido_entre_cidadaos_e_recusado(): void
    {
        $primeiro = Cidadao::factory()->create();

        $this->expectException(QueryException::class);

        Cidadao::factory()->create(['cpf' => $primeiro->cpf]);
    }

    public function test_cidadao_sem_senha_nao_tem_senha(): void
    {
        $cidadao = Cidadao::factory()->semSenha()->create();

        $this->assertFalse($cidadao->temSenha());
    }
}

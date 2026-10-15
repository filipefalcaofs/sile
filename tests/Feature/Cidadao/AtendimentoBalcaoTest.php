<?php

namespace Tests\Feature\Cidadao;

use App\Models\Cidadao;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AtendimentoBalcaoTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_registro_de_balcao_nasce_sem_senha(): void
    {
        $cidadao = Cidadao::criarRegistroDeBalcao('52998224725', 'Maria da Silva', 'maria@example.com', '(71) 99999-1234');

        $this->assertFalse($cidadao->temSenha());
        $this->assertNull($cidadao->email_verified_at);
    }

    public function test_registro_de_balcao_reaproveita_o_cidadao_existente_pelo_cpf(): void
    {
        $existente = Cidadao::factory()->create(['cpf' => '52998224725']);

        $cidadao = Cidadao::criarRegistroDeBalcao('52998224725', 'Outro Nome', 'outro@example.com', null);

        $this->assertTrue($cidadao->is($existente));
        $this->assertSame($existente->name, $cidadao->fresh()->name);
    }

    public function test_corrida_na_criacao_do_mesmo_cpf_devolve_o_registro_que_ganhou(): void
    {
        $vencedor = null;

        Cidadao::creating(function (Cidadao $novo) use (&$vencedor): void {
            if ($vencedor !== null) {
                return;
            }

            $vencedor = true;
            $id = DB::table('cidadaos')->insertGetId([
                'name' => 'Quem Chegou Antes',
                'cpf' => $novo->cpf,
                'email' => 'antes@example.com',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $vencedor = $id;
        });

        $cidadao = Cidadao::criarRegistroDeBalcao('52998224725', 'Maria', 'maria@example.com', null);

        $this->assertSame($vencedor, $cidadao->id);
        $this->assertSame('Quem Chegou Antes', $cidadao->name);
        $this->assertSame(1, Cidadao::query()->count());
    }

    public function test_registro_de_balcao_nao_recebe_email_de_verificacao(): void
    {
        Notification::fake();

        Cidadao::criarRegistroDeBalcao('52998224725', 'Maria', 'maria@example.com', null);

        Notification::assertNothingSent();
    }

    public function test_email_em_uso_recebe_endereco_tecnico_unico(): void
    {
        Cidadao::factory()->create(['email' => 'repetido@example.com']);

        $cidadao = Cidadao::criarRegistroDeBalcao('52998224725', 'Maria', 'repetido@example.com', null);

        $this->assertSame('52998224725@solicitante.viabiliza.local', $cidadao->email);
    }
}

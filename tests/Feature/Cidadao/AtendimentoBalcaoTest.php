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

    /**
     * Simula a corrida: executa $acao logo depois da primeira consulta que contém
     * $trecho (a checagem que antecede o insert), fora do savepoint do insert.
     */
    private function corridaApos(string $trecho, callable $acao): void
    {
        $disparou = false;

        DB::listen(function ($query) use ($trecho, $acao, &$disparou): void {
            if ($disparou || ! str_contains($query->sql, $trecho) || ! str_starts_with($query->sql, 'select')) {
                return;
            }

            $disparou = true;
            $acao();
        });
    }

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

        $this->corridaApos('"email"', function () use (&$vencedor): void {
            $vencedor = DB::table('cidadaos')->insertGetId([
                'name' => 'Quem Chegou Antes',
                'cpf' => '52998224725',
                'email' => 'antes@example.com',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $cidadao = Cidadao::criarRegistroDeBalcao('52998224725', 'Maria', 'maria@example.com', null);

        $this->assertSame($vencedor, $cidadao->id);
        $this->assertSame('Quem Chegou Antes', $cidadao->name);
        $this->assertSame(1, Cidadao::query()->count());
    }

    public function test_corrida_de_email_com_outro_cidadao_usa_o_email_tecnico_e_nao_devolve_o_outro(): void
    {
        $outro = null;

        $this->corridaApos('"email"', function () use (&$outro): void {
            $outro = Cidadao::factory()->create(['email' => 'maria@example.com']);
        });

        $cidadao = Cidadao::criarRegistroDeBalcao('52998224725', 'Maria', 'maria@example.com', null);

        $this->assertFalse($cidadao->is($outro));
        $this->assertSame('52998224725', $cidadao->cpf);
        $this->assertSame('52998224725'.Cidadao::SUFIXO_EMAIL_TECNICO, $cidadao->email);
        $this->assertSame(2, Cidadao::query()->count());
    }

    public function test_corrida_dentro_de_transacao_externa_devolve_o_vencedor_e_a_transacao_segue_utilizavel(): void
    {
        $this->corridaApos('"cpf"', function (): void {
            DB::table('cidadaos')->insert([
                'name' => 'Quem Chegou Antes',
                'cpf' => '52998224725',
                'email' => 'antes@example.com',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->assertSame(0, Cidadao::query()->count());
        $nivelAntes = DB::transactionLevel();

        $cidadao = DB::transaction(function () use ($nivelAntes): Cidadao {
            $encontrado = Cidadao::criarRegistroDeBalcao('52998224725', 'Maria', null, null);

            $this->assertSame($nivelAntes + 1, DB::transactionLevel());
            $this->assertSame(1, Cidadao::query()->count());

            return $encontrado;
        });

        $this->assertSame('Quem Chegou Antes', $cidadao->name);
        $this->assertSame($nivelAntes, DB::transactionLevel());
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

<?php

namespace Tests\Feature\Security;

use App\Models\Cidadao;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/** Cidadão e servidor com o mesmo id não dividem a cota dos limitadores usados nos dois ambientes. */
class ChaveDosLimitesPorContaTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function chave(string $limitador, ?object $conta): string
    {
        $request = Request::create('/qualquer', 'GET', server: ['REMOTE_ADDR' => '10.0.0.7']);
        $request->setUserResolver(fn () => $conta);

        return RateLimiter::limiter($limitador)($request)->key;
    }

    public function test_cidadao_e_servidor_de_mesmo_id_tem_chaves_diferentes(): void
    {
        $servidor = User::factory()->create();
        $cidadao = Cidadao::factory()->create(['id' => $servidor->id]);

        foreach (['cnpj-lookup', 'inscricao-lookup', 'sedur-contato', 'geocoding', 'consulta-viabilidade', 'consulta-protocolo', 'ai-connection-test'] as $limitador) {
            $doServidor = $this->chave($limitador, $servidor);
            $doCidadao = $this->chave($limitador, $cidadao);

            $this->assertNotSame($doServidor, $doCidadao, $limitador);
            $this->assertSame(User::class.'|'.$servidor->id, $doServidor);
            $this->assertSame(Cidadao::class.'|'.$cidadao->id, $doCidadao);
        }
    }

    public function test_sem_conta_a_chave_e_o_ip(): void
    {
        $this->assertSame('10.0.0.7', $this->chave('geocoding', null));
    }
}

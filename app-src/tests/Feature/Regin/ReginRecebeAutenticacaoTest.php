<?php

namespace Tests\Feature\Regin;

use App\Models\ReginRecebimento;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ReginRecebeAutenticacaoTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sem_segredo_configurado_o_post_segue_aceito(): void
    {
        config(['viabiliza.integrations.regin.recebe.segredo' => '']);

        $this->postJson('/api_integracao/recebe', $this->envio('50001'))->assertOk();

        $this->assertDatabaseHas('regin_recebimentos', ['protocolo' => '50001']);
    }

    public function test_com_segredo_o_post_sem_header_e_recusado_e_nada_e_gravado(): void
    {
        config(['viabiliza.integrations.regin.recebe.segredo' => 'segredo-forte']);

        $this->postJson('/api_integracao/recebe', $this->envio('50002'))->assertUnauthorized();

        $this->assertSame(0, ReginRecebimento::query()->count());
    }

    public function test_com_segredo_o_post_com_header_errado_e_recusado(): void
    {
        config(['viabiliza.integrations.regin.recebe.segredo' => 'segredo-forte']);

        $this->postJson('/api_integracao/recebe', $this->envio('50003'), ['X-Regin-Token' => 'outro'])
            ->assertUnauthorized();

        $this->assertSame(0, ReginRecebimento::query()->count());
    }

    public function test_com_segredo_o_post_com_header_correto_e_aceito(): void
    {
        config(['viabiliza.integrations.regin.recebe.segredo' => 'segredo-forte']);

        $response = $this->postJson('/api_integracao/recebe', $this->envio('50004'), ['X-Regin-Token' => 'segredo-forte']);

        $response->assertOk();
        $this->assertSame('3', $response->getContent());
    }

    public function test_com_segredo_o_bearer_tambem_vale(): void
    {
        config(['viabiliza.integrations.regin.recebe.segredo' => 'segredo-forte']);

        $this->postJson('/api_integracao/recebe', $this->envio('50005'), ['Authorization' => 'Bearer segredo-forte'])
            ->assertOk();
    }

    public function test_get_de_conectividade_continua_publico(): void
    {
        config(['viabiliza.integrations.regin.recebe.segredo' => 'segredo-forte']);

        $this->get('/api_integracao/recebe')->assertOk();
    }

    public function test_ip_fora_da_lista_e_recusado(): void
    {
        config(['viabiliza.integrations.regin.recebe.ips_permitidos' => ['10.57.247.9']]);

        $this->postJson('/api_integracao/recebe', $this->envio('50006'))->assertForbidden();

        $this->assertSame(0, ReginRecebimento::query()->count());
    }

    public function test_ip_dentro_da_lista_e_aceito(): void
    {
        config(['viabiliza.integrations.regin.recebe.ips_permitidos' => ['10.57.247.9']]);

        $this->withServerVariables(['REMOTE_ADDR' => '10.57.247.9'])
            ->postJson('/api_integracao/recebe', $this->envio('50007'))
            ->assertOk();
    }

    public function test_corpo_acima_do_limite_e_recusado_com_413(): void
    {
        config(['viabiliza.integrations.regin.recebe.limite_corpo' => 200]);

        $payload = $this->envio('50008') + ['json' => ['lixo' => str_repeat('x', 500)]];

        $this->postJson('/api_integracao/recebe', $payload)->assertStatus(413);

        $this->assertSame(0, ReginRecebimento::query()->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function envio(string $protocolo): array
    {
        return [
            'protocolo' => $protocolo,
            'cnpjDestino' => '13927801000149',
            'cnpjEmpresa' => '13927801000149',
            'codFuncao' => 103,
            'servico' => 'WsProSol098',
        ];
    }
}

<?php

namespace Tests\Feature\Integracao;

use App\Jobs\DecidirFluxoExpressoJob;
use App\Jobs\ProtocolarRecebimentoReginJob;
use App\Models\IntegrationCall;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class IntegrationCallLogTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_chamada_de_saida_grava_pedido_resposta_e_esconde_segredo(): void
    {
        Http::fake([
            'https://regin.exemplo.test/*' => Http::response('RECEBIDO_SUCESSO', 200, ['X-Trace' => 'ok']),
        ]);

        Http::asJson()
            ->withHeaders(['JWT' => 'token-secreto', 'Accept' => 'application/json'])
            ->post('https://regin.exemplo.test/api_integracao/recebe?token=segredo-url', [
                'protocolo' => 'PBA2600001',
                'password' => 'segredo',
            ]);

        $chamada = IntegrationCall::query()->sole();

        $this->assertSame('saida', $chamada->direction);
        $this->assertSame('regin', $chamada->integration);
        $this->assertSame('POST', $chamada->method);
        $this->assertSame(200, $chamada->http_status);
        $this->assertSame('sucesso', $chamada->outcome);
        $this->assertStringContainsString('PBA2600001', (string) $chamada->request_body);
        $this->assertStringContainsString('RECEBIDO_SUCESSO', (string) $chamada->response_body);
        $this->assertStringNotContainsString('segredo', (string) $chamada->request_body);
        $this->assertStringNotContainsString('token-secreto', json_encode($chamada->request_headers, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('segredo-url', $chamada->url);
        $this->assertStringContainsString('[redigido]', (string) $chamada->request_body);
    }

    public function test_resposta_em_iso_8859_1_e_convertida_para_utf8_e_gravada(): void
    {
        $corpo = mb_convert_encoding('{"OBS":"Da Rua dos Rodoviários à Avenida Edgard Santos"}', 'ISO-8859-1', 'UTF-8');

        Http::fake([
            'https://geoserver.exemplo.test/*' => Http::response($corpo, 200, ['Content-Type' => 'application/json; charset=ISO-8859-1']),
        ]);

        $resposta = Http::acceptJson()->get('https://geoserver.exemplo.test/geoserver/wfs');

        $this->assertSame('Da Rua dos Rodoviários à Avenida Edgard Santos', $resposta->json('OBS'));
        $this->assertStringContainsString('charset=utf-8', strtolower($resposta->header('Content-Type')));

        $chamada = IntegrationCall::query()->sole();

        $this->assertSame('sucesso', $chamada->outcome);
        $this->assertStringContainsString('Rodoviários', (string) $chamada->response_body);
    }

    public function test_resposta_binaria_nao_e_convertida(): void
    {
        $binario = "\x89PNG\r\n\x1a\n\xe1\x72\x69";

        Http::fake(['https://x.exemplo.test/*' => Http::response($binario, 200, ['Content-Type' => 'image/png'])]);

        $this->assertSame($binario, Http::get('https://x.exemplo.test/a.png')->body());
    }

    public function test_resposta_de_erro_grava_como_erro(): void
    {
        Http::fake([
            'https://geoserver.exemplo.test/*' => Http::response('falha wfs', 503),
        ]);

        Http::get('https://geoserver.exemplo.test/geoserver/wfs');

        $chamada = IntegrationCall::query()->sole();

        $this->assertSame('geoserver', $chamada->integration);
        $this->assertSame(503, $chamada->http_status);
        $this->assertSame('erro', $chamada->outcome);
        $this->assertStringContainsString('falha wfs', (string) $chamada->response_body);
    }

    public function test_falha_de_conexao_grava_erro_sem_status(): void
    {
        Http::fake([
            'https://api.sedur.exemplo.test/*' => Http::failedConnection('tempo esgotado'),
        ]);

        try {
            Http::asJson()->post('https://api.sedur.exemplo.test/ws-autenticacao/auth', [
                'usuario' => 'ana',
                'senha' => 'segredo',
            ]);
            $this->fail('A falha de conexão deveria estourar.');
        } catch (ConnectionException) {
            // o cliente segue lançando; o log não pode engolir a falha
        }

        $chamada = IntegrationCall::query()->sole();

        $this->assertSame('sedur_auth', $chamada->integration);
        $this->assertNull($chamada->http_status);
        $this->assertSame('erro', $chamada->outcome);
        $this->assertNotSame('', (string) $chamada->error_message);
        $this->assertStringContainsString('ana', (string) $chamada->request_body);
        $this->assertStringNotContainsString('segredo', (string) $chamada->request_body);
    }

    public function test_get_e_post_do_regin_gravam_a_entrada(): void
    {
        Queue::fake([
            DecidirFluxoExpressoJob::class,
            ProtocolarRecebimentoReginJob::class,
        ]);

        $this->get('/api_integracao/recebe')->assertOk()->assertContent('3');

        $this->postJson('/api_integracao/recebe', [
            'protocolo' => 'PBA2600002',
            'cnpjEmpresa' => '13927801000149',
            'codFuncao' => 103,
            'servico' => 'WsProSol098',
        ])->assertOk()->assertContent('3');

        $entradas = IntegrationCall::query()->where('direction', 'entrada')->orderBy('id')->get();

        $this->assertCount(2, $entradas);
        $this->assertSame('GET', $entradas[0]->method);
        $this->assertSame('regin', $entradas[0]->integration);
        $this->assertSame('sucesso', $entradas[0]->outcome);
        $this->assertSame('3', $entradas[0]->response_body);
        $this->assertSame('POST', $entradas[1]->method);
        $this->assertSame('sucesso', $entradas[1]->outcome);
        $this->assertStringContainsString('PBA2600002', (string) $entradas[1]->request_body);
        $this->assertSame('3', $entradas[1]->response_body);
    }

    public function test_falha_ao_gravar_o_log_nao_muda_o_ack_do_regin(): void
    {
        $tentou = false;

        IntegrationCall::creating(function () use (&$tentou): void {
            $tentou = true;

            throw new RuntimeException('disco cheio');
        });

        $this->get('/api_integracao/recebe')->assertOk()->assertContent('3');
        $this->assertTrue($tentou);
    }

    public function test_consulta_exige_permissao_e_mostra_a_chamada(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        Http::fake([
            'https://nominatim.exemplo.test/*' => Http::response(['lat' => '-12.9'], 200),
        ]);
        Http::get('https://nominatim.exemplo.test/search?q=Salvador');

        $chamada = IntegrationCall::query()->sole();

        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->actingAs($analista, 'gestao')
            ->get('/gestao/auditoria/integracoes')
            ->assertForbidden();

        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($admin, 'gestao')
            ->get('/gestao/auditoria/integracoes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/auditoria/integracoes/index')
                ->has('chamadas.data', 1)
                ->where('chamadas.data.0.integracao', 'geocoding')
                ->where('chamadas.data.0.direcao', 'saida'));

        $this->actingAs($admin, 'gestao')
            ->get('/gestao/auditoria/integracoes/'.$chamada->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/auditoria/integracoes/show')
                ->where('chamada.resultado', 'sucesso')
                ->where('chamada.corpo_resposta', '{"lat":"-12.9"}'));
    }
}

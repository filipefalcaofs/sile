<?php

namespace Tests\Feature\Realty;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InscricaoImobiliariaTestarCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const BASE_HML = 'https://api.sedur.salvador.ba.gov.br/k8s/hml/ws-bff-portal-servicos/v1/inscricao-imobiliaria';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config(['viabiliza.features.cadastro_imobiliario' => true]);
    }

    public function test_api_respondendo_termina_com_sucesso(): void
    {
        Http::fake([
            self::BASE_HML.'/1729950' => Http::response([
                'CodigoRetorno' => '0',
                'ListaEndereco' => ['RetornoEndereco' => [
                    ['TipoEndereco' => 'Localização', 'Logradouro' => 'RUA EXEMPLO', 'NumeroPorta' => '58', 'Bairro' => 'GRACA'],
                ]],
            ], 200),
        ]);

        $this->artisan('inscricao:testar', ['inscricoes' => ['1729950'], '--ambiente' => 'hml'])
            ->expectsOutputToContain('HTTP 200')
            ->expectsOutputToContain('RUA EXEMPLO, 58')
            ->assertSuccessful();
    }

    public function test_api_fora_do_ar_mostra_o_erro_de_conexao_e_falha(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $this->artisan('inscricao:testar', ['inscricoes' => ['4186370'], '--ambiente' => 'hml'])
            ->expectsOutputToContain('Operation timed out')
            ->expectsOutputToContain('INDISPONÍVEL')
            ->assertFailed();
    }

    public function test_tentativas_repete_a_chamada_para_revelar_intermitencia(): void
    {
        Http::fake([
            self::BASE_HML.'/1729950' => Http::sequence()
                ->push(['CodigoRetorno' => '0'], 200)
                ->push('erro', 503)
                ->push(['CodigoRetorno' => '0'], 200),
        ]);

        $this->artisan('inscricao:testar', ['inscricoes' => ['1729950'], '--ambiente' => 'hml', '--tentativas' => 2])
            ->expectsOutputToContain('HTTP 503')
            ->assertFailed();
    }
}

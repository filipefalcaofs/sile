<?php

namespace Tests\Feature\Realty;

use App\Models\Parameter;
use App\Services\Realty\PropertyNotFoundException;
use App\Services\Realty\PropertyRegistryLookup;
use App\Services\Realty\PropertyRegistryUnavailableException;
use App\Services\Realty\SedurSefazPropertyRegistryLookup;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SedurSefazPropertyRegistryLookupTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config(['sile.features.cadastro_imobiliario' => true]);
    }

    public function test_binding_real_e_o_cliente_do_bff_sedur(): void
    {
        $this->assertInstanceOf(SedurSefazPropertyRegistryLookup::class, app(PropertyRegistryLookup::class));
    }

    public function test_sucesso_mapeia_localizacao_converte_utm_e_descarta_proprietario(): void
    {
        Http::fake([
            'https://api.sedur.salvador.ba.gov.br/k8s/prd/ws-bff-portal-servicos/v1/inscricao-imobiliaria/0010010010' => Http::response($this->fixtureSucesso(), 200),
        ]);

        $result = app(PropertyRegistryLookup::class)->resolve('001.001.001-0');

        $this->assertSame('0010010010', $result->inscricao);
        $this->assertSame('SEFAZ via SEDUR', $result->source);
        $this->assertEqualsWithDelta(-12.996867, (float) $result->latitude, 0.00002);
        $this->assertEqualsWithDelta(-38.521245, (float) $result->longitude, 0.00002);

        $this->assertNotNull($result->cadastro);
        $this->assertSame('RUA EXEMPLO, 58', $result->cadastro->endereco);
        $this->assertSame('58', $result->cadastro->numero_porta);
        $this->assertSame('GRACA', $result->cadastro->bairro);
        $this->assertSame('40150-000', $result->cadastro->cep);
        $this->assertSame('30', $result->cadastro->area_construida_m2);
        $this->assertSame('30', $result->cadastro->area_terreno_m2);
        $this->assertSame('1234.56', $result->cadastro->valor_venal_iptu);
        $this->assertSame('123 - RUA EXEMPLO', $result->cadastro->logradouro_tributario);
        $this->assertNull($result->cadastro->contribuinte);
        $this->assertNull($result->cadastro->cpf_cnpj);
        $this->assertArrayNotHasKey('NomeProprietario', $result->raw);
        $this->assertArrayNotHasKey('ListaOutrosProprietarios', $result->raw);
        $this->assertArrayNotHasKey('ListaHistoricoPropriedade', $result->raw);
    }

    public function test_endereco_unico_como_objeto_e_normalizado(): void
    {
        $corpo = $this->fixtureSucesso();
        $corpo['ListaEndereco']['RetornoEndereco'] = $corpo['ListaEndereco']['RetornoEndereco'][1];

        Http::fake(['*' => Http::response($corpo, 200)]);

        $result = app(PropertyRegistryLookup::class)->resolve('0010010010');

        $this->assertSame('RUA EXEMPLO, 58', $result->cadastro?->endereco);
        $this->assertSame('58', $result->cadastro?->numero_porta);
    }

    public function test_404_e_codigo_retorno_diferente_de_zero_sao_nao_encontrado(): void
    {
        Http::fake([
            '*/inscricao-imobiliaria/4044044040' => Http::response(['code' => 'E404', 'message' => 'não encontrado'], 404),
            '*/inscricao-imobiliaria/0000000000' => Http::response([
                'CodigoRetorno' => '1',
                'MensagemRetorno' => 'recusado pelo parceiro',
            ], 200),
        ]);

        try {
            app(PropertyRegistryLookup::class)->resolve('4044044040');
            $this->fail('Esperava PropertyNotFoundException para 404.');
        } catch (PropertyNotFoundException $exception) {
            $this->assertSame('4044044040', $exception->inscricao);
        }

        try {
            app(PropertyRegistryLookup::class)->resolve('0000000000');
            $this->fail('Esperava PropertyNotFoundException para CodigoRetorno diferente de 0.');
        } catch (PropertyNotFoundException $exception) {
            $this->assertSame('0000000000', $exception->inscricao);
        }
    }

    public function test_5xx_apos_retentativas_e_indisponivel(): void
    {
        Http::fake(['*' => Http::response('cold start', 503)]);

        try {
            app(PropertyRegistryLookup::class)->resolve('0010010010');
            $this->fail('Esperava PropertyRegistryUnavailableException para 5xx.');
        } catch (PropertyRegistryUnavailableException $exception) {
            $this->assertSame('0010010010', $exception->inscricao);
        }

        Http::assertSentCount(3);
    }

    public function test_interruptor_de_homologacao_consulta_a_url_ativa(): void
    {
        Parameter::factory()->create([
            'key' => 'integrations.inscricao_imobiliaria.em_producao',
            'group' => 'integracoes',
            'type' => 'boolean',
            'value' => '0',
            'default_value' => '0',
        ]);
        Parameter::factory()->create([
            'key' => 'integrations.inscricao_imobiliaria.url_homologacao',
            'group' => 'integracoes',
            'type' => 'string',
            'value' => 'https://api.sedur.local/hml/inscricao-imobiliaria',
            'default_value' => 'https://api.sedur.local/hml/inscricao-imobiliaria',
        ]);

        Http::fake([
            'https://api.sedur.local/hml/inscricao-imobiliaria/0010010010' => Http::response($this->fixtureSucesso(), 200),
        ]);

        $result = app(PropertyRegistryLookup::class)->resolve('0010010010');

        $this->assertSame('0010010010', $result->inscricao);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.sedur.local/hml/inscricao-imobiliaria/0010010010');
    }

    public function test_toggle_desligado_nao_chama_a_api(): void
    {
        config(['sile.features.cadastro_imobiliario' => false]);
        Http::fake();

        try {
            app(PropertyRegistryLookup::class)->resolve('0010010010');
            $this->fail('Esperava PropertyRegistryUnavailableException com o toggle desligado.');
        } catch (PropertyRegistryUnavailableException $exception) {
            $this->assertSame('0010010010', $exception->inscricao);
        }

        Http::assertNothingSent();
    }

    /**
     * Estrutura medida em 17/09/2026. Valores ilustrativos — nenhum dado real.
     *
     * @return array<string, mixed>
     */
    private function fixtureSucesso(): array
    {
        return [
            'CodigoRetorno' => '0',
            'MensagemRetorno' => 'Operação efetuada com sucesso.',
            'DataProcessamento' => '2026-09-17T13:58:03.123-03:00',
            'CodigoIdentificador' => '1',
            'AreaConstruida' => '30',
            'AreaTerreno' => '30',
            'PadraoConstrutivo' => 'C2',
            'Vupt' => '1234.56',
            'Status' => '0',
            'CoordenadaX' => '551918',
            'CoordenadaY' => '8563162',
            'CodigoLogradouroTributario' => '123',
            'LogradouroTributario' => 'RUA EXEMPLO',
            'NomeProprietario' => 'NOME DO PROPRIETÁRIO',
            'ListaOutrosProprietarios' => [],
            'ListaHistoricoPropriedade' => [],
            'ListaEndereco' => [
                'RetornoEndereco' => [
                    [
                        'TipoEndereco' => 'Correspondência Inscricao Imobiliária',
                        'Logradouro' => 'AV OUTRO LADO',
                        'NumeroPorta' => '000999',
                        'Bairro' => 'ITAPOA',
                        'CEP' => '41680000',
                        'Municipio' => 'SALVADOR',
                        'UF' => 'BA',
                    ],
                    [
                        'TipoEndereco' => 'Localização',
                        'Logradouro' => 'RUA EXEMPLO',
                        'DescricaoLogradouro' => 'RUA EXEMPLO',
                        'NumeroPorta' => '000058',
                        'NumeroMetrico' => '58',
                        'Complemento' => null,
                        'Edificio' => null,
                        'Bairro' => 'GRACA',
                        'CEP' => '40150000',
                        'Municipio' => 'SALVADOR',
                        'UF' => 'BA',
                        'SubUnidade' => null,
                        'NumeroSubUnidade' => null,
                    ],
                ],
            ],
        ];
    }
}

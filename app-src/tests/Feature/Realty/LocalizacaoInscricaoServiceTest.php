<?php

namespace Tests\Feature\Realty;

use App\Enums\PropertyLocationSource;
use App\Services\Geo\AddressNotFoundException;
use App\Services\Geo\Geocoder;
use App\Services\Geo\GeocodeResult;
use App\Services\Realty\LocalizacaoInscricao;
use App\Services\Realty\LocalizacaoInscricaoService;
use App\Services\Realty\PropertyCadastroCampos;
use App\Services\Realty\PropertyNotFoundException;
use App\Services\Realty\PropertyRegistryLookup;
use App\Services\Realty\PropertyRegistryResult;
use App\Services\Realty\PropertyRegistryUnavailableException;
use Tests\TestCase;

class LocalizacaoInscricaoServiceTest extends TestCase
{
    /**
     * @param  array<string, mixed>|null  $raw
     */
    private function fakeCadastro(?array $raw, ?float $lat = null, ?float $lng = null, ?string $falha = null, ?PropertyCadastroCampos $cadastro = null): object
    {
        $fake = new class($raw, $lat, $lng, $falha, $cadastro) implements PropertyRegistryLookup
        {
            public int $chamadas = 0;

            /**
             * @param  array<string, mixed>|null  $raw
             */
            public function __construct(
                private ?array $raw,
                private ?float $lat,
                private ?float $lng,
                private ?string $falha,
                private ?PropertyCadastroCampos $cadastro,
            ) {}

            public function resolve(string $inscricao): PropertyRegistryResult
            {
                $this->chamadas++;

                if ($this->falha === 'indisponivel') {
                    throw new PropertyRegistryUnavailableException($inscricao);
                }

                if ($this->falha === 'nao_encontrada') {
                    throw new PropertyNotFoundException($inscricao);
                }

                return new PropertyRegistryResult(
                    latitude: $this->lat,
                    longitude: $this->lng,
                    inscricao: $inscricao,
                    source: 'SEFAZ via SEDUR',
                    raw: $this->raw ?? [],
                    cadastro: $this->cadastro,
                );
            }
        };

        $this->app->instance(PropertyRegistryLookup::class, $fake);

        return $fake;
    }

    public function test_localiza_inscricao_com_ponto_e_logradouro_tributario(): void
    {
        $this->fakeCadastro(
            ['CodigoLogradouroTributario' => '0212', 'LogradouroTributario' => 'Avenida Lafayete Coutinho'],
            -12.977769,
            -38.516404,
        );

        $localizacao = app(LocalizacaoInscricaoService::class)->localizar('344672');

        $this->assertSame(LocalizacaoInscricao::LOCALIZADA, $localizacao->status);
        $this->assertSame(-12.977769, $localizacao->lat);
        $this->assertSame(-38.516404, $localizacao->lng);
        $this->assertSame('212', $localizacao->codLogTributario);
        $this->assertSame('Avenida Lafayete Coutinho', $localizacao->logradouroTributario);
        $this->assertTrue($localizacao->temPonto());
    }

    public function test_inscricao_sem_coordenada_devolve_sem_coordenada_mas_mantem_o_logradouro(): void
    {
        $this->fakeCadastro(['CodigoLogradouroTributario' => '212']);

        $localizacao = app(LocalizacaoInscricaoService::class)->localizar('344672');

        $this->assertSame(LocalizacaoInscricao::SEM_COORDENADA, $localizacao->status);
        $this->assertFalse($localizacao->temPonto());
        $this->assertSame('212', $localizacao->codLogTributario);
    }

    public function test_sem_coordenada_geocodifica_o_endereco_devolvido_pela_sefaz(): void
    {
        $this->fakeCadastro(
            ['CodigoLogradouroTributario' => '1625', 'LogradouroTributario' => '3ª Travessa Alto dos Coqueiros'],
            cadastro: $this->cadastro('3ª Travessa Alto dos Coqueiros, 212', '212', 'Matatu'),
        );
        $consultado = new \stdClass;
        $this->app->instance(Geocoder::class, new class($consultado) implements Geocoder
        {
            public function __construct(private \stdClass $consultado) {}

            public function geocode(string $address): GeocodeResult
            {
                $this->consultado->endereco = $address;

                return new GeocodeResult(
                    latitude: -12.965785,
                    longitude: -38.484540,
                    displayName: '3ª Travessa Alto dos Coqueiros, Matatu, Salvador',
                    confidence: 0.5,
                    address: ['city' => 'Salvador', 'state' => 'Bahia'],
                );
            }
        });

        $localizacao = app(LocalizacaoInscricaoService::class)->localizar('625553');

        $this->assertSame('3ª Travessa Alto dos Coqueiros, 212, Matatu, Salvador', $consultado->endereco);
        $this->assertSame(LocalizacaoInscricao::LOCALIZADA, $localizacao->status);
        $this->assertSame(PropertyLocationSource::Geocodificacao, $localizacao->origemPonto);
        $this->assertEqualsWithDelta(-12.965785, $localizacao->lat, 0.000001);
        $this->assertEqualsWithDelta(-38.484540, $localizacao->lng, 0.000001);
    }

    public function test_geocodificacao_que_nao_encontra_o_endereco_mantem_sem_coordenada(): void
    {
        $this->fakeCadastro(
            ['CodigoLogradouroTributario' => '1625'],
            cadastro: $this->cadastro('3ª Travessa Alto dos Coqueiros, 212', '212'),
        );
        $this->app->instance(Geocoder::class, new class implements Geocoder
        {
            public function geocode(string $address): GeocodeResult
            {
                throw new AddressNotFoundException($address);
            }
        });

        $localizacao = app(LocalizacaoInscricaoService::class)->localizar('625553');

        $this->assertSame(LocalizacaoInscricao::SEM_COORDENADA, $localizacao->status);
        $this->assertFalse($localizacao->temPonto());
    }

    public function test_cadastro_indisponivel_devolve_indisponivel(): void
    {
        $this->fakeCadastro(null, falha: 'indisponivel');

        $localizacao = app(LocalizacaoInscricaoService::class)->localizar('344672');

        $this->assertSame(LocalizacaoInscricao::INDISPONIVEL, $localizacao->status);
        $this->assertFalse($localizacao->temPonto());
    }

    public function test_inscricao_inexistente_devolve_nao_encontrada(): void
    {
        $this->fakeCadastro(null, falha: 'nao_encontrada');

        $localizacao = app(LocalizacaoInscricaoService::class)->localizar('344672');

        $this->assertSame(LocalizacaoInscricao::NAO_ENCONTRADA, $localizacao->status);
    }

    private function cadastro(string $endereco, string $numero, ?string $bairro = null): PropertyCadastroCampos
    {
        return new PropertyCadastroCampos(
            inscricao: '625553',
            endereco: $endereco,
            numero_metrico: null,
            loteamento: null,
            quadra: null,
            lote: null,
            conjunto_edificio: null,
            bloco: null,
            sub_unidade: null,
            numero_sub_unidade: null,
            bairro: $bairro,
            cep: null,
            area_construida_m2: null,
            tipo_imovel: null,
            data_lancamento: null,
            situacao_cadastral: null,
            contribuinte: null,
            cpf_cnpj: null,
            numero_porta: $numero,
            area_terreno_m2: null,
            valor_venal_iptu: null,
            logradouro_tributario: null,
            situacao_fiscal: null,
            data_emissao_certidao: null,
        );
    }

    public function test_sem_inscricao_nao_consulta_a_sefaz(): void
    {
        $cadastro = $this->fakeCadastro(['CodigoLogradouroTributario' => '212'], -12.9, -38.5);

        $localizacao = app(LocalizacaoInscricaoService::class)->localizar(null);

        $this->assertSame(LocalizacaoInscricao::SEM_INSCRICAO, $localizacao->status);
        $this->assertSame(0, $cadastro->chamadas);
    }

    public function test_consulta_a_sefaz_uma_vez_por_inscricao_na_mesma_execucao(): void
    {
        $cadastro = $this->fakeCadastro(['CodigoLogradouroTributario' => '212'], -12.9, -38.5);

        $servico = app(LocalizacaoInscricaoService::class);
        $servico->localizar('344.672');
        $servico->localizar('344672');

        $this->assertSame(1, $cadastro->chamadas);
    }
}

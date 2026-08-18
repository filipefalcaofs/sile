<?php

namespace Tests\Feature\Analise;

use App\Models\ViabilityRequest;
use App\Services\Analise\CadastroImobiliarioFichaService;
use App\Services\Realty\PropertyCadastroCampos;
use App\Services\Realty\PropertyNotFoundException;
use App\Services\Realty\PropertyRegistryLookup;
use App\Services\Realty\PropertyRegistryResult;
use App\Services\Realty\PropertyRegistryUnavailableException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CadastroImobiliarioFichaServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sem_inscricao_nao_chama_lookup(): void
    {
        $this->app->instance(PropertyRegistryLookup::class, new class implements PropertyRegistryLookup
        {
            public function resolve(string $inscricao): PropertyRegistryResult
            {
                throw new \RuntimeException('lookup não deveria ser chamado');
            }
        });

        $request = ViabilityRequest::factory()->create(['property_registration' => null]);
        $payload = app(CadastroImobiliarioFichaService::class)->para($request);

        $this->assertSame('sem_inscricao', $payload['status']);
        $this->assertStringContainsString('não informada', (string) $payload['mensagem']);
        $this->assertNull($payload['campos']['quadra']);
    }

    public function test_inscricao_apenas_espacos_nao_chama_lookup(): void
    {
        $this->app->instance(PropertyRegistryLookup::class, new class implements PropertyRegistryLookup
        {
            public function resolve(string $inscricao): PropertyRegistryResult
            {
                throw new \RuntimeException('lookup não deveria ser chamado');
            }
        });

        $request = ViabilityRequest::factory()->create(['property_registration' => '   ']);
        $payload = app(CadastroImobiliarioFichaService::class)->para($request);

        $this->assertSame('sem_inscricao', $payload['status']);
        $this->assertStringContainsString('não informada', (string) $payload['mensagem']);
        $this->assertNull($payload['inscricao']);
        $this->assertSame(PropertyCadastroCampos::vazios()->toArray(), $payload['campos']);
    }

    public function test_lookup_indisponivel_degrada_com_aviso(): void
    {
        $this->app->instance(PropertyRegistryLookup::class, new class implements PropertyRegistryLookup
        {
            public function resolve(string $inscricao): PropertyRegistryResult
            {
                throw new PropertyRegistryUnavailableException($inscricao);
            }
        });

        $request = ViabilityRequest::factory()->create(['property_registration' => '379387-7']);
        $payload = app(CadastroImobiliarioFichaService::class)->para($request);

        $this->assertSame('indisponivel', $payload['status']);
        $this->assertStringContainsString('indisponível', (string) $payload['mensagem']);
        $this->assertSame('379387-7', $payload['inscricao']);
        $this->assertNull($payload['campos']['contribuinte']);
    }

    public function test_lookup_nao_encontrado(): void
    {
        $this->app->instance(PropertyRegistryLookup::class, new class implements PropertyRegistryLookup
        {
            public function resolve(string $inscricao): PropertyRegistryResult
            {
                throw new PropertyNotFoundException($inscricao);
            }
        });

        $request = ViabilityRequest::factory()->create(['property_registration' => '000']);
        $payload = app(CadastroImobiliarioFichaService::class)->para($request);

        $this->assertSame('nao_encontrado', $payload['status']);
        $this->assertStringContainsString('não encontrada', (string) $payload['mensagem']);
        $this->assertSame('000', $payload['inscricao']);
        $this->assertSame(PropertyCadastroCampos::vazios()->toArray(), $payload['campos']);
        $this->assertNull($payload['consultado_em']);
        $this->assertNull($payload['source']);
    }

    public function test_lookup_disponivel_mapeia_campos_do_cadastro(): void
    {
        $this->app->instance(PropertyRegistryLookup::class, new class implements PropertyRegistryLookup
        {
            public function resolve(string $inscricao): PropertyRegistryResult
            {
                return new PropertyRegistryResult(
                    latitude: -12.97,
                    longitude: -38.50,
                    inscricao: $inscricao,
                    source: 'fake-cadastro',
                    raw: [],
                    cadastro: new PropertyCadastroCampos(
                        inscricao: $inscricao,
                        endereco: 'Rua Martiniano Bonfim',
                        numero_metrico: '224',
                        loteamento: null,
                        quadra: '0181',
                        lote: '0043',
                        conjunto_edificio: null,
                        bloco: null,
                        sub_unidade: 'GL - Galpão',
                        numero_sub_unidade: null,
                        bairro: 'CABULA',
                        cep: null,
                        area_construida_m2: '0,00',
                        tipo_imovel: 'Residencial Horizontal',
                        data_lancamento: '01/01/1986',
                        situacao_cadastral: 'Ativo',
                        contribuinte: 'ANTONIO COSTA NETO',
                        cpf_cnpj: '035.385.595-20',
                        numero_porta: '000224',
                        area_terreno_m2: '704,00',
                        valor_venal_iptu: 'R$ 769.120,00',
                        logradouro_tributario: '3704 - Rua Martiniano Bonfim',
                        situacao_fiscal: 'Contribuinte',
                        data_emissao_certidao: '01/12/2023 10:22:28',
                    ),
                );
            }
        });

        $request = ViabilityRequest::factory()->create(['property_registration' => '379387-7']);
        $payload = app(CadastroImobiliarioFichaService::class)->para($request);

        $this->assertSame('disponivel', $payload['status']);
        $this->assertSame('0181', $payload['campos']['quadra']);
        $this->assertSame('fake-cadastro', $payload['source']);
        $this->assertNotNull($payload['consultado_em']);
    }

    public function test_lookup_disponivel_sem_cadastro_usa_campos_vazios(): void
    {
        $this->app->instance(PropertyRegistryLookup::class, new class implements PropertyRegistryLookup
        {
            public function resolve(string $inscricao): PropertyRegistryResult
            {
                return new PropertyRegistryResult(
                    latitude: -12.97,
                    longitude: -38.50,
                    inscricao: $inscricao,
                    source: 'coordenada-apenas',
                    raw: [],
                );
            }
        });

        $request = ViabilityRequest::factory()->create(['property_registration' => '379387-7']);
        $payload = app(CadastroImobiliarioFichaService::class)->para($request);

        $this->assertSame('disponivel', $payload['status']);
        $this->assertNull($payload['mensagem']);
        $this->assertSame('379387-7', $payload['inscricao']);
        $this->assertSame(PropertyCadastroCampos::vazios()->toArray(), $payload['campos']);
        $this->assertSame('coordenada-apenas', $payload['source']);
        $this->assertNotNull($payload['consultado_em']);
    }
}

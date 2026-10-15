<?php

namespace Tests\Feature\Solicitacao;

use App\Models\Parameter;
use App\Models\User;
use App\Services\Geo\Geocoder;
use App\Services\Geo\GeocodeResult;
use App\Services\Realty\PropertyCadastroCampos;
use App\Services\Realty\PropertyNotFoundException;
use App\Services\Realty\PropertyRegistryLookup;
use App\Services\Realty\PropertyRegistryResult;
use App\Services\Realty\PropertyRegistryUnavailableException;
use Database\Seeders\ParameterSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pré-preenchimento do imóvel na contingência a partir do Cadastro Imobiliário
 * (SEFAZ via SEDUR). A inscrição informada pelo operador devolve endereço e
 * coordenada reais; indisponibilidade não inventa dado.
 */
class ConsultarInscricaoContingenciaTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        // A suíte desliga o cadastro real (phpunit.xml) para não chamar a SEFAZ.
        // Estes testes ligam o recurso e substituem o provider.
        config(['viabiliza.features.cadastro_imobiliario' => true]);
    }

    public function test_consulta_preenche_endereco_e_coordenada_do_cadastro(): void
    {
        $this->app->instance(PropertyRegistryLookup::class, new class implements PropertyRegistryLookup
        {
            public function resolve(string $inscricao): PropertyRegistryResult
            {
                return new PropertyRegistryResult(
                    latitude: -12.9748,
                    longitude: -38.4765,
                    inscricao: $inscricao,
                    source: 'SEFAZ via SEDUR',
                    cadastro: new PropertyCadastroCampos(
                        inscricao: $inscricao,
                        endereco: 'Rua Chile, 10 — Edifício Oceania - Sala 2',
                        numero_metrico: null,
                        loteamento: null,
                        quadra: null,
                        lote: null,
                        conjunto_edificio: null,
                        bloco: null,
                        sub_unidade: null,
                        numero_sub_unidade: null,
                        bairro: 'Centro',
                        cep: '40020-000',
                        area_construida_m2: null,
                        tipo_imovel: null,
                        data_lancamento: null,
                        situacao_cadastral: null,
                        contribuinte: null,
                        cpf_cnpj: null,
                        numero_porta: '10',
                        area_terreno_m2: null,
                        valor_venal_iptu: null,
                        logradouro_tributario: null,
                        situacao_fiscal: null,
                        data_emissao_certidao: null,
                    ),
                );
            }
        });

        $this->actingAs($this->operador(), 'gestao')
            ->postJson(route('gestao.contingencia.consultar-inscricao'), ['inscricao' => '001.001.001-0'])
            ->assertOk()
            ->assertJsonPath('address_street', 'Rua Chile')
            ->assertJsonPath('address_number', '10')
            ->assertJsonPath('address_complement', 'Edifício Oceania - Sala 2')
            ->assertJsonPath('address_neighborhood', 'Centro')
            ->assertJsonPath('address_zip', '40020000')
            ->assertJsonPath('latitude', -12.9748)
            ->assertJsonPath('longitude', -38.4765);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'contingencia',
            'event' => 'consulta-inscricao',
            'result' => 'sucesso',
        ]);
    }

    public function test_sem_coordenada_no_cadastro_geocodifica_o_endereco(): void
    {
        $this->app->instance(PropertyRegistryLookup::class, new class implements PropertyRegistryLookup
        {
            public function resolve(string $inscricao): PropertyRegistryResult
            {
                return new PropertyRegistryResult(
                    latitude: null,
                    longitude: null,
                    inscricao: $inscricao,
                    cadastro: new PropertyCadastroCampos(
                        inscricao: $inscricao,
                        endereco: 'Rua Chile, 10',
                        numero_metrico: null,
                        loteamento: null,
                        quadra: null,
                        lote: null,
                        conjunto_edificio: null,
                        bloco: null,
                        sub_unidade: null,
                        numero_sub_unidade: null,
                        bairro: 'Centro',
                        cep: null,
                        area_construida_m2: null,
                        tipo_imovel: null,
                        data_lancamento: null,
                        situacao_cadastral: null,
                        contribuinte: null,
                        cpf_cnpj: null,
                        numero_porta: '10',
                        area_terreno_m2: null,
                        valor_venal_iptu: null,
                        logradouro_tributario: null,
                        situacao_fiscal: null,
                        data_emissao_certidao: null,
                    ),
                );
            }
        });

        $this->app->instance(Geocoder::class, new class implements Geocoder
        {
            public function geocode(string $address): GeocodeResult
            {
                return new GeocodeResult(
                    latitude: -12.974,
                    longitude: -38.513,
                    displayName: $address,
                    confidence: 0.8,
                    address: ['city' => 'Salvador', 'state' => 'Bahia'],
                );
            }
        });

        $this->actingAs($this->operador(), 'gestao')
            ->postJson(route('gestao.contingencia.consultar-inscricao'), ['inscricao' => '344672'])
            ->assertOk()
            ->assertJsonPath('address_street', 'Rua Chile')
            ->assertJsonPath('latitude', -12.974)
            ->assertJsonPath('longitude', -38.513);
    }

    public function test_inscricao_inexistente_nao_inventa_endereco(): void
    {
        $this->app->instance(PropertyRegistryLookup::class, new class implements PropertyRegistryLookup
        {
            public function resolve(string $inscricao): PropertyRegistryResult
            {
                throw new PropertyNotFoundException($inscricao);
            }
        });

        $this->actingAs($this->operador(), 'gestao')
            ->postJson(route('gestao.contingencia.consultar-inscricao'), ['inscricao' => '999999'])
            ->assertNotFound()
            ->assertJsonPath('message', 'Inscrição não encontrada no Cadastro Imobiliário.');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'contingencia',
            'event' => 'consulta-inscricao',
            'result' => 'falha',
        ]);
    }

    public function test_cadastro_indisponivel_pede_preenchimento_manual(): void
    {
        $this->app->instance(PropertyRegistryLookup::class, new class implements PropertyRegistryLookup
        {
            public function resolve(string $inscricao): PropertyRegistryResult
            {
                throw new PropertyRegistryUnavailableException($inscricao);
            }
        });

        $this->actingAs($this->operador(), 'gestao')
            ->postJson(route('gestao.contingencia.consultar-inscricao'), ['inscricao' => '344672'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'Cadastro Imobiliário temporariamente indisponível. Preencha o endereço manualmente.');
    }

    public function test_consulta_desligada_nao_chama_a_api(): void
    {
        $this->seed(ParameterSeeder::class);

        Parameter::query()
            ->where('key', 'features.cadastro_imobiliario')
            ->first()
            ->update(['value' => '0']);

        Http::fake();

        $this->actingAs($this->operador(), 'gestao')
            ->postJson(route('gestao.contingencia.consultar-inscricao'), ['inscricao' => '344672'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'A consulta do Cadastro Imobiliário está desativada. Preencha o endereço manualmente.');

        Http::assertNothingSent();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'contingencia',
            'event' => 'consulta-inscricao',
            'result' => 'bloqueado',
        ]);
    }

    public function test_exige_permissao_de_contingencia(): void
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->actingAs($analista, 'gestao')
            ->postJson(route('gestao.contingencia.consultar-inscricao'), ['inscricao' => '344672'])
            ->assertForbidden();
    }

    public function test_inscricao_vazia_e_rejeitada(): void
    {
        $this->actingAs($this->operador(), 'gestao')
            ->postJson(route('gestao.contingencia.consultar-inscricao'), ['inscricao' => 'abc'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('inscricao');
    }

    private function operador(): User
    {
        return User::factory()->administrador()->withAcceptedLgpdTerm()->create();
    }
}

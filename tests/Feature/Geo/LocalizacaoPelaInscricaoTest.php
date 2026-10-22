<?php

namespace Tests\Feature\Geo;

use App\Enums\GeoLayerType;
use App\Enums\PropertyLocationSource;
use App\Enums\ViabilityRequestOrigin;
use App\Models\Cnae;
use App\Models\GeoLayer;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Geo\CamadasIncidentesService;
use App\Services\Geo\Geocoder;
use App\Services\Geo\GeocodeResult;
use App\Services\Geo\SpatialRepository;
use App\Services\Realty\PropertyCadastroCampos;
use App\Services\Realty\PropertyRegistryLookup;
use App\Services\Realty\PropertyRegistryResult;
use App\Services\Realty\PropertyRegistryUnavailableException;
use App\Services\Solicitacao\ProtocolarSolicitacaoService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\TestCase;

class LocalizacaoPelaInscricaoTest extends TestCase
{
    use LazilyRefreshDatabase;

    private FakeSpatialRepository $espacial;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Bairro, 'version' => 'bairro-2024']);
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Zona, 'version' => 'zoneamento-louos-2026']);
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Via, 'version' => 'logradouro-2024']);

        $this->espacial = new FakeSpatialRepository;
        $this->espacial->setContaining(GeoLayerType::Bairro, ['id' => 1, 'properties' => ['NOME_BAIRRO' => 'Comércio']]);
        $this->espacial->setContaining(GeoLayerType::Zona, ['id' => 2, 'properties' => ['NOME' => 'ZCN-1']]);
        $this->espacial->setNearest(GeoLayerType::Via, [
            'id' => 3,
            'properties' => ['CODLOG' => '958', 'NOME_LOGRADOURO' => 'Rua Luiz Murat'],
            'distancia_m' => 25.9,
        ]);
        $this->app->instance(SpatialRepository::class, $this->espacial);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function rascunhoRegin(array $atributos = []): ViabilityRequest
    {
        $solicitacao = ViabilityRequest::factory()->draft()->create([
            'origin' => ViabilityRequestOrigin::Regin,
            'property_polygon_geojson' => null,
            'property_registration' => '344672',
            'used_area_m2' => 120,
            ...$atributos,
        ]);

        $cnae = Cnae::factory()->create();
        $solicitacao->cnaes()->attach($cnae->id, ['is_primary' => true]);

        return $solicitacao;
    }

    /**
     * @param  list<array<string, mixed>>  $enderecos
     */
    private function fakeCadastro(?string $codLog, ?float $lat = -12.977769, ?float $lng = -38.516404, bool $indisponivel = false, array $enderecos = []): void
    {
        $this->app->instance(PropertyRegistryLookup::class, new class($codLog, $lat, $lng, $indisponivel, $enderecos) implements PropertyRegistryLookup
        {
            /**
             * @param  list<array<string, mixed>>  $enderecos
             */
            public function __construct(
                private ?string $codLog,
                private ?float $lat,
                private ?float $lng,
                private bool $indisponivel,
                private array $enderecos,
            ) {}

            public function resolve(string $inscricao): PropertyRegistryResult
            {
                if ($this->indisponivel) {
                    throw new PropertyRegistryUnavailableException($inscricao);
                }

                return new PropertyRegistryResult(
                    latitude: $this->lat,
                    longitude: $this->lng,
                    inscricao: $inscricao,
                    raw: [
                        'CodigoLogradouroTributario' => $this->codLog,
                        'LogradouroTributario' => 'Avenida Lafayete Coutinho',
                        'ListaEndereco' => ['RetornoEndereco' => $this->enderecos],
                    ],
                );
            }
        });
    }

    public function test_processo_regin_sem_poligono_protocola_com_ponto_do_cadastro_imobiliario(): void
    {
        $this->fakeCadastro('212');
        $ator = User::factory()->create();
        $solicitacao = $this->rascunhoRegin();

        app(ProtocolarSolicitacaoService::class)->protocol($solicitacao, $ator);

        $solicitacao->refresh();

        $this->assertEqualsWithDelta(-12.977769, $solicitacao->property_point_lat, 0.0000001);
        $this->assertEqualsWithDelta(-38.516404, $solicitacao->property_point_lng, 0.0000001);
        $this->assertSame(PropertyLocationSource::CadastroImobiliario, $solicitacao->property_location_source);
        $this->assertSame('ZCN-1', $solicitacao->zona_codigo);
        $this->assertSame('Comércio', $solicitacao->bairro_oficial);
        $this->assertSame('212', $solicitacao->cod_log);
    }

    public function test_cadastro_sem_coordenada_protocola_com_ponto_geocodificado(): void
    {
        $this->app->instance(PropertyRegistryLookup::class, new class implements PropertyRegistryLookup
        {
            public function resolve(string $inscricao): PropertyRegistryResult
            {
                return new PropertyRegistryResult(
                    latitude: null,
                    longitude: null,
                    inscricao: $inscricao,
                    raw: ['CodigoLogradouroTributario' => '1625', 'LogradouroTributario' => '3ª Travessa Alto dos Coqueiros'],
                    cadastro: new PropertyCadastroCampos(
                        inscricao: $inscricao,
                        endereco: '3ª Travessa Alto dos Coqueiros, 212',
                        numero_metrico: null,
                        loteamento: null,
                        quadra: null,
                        lote: null,
                        conjunto_edificio: null,
                        bloco: null,
                        sub_unidade: null,
                        numero_sub_unidade: null,
                        bairro: 'Matatu',
                        cep: null,
                        area_construida_m2: null,
                        tipo_imovel: null,
                        data_lancamento: null,
                        situacao_cadastral: null,
                        contribuinte: null,
                        cpf_cnpj: null,
                        numero_porta: '212',
                        area_terreno_m2: null,
                        valor_venal_iptu: null,
                        logradouro_tributario: '1625 - 3ª Travessa Alto dos Coqueiros',
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
                return new GeocodeResult(-12.965785, -38.484540, 'Matatu, Salvador', 0.5, ['city' => 'Salvador', 'state' => 'Bahia']);
            }
        });
        $ator = User::factory()->create();
        $solicitacao = $this->rascunhoRegin(['property_registration' => '625553']);

        app(ProtocolarSolicitacaoService::class)->protocol($solicitacao, $ator);

        $solicitacao->refresh();

        $this->assertSame(PropertyLocationSource::Geocodificacao, $solicitacao->property_location_source);
        $this->assertEqualsWithDelta(-12.965785, $solicitacao->property_point_lat, 0.000001);
        $this->assertEqualsWithDelta(-38.484540, $solicitacao->property_point_lng, 0.000001);
        $this->assertSame('1625', $solicitacao->cod_log);
    }

    public function test_cadastro_indisponivel_protocola_sem_ponto_e_audita(): void
    {
        $this->fakeCadastro(null, indisponivel: true);
        $ator = User::factory()->create();
        $solicitacao = $this->rascunhoRegin();

        app(ProtocolarSolicitacaoService::class)->protocol($solicitacao, $ator);

        $solicitacao->refresh();

        $this->assertNull($solicitacao->property_point_lat);
        $this->assertNull($solicitacao->property_location_source);
        $this->assertTrue(
            Activity::query()->where('event', 'localizacao-inscricao')->where('subject_id', $solicitacao->id)->exists(),
        );
    }

    public function test_logradouro_tributario_sobrepoe_a_via_mais_proxima_e_audita_a_divergencia(): void
    {
        $this->fakeCadastro('212');
        $ator = User::factory()->create();
        $solicitacao = $this->rascunhoRegin();

        app(ProtocolarSolicitacaoService::class)->protocol($solicitacao, $ator);

        $this->assertSame('212', $solicitacao->fresh()->cod_log);

        $auditoria = Activity::query()->where('event', 'logradouro-tributario-diverge')->first();

        $this->assertNotNull($auditoria);
        $this->assertSame('958', $auditoria->properties['cod_log_anterior']);
        $this->assertSame('212', $auditoria->properties['cod_log_tributario']);
    }

    public function test_sem_inscricao_mantem_o_codlog_da_via_mais_proxima(): void
    {
        $this->fakeCadastro('212');
        $ator = User::factory()->create();
        $solicitacao = $this->rascunhoRegin(['property_registration' => null]);
        $solicitacao->forceFill([
            'property_point_lat' => -12.977769,
            'property_point_lng' => -38.516404,
            'property_location_source' => PropertyLocationSource::CatalogoSimulacao,
        ])->save();

        app(ProtocolarSolicitacaoService::class)->protocol($solicitacao, $ator);

        $this->assertSame('958', $solicitacao->fresh()->cod_log);
    }

    public function test_processo_com_poligono_nao_grava_ponto_mas_aplica_o_logradouro_tributario(): void
    {
        $this->fakeCadastro('212');
        $ator = User::factory()->create();
        $solicitacao = $this->rascunhoRegin([
            'property_polygon_geojson' => [
                'type' => 'Polygon',
                'coordinates' => [[
                    [-38.5108, -12.9711],
                    [-38.5108, -12.9709],
                    [-38.5106, -12.9709],
                    [-38.5106, -12.9711],
                    [-38.5108, -12.9711],
                ]],
            ],
        ]);

        app(ProtocolarSolicitacaoService::class)->protocol($solicitacao, $ator);

        $solicitacao->refresh();

        $this->assertNull($solicitacao->property_point_lat);
        $this->assertNull($solicitacao->property_location_source);
        $this->assertSame('212', $solicitacao->cod_log);
    }

    public function test_codlog_do_processo_e_o_do_endereco_de_localizacao_e_nao_o_tributario(): void
    {
        $this->fakeCadastro('579', enderecos: [
            [
                'TipoEndereco' => 'Endereço Contribuinte Inscricao Imobiliaria',
                'CodigoLogradouro' => '0401',
                'Logradouro' => 'Rua Catharina Paraguassú',
            ],
            [
                'TipoEndereco' => 'Localização',
                'CodigoLogradouro' => '401',
                'Logradouro' => 'Rua Catharina Paraguassú',
                'NumeroMetrico' => '365',
            ],
        ]);
        $ator = User::factory()->create();
        $solicitacao = $this->rascunhoRegin(['address_street' => 'CATHARINA PARAGUASSÚ', 'address_number' => '365']);

        app(ProtocolarSolicitacaoService::class)->protocol($solicitacao, $ator);

        $solicitacao->refresh();

        $this->assertSame('401', $solicitacao->cod_log);

        $inventario = app(CamadasIncidentesService::class)->identificar($solicitacao);

        $this->assertSame(['cod_log' => '401', 'nome' => 'Rua Catharina Paraguassú'], $inventario['logradouro']);
        $this->assertSame('579', $inventario['logradouro_tributario']['cod_log']);
    }
}

<?php

namespace Tests\Feature\Expresso;

use App\Enums\GeoLayerType;
use App\Enums\Quadro10Permissao;
use App\Enums\RiscoMunicipal;
use App\Enums\RuleDomain;
use App\Enums\TipoGatilho;
use App\Enums\ViabilityRequestStatus;
use App\Enums\ZonaFonte;
use App\Events\ResultadoEmitido;
use App\Models\Cnae;
use App\Models\ExpressoQueda;
use App\Models\GeoLayer;
use App\Models\LouosQuadro10Permissao;
use App\Models\RiskClassification;
use App\Models\RuleVersion;
use App\Models\ViabilityRequest;
use App\Services\Expresso\FluxoExpressoService;
use App\Services\Geo\SpatialRepository;
use App\Services\Geo\ZonaMaiorPorcao;
use App\Services\Geo\ZonaMaiorPorcaoResolver;
use App\Services\Realty\PropertyRegistryLookup;
use App\Services\Realty\PropertyRegistryResult;
use App\Services\Realty\PropertyRegistryUnavailableException;
use Database\Seeders\DecisionTextSeeder;
use Database\Seeders\PropertyTypeSeeder;
use Database\Seeders\RiskTriggerSeeder;
use Database\Seeders\TerritorialRuleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\Support\Geo\FakeZonaMaiorPorcaoResolver;
use Tests\Support\SeedsTratamentoPlanilha;
use Tests\TestCase;

class FluxoExpressoZonaMaiorPorcaoTest extends TestCase
{
    use LazilyRefreshDatabase;
    use SeedsTratamentoPlanilha;

    private const DESENHO = ['type' => 'Polygon', 'coordinates' => [[[-38.5110, -12.9710], [-38.5105, -12.9710], [-38.5105, -12.9705], [-38.5110, -12.9705], [-38.5110, -12.9710]]]];

    private const ENDERECAMENTO = ['type' => 'Polygon', 'coordinates' => [[[-38.5111, -12.9711], [-38.5104, -12.9711], [-38.5104, -12.9704], [-38.5111, -12.9704], [-38.5111, -12.9711]]]];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RiskTriggerSeeder::class);
        $this->seed(PropertyTypeSeeder::class);
        $this->seed(DecisionTextSeeder::class);
        $this->seed(TerritorialRuleSeeder::class);
    }

    /**
     * @param  list<string>  $cnaeCodes
     */
    private function protocoladaComCnaes(array $cnaeCodes): ViabilityRequest
    {
        $solicitacao = ViabilityRequest::factory()->protocoled()->create([
            'used_area_m2' => 120.0,
            'property_registration' => '344672',
        ]);

        foreach (array_values($cnaeCodes) as $indice => $code) {
            $cnae = Cnae::factory()->create(['code' => $code]);
            $solicitacao->cnaes()->attach($cnae->id, ['is_primary' => $indice === 0]);
        }

        $solicitacao->respostasTratamento = [11 => true];

        return $solicitacao;
    }

    private function setupDeferivel(string $cnae = '4712100'): ViabilityRequest
    {
        Http::fake(fn () => Http::response(['type' => 'FeatureCollection', 'features' => []]));

        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Bairro, 'version' => 'bairro-2024']);
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Zona, 'version' => 'zoneamento-louos-2026']);
        $fake = new FakeSpatialRepository;
        $fake->setContaining(GeoLayerType::Bairro, ['id' => 1, 'properties' => ['NOME_BAIRRO' => 'Comércio']]);
        $fake->setContaining(GeoLayerType::Zona, ['id' => 2, 'properties' => ['NOME' => 'ZCN-1']]);
        $this->app->instance(SpatialRepository::class, $fake);

        $versao = RuleVersion::vigente(RuleDomain::RiscoMunicipal)->first()
            ?? RuleVersion::factory()->create([
                'domain' => RuleDomain::RiscoMunicipal,
                'version' => 'decreto-32636-2020',
                'rules_version' => 'decreto-32636-2020',
            ]);

        RiskClassification::factory()->create([
            'rule_version_id' => $versao->id,
            'cnae_code' => $cnae,
            'risco_municipal' => RiscoMunicipal::BaixoA,
        ]);

        $this->seedTratamentoPlanilha();

        $quadro = RuleVersion::vigente(RuleDomain::LouosQuadro10)->first()
            ?? RuleVersion::factory()->create([
                'domain' => RuleDomain::LouosQuadro10,
                'version' => 'lei-9148-2016-quadro10',
                'rules_version' => 'lei-9148-2016-quadro10',
            ]);

        LouosQuadro10Permissao::factory()->create([
            'rule_version_id' => $quadro->id,
            'zona' => 'ZCN-1',
            'grupo_uso' => 'nR1',
            'subgrupo' => '',
            'permissao' => Quadro10Permissao::Permitido,
            'condicionante_ref' => null,
            'base_legal' => 'Quadro 10 da Lei nº 9.148/2016',
        ]);

        return $this->protocoladaComCnaes([$cnae]);
    }

    private function fakeCadastro(float $lat, float $lng, bool $indisponivel = false): void
    {
        $this->app->instance(PropertyRegistryLookup::class, new class($lat, $lng, $indisponivel) implements PropertyRegistryLookup
        {
            public function __construct(
                private float $lat,
                private float $lng,
                private bool $indisponivel,
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
                    raw: ['CodigoLogradouroTributario' => '999'],
                );
            }
        });
    }

    private function zonas(ZonaMaiorPorcao $enderecamento, ?ZonaMaiorPorcao $desenho = null): FakeZonaMaiorPorcaoResolver
    {
        $fake = (new FakeZonaMaiorPorcaoResolver)->para(self::ENDERECAMENTO, $enderecamento);

        if ($desenho !== null) {
            $fake->para(self::DESENHO, $desenho);
        }

        $this->app->instance(ZonaMaiorPorcaoResolver::class, $fake);

        return $fake;
    }

    private function comPoligonos(ViabilityRequest $request): ViabilityRequest
    {
        $request->forceFill([
            'property_polygon_geojson' => self::DESENHO,
            'address_polygon_geojson' => self::ENDERECAMENTO,
            'zona_codigo' => null,
        ])->save();

        return $request->fresh();
    }

    public function test_ca_zn_04_empate_encaminha_com_as_duas_zonas_e_nao_grava_vencedora(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $this->fakeCadastro(-12.9707, -38.5107);
        $request = $this->comPoligonos($this->setupDeferivel());
        $this->zonas(FakeZonaMaiorPorcaoResolver::empate('ZCN-1', 'ZPR 3'));

        $result = app(FluxoExpressoService::class)->decide($request);

        $this->assertSame(ViabilityRequestStatus::EmAnalise, $result->status);
        $this->assertStringContainsString('ZCN-1', (string) $result->reason);
        $this->assertStringContainsString('ZPR 3', (string) $result->reason);
        $this->assertSame(TipoGatilho::ZonaEmpate->value, ExpressoQueda::query()->where('viability_request_id', $request->id)->first()?->tipo_gatilho);
        $this->assertNotSame(ZonaFonte::EnderecamentoMaiorPorcao, $request->fresh()->zona_fonte);
    }

    public function test_ca_dv_01_desenho_em_outra_zona_encaminha_e_mantem_a_zona_do_enderecamento(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $this->fakeCadastro(-12.9707, -38.5107);
        $request = $this->comPoligonos($this->setupDeferivel());
        $this->zonas(FakeZonaMaiorPorcaoResolver::identificado('ZCN-1'), FakeZonaMaiorPorcaoResolver::identificado('ZPR 3'));

        $result = app(FluxoExpressoService::class)->decide($request);

        $this->assertSame(ViabilityRequestStatus::EmAnalise, $result->status);
        $this->assertStringContainsString('ZCN-1', (string) $result->reason);
        $this->assertStringContainsString('ZPR 3', (string) $result->reason);
        $this->assertSame('ZCN-1', $request->fresh()->zona_codigo);
        $this->assertSame(TipoGatilho::LocalizacaoDivergente->value, ExpressoQueda::query()->where('viability_request_id', $request->id)->first()?->tipo_gatilho);
    }

    public function test_ca_dv_02_zonas_iguais_seguem_o_fluxo_e_a_coincidencia_e_auditada(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $this->fakeCadastro(-12.9707, -38.5107);
        $request = $this->comPoligonos($this->setupDeferivel());
        $this->zonas(FakeZonaMaiorPorcaoResolver::identificado('ZCN-1'), FakeZonaMaiorPorcaoResolver::identificado('ZCN-1'));

        $result = app(FluxoExpressoService::class)->decide($request);

        $this->assertSame(ViabilityRequestStatus::Deferida, $result->status);
        $auditoria = Activity::query()->where('event', 'zona-divergencia-enderecamento')->where('subject_id', $request->id)->first();
        $this->assertSame('coincide', $auditoria?->properties['resultado']);
    }

    public function test_zona_da_maior_porcao_decide_o_quadro_10(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $this->fakeCadastro(-12.9707, -38.5107);
        $request = $this->setupDeferivel();
        // Ponto do imóvel em zona sem permissão; a maior porção do lote é ZCN-1.
        $fake = new FakeSpatialRepository;
        $fake->setContaining(GeoLayerType::Bairro, ['id' => 1, 'properties' => ['NOME_BAIRRO' => 'Comércio']]);
        $fake->setContaining(GeoLayerType::Zona, ['id' => 2, 'properties' => ['NOME' => 'ZPR 1']]);
        $this->app->instance(SpatialRepository::class, $fake);
        $request->forceFill(['property_polygon_geojson' => null, 'property_point_lat' => -12.9707, 'property_point_lng' => -38.5107, 'address_polygon_geojson' => self::ENDERECAMENTO])->save();
        $this->zonas(FakeZonaMaiorPorcaoResolver::identificado('ZCN-1'));

        $result = app(FluxoExpressoService::class)->decide($request->fresh());

        $this->assertSame(ViabilityRequestStatus::Deferida, $result->status);
        $this->assertSame(ZonaFonte::EnderecamentoMaiorPorcao, $request->fresh()->zona_fonte);
        $this->assertSame('enderecamento_maior_porcao', $request->fresh()->camadas_incidentes['zona']['fonte']);
    }

    public function test_rt01_tem_precedencia_sobre_o_empate(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $this->fakeCadastro(-12.9707, -38.5107);
        $request = $this->comPoligonos($this->setupDeferivel('4520006'));
        $request->forceFill(['cod_log' => '212'])->save();
        $this->zonas(FakeZonaMaiorPorcaoResolver::empate('ZCN-1', 'ZPR 3'));

        $result = app(FluxoExpressoService::class)->decide($request->fresh());

        $this->assertSame(ViabilityRequestStatus::Indeferida, $result->status);
    }

    public function test_ca_zn_08_uma_consulta_por_poligono_na_decisao(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $this->fakeCadastro(-12.9707, -38.5107);
        $request = $this->comPoligonos($this->setupDeferivel());
        $fake = $this->zonas(FakeZonaMaiorPorcaoResolver::identificado('ZCN-1'), FakeZonaMaiorPorcaoResolver::identificado('ZCN-1'));

        app(FluxoExpressoService::class)->decide($request);

        // Endereçamento: materializar + inventário; desenho: divergência. O fake
        // conta chamadas; o memo real fica provado no teste postgis do resolvedor.
        $this->assertLessThanOrEqual(3, $fake->chamadas);
    }
}

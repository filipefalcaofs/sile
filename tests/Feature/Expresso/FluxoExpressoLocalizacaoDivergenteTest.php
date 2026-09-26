<?php

namespace Tests\Feature\Expresso;

use App\Enums\DecisionOutcome;
use App\Enums\GeoLayerType;
use App\Enums\Quadro10Permissao;
use App\Enums\RiscoMunicipal;
use App\Enums\RuleDomain;
use App\Enums\TipoGatilho;
use App\Enums\ViabilityRequestStatus;
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
use Tests\Support\SeedsTratamentoPlanilha;
use Tests\TestCase;

class FluxoExpressoLocalizacaoDivergenteTest extends TestCase
{
    use LazilyRefreshDatabase;
    use SeedsTratamentoPlanilha;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RiskTriggerSeeder::class);
        $this->seed(PropertyTypeSeeder::class);
        $this->seed(DecisionTextSeeder::class);
        $this->seed(TerritorialRuleSeeder::class);
    }

    private function zonasPorLatitude(): void
    {
        $this->app->instance(SpatialRepository::class, new class implements SpatialRepository
        {
            public function containingFeature(GeoLayer $layer, float $lng, float $lat): ?array
            {
                return [
                    'id' => 1,
                    'properties' => ['NOME' => $lat < -12.99 ? 'ZPR-3' : 'ZCN-1'],
                ];
            }

            public function nearestFeature(GeoLayer $layer, float $lng, float $lat, int $maxMeters): ?array
            {
                return null;
            }

            public function intersectingFeatures(GeoLayer $layer, float $lng, float $lat): array
            {
                return [];
            }

            public function featuresWithin(GeoLayer $layer, float $lng, float $lat, int $maxMeters, int $limit = 20): array
            {
                return [];
            }
        });
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

    public function test_poligono_em_zona_diferente_da_inscricao_encaminha_analise_com_as_duas_zonas(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $this->fakeCadastro(-13.01, -38.52);

        $request = $this->setupDeferivel();
        $this->zonasPorLatitude();
        $request->forceFill(['cod_log' => '999'])->save();

        $result = app(FluxoExpressoService::class)->decide($request->fresh());

        $this->assertSame(ViabilityRequestStatus::EmAnalise, $result->status);
        $this->assertStringContainsString('ZCN-1', (string) $result->reason);
        $this->assertStringContainsString('ZPR-3', (string) $result->reason);

        $queda = ExpressoQueda::query()->where('viability_request_id', $request->id)->first();
        $this->assertSame(TipoGatilho::LocalizacaoDivergente->value, $queda?->tipo_gatilho);
    }

    public function test_mesma_zona_segue_o_fluxo_normal(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $this->fakeCadastro(-12.971, -38.5107);

        $request = $this->setupDeferivel();
        $this->zonasPorLatitude();

        $result = app(FluxoExpressoService::class)->decide($request->fresh());

        $this->assertSame(ViabilityRequestStatus::Deferida, $result->status);
        $auditoria = Activity::query()->where('event', 'localizacao-divergencia')->where('subject_id', $request->id)->first();
        $this->assertSame('coincide', $auditoria?->properties['resultado'] ?? null);
    }

    public function test_cadastro_indisponivel_nao_bloqueia_e_audita_verificacao_nao_realizada(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $this->fakeCadastro(0, 0, indisponivel: true);

        $request = $this->setupDeferivel();

        $result = app(FluxoExpressoService::class)->decide($request->fresh());

        $this->assertSame(ViabilityRequestStatus::Deferida, $result->status);
        $auditoria = Activity::query()->where('event', 'localizacao-divergencia')->where('subject_id', $request->id)->first();
        $this->assertSame('nao_verificada', $auditoria?->properties['resultado'] ?? null);
    }

    public function test_rt01_tem_precedencia_sobre_a_divergencia(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $this->fakeCadastro(-13.01, -38.52);

        $request = $this->setupDeferivel('4520006');
        $this->zonasPorLatitude();
        $request->forceFill(['cod_log' => '212'])->save();

        $result = app(FluxoExpressoService::class)->decide($request->fresh());

        $this->assertSame(ViabilityRequestStatus::Indeferida, $result->status);
        $this->assertSame(DecisionOutcome::Indeferida, $request->fresh()->decision->outcome);
        $this->assertNull(ExpressoQueda::query()->where('viability_request_id', $request->id)->first());
    }

    public function test_processo_sem_poligono_nao_passa_pela_verificacao(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $this->fakeCadastro(-13.01, -38.52);

        $request = $this->setupDeferivel();
        $request->forceFill([
            'property_polygon_geojson' => null,
            'property_point_lat' => -12.977769,
            'property_point_lng' => -38.516404,
        ])->save();

        $result = app(FluxoExpressoService::class)->decide($request->fresh());

        $this->assertSame(ViabilityRequestStatus::Deferida, $result->status);
        $this->assertFalse(
            Activity::query()->where('event', 'localizacao-divergencia')->where('subject_id', $request->id)->exists(),
        );
    }
}

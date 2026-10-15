<?php

namespace Tests\Feature\Renovacao;

use App\Enums\AnalysisCategory;
use App\Enums\AnalysisStatus;
use App\Enums\GeoLayerType;
use App\Enums\RiscoMunicipal;
use App\Enums\RuleDomain;
use App\Enums\TvlKind;
use App\Enums\ViabilityRequestStatus;
use App\Models\Cnae;
use App\Models\GeoLayer;
use App\Models\Parameter;
use App\Models\RiskClassification;
use App\Models\RuleVersion;
use App\Models\Sector;
use App\Models\ServiceDeclaration;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Models\ViabilityServiceType;
use App\Services\Geo\SpatialRepository;
use App\Services\Realty\PropertyRegistryLookup;
use App\Services\Realty\PropertyRegistryResult;
use App\Services\Renovacao\RenovacaoProtocoloService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\TestCase;

class RenovacaoTerritorioMotorTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_protocolo_completa_territorio_e_deixa_o_motor_na_ficha(): void
    {
        config([
            'viabiliza.features.geoserver_enderecamento' => true,
            'viabiliza.features.arcgis_semap' => true,
        ]);

        $this->territorio();
        $this->cadastroComCodLog('212');
        $this->httpDoEnderecamentoVazioESemapComLote();

        $setor = Sector::factory()->ativo()->create();
        Parameter::query()->updateOrCreate(
            ['key' => 'analise.setor_triagem_id'],
            [
                'group' => 'analise',
                'type' => 'integer',
                'value' => (string) $setor->id,
                'default_value' => null,
                'validation_rules' => ['nullable', 'integer'],
                'description' => 'Setor de triagem da análise.',
            ],
        );

        [$origem, $declaracao, $ator] = $this->cenario();

        $renovacao = app(RenovacaoProtocoloService::class)->protocolar(
            $origem,
            $ator,
            ['email' => 'renovacao@example.com', 'phone' => '7133334444'],
            [$declaracao->id],
        );

        $renovacao->refresh();

        $this->assertSame(ViabilityRequestStatus::EmAnalise, $renovacao->status);
        $this->assertSame(AnalysisCategory::SemiExpresso, $renovacao->analysis_category);
        $this->assertSame(AnalysisStatus::ParaDistribuir, $renovacao->analysis_status);
        $this->assertSame($setor->id, $renovacao->sector_id);
        $this->assertSame('Rua do TVL', $renovacao->address_street);
        $this->assertSame('212', $renovacao->cod_log);
        $this->assertSame('ZCN-1', $renovacao->zona_codigo);
        $this->assertSame('Polygon', $renovacao->address_polygon_geojson['type'] ?? null);
        $this->assertSame('COMERCIAL', $renovacao->semap_use_category);

        $ficha = $renovacao->analysisRecords()->where('revision', 1)->first();
        $this->assertNotNull($ficha);
        $this->assertTrue($ficha->engine_available);
        $this->assertNotEmpty($ficha->engine_snapshot);

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'geoserver.sedur'));
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'geo.salvador'));
    }

    private function territorio(): void
    {
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Bairro, 'version' => 'bairro-2024']);
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Zona, 'version' => 'zoneamento-louos-2026']);
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Via, 'version' => 'logradouro-2024']);

        $espacial = new FakeSpatialRepository;
        $espacial->setContaining(GeoLayerType::Bairro, ['id' => 1, 'properties' => ['NOME_BAIRRO' => 'Comércio']]);
        $espacial->setContaining(GeoLayerType::Zona, ['id' => 2, 'properties' => ['NOME' => 'ZCN-1']]);
        $espacial->setNearest(GeoLayerType::Via, [
            'id' => 3,
            'properties' => ['CODLOG' => '958', 'NOME_LOGRADOURO' => 'Rua Luiz Murat'],
            'distancia_m' => 12.0,
        ]);
        $this->app->instance(SpatialRepository::class, $espacial);

        $versao = RuleVersion::vigente(RuleDomain::RiscoMunicipal)->first()
            ?? RuleVersion::factory()->create([
                'domain' => RuleDomain::RiscoMunicipal,
                'version' => 'decreto-32636-2020',
                'rules_version' => 'decreto-32636-2020',
            ]);

        RiskClassification::factory()->create([
            'rule_version_id' => $versao->id,
            'cnae_code' => '2222222',
            'risco_municipal' => RiscoMunicipal::BaixoA,
        ]);
    }

    private function cadastroComCodLog(string $codLog): void
    {
        $this->app->instance(PropertyRegistryLookup::class, new class($codLog) implements PropertyRegistryLookup
        {
            public function __construct(private string $codLog) {}

            public function resolve(string $inscricao): PropertyRegistryResult
            {
                return new PropertyRegistryResult(
                    latitude: -12.977769,
                    longitude: -38.516404,
                    inscricao: $inscricao,
                    source: 'SEFAZ via SEDUR',
                    raw: [
                        'CodigoLogradouroTributario' => $this->codLog,
                        'LogradouroTributario' => 'Avenida Lafayete Coutinho',
                    ],
                );
            }
        });
    }

    private function httpDoEnderecamentoVazioESemapComLote(): void
    {
        $lote = [
            'type' => 'FeatureCollection',
            'features' => [[
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Polygon',
                    'coordinates' => [[
                        [-38.516331, -12.977852],
                        [-38.516291, -12.977746],
                        [-38.516453, -12.977688],
                        [-38.516492, -12.977795],
                        [-38.516331, -12.977852],
                    ]],
                ],
                'properties' => [
                    'INSCRICAO' => '344672',
                    'CODLOG' => '212',
                    'CAT_DE_USO' => 'COMERCIAL',
                    'PADRAOCONS' => 'MÉDIO',
                ],
            ]],
        ];

        Http::fake(function (Request $request) use ($lote) {
            if (str_contains($request->url(), 'geo.salvador')) {
                return Http::response($lote);
            }

            return Http::response(['type' => 'FeatureCollection', 'features' => []]);
        });
    }

    /**
     * @return array{0: ViabilityRequest, 1: ServiceDeclaration, 2: User}
     */
    private function cenario(): array
    {
        $tipo = ViabilityServiceType::factory()->create(['code' => 'renovacao-tvl']);
        $origem = ViabilityRequest::factory()->create([
            'address_street' => 'Rua do TVL',
            'address_number' => '70',
            'property_registration' => '344672',
        ]);
        $origem->forceFill(['status' => ViabilityRequestStatus::Deferida])->save();
        ViabilityDecision::factory()->create([
            'viability_request_id' => $origem->id,
            'tvl_kind' => TvlKind::Provisoria,
            'tvl_valid_until' => '2027-09-28',
        ]);
        $cnae = Cnae::factory()->create(['code' => '2222222']);
        $origem->cnaes()->attach($cnae->id, ['is_primary' => true]);
        $declaracao = ServiceDeclaration::factory()->create([
            'service_type_id' => $tipo->id,
            'version' => 1,
            'required' => true,
        ]);

        return [$origem->fresh(), $declaracao, User::factory()->create()];
    }
}

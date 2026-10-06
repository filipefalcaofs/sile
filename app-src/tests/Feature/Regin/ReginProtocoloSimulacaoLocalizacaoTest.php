<?php

namespace Tests\Feature\Regin;

use App\Enums\DecisionOutcome;
use App\Enums\PropertyLocationSource;
use App\Enums\ViabilityRequestStatus;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Realty\PropertyRegistryLookup;
use App\Services\Realty\PropertyRegistryResult;
use App\Services\Regin\ReginProtocoloSimulacaoService;
use Database\Seeders\DecisionTextSeeder;
use Database\Seeders\PropertyTypeSeeder;
use Database\Seeders\RiscoMunicipalSeeder;
use Database\Seeders\RiscoSanitarioSeeder;
use Database\Seeders\RiskTriggerSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\TerritorialRuleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ReginProtocoloSimulacaoLocalizacaoTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolesAndPermissionsSeeder::class,
            RiscoMunicipalSeeder::class,
            RiscoSanitarioSeeder::class,
            RiskTriggerSeeder::class,
            PropertyTypeSeeder::class,
            DecisionTextSeeder::class,
            TerritorialRuleSeeder::class,
        ]);

        $this->actingAs(
            User::factory()->administrador()->withAcceptedLgpdTerm()->create(),
            'gestao',
        );
    }

    private function fakeCadastro(string $codLog, float $lat, float $lng): void
    {
        $this->app->instance(PropertyRegistryLookup::class, new class($codLog, $lat, $lng) implements PropertyRegistryLookup
        {
            public function __construct(
                private string $codLog,
                private float $lat,
                private float $lng,
            ) {}

            public function resolve(string $inscricao): PropertyRegistryResult
            {
                return new PropertyRegistryResult(
                    latitude: $this->lat,
                    longitude: $this->lng,
                    inscricao: $inscricao,
                    raw: ['CodigoLogradouroTributario' => $this->codLog],
                );
            }
        });
    }

    public function test_simulacao_sem_ponto_no_catalogo_usa_o_ponto_do_cadastro_imobiliario(): void
    {
        $this->fakeCadastro('212', -12.977769, -38.516404);

        $relatorio = app(ReginProtocoloSimulacaoService::class)->simular('33072');
        $processo = ViabilityRequest::query()->findOrFail($relatorio['processo_id']);

        $this->assertNull($processo->property_polygon_geojson);
        $this->assertSame(PropertyLocationSource::CadastroImobiliario, $processo->property_location_source);
        $this->assertEqualsWithDelta(-12.977769, $processo->property_point_lat, 0.0000001);
        $this->assertEqualsWithDelta(-38.516404, $processo->property_point_lng, 0.0000001);
    }

    public function test_simulacao_sem_cadastro_usa_o_ponto_do_catalogo(): void
    {
        $relatorio = app(ReginProtocoloSimulacaoService::class)->simular('594902-5');
        $processo = ViabilityRequest::query()->findOrFail($relatorio['processo_id']);

        $this->assertNull($processo->property_polygon_geojson);
        $this->assertSame(PropertyLocationSource::CatalogoSimulacao, $processo->property_location_source);
        $this->assertEqualsWithDelta(-13.001053, $processo->property_point_lat, 0.0000001);
        $this->assertEqualsWithDelta(-38.465458, $processo->property_point_lng, 0.0000001);
    }

    public function test_simulacao_sem_cadastro_e_sem_ponto_fica_sem_localizacao(): void
    {
        $relatorio = app(ReginProtocoloSimulacaoService::class)->simular('33072');
        $processo = ViabilityRequest::query()->findOrFail($relatorio['processo_id']);

        $this->assertNull($processo->property_polygon_geojson);
        $this->assertNull($processo->property_point_lat);
        $this->assertNull($processo->property_location_source);
    }

    public function test_reproducao_do_processo_92_indefere_pela_rt01(): void
    {
        $this->fakeCadastro('212', -12.977769, -38.516404);

        $relatorio = app(ReginProtocoloSimulacaoService::class)->simular('34467-2');
        $processo = ViabilityRequest::query()->findOrFail($relatorio['processo_id']);

        $this->assertSame(ViabilityRequestStatus::Indeferida, $processo->status);
        $this->assertSame(DecisionOutcome::Indeferida, $processo->decision?->outcome);
        $this->assertStringContainsString('art. 172-A', (string) $processo->decision?->reason);
    }
}

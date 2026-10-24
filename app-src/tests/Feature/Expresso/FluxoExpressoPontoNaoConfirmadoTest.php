<?php

namespace Tests\Feature\Expresso;

use App\Enums\ConfirmacaoPonto;
use App\Enums\DecisionOutcome;
use App\Enums\GeoLayerType;
use App\Enums\PropertyLocationSource;
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
use App\Services\Geo\ResultadoPonto;
use App\Services\Geo\SpatialRepository;
use App\Services\Geo\ValidadorPontoInscricao;
use App\Services\Realty\PropertyRegistryLookup;
use App\Services\Realty\PropertyRegistryResult;
use Database\Seeders\DecisionTextSeeder;
use Database\Seeders\PropertyTypeSeeder;
use Database\Seeders\RiskTriggerSeeder;
use Database\Seeders\TerritorialRuleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\Support\SeedsTratamentoPlanilha;
use Tests\TestCase;

class FluxoExpressoPontoNaoConfirmadoTest extends TestCase
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

    private function fakeCadastro(): void
    {
        $this->app->instance(PropertyRegistryLookup::class, new class implements PropertyRegistryLookup
        {
            public function resolve(string $inscricao): PropertyRegistryResult
            {
                return new PropertyRegistryResult(
                    latitude: -12.965543,
                    longitude: -38.478635,
                    inscricao: $inscricao,
                    raw: ['CodigoLogradouroTributario' => '2075', 'LogradouroTributario' => 'Avenida Heitor Dias'],
                );
            }
        });
    }

    private function validadorDevolve(ConfirmacaoPonto $confirmacao, float $distanciaM = 640.0): void
    {
        $this->mock(ValidadorPontoInscricao::class, function ($mock) use ($confirmacao, $distanciaM): void {
            $mock->shouldReceive('validar')->andReturn(new ResultadoPonto(
                $confirmacao,
                -12.965543,
                -38.478635,
                PropertyLocationSource::CadastroImobiliario,
                $distanciaM,
            ));
        });
    }

    public function test_ponto_nao_confirmado_encaminha_para_analise_com_o_motivo(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $this->fakeCadastro();
        $this->validadorDevolve(ConfirmacaoPonto::NaoConfirmado);

        $request = $this->setupDeferivel();

        $result = app(FluxoExpressoService::class)->decide($request->fresh());

        $this->assertSame(ViabilityRequestStatus::EmAnalise, $result->status);
        $this->assertStringContainsString('640 m', (string) $result->reason);
        $this->assertStringContainsString('Avenida Heitor Dias', (string) $result->reason);
        $queda = ExpressoQueda::query()->where('viability_request_id', $request->id)->first();
        $this->assertSame(TipoGatilho::LocalizacaoDivergente->value, $queda?->tipo_gatilho);
    }

    public function test_ponto_substituido_nao_gera_alerta_e_segue_o_fluxo(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $this->fakeCadastro();
        $this->validadorDevolve(ConfirmacaoPonto::Substituido);

        $request = $this->setupDeferivel();

        $result = app(FluxoExpressoService::class)->decide($request->fresh());

        $this->assertSame(ViabilityRequestStatus::Deferida, $result->status);
        $this->assertNull(ExpressoQueda::query()->where('viability_request_id', $request->id)->first());
    }

    public function test_ponto_nao_verificado_nao_gera_alerta(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $this->fakeCadastro();
        $this->validadorDevolve(ConfirmacaoPonto::NaoVerificado);

        $request = $this->setupDeferivel();

        $result = app(FluxoExpressoService::class)->decide($request->fresh());

        $this->assertSame(ViabilityRequestStatus::Deferida, $result->status);
    }

    public function test_rt01_continua_tendo_precedencia_sobre_o_ponto_nao_confirmado(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $this->fakeCadastro();
        $this->validadorDevolve(ConfirmacaoPonto::NaoConfirmado);

        $request = $this->setupDeferivel('4520006');
        $request->forceFill(['cod_log' => '212'])->save();

        $result = app(FluxoExpressoService::class)->decide($request->fresh());

        $this->assertSame(ViabilityRequestStatus::Indeferida, $result->status);
        $this->assertSame(DecisionOutcome::Indeferida, $request->fresh()->decision->outcome);
    }
}

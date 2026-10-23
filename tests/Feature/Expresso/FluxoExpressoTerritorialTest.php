<?php

namespace Tests\Feature\Expresso;

use App\Enums\DecisionOutcome;
use App\Enums\EtapaMotor;
use App\Enums\GeoLayerType;
use App\Enums\PropertyLocationSource;
use App\Enums\Quadro10Permissao;
use App\Enums\RiscoMunicipal;
use App\Enums\RuleDomain;
use App\Enums\SituacaoEtapa;
use App\Enums\TipoGatilho;
use App\Enums\ViabilityRequestStatus;
use App\Events\ResultadoEmitido;
use App\Models\Cnae;
use App\Models\GeoLayer;
use App\Models\LouosQuadro10Permissao;
use App\Models\RiskClassification;
use App\Models\RuleVersion;
use App\Models\TerritorialRule;
use App\Models\ViabilityRequest;
use App\Services\Expresso\FluxoExpressoService;
use App\Services\Geo\SpatialRepository;
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

/**
 * Regras territoriais na decisão expressa (RT-01 a RT-04 — pacote SEDUR
 * 2026-08-28): RT-01 indefere com o texto legal do art. 172-A (CA-RT-01) e
 * CNAE fora da lista segue o fluxo normal (CA-RT-02); poligonal incidente
 * encaminha à análise com o motivo exato da regra e queda
 * regra_territorial (CA-RT-03/06/07); camada indisponível espera o motor na fila e,
 * esgotada a janela de tentativas, encaminha registrando a indisponibilidade
 * (CA-RT-09).
 */
class FluxoExpressoTerritorialTest extends TestCase
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

    private function service(): FluxoExpressoService
    {
        return app(FluxoExpressoService::class);
    }

    /**
     * @param  list<string>  $cnaeCodes
     */
    private function protocoladaComCnaes(array $cnaeCodes): ViabilityRequest
    {
        $solicitacao = ViabilityRequest::factory()->protocoled()->create(['used_area_m2' => 120.0]);

        foreach (array_values($cnaeCodes) as $indice => $code) {
            $cnae = Cnae::factory()->create(['code' => $code]);
            $solicitacao->cnaes()->attach($cnae->id, ['is_primary' => $indice === 0]);
        }

        $solicitacao->respostasTratamento = [11 => true];

        return $solicitacao;
    }

    private function classificarMunicipal(string $cnae, RiscoMunicipal $nivel): void
    {
        $versao = RuleVersion::vigente(RuleDomain::RiscoMunicipal)->first()
            ?? RuleVersion::factory()->create([
                'domain' => RuleDomain::RiscoMunicipal,
                'version' => 'decreto-32636-2020',
                'rules_version' => 'decreto-32636-2020',
            ]);

        RiskClassification::factory()->create([
            'rule_version_id' => $versao->id,
            'cnae_code' => $cnae,
            'risco_municipal' => $nivel,
        ]);
    }

    private function fakeBairroComZona(string $zona): void
    {
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Bairro, 'version' => 'bairro-2024']);
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Zona, 'version' => 'zoneamento-louos-2026']);

        $fake = new FakeSpatialRepository;
        $fake->setContaining(GeoLayerType::Bairro, ['id' => 1, 'properties' => ['NOME_BAIRRO' => 'Comércio']]);
        $fake->setContaining(GeoLayerType::Zona, ['id' => 2, 'properties' => ['NOME' => $zona]]);

        $this->app->instance(SpatialRepository::class, $fake);
    }

    private function seedQuadro10(string $zona, string $grupo, Quadro10Permissao $permissao): void
    {
        $version = RuleVersion::vigente(RuleDomain::LouosQuadro10)->first()
            ?? RuleVersion::factory()->create([
                'domain' => RuleDomain::LouosQuadro10,
                'version' => 'lei-9148-2016-quadro10',
                'rules_version' => 'lei-9148-2016-quadro10',
            ]);

        LouosQuadro10Permissao::factory()->create([
            'rule_version_id' => $version->id,
            'zona' => $zona,
            'grupo_uso' => $grupo,
            'subgrupo' => '',
            'permissao' => $permissao,
            'condicionante_ref' => null,
            'base_legal' => 'Quadro 10 da Lei nº 9.148/2016',
        ]);
    }

    /**
     * Setup deferível fora de qualquer regra territorial: CNAE de baixo risco
     * permitido na zona, poligonais respondendo "fora".
     */
    private function setupDeferivel(string $cnae = '4712100'): ViabilityRequest
    {
        Http::fake(fn () => Http::response(['type' => 'FeatureCollection', 'features' => []]));

        $this->fakeBairroComZona('ZR-1');
        $this->classificarMunicipal($cnae, RiscoMunicipal::BaixoA);
        $this->seedTratamentoPlanilha();
        $this->seedQuadro10('ZR-1', 'nR1', Quadro10Permissao::Permitido);

        return $this->protocoladaComCnaes([$cnae]);
    }

    // CA-RT-01
    public function test_rt01_indefere_expresso_com_texto_legal_do_art_172a(): void
    {
        Event::fake([ResultadoEmitido::class]);

        $request = $this->setupDeferivel('4520006');
        $request->forceFill(['cod_log' => '212'])->save();

        $result = $this->service()->decide($request);

        $this->assertSame(ViabilityRequestStatus::Indeferida, $result->status);

        $decisao = $request->fresh()->decision;

        $this->assertSame(DecisionOutcome::Indeferida, $decisao->outcome);
        $this->assertNull($decisao->tvl_product_number);
        $this->assertStringContainsString('art. 172-A', (string) $decisao->reason);
        $this->assertStringContainsString('Avenida Lafayette Coutinho', (string) $decisao->reason);
        $this->assertSame(
            'sedur-2026-08-28-regras-territoriais',
            $decisao->rules_versions['territorial']['regras'] ?? null,
        );
        $this->assertIsArray($request->fresh()->camadas_incidentes);
        $this->assertNotEmpty($request->fresh()->camadas_incidentes['camadas']);

        Event::assertDispatched(ResultadoEmitido::class);
    }

    public function test_rt01_indefere_pelo_logradouro_tributario_do_cadastro_quando_gis_aponta_outra_via(): void
    {
        Event::fake([ResultadoEmitido::class]);

        $this->app->instance(PropertyRegistryLookup::class, new class implements PropertyRegistryLookup
        {
            public function resolve(string $inscricao): PropertyRegistryResult
            {
                return new PropertyRegistryResult(
                    latitude: null,
                    longitude: null,
                    inscricao: $inscricao,
                    raw: ['CodigoLogradouroTributario' => '212', 'LogradouroTributario' => 'Avenida Lafayete Coutinho'],
                );
            }
        });

        $request = $this->setupDeferivel('4681802');
        $request->forceFill([
            'cod_log' => '881',
            'property_registration' => '344672',
            'address_street' => 'Simulação REGIN',
        ])->save();

        $result = $this->service()->decide($request);

        $this->assertSame(ViabilityRequestStatus::Indeferida, $result->status);

        $decisao = $request->fresh()->decision;

        $this->assertSame(DecisionOutcome::Indeferida, $decisao->outcome);
        $this->assertNull($decisao->tvl_product_number);
        $this->assertStringContainsString('art. 172-A', (string) $decisao->reason);
    }

    public function test_regras_de_poligonal_avaliam_o_ponto_do_cadastro_quando_nao_ha_poligono(): void
    {
        Event::fake([ResultadoEmitido::class]);

        $request = $this->setupDeferivel('4712100');
        $request->forceFill([
            'property_polygon_geojson' => null,
            'property_point_lat' => -12.977769,
            'property_point_lng' => -38.516404,
            'property_location_source' => PropertyLocationSource::CadastroImobiliario,
        ])->save();

        $result = $this->service()->decide($request->fresh());

        $this->assertSame(ViabilityRequestStatus::Deferida, $result->status);
    }

    // CA-RT-02
    public function test_rt01_nao_se_aplica_a_cnae_fora_da_lista_e_defere(): void
    {
        Event::fake([ResultadoEmitido::class]);

        $request = $this->setupDeferivel('4712100');
        $request->forceFill(['cod_log' => '212'])->save();

        $result = $this->service()->decide($request);

        $this->assertSame(ViabilityRequestStatus::Deferida, $result->status);
        $this->assertSame(DecisionOutcome::Deferida, $request->fresh()->decision->outcome);
    }

    // CA-RT-06 — poligonal ZEM incidente
    public function test_poligonal_zem_encaminha_analise_com_queda_territorial(): void
    {
        Http::fake(fn () => Http::response([
            'type' => 'FeatureCollection',
            'features' => [['type' => 'Feature', 'properties' => ['SUBZONA' => 'ZEM'], 'geometry' => null]],
        ]));

        // Isola a ZEM: o fake responde feição para qualquer camada, e as
        // demais poligonais ativas casariam antes (ordem de cadastro).
        TerritorialRule::query()->where('codigo', '!=', 'RT-03-ZEM')->update(['ativo' => false]);

        $request = $this->setupDeferivelSemHttp('4712100');

        $result = $this->service()->decide($request);

        $this->assertSame(ViabilityRequestStatus::EmAnalise, $result->status);
        $this->assertNull($request->fresh()->decision);

        $queda = $request->expressoQuedas()->sole();

        $this->assertSame(TipoGatilho::RegraTerritorial->value, $queda->tipo_gatilho);
        $this->assertSame('territorio', $queda->dimensao);
        $this->assertSame('Solicitação cadastrada na Zona de Exploração Mineral.', $queda->motivo);
    }

    // CA-RT-09 — camada indisponível: nem defere nem encaminha; espera o motor rodar
    public function test_camada_territorial_indisponivel_mantem_o_processo_na_fila_do_motor(): void
    {
        Http::fake(fn () => Http::response('erro', 500));

        $request = $this->setupDeferivelSemHttp('4712100');

        $result = $this->service()->decide($request);

        $this->assertSame(ViabilityRequestStatus::Protocolada, $result->status);
        $this->assertNull($result->decision);
        $this->assertStringContainsString('indisponível', (string) $result->reason);

        $request->refresh();

        $this->assertSame(ViabilityRequestStatus::Protocolada, $request->status);
        $this->assertNull($request->decision);
        $this->assertSame(0, $request->expressoQuedas()->count());

        $etapa = $request->etapas()->where('etapa', EtapaMotor::RegraTerritorial->value)->sole();

        $this->assertSame(SituacaoEtapa::Indisponivel, $etapa->situacao);
        $this->assertNotNull($etapa->proxima_tentativa_em);
        $this->assertNull($etapa->esgotada_em);
    }

    public function test_processo_que_esperava_o_geoserver_defere_quando_a_camada_volta(): void
    {
        $geoserverNoAr = false;
        Http::fake(function () use (&$geoserverNoAr) {
            return $geoserverNoAr
                ? Http::response(['type' => 'FeatureCollection', 'features' => []])
                : Http::response('erro', 500);
        });

        $request = $this->setupDeferivelSemHttp('4712100');

        $this->service()->decide($request);

        $geoserverNoAr = true;
        $this->app->forgetScopedInstances();

        $result = $this->service()->reavaliar($request);

        $this->assertSame(ViabilityRequestStatus::Deferida, $result->status);
        $this->assertSame(DecisionOutcome::Deferida, $request->fresh()->decision->outcome);
    }

    public function test_camada_indisponivel_esgotada_a_janela_encaminha_analise(): void
    {
        Http::fake(fn () => Http::response('erro', 500));

        $request = $this->setupDeferivelSemHttp('4712100');

        $this->service()->decide($request);

        $this->travel(25)->hours();
        $this->app->forgetScopedInstances();

        $result = $this->service()->reavaliar($request);

        $this->assertSame(ViabilityRequestStatus::EmAnalise, $result->status);

        $queda = $request->expressoQuedas()->sole();

        $this->assertSame(TipoGatilho::RegraTerritorial->value, $queda->tipo_gatilho);
        $this->assertStringContainsString('indisponível', (string) $queda->motivo);
    }

    /**
     * Mesmo setup deferível, sem sobrescrever o Http::fake do teste.
     */
    private function setupDeferivelSemHttp(string $cnae): ViabilityRequest
    {
        $this->fakeBairroComZona('ZR-1');
        $this->classificarMunicipal($cnae, RiscoMunicipal::BaixoA);
        $this->seedTratamentoPlanilha();
        $this->seedQuadro10('ZR-1', 'nR1', Quadro10Permissao::Permitido);

        return $this->protocoladaComCnaes([$cnae]);
    }

    public function test_regra_territorial_so_avalia_as_atividades_incluidas_na_comparacao_da_sefaz(): void
    {
        Event::fake([ResultadoEmitido::class]);

        $request = $this->setupDeferivel('4520006');
        $outra = Cnae::factory()->create(['code' => '4712100']);
        $request->cnaes()->attach($outra->id, ['is_primary' => false]);
        $this->classificarMunicipal('4712100', RiscoMunicipal::BaixoA);
        $request->forceFill([
            'cod_log' => '212',
            'comparacao_cnaes' => ['incluidas' => ['4712100'], 'excluidas' => [], 'mantidas' => ['4520006'], 'invalidas' => []],
        ])->save();

        $result = $this->service()->decide($request->fresh());

        $this->assertNotSame(ViabilityRequestStatus::Indeferida, $result->status, 'a atividade que já existia não é reavaliada pela RT-01');
    }
}

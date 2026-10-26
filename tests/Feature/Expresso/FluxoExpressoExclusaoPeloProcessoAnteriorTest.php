<?php

namespace Tests\Feature\Expresso;

use App\Enums\GeoLayerType;
use App\Enums\Quadro10Permissao;
use App\Enums\RiscoMunicipal;
use App\Enums\RuleDomain;
use App\Enums\ViabilityRequestStatus;
use App\Events\ResultadoEmitido;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\GeoLayer;
use App\Models\LouosQuadro10Permissao;
use App\Models\RiskClassification;
use App\Models\RuleVersion;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestEvento;
use App\Models\VirtualOfficeInscriptionLock;
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
 * Alteração de atividade da JUCEB (evento 244) sem intenção por CNAE e sem a
 * comparação da SEFAZ: comparada com o último processo deferido da mesma
 * empresa. Só exclusão defere sem enquadramento; inclusão segue o fluxo normal.
 */
class FluxoExpressoExclusaoPeloProcessoAnteriorTest extends TestCase
{
    use LazilyRefreshDatabase;
    use SeedsTratamentoPlanilha;

    private Company $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RiskTriggerSeeder::class);
        $this->seed(PropertyTypeSeeder::class);
        $this->seed(DecisionTextSeeder::class);
        $this->seed(TerritorialRuleSeeder::class);

        config(['viabiliza.sefaz.consulta.ativa' => false]);
        Event::fake([ResultadoEmitido::class]);
        $this->fakeCadastro(-12.971, -38.5107);

        $this->empresa = Company::factory()->create(['cnpj' => '12345678000190']);
    }

    private function cnae(string $codigo): Cnae
    {
        return Cnae::query()->where('code', $codigo)->first() ?? Cnae::factory()->create(['code' => $codigo]);
    }

    /**
     * @param  list<string>  $codigos
     */
    private function processoAnterior(array $codigos, ViabilityRequestStatus $status = ViabilityRequestStatus::Deferida): ViabilityRequest
    {
        $anterior = ViabilityRequest::factory()->protocoled()->create([
            'company_id' => $this->empresa->id,
            'property_registration' => '344672',
            'protocol_number' => 'VIA-2025-ANTERIOR',
        ]);
        $anterior->forceFill(['status' => $status])->save();

        foreach ($codigos as $indice => $codigo) {
            $anterior->cnaes()->attach($this->cnae($codigo)->id, ['is_primary' => $indice === 0]);
        }

        return $anterior;
    }

    /**
     * @param  list<string>  $codigos
     */
    private function alteracaoDaJunta(array $codigos): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->protocoled()->create([
            'company_id' => $this->empresa->id,
            'used_area_m2' => 120.0,
            'property_registration' => '344672',
        ]);

        foreach ($codigos as $indice => $codigo) {
            $request->cnaes()->attach($this->cnae($codigo)->id, ['is_primary' => $indice === 0]);
        }

        ViabilityRequestEvento::factory()->create(['viability_request_id' => $request->id, 'codigo' => '244', 'nome' => 'Alteração de atividades econômicas']);

        $request = $request->fresh();
        $request->respostasTratamento = [11 => true];

        return $request;
    }

    /**
     * @param  list<string>  $codigos
     */
    private function enquadramentoPermitido(array $codigos): void
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

        foreach ($codigos as $codigo) {
            RiskClassification::factory()->create([
                'rule_version_id' => $versao->id,
                'cnae_code' => $codigo,
                'risco_municipal' => RiscoMunicipal::BaixoA,
            ]);
        }

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
    }

    private function fakeCadastro(float $lat, float $lng): void
    {
        $this->app->instance(PropertyRegistryLookup::class, new class($lat, $lng) implements PropertyRegistryLookup
        {
            public function __construct(
                private float $lat,
                private float $lng,
            ) {}

            public function resolve(string $inscricao): PropertyRegistryResult
            {
                return new PropertyRegistryResult(
                    latitude: $this->lat,
                    longitude: $this->lng,
                    inscricao: $inscricao,
                    raw: ['CodigoLogradouroTributario' => '999'],
                );
            }
        });
    }

    public function test_so_exclusao_frente_ao_processo_anterior_defere_sem_enquadramento(): void
    {
        $this->processoAnterior(['4712100', '4520006']);
        $request = $this->alteracaoDaJunta(['4712100']);

        $resultado = app(FluxoExpressoService::class)->decide($request);

        $this->assertSame(ViabilityRequestStatus::Deferida, $resultado->status);
        $this->assertSame(['4520006'], array_column($resultado->decision->per_cnae, 'cnae'));
        $this->assertSame('excluir', $resultado->decision->per_cnae[0]['intencao']);
        $this->assertNull($request->fresh()->camadas_incidentes, 'sem zoneamento: nada novo a exercer no local');
    }

    public function test_atividade_nova_frente_ao_processo_anterior_segue_o_enquadramento(): void
    {
        $this->enquadramentoPermitido(['4712100', '5611201']);
        $this->processoAnterior(['4712100', '4520006']);
        $request = $this->alteracaoDaJunta(['4712100', '5611201']);

        $resultado = app(FluxoExpressoService::class)->decide($request);

        $this->assertSame(ViabilityRequestStatus::Deferida, $resultado->status);
        $this->assertEqualsCanonicalizing(['4712100', '5611201'], array_column($resultado->decision->per_cnae, 'cnae'));
        $this->assertArrayNotHasKey('intencao', $resultado->decision->per_cnae[0]);
    }

    public function test_sem_processo_anterior_deferido_segue_o_enquadramento(): void
    {
        $this->enquadramentoPermitido(['4712100']);
        $request = $this->alteracaoDaJunta(['4712100']);

        $resultado = app(FluxoExpressoService::class)->decide($request);

        $this->assertSame(ViabilityRequestStatus::Deferida, $resultado->status);
        $this->assertSame(['4712100'], array_column($resultado->decision->per_cnae, 'cnae'));
        $this->assertArrayNotHasKey('intencao', $resultado->decision->per_cnae[0]);
    }

    public function test_processo_anterior_indeferido_nao_conta(): void
    {
        $this->enquadramentoPermitido(['4712100']);
        $this->processoAnterior(['4712100', '4520006'], ViabilityRequestStatus::Indeferida);
        $request = $this->alteracaoDaJunta(['4712100']);

        $resultado = app(FluxoExpressoService::class)->decide($request);

        $this->assertSame(['4712100'], array_column($resultado->decision->per_cnae, 'cnae'));
        $this->assertArrayNotHasKey('intencao', $resultado->decision->per_cnae[0]);
    }

    public function test_chave_desligada_mantem_o_comportamento_anterior(): void
    {
        config(['viabiliza.expresso.exclusao_pelo_processo_anterior' => false]);
        $this->enquadramentoPermitido(['4712100']);
        $this->processoAnterior(['4712100', '4520006']);
        $request = $this->alteracaoDaJunta(['4712100']);

        $resultado = app(FluxoExpressoService::class)->decide($request);

        $this->assertSame(['4712100'], array_column($resultado->decision->per_cnae, 'cnae'));
        $this->assertArrayNotHasKey('intencao', $resultado->decision->per_cnae[0]);
    }

    public function test_sem_o_evento_244_nao_compara_com_o_processo_anterior(): void
    {
        $this->enquadramentoPermitido(['4712100']);
        $this->processoAnterior(['4712100', '4520006']);
        $request = $this->alteracaoDaJunta(['4712100']);
        $request->eventos()->delete();

        $resultado = app(FluxoExpressoService::class)->decide($request->fresh());

        $this->assertSame(['4712100'], array_column($resultado->decision->per_cnae, 'cnae'));
    }

    public function test_saida_do_cnae_gatilho_da_sede_ativa_sem_confirmacao_vai_para_analise(): void
    {
        $anterior = $this->processoAnterior(['4712100', '8211300']);
        ViabilityDecision::factory()->create(['viability_request_id' => $anterior->id, 'is_virtual_office_hq' => true, 'tvl_product_number' => 'TVL-2026-SEDE01']);
        $lock = VirtualOfficeInscriptionLock::create([
            'property_registration' => '344672',
            'sede_viability_request_id' => $anterior->id,
            'active' => true,
            'locked_at' => now(),
        ]);
        $request = $this->alteracaoDaJunta(['4712100']);

        $resultado = app(FluxoExpressoService::class)->decide($request);

        $this->assertSame(ViabilityRequestStatus::EmAnalise, $resultado->status);
        $this->assertStringContainsString('8211-3/00', (string) $resultado->reason);
        $this->assertTrue($lock->fresh()->active);
    }

    public function test_saida_do_cnae_gatilho_confirmada_defere_e_retira_a_condicao_de_sede(): void
    {
        $anterior = $this->processoAnterior(['4712100', '8211300']);
        ViabilityDecision::factory()->create(['viability_request_id' => $anterior->id, 'is_virtual_office_hq' => true, 'tvl_product_number' => 'TVL-2026-SEDE02']);
        $lock = VirtualOfficeInscriptionLock::create([
            'property_registration' => '344672',
            'sede_viability_request_id' => $anterior->id,
            'active' => true,
            'locked_at' => now(),
        ]);
        $request = $this->alteracaoDaJunta(['4712100']);
        $request->forceFill(['confirma_perda_condicao_sede' => true])->save();

        $resultado = app(FluxoExpressoService::class)->decide($request);

        $this->assertSame(ViabilityRequestStatus::Deferida, $resultado->status);
        $this->assertSame(['8211300'], array_column($resultado->decision->per_cnae, 'cnae'));
        $this->assertFalse($lock->fresh()->active);
    }
}

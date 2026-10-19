<?php

namespace Tests\Feature\Expresso;

use App\Enums\GeoLayerType;
use App\Enums\Quadro10Permissao;
use App\Enums\RiscoMunicipal;
use App\Enums\RuleDomain;
use App\Enums\SefazNotificationEvent;
use App\Enums\ViabilityRequestStatus;
use App\Events\ResultadoEmitido;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\GeoLayer;
use App\Models\LouosQuadro10Permissao;
use App\Models\RiskClassification;
use App\Models\RuleVersion;
use App\Models\SefazNotification;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestEvento;
use App\Models\VirtualOfficeInscriptionLock;
use App\Services\Expresso\FluxoExpressoService;
use App\Services\Expresso\SedeEscritorioVirtualGatilho;
use App\Services\Geo\SpatialRepository;
use App\Services\Realty\PropertyCadastroCampos;
use App\Services\Realty\PropertyRegistryLookup;
use App\Services\Realty\PropertyRegistryResult;
use App\Services\Realty\PropertyRegistryUnavailableException;
use Database\Seeders\DecisionTextSeeder;
use Database\Seeders\EscritorioVirtualCnaeSeeder;
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
 * Alteração de atividades econômicas recebida da JUCEB (evento 244): a lista
 * traz as atividades que a empresa vai exercer depois da mudança. Se a empresa
 * é a sede de escritório virtual da inscrição e o CNAE da sede (8211-3/00) não
 * está mais na lista, ao deferir ela perde a condição de sede: a inscrição é
 * desvinculada, os abrigados são avisados e a SEFAZ é comunicada.
 */
class AlteracaoAtividadeJucebSedeTest extends TestCase
{
    use LazilyRefreshDatabase;
    use SeedsTratamentoPlanilha;

    private const INSCRICAO = '344672';

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

    private function sedeAtiva(Company $titular): VirtualOfficeInscriptionLock
    {
        $sede = ViabilityRequest::factory()->protocoled()->create([
            'company_id' => $titular->id,
            'property_registration' => self::INSCRICAO,
            'protocol_number' => 'VIA-2026-SEDE01',
        ]);
        ViabilityDecision::factory()->create([
            'viability_request_id' => $sede->id,
            'is_virtual_office_hq' => true,
            'tvl_product_number' => 'TVL-2026-SEDE01',
        ]);

        return VirtualOfficeInscriptionLock::create([
            'property_registration' => self::INSCRICAO,
            'sede_viability_request_id' => $sede->id,
            'active' => true,
            'locked_at' => now(),
        ]);
    }

    private function alteracaoDaJunta(Company $empresa, string $codigoDoEvento = '244'): ViabilityRequest
    {
        $this->fakeCadastro(-12.971, -38.5107);
        $request = $this->setupDeferivel();
        $this->zonasPorLatitude();
        $request->forceFill(['company_id' => $empresa->id])->save();
        ViabilityRequestEvento::factory()->create([
            'viability_request_id' => $request->id,
            'codigo' => $codigoDoEvento,
            'nome' => 'Alteração de atividades econômicas (principal e secundárias)',
        ]);

        return $request->fresh();
    }

    public function test_sede_que_tira_o_cnae_8211_perde_a_condicao_ao_deferir(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $titular = Company::factory()->create();
        $lock = $this->sedeAtiva($titular);
        $request = $this->alteracaoDaJunta($titular);

        $resultado = app(FluxoExpressoService::class)->decide($request);

        $this->assertSame(ViabilityRequestStatus::Deferida, $resultado->status);
        $this->assertFalse($lock->fresh()->active, 'a inscrição deixa de estar vinculada à sede');
        $this->assertTrue(
            SefazNotification::query()->where('event', SefazNotificationEvent::SedePerdeuCondicao->value)->exists(),
            'a SEFAZ é comunicada',
        );
    }

    public function test_se_a_lista_mantem_o_cnae_8211_a_sede_continua(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $titular = Company::factory()->create();
        $lock = $this->sedeAtiva($titular);
        $request = $this->alteracaoDaJunta($titular);
        $gatilho = app(SedeEscritorioVirtualGatilho::class)->cnaeGatilho();
        $request->cnaes()->attach(Cnae::factory()->create(['code' => $gatilho])->id, ['is_primary' => false]);

        app(FluxoExpressoService::class)->decide($request->fresh());

        $this->assertTrue($lock->fresh()->active);
    }

    public function test_outro_evento_da_junta_nao_derruba_a_sede(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $titular = Company::factory()->create();
        $lock = $this->sedeAtiva($titular);
        $request = $this->alteracaoDaJunta($titular, '101');

        app(FluxoExpressoService::class)->decide($request);

        $this->assertTrue($lock->fresh()->active);
    }

    public function test_empresa_que_nao_e_a_titular_da_sede_nao_a_derruba(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $lock = $this->sedeAtiva(Company::factory()->create());
        $request = $this->alteracaoDaJunta(Company::factory()->create());

        app(FluxoExpressoService::class)->decide($request);

        $this->assertTrue($lock->fresh()->active);
    }

    public function test_indeferida_nao_derruba_a_sede(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $titular = Company::factory()->create();
        $lock = $this->sedeAtiva($titular);
        $request = $this->alteracaoDaJunta($titular);
        $this->app->instance(PropertyRegistryLookup::class, new class implements PropertyRegistryLookup
        {
            public function resolve(string $inscricao): PropertyRegistryResult
            {
                return new PropertyRegistryResult(null, null, $inscricao, cadastro: new PropertyCadastroCampos(...array_merge(PropertyCadastroCampos::vazios()->toArray(), ['situacao_cadastral' => '1'])));
            }
        });

        $resultado = app(FluxoExpressoService::class)->decide($request);

        $this->assertSame(ViabilityRequestStatus::Indeferida, $resultado->status);
        $this->assertTrue($lock->fresh()->active);
    }

    /**
     * Empresa abrigada: já tem produto de abrigado na inscrição, cuja sede está ativa.
     */
    private function abrigadaNaInscricao(Company $abrigada): void
    {
        $anterior = ViabilityRequest::factory()->protocoled()->create([
            'company_id' => $abrigada->id,
            'property_registration' => self::INSCRICAO,
            'protocol_number' => 'VIA-2026-ABRIG1',
        ]);
        ViabilityDecision::factory()->create([
            'viability_request_id' => $anterior->id,
            'is_virtual_office_tenant' => true,
            'tvl_product_number' => 'TVL-2026-ABRIG1',
        ]);
    }

    public function test_abrigada_que_traz_atividade_fora_do_anexo_b_vai_para_analise(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $this->seed(EscritorioVirtualCnaeSeeder::class);
        $this->sedeAtiva(Company::factory()->create());
        $abrigada = Company::factory()->create();
        $this->abrigadaNaInscricao($abrigada);
        $request = $this->alteracaoDaJunta($abrigada);

        $resultado = app(FluxoExpressoService::class)->decide($request);

        $this->assertSame(ViabilityRequestStatus::EmAnalise, $resultado->status);
        $this->assertStringContainsString('Anexo B', (string) $resultado->reason);
        $this->assertStringContainsString('4712-1/00', (string) $resultado->reason);
    }

    public function test_empresa_que_nao_e_abrigada_nao_e_barrada_pelo_anexo_b(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $this->seed(EscritorioVirtualCnaeSeeder::class);
        $this->sedeAtiva(Company::factory()->create());
        $request = $this->alteracaoDaJunta(Company::factory()->create());

        $resultado = app(FluxoExpressoService::class)->decide($request);

        $this->assertStringNotContainsString('Anexo B', (string) $resultado->reason);
    }
}

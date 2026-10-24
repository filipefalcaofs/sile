<?php

namespace Tests\Feature\Expresso;

use App\Enums\GeoLayerType;
use App\Enums\Quadro10Permissao;
use App\Enums\RiscoMunicipal;
use App\Enums\RuleDomain;
use App\Enums\SituacaoEtapa;
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
use App\Services\Realty\PropertyRegistryUnavailableException;
use App\Services\Sefaz\SefazViabilidadeConsultaGateway;
use Database\Seeders\DecisionTextSeeder;
use Database\Seeders\PropertyTypeSeeder;
use Database\Seeders\RiskTriggerSeeder;
use Database\Seeders\TerritorialRuleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\Support\SeedsTratamentoPlanilha;
use Tests\Support\Sefaz\FakeSefazViabilidadeConsultaGateway;
use Tests\TestCase;

/**
 * Alteração de atividade da JUCEB (evento 244) comparada com a viabilidade
 * vinculada ao CNPJ na SEFAZ: incluídas passam pelo enquadramento, excluídas
 * defere sem taxa, código inválido vai para a análise.
 */
class FluxoExpressoAlteracaoComparacaoTest extends TestCase
{
    use LazilyRefreshDatabase;
    use SeedsTratamentoPlanilha;

    private const CNPJ = '12345678000190';

    private FakeSefazViabilidadeConsultaGateway $sefaz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RiskTriggerSeeder::class);
        $this->seed(PropertyTypeSeeder::class);
        $this->seed(DecisionTextSeeder::class);
        $this->seed(TerritorialRuleSeeder::class);

        $this->viabilidadeNaSefaz(['4712-1/00', '4520-0/06']);
    }

    /**
     * @param  list<string>|null  $cnaes  null = CNPJ sem viabilidade vinculada
     */
    private function viabilidadeNaSefaz(?array $cnaes, string $numero = 'VIA-2026-77'): void
    {
        config(['viabiliza.sefaz.consulta.ativa' => true]);

        $this->sefaz = new FakeSefazViabilidadeConsultaGateway;

        if ($cnaes !== null) {
            $this->sefaz->com(self::CNPJ, $numero, $cnaes);
        }

        $this->app->instance(SefazViabilidadeConsultaGateway::class, $this->sefaz);
    }

    private function comoAlteracaoDaJunta(ViabilityRequest $request, ?Company $empresa = null): ViabilityRequest
    {
        $request->forceFill(['company_id' => ($empresa ?? Company::factory()->create(['cnpj' => self::CNPJ]))->id])->save();
        ViabilityRequestEvento::factory()->create(['viability_request_id' => $request->id, 'codigo' => '244', 'nome' => 'Alteração de atividades econômicas']);

        return $request->fresh();
    }

    private function mantida(ViabilityRequest $request, string $codigo, string $secao = 'Seção de teste'): void
    {
        $request->cnaes()->attach(Cnae::factory()->create(['code' => $codigo, 'section_description' => $secao])->id, ['is_primary' => false]);
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

    public function test_so_exclusao_defere_sem_taxa_e_sem_consultar_o_zoneamento(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $this->fakeCadastro(-12.971, -38.5107);
        $request = $this->setupDeferivel('4712100');
        $request = $this->comoAlteracaoDaJunta($request);

        $resultado = app(FluxoExpressoService::class)->decide($request);

        $this->assertSame(ViabilityRequestStatus::Deferida, $resultado->status);
        $this->assertSame(['4520006'], $request->fresh()->comparacao_cnaes['excluidas']);
        $this->assertSame('4520006', $resultado->decision->per_cnae[0]['cnae']);
        $this->assertSame('excluir', $resultado->decision->per_cnae[0]['intencao']);
        $this->assertNull($request->fresh()->camadas_incidentes, 'sem zoneamento: nada novo a exercer no local');
    }

    public function test_inclusao_defere_enquadrando_so_a_atividade_incluida(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $this->fakeCadastro(-12.971, -38.5107);
        $request = $this->setupDeferivel('5611201');
        $this->mantida($request, '4712100');
        $request = $this->comoAlteracaoDaJunta($request);

        $resultado = app(FluxoExpressoService::class)->decide($request);

        $this->assertSame(ViabilityRequestStatus::Deferida, $resultado->status);
        $this->assertSame(['5611201'], array_column($resultado->decision->per_cnae, 'cnae'));
        $this->assertSame(['5611201'], $request->fresh()->comparacao_cnaes['incluidas']);
    }

    public function test_inclusao_e_exclusao_na_mesma_solicitacao_prevalece_o_fluxo_da_inclusao(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $this->fakeCadastro(-12.971, -38.5107);
        $request = $this->setupDeferivel('5611201');
        $this->mantida($request, '4712100');
        $request = $this->comoAlteracaoDaJunta($request);

        $resultado = app(FluxoExpressoService::class)->decide($request);

        $comparacao = $request->fresh()->comparacao_cnaes;
        $this->assertSame(['5611201'], $comparacao['incluidas']);
        $this->assertSame(['4520006'], $comparacao['excluidas']);
        $this->assertSame(['5611201'], array_column($resultado->decision->per_cnae, 'cnae'), 'a exclusão não vira linha da decisão de inclusão');
    }

    public function test_codigo_invalido_vai_para_analise_com_o_codigo_no_motivo(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $this->fakeCadastro(-12.971, -38.5107);
        $request = $this->setupDeferivel('4712100');
        $this->mantida($request, '9999999', 'REGIN');
        $request = $this->comoAlteracaoDaJunta($request);

        $resultado = app(FluxoExpressoService::class)->decide($request);

        $this->assertSame(ViabilityRequestStatus::EmAnalise, $resultado->status);
        $this->assertStringContainsString('9999-9/99', (string) $resultado->reason);
    }

    public function test_cnpj_sem_viabilidade_trata_tudo_como_incluido(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $this->viabilidadeNaSefaz(null);
        $this->fakeCadastro(-12.971, -38.5107);
        $request = $this->comoAlteracaoDaJunta($this->setupDeferivel('4712100'));

        $resultado = app(FluxoExpressoService::class)->decide($request);

        $this->assertSame(ViabilityRequestStatus::Deferida, $resultado->status);
        $this->assertTrue($request->fresh()->comparacao_cnaes['sem_viabilidade']);
        $this->assertSame(['4712100'], array_column($resultado->decision->per_cnae, 'cnae'));
    }

    public function test_pedido_identico_a_viabilidade_defere_sem_inclusao_nem_taxa(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $this->viabilidadeNaSefaz(['4712-1/00']);
        $this->fakeCadastro(-12.971, -38.5107);
        $request = $this->comoAlteracaoDaJunta($this->setupDeferivel('4712100'));

        $resultado = app(FluxoExpressoService::class)->decide($request);

        $this->assertSame(ViabilityRequestStatus::Deferida, $resultado->status);
        $this->assertSame([], $request->fresh()->comparacao_cnaes['incluidas']);
        $this->assertSame([], $request->fresh()->comparacao_cnaes['excluidas']);
        $this->assertNull($request->fresh()->camadas_incidentes);
    }

    public function test_deferimento_mantem_o_numero_da_viabilidade_existente(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $this->viabilidadeNaSefaz(['4712-1/00'], 'TVL-3000123');
        $this->fakeCadastro(-12.971, -38.5107);
        $request = $this->setupDeferivel('5611201');
        $this->mantida($request, '4712100');
        $request = $this->comoAlteracaoDaJunta($request);

        $resultado = app(FluxoExpressoService::class)->decide($request);

        $this->assertSame(ViabilityRequestStatus::Deferida, $resultado->status);
        $this->assertSame('TVL-3000123', $resultado->decision->tvl_product_number);
        $this->assertSame(3000123, $resultado->decision->numeroSefaz());
    }

    public function test_so_exclusao_tambem_mantem_o_numero_da_viabilidade_existente(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $this->viabilidadeNaSefaz(['4712-1/00', '4520-0/06'], 'TVL-3000124');
        $this->fakeCadastro(-12.971, -38.5107);
        $request = $this->comoAlteracaoDaJunta($this->setupDeferivel('4712100'));

        $resultado = app(FluxoExpressoService::class)->decide($request);

        $this->assertSame('TVL-3000124', $resultado->decision->tvl_product_number);
    }

    public function test_numero_que_ja_pertence_a_outra_decisao_gera_numero_novo(): void
    {
        Event::fake([ResultadoEmitido::class]);
        ViabilityDecision::factory()->create([
            'viability_request_id' => ViabilityRequest::factory()->protocoled()->create(['protocol_number' => 'VIA-2026-ORIGEM'])->id,
            'tvl_product_number' => 'TVL-3000125',
        ]);
        $this->viabilidadeNaSefaz(['4712-1/00'], 'TVL-3000125');
        $this->fakeCadastro(-12.971, -38.5107);
        $request = $this->setupDeferivel('5611201');
        $this->mantida($request, '4712100');
        $request = $this->comoAlteracaoDaJunta($request);

        $resultado = app(FluxoExpressoService::class)->decide($request);

        $this->assertNotSame('TVL-3000125', $resultado->decision->tvl_product_number);
        $this->assertMatchesRegularExpression('/^TVL-\d+$/', (string) $resultado->decision->tvl_product_number);
    }

    public function test_sem_viabilidade_vinculada_gera_numero_novo(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $this->viabilidadeNaSefaz(null);
        $this->fakeCadastro(-12.971, -38.5107);
        $request = $this->comoAlteracaoDaJunta($this->setupDeferivel('4712100'));

        $resultado = app(FluxoExpressoService::class)->decide($request);

        $this->assertMatchesRegularExpression('/^TVL-\d+$/', (string) $resultado->decision->tvl_product_number);
    }

    private function sefazForaDoAr(): void
    {
        $this->sefaz->foraDoAr(self::CNPJ);
    }

    public function test_sefaz_fora_do_ar_dentro_da_janela_deixa_o_processo_aguardando(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $this->sefazForaDoAr();
        $this->fakeCadastro(-12.971, -38.5107);
        $request = $this->comoAlteracaoDaJunta($this->setupDeferivel('4712100'));

        $resultado = app(FluxoExpressoService::class)->decide($request);

        $this->assertSame(ViabilityRequestStatus::Protocolada, $resultado->status);
        $this->assertNull($resultado->decision);
        $etapa = $request->etapas()->where('etapa', 'consulta_viabilidade_sefaz')->sole();
        $this->assertSame(SituacaoEtapa::Indisponivel, $etapa->situacao);
        $this->assertNotNull($etapa->proxima_tentativa_em);
        $this->assertNull($etapa->esgotada_em);
    }

    public function test_sefaz_volta_e_o_processo_segue_normalmente(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $this->sefazForaDoAr();
        $this->fakeCadastro(-12.971, -38.5107);
        $request = $this->comoAlteracaoDaJunta($this->setupDeferivel('4712100'));
        app(FluxoExpressoService::class)->decide($request);

        $this->sefaz->voltou(self::CNPJ);
        $resultado = app(FluxoExpressoService::class)->decide($request->fresh());

        $this->assertSame(ViabilityRequestStatus::Deferida, $resultado->status);
        $this->assertSame(SituacaoEtapa::Ok, $request->etapas()->where('etapa', 'consulta_viabilidade_sefaz')->sole()->situacao);
    }

    public function test_apos_24_horas_indefere_por_estabilidade_sistemica_e_orienta_nova_solicitacao(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $this->sefazForaDoAr();
        $this->fakeCadastro(-12.971, -38.5107);
        $request = $this->comoAlteracaoDaJunta($this->setupDeferivel('4712100'));
        app(FluxoExpressoService::class)->decide($request);

        $this->travel(24)->hours();
        $resultado = app(FluxoExpressoService::class)->decide($request->fresh());

        $this->assertSame(ViabilityRequestStatus::Indeferida, $resultado->status);
        $this->assertStringContainsString('estabilidade sistêmica', (string) $resultado->decision->reason);
        $this->assertStringContainsString('nova solicitação', (string) $resultado->decision->reason);
        $this->assertNull($resultado->decision->tvl_product_number);
        Event::assertDispatched(ResultadoEmitido::class);
    }

    public function test_sede_que_sai_da_atividade_gatilho_so_pela_comparacao_perde_a_condicao(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $this->viabilidadeNaSefaz(['4712-1/00', '8211-3/00']);
        $this->fakeCadastro(-12.971, -38.5107);
        $request = $this->setupDeferivel('4712100');
        $empresa = Company::factory()->create(['cnpj' => self::CNPJ]);
        $sede = ViabilityRequest::factory()->protocoled()->create([
            'company_id' => $empresa->id,
            'property_registration' => '344672',
            'protocol_number' => 'VIA-2026-SEDE01',
        ]);
        ViabilityDecision::factory()->create(['viability_request_id' => $sede->id, 'is_virtual_office_hq' => true, 'tvl_product_number' => 'TVL-2026-SEDE01']);
        $lock = VirtualOfficeInscriptionLock::create([
            'property_registration' => '344672',
            'sede_viability_request_id' => $sede->id,
            'active' => true,
            'locked_at' => now(),
        ]);
        $request = $this->comoAlteracaoDaJunta($request, $empresa);

        $resultado = app(FluxoExpressoService::class)->decide($request);

        $this->assertSame(['8211300'], $request->fresh()->comparacao_cnaes['excluidas']);
        $this->assertSame(ViabilityRequestStatus::Deferida, $resultado->status);
        $this->assertFalse($lock->fresh()->active, 'a sede que sai da atividade gatilho perde a condição');
    }

    public function test_consulta_desligada_mantem_o_comportamento_anterior(): void
    {
        Event::fake([ResultadoEmitido::class]);
        config(['viabiliza.sefaz.consulta.ativa' => false]);
        $this->fakeCadastro(-12.971, -38.5107);
        $request = $this->comoAlteracaoDaJunta($this->setupDeferivel('4712100'));

        $resultado = app(FluxoExpressoService::class)->decide($request);

        $this->assertSame(ViabilityRequestStatus::Deferida, $resultado->status);
        $this->assertNull($request->fresh()->comparacao_cnaes);
        $this->assertSame(['4712100'], array_column($resultado->decision->per_cnae, 'cnae'));
    }
}

<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Enums\GeoLayerType;
use App\Enums\Quadro10Permissao;
use App\Enums\RiscoMunicipal;
use App\Enums\RuleDomain;
use App\Enums\ViabilityRequestStatus;
use App\Events\EncaminhadoParaAnalise;
use App\Events\ResultadoEmitido;
use App\Models\Cnae;
use App\Models\GeoLayer;
use App\Models\LouosQuadro10Permissao;
use App\Models\LouosQuadro7Faixa;
use App\Models\RiskClassification;
use App\Models\RuleVersion;
use App\Models\ViabilityRequest;
use App\Services\Expresso\FluxoExpressoService;
use App\Services\Expresso\SedeEscritorioVirtualGatilho;
use App\Services\Geo\SpatialRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\TestCase;

class GatilhoSedeTest extends TestCase
{
    use RefreshDatabase;

    public function test_aplica_quando_cnae_8211_e_quer_ser_sede(): void
    {
        $cnae = Cnae::factory()->create(['code' => '8211-3/00']);
        $req = ViabilityRequest::factory()->create(['wants_virtual_office_hq' => true]);
        $req->cnaes()->attach($cnae, ['is_primary' => true]);

        $this->assertTrue(app(SedeEscritorioVirtualGatilho::class)->aplica($req->fresh()));
    }

    public function test_nao_aplica_sem_a_flag_mesmo_com_cnae(): void
    {
        $cnae = Cnae::factory()->create(['code' => '8211-3/00']);
        $req = ViabilityRequest::factory()->create(['wants_virtual_office_hq' => false]);
        $req->cnaes()->attach($cnae, ['is_primary' => true]);

        $this->assertFalse(app(SedeEscritorioVirtualGatilho::class)->aplica($req->fresh()));
    }

    /**
     * Integração (RN-EV-01): uma solicitação em TUDO elegível para o expresso
     * (baixo risco, zona permitida no Quadro 10/7 — cenário de
     * EncaminhamentoAnaliseTest::protocoladaDeferivel) MAS com o CNAE gatilho
     * (8211-3/00) e "será sede? = Sim" NÃO deve deferir — o
     * FluxoExpressoService::decide() precisa desviar para
     * encaminharAnalise() ANTES de emitir(): status final em_analise, SEM
     * ViabilityDecision e SEM ResultadoEmitido (só EncaminhadoParaAnalise).
     */
    public function test_gatilho_sede_desvia_solicitacao_elegivel_para_expresso_para_a_analise(): void
    {
        Event::fake([ResultadoEmitido::class, EncaminhadoParaAnalise::class]);

        $request = $this->protocoladaElegivelParaExpressoComSede();

        app(FluxoExpressoService::class)->decide($request);

        $fresh = $request->fresh();
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $fresh->status);
        $this->assertNull($fresh->decision()->first());

        Event::assertDispatched(EncaminhadoParaAnalise::class);
        Event::assertNotDispatched(ResultadoEmitido::class);
    }

    /**
     * Mesmo cenário de app/Services/Expresso/FluxoExpressoService.php's
     * decisão (permitido/Quadro10/Quadro7/risco baixo) de
     * tests/Feature/Analise/EncaminhamentoAnaliseTest::protocoladaDeferivel,
     * mas com o CNAE gatilho da sede + wants_virtual_office_hq=true.
     */
    private function protocoladaElegivelParaExpressoComSede(string $cnae = '8211300'): ViabilityRequest
    {
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Bairro, 'version' => 'bairro-2024']);
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Zona, 'version' => 'zoneamento-louos-2026']);

        $fake = new FakeSpatialRepository;
        $fake->setContaining(GeoLayerType::Bairro, ['id' => 1, 'properties' => ['NOME_BAIRRO' => 'Comércio']]);
        $fake->setContaining(GeoLayerType::Zona, ['id' => 2, 'properties' => ['NOME' => 'ZR-1']]);
        $this->app->instance(SpatialRepository::class, $fake);

        $versaoRisco = RuleVersion::vigente(RuleDomain::RiscoMunicipal)->first()
            ?? RuleVersion::factory()->create([
                'domain' => RuleDomain::RiscoMunicipal,
                'version' => 'decreto-32636-2020',
                'rules_version' => 'decreto-32636-2020',
            ]);
        RiskClassification::factory()->create([
            'rule_version_id' => $versaoRisco->id,
            'cnae_code' => $cnae,
            'risco_municipal' => RiscoMunicipal::BaixoA,
        ]);

        $versaoQuadro7 = RuleVersion::vigente(RuleDomain::LouosQuadro7)->first()
            ?? RuleVersion::factory()->create([
                'domain' => RuleDomain::LouosQuadro7,
                'version' => 'lei-9148-2016-quadro7',
                'rules_version' => 'lei-9148-2016-quadro7',
            ]);
        LouosQuadro7Faixa::factory()->create([
            'rule_version_id' => $versaoQuadro7->id,
            'cnae_code' => $cnae,
            'grupo' => 'nR1',
            'subgrupo' => 'nR1-01',
            'area_min' => 0,
            'area_max' => null,
        ]);

        $versaoQuadro10 = RuleVersion::vigente(RuleDomain::LouosQuadro10)->first()
            ?? RuleVersion::factory()->create([
                'domain' => RuleDomain::LouosQuadro10,
                'version' => 'lei-9148-2016-quadro10',
                'rules_version' => 'lei-9148-2016-quadro10',
            ]);
        LouosQuadro10Permissao::factory()->create([
            'rule_version_id' => $versaoQuadro10->id,
            'zona' => 'ZR-1',
            'grupo_uso' => 'nR1',
            'subgrupo' => '',
            'permissao' => Quadro10Permissao::Permitido,
            'condicionante_ref' => null,
            'base_legal' => 'Quadro 10 da Lei nº 9.148/2016',
        ]);

        $solicitacao = ViabilityRequest::factory()->protocoled()->create([
            'used_area_m2' => 120.0,
            'wants_virtual_office_hq' => true,
        ]);
        $cnaeModel = Cnae::factory()->create(['code' => $cnae]);
        $solicitacao->cnaes()->attach($cnaeModel->id, ['is_primary' => true]);

        return $solicitacao;
    }
}

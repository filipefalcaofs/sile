<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Enums\DecisionOutcome;
use App\Enums\GeoLayerType;
use App\Enums\Quadro10Permissao;
use App\Enums\RiscoMunicipal;
use App\Enums\RuleDomain;
use App\Enums\ViabilityRequestStatus;
use App\Events\ResultadoEmitido;
use App\Models\Cnae;
use App\Models\GeoLayer;
use App\Models\LouosQuadro10Permissao;
use App\Models\LouosQuadro7Faixa;
use App\Models\RiskClassification;
use App\Models\RuleVersion;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Models\VirtualOfficeInscriptionLock;
use App\Services\Expresso\FluxoExpressoService;
use App\Services\Geo\SpatialRepository;
use Database\Seeders\EscritorioVirtualCnaeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\TestCase;

/**
 * Produto do ABRIGADO de escritório virtual (RN-EV-05): quando a inscrição tem
 * uma SEDE ativa e os CNAEs estão na Lista EV vigente, a decisão do abrigado
 * grava is_virtual_office_tenant=true e virtual_office_hq_tvl_number = o nº TVL
 * da sede ("End. Virtual - TVL Nº"). Sem sede na inscrição → tenant false, sem
 * referência de TVL. Exercita o fluxo expresso real (FluxoExpressoService).
 */
class ProdutoAbrigadoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Lista EV vigente (8211-3/00, 6204-0/00, etc. — snapshot SEDUR).
        $this->seed(EscritorioVirtualCnaeSeeder::class);
    }

    public function test_abrigado_grava_tenant_e_tvl_da_sede(): void
    {
        Event::fake([ResultadoEmitido::class]);

        // SEDE deferida com produto (TVL) + trava a inscrição 'X'.
        $sede = ViabilityRequest::factory()->protocoled()->create([
            'property_registration' => 'X',
            'protocol_number' => 'VIA-2026-SEDE01',
        ]);
        ViabilityDecision::factory()->create([
            'viability_request_id' => $sede->id,
            'is_virtual_office_hq' => true,
            'tvl_product_number' => 'TVL-2026-SEDE01',
        ]);
        VirtualOfficeInscriptionLock::create([
            'property_registration' => 'X',
            'sede_viability_request_id' => $sede->id,
            'active' => true,
            'locked_at' => now(),
        ]);

        // Abrigado na inscrição 'X' com CNAE da Lista EV (6204-0/00) que defere no expresso.
        $abrigado = $this->setupDeferivel('6204000', 'X');

        $result = app(FluxoExpressoService::class)->decide($abrigado);

        $this->assertSame(ViabilityRequestStatus::Deferida, $result->status);
        $decision = $abrigado->fresh()->decision;
        $this->assertSame(DecisionOutcome::Deferida, $decision->outcome);
        $this->assertTrue($decision->is_virtual_office_tenant);
        $this->assertSame('TVL-2026-SEDE01', $decision->virtual_office_hq_tvl_number);
    }

    public function test_solicitacao_sem_sede_nao_e_abrigado(): void
    {
        Event::fake([ResultadoEmitido::class]);

        // Inscrição sem sede ativa → não é abrigado.
        $comum = $this->setupDeferivel('8888881', null);

        $result = app(FluxoExpressoService::class)->decide($comum);

        $this->assertSame(ViabilityRequestStatus::Deferida, $result->status);
        $decision = $comum->fresh()->decision;
        $this->assertSame(DecisionOutcome::Deferida, $decision->outcome);
        $this->assertFalse($decision->is_virtual_office_tenant);
        $this->assertNull($decision->virtual_office_hq_tvl_number);
    }

    /**
     * Setup deferível: um CNAE de baixo risco (expresso) permitido na zona.
     */
    private function setupDeferivel(string $cnae, ?string $propertyRegistration): ViabilityRequest
    {
        $this->fakeBairroComZona('ZR-1');
        $this->classificarMunicipal($cnae, RiscoMunicipal::BaixoA);
        $this->seedQuadro7($cnae, 'nR1', 'nR1-01');
        $this->seedQuadro10('ZR-1', 'nR1', Quadro10Permissao::Permitido);

        $solicitacao = ViabilityRequest::factory()->protocoled()->create([
            'used_area_m2' => 120.0,
            'property_registration' => $propertyRegistration,
        ]);
        $cnaeModel = Cnae::factory()->create(['code' => $cnae]);
        $solicitacao->cnaes()->attach($cnaeModel->id, ['is_primary' => true]);

        return $solicitacao;
    }

    private function versaoRiscoMunicipal(): RuleVersion
    {
        return RuleVersion::vigente(RuleDomain::RiscoMunicipal)->first()
            ?? RuleVersion::factory()->create([
                'domain' => RuleDomain::RiscoMunicipal,
                'version' => 'decreto-32636-2020',
                'rules_version' => 'decreto-32636-2020',
            ]);
    }

    private function classificarMunicipal(string $cnae, RiscoMunicipal $nivel): void
    {
        RiskClassification::factory()->create([
            'rule_version_id' => $this->versaoRiscoMunicipal()->id,
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

    private function seedQuadro7(string $cnae, string $grupo, string $subgrupo): void
    {
        $version = RuleVersion::vigente(RuleDomain::LouosQuadro7)->first()
            ?? RuleVersion::factory()->create([
                'domain' => RuleDomain::LouosQuadro7,
                'version' => 'lei-9148-2016-quadro7',
                'rules_version' => 'lei-9148-2016-quadro7',
            ]);

        LouosQuadro7Faixa::factory()->create([
            'rule_version_id' => $version->id,
            'cnae_code' => $cnae,
            'grupo' => $grupo,
            'subgrupo' => $subgrupo,
            'area_min' => 0,
            'area_max' => null,
        ]);
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
}

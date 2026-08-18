<?php

namespace Tests\Feature\Expresso;

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
use App\Models\ViabilityRequest;
use App\Services\Expresso\FluxoExpressoService;
use App\Services\Geo\SpatialRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\TestCase;

/**
 * Decisão automática do fluxo expresso (HU-074/075/076, RN-009): com a zona
 * oficial disponível e todos os CNAEs encaminhados ao expresso, o
 * FluxoExpressoService DEFERE (todos permitido/permitido_com_condicoes) gerando
 * o número TVL, ou INDEFERE (algum nao_permitido) sem TVL — sempre criando a
 * ViabilityDecision imutável (per_cnae/rules_versions/fundamentacao),
 * transicionando a solicitação e disparando ResultadoEmitido após o commit.
 *
 * Os motores rodam em SQLite com FakeSpatialRepository + dados versionados via
 * factory (Quadro 7/10 da LOUOS, risco municipal do Decreto 32.636/2020) —
 * mesma lógica do fluxo oficial, sem PostGIS.
 */
class FluxoExpressoDecisaoTest extends TestCase
{
    use RefreshDatabase;

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

    /**
     * Território com BAIRRO e ZONA identificados — o motor LOUOS consolida
     * permitido/permitido_com_condicoes/nao_permitido a partir do Quadro 10.
     */
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

    /**
     * Setup deferível: um CNAE de baixo risco (expresso) permitido na zona.
     */
    private function setupDeferivel(string $cnae = '8888881'): ViabilityRequest
    {
        $this->fakeBairroComZona('ZR-1');
        $this->classificarMunicipal($cnae, RiscoMunicipal::BaixoA);
        $this->seedQuadro7($cnae, 'nR1', 'nR1-01');
        $this->seedQuadro10('ZR-1', 'nR1', Quadro10Permissao::Permitido);

        return $this->protocoladaComCnaes([$cnae]);
    }

    public function test_todos_permitido_defere_com_tvl_e_dispara_evento(): void
    {
        // HU-074 / RN-009: CNAE expresso permitido na zona → DEFERE. Cria a
        // ViabilityDecision (outcome deferida) com número TVL, transiciona para
        // deferida e dispara ResultadoEmitido após o commit.
        Event::fake([ResultadoEmitido::class]);
        $request = $this->setupDeferivel();

        $result = $this->service()->decide($request);

        $this->assertTrue($result->emitted);
        $this->assertNotNull($result->decision);
        $this->assertSame(ViabilityRequestStatus::Deferida, $result->status);

        $this->assertDatabaseCount('viability_decisions', 1);
        $decision = $request->fresh()->decision;
        $this->assertSame(DecisionOutcome::Deferida, $decision->outcome);
        $this->assertSame('expresso', $decision->flow);
        $this->assertSame('permitido', $decision->consolidated_result);
        $this->assertNotNull($decision->tvl_product_number);
        $this->assertStringStartsWith('TVL-', $decision->tvl_product_number);
        $this->assertNull($decision->decided_by_user_id);

        $this->assertSame(ViabilityRequestStatus::Deferida, $request->fresh()->status);
        $this->assertSame(1, $request->transitions()->where('to_status', ViabilityRequestStatus::Deferida)->count());

        Event::assertDispatched(
            ResultadoEmitido::class,
            fn (ResultadoEmitido $e): bool => $e->request->is($request) && $e->decision->is($decision),
        );
    }

    public function test_um_cnae_nao_permitido_indefere_sem_tvl(): void
    {
        // HU-075 / RN-009: dois CNAEs expressos, um proibido na zona → o pior
        // caso governa: INDEFERE o processo inteiro, sem número TVL.
        Event::fake([ResultadoEmitido::class]);
        $this->fakeBairroComZona('ZR-1');
        $this->classificarMunicipal('8888881', RiscoMunicipal::BaixoA);
        $this->classificarMunicipal('8888883', RiscoMunicipal::BaixoA);
        $this->seedQuadro7('8888881', 'nR1', 'nR1-01');
        $this->seedQuadro7('8888883', 'nR3', 'nR3-01');
        $this->seedQuadro10('ZR-1', 'nR1', Quadro10Permissao::Permitido);
        $this->seedQuadro10('ZR-1', 'nR3', Quadro10Permissao::Proibido);

        $request = $this->protocoladaComCnaes(['8888881', '8888883']);

        $result = $this->service()->decide($request);

        $this->assertTrue($result->emitted);
        $this->assertSame(ViabilityRequestStatus::Indeferida, $result->status);

        $decision = $request->fresh()->decision;
        $this->assertSame(DecisionOutcome::Indeferida, $decision->outcome);
        $this->assertSame('nao_permitido', $decision->consolidated_result);
        $this->assertNull($decision->tvl_product_number);
        $this->assertSame(ViabilityRequestStatus::Indeferida, $request->fresh()->status);

        Event::assertDispatched(ResultadoEmitido::class);
    }

    public function test_permitido_com_condicoes_defere(): void
    {
        // HU-074 / RN-006/009: permitido condicionado na zona → permitido_com_
        // condicoes → DEFERE (com condicionantes na fundamentação), com TVL.
        Event::fake([ResultadoEmitido::class]);
        $this->fakeBairroComZona('ZR-1');
        $this->classificarMunicipal('8888881', RiscoMunicipal::BaixoA);
        $this->seedQuadro7('8888881', 'nR1', 'nR1-01');
        $this->seedQuadro10('ZR-1', 'nR1', Quadro10Permissao::PermitidoCondicionado);

        $request = $this->protocoladaComCnaes(['8888881']);

        $result = $this->service()->decide($request);

        $this->assertSame(ViabilityRequestStatus::Deferida, $result->status);
        $decision = $request->fresh()->decision;
        $this->assertSame(DecisionOutcome::Deferida, $decision->outcome);
        $this->assertSame('permitido_com_condicoes', $decision->consolidated_result);
        $this->assertNotNull($decision->tvl_product_number);
    }

    public function test_per_cnae_rules_versions_e_fundamentacao_persistidos(): void
    {
        // RN-005: a decisão imutável guarda o veredito por CNAE, as versões das
        // regras da época e a fundamentação legal consolidada — fonte do PDF
        // (Fase 10) e da explicabilidade (Fase 12).
        Event::fake([ResultadoEmitido::class]);
        $request = $this->setupDeferivel();

        $this->service()->decide($request);
        $decision = $request->fresh()->decision;

        $this->assertCount(1, $decision->per_cnae);
        $item = $decision->per_cnae[0];
        $this->assertSame('8888881', $item['cnae']);
        $this->assertArrayHasKey('tendencia', $item);
        $this->assertArrayHasKey('fluxo', $item);
        $this->assertArrayHasKey('fundamentacao', $item);

        $this->assertArrayHasKey('territorio', $decision->rules_versions);
        $this->assertArrayHasKey('louos', $decision->rules_versions);
        $this->assertArrayHasKey('risco', $decision->rules_versions);

        $this->assertIsArray($decision->fundamentacao);
        $this->assertNotEmpty($decision->fundamentacao);
    }
}

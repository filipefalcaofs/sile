<?php

namespace Tests\Feature\Expresso;

use App\Enums\DecisionOutcome;
use App\Enums\GeoLayerType;
use App\Enums\Quadro10Permissao;
use App\Enums\ResultadoViabilidade;
use App\Enums\RiscoMunicipal;
use App\Enums\RuleDomain;
use App\Enums\ViabilityRequestStatus;
use App\Events\ResultadoEmitido;
use App\Models\Cnae;
use App\Models\GeoLayer;
use App\Models\LouosQuadro10Permissao;
use App\Models\RiskClassification;
use App\Models\RuleVersion;
use App\Models\ViabilityRequest;
use App\Services\Expresso\FluxoExpressoService;
use App\Services\Geo\SpatialRepository;
use App\Services\Solicitacao\SolicitacaoViabilityResolver;
use Database\Seeders\PropertyTypeSeeder;
use Database\Seeders\RiskTriggerSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\Support\SeedsTratamentoPlanilha;
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
    use LazilyRefreshDatabase;
    use SeedsTratamentoPlanilha;

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

    private function seedTratamento(string $cnae = '', string $grupo = '', string $subgrupo = ''): void
    {
        $this->seedTratamentoPlanilha();
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
    private function setupDeferivel(string $cnae = '4712100'): ViabilityRequest
    {
        $this->fakeBairroComZona('ZR-1');
        $this->classificarMunicipal($cnae, RiscoMunicipal::BaixoA);
        $this->seedTratamento($cnae, 'nR1', 'nR1-01');
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
        $this->classificarMunicipal('4712100', RiscoMunicipal::BaixoA);
        $this->seedTratamento('4712100', 'nR1', 'nR1-01');
        $this->seedQuadro10('ZR-1', 'nR1', Quadro10Permissao::Proibido);

        $request = $this->protocoladaComCnaes(['4712100']);

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

    public function test_nao_permitido_indefere_mesmo_quando_gatilho_mandaria_para_analise(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $this->seed([RiskTriggerSeeder::class, PropertyTypeSeeder::class]);
        $this->fakeBairroComZona('ZR-1');
        $this->classificarMunicipal('4712100', RiscoMunicipal::BaixoA);
        $this->seedTratamento('4712100', 'nR1', 'nR1-01');
        $this->seedQuadro10('ZR-1', 'nR1', Quadro10Permissao::Proibido);

        $request = $this->protocoladaComCnaes(['4712100']);
        $request->forceFill([
            'tipo_imovel' => 'Galpão',
            'tipo_imovel_normalized' => 'galpao',
        ])->save();

        $result = $this->service()->decide($request);

        $this->assertTrue($result->emitted);
        $this->assertSame(ViabilityRequestStatus::Indeferida, $result->status);

        $decision = $request->fresh()->decision;
        $this->assertSame(DecisionOutcome::Indeferida, $decision->outcome);
        $this->assertSame('nao_permitido', $decision->consolidated_result);
        $this->assertNull($decision->tvl_product_number);
        Event::assertDispatched(ResultadoEmitido::class);
    }

    public function test_permitido_com_galpao_continua_na_analise(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $this->seed([RiskTriggerSeeder::class, PropertyTypeSeeder::class]);
        $this->fakeBairroComZona('ZR-1');
        $this->classificarMunicipal('4712100', RiscoMunicipal::BaixoA);
        $this->seedTratamento('4712100', 'nR1', 'nR1-01');
        $this->seedQuadro10('ZR-1', 'nR1', Quadro10Permissao::Permitido);

        $request = $this->protocoladaComCnaes(['4712100']);
        $request->forceFill([
            'tipo_imovel' => 'Galpão',
            'tipo_imovel_normalized' => 'galpao',
        ])->save();

        $result = $this->service()->decide($request);

        $this->assertSame(ViabilityRequestStatus::EmAnalise, $result->status);
        $this->assertFalse($result->emitted);
        $this->assertDatabaseCount('viability_decisions', 0);
        Event::assertNotDispatched(ResultadoEmitido::class);
    }

    public function test_medio_risco_permitido_nos_dois_quadros_defere_automaticamente(): void
    {
        // RN-041-C: Quadro 10 e 11A liberando, o deferimento automático vale
        // para baixo E médio risco. Aqui o médio vem da faixa de área da
        // planilha (0111-3/01 escritório acima de 1.250 m² → nR2-12, médio).
        Event::fake([ResultadoEmitido::class]);
        $this->fakeBairroComZona('ZR-1');
        $this->classificarMunicipal('0111301', RiscoMunicipal::BaixoA);
        $this->seedTratamento('0111301', 'nR2', 'nR2-12');
        $this->seedQuadro10('ZR-1', 'nR2', Quadro10Permissao::Permitido);

        $request = $this->protocoladaComCnaes(['0111301']);
        $request->respostasTratamento = [11 => false];
        $request->forceFill(['used_area_m2' => 1300.0])->save();

        $fresco = $request->fresh();
        $fresco->respostasTratamento = [11 => false];
        $resolvido = app(SolicitacaoViabilityResolver::class)->resolve($fresco);
        $this->assertSame('permitido', $resolvido->consolidado);
        $this->assertSame(
            'medio',
            $resolvido->por_cnae[0]['consulta']->risco->encaminhamento['nivel'],
            'A premissa do teste é o nível MÉDIO (faixa acima de 1.250 m²).',
        );

        $result = $this->service()->decide($request);

        $this->assertSame(ViabilityRequestStatus::Deferida, $result->status);
        $this->assertTrue($result->emitted);

        $decision = $request->fresh()->decision;
        $this->assertSame(DecisionOutcome::Deferida, $decision->outcome);
        $this->assertNotNull($decision->tvl_product_number);
        Event::assertDispatched(ResultadoEmitido::class);
    }

    public function test_alto_risco_por_pergunta_condicional_indefere_expresso_com_veto_locacional(): void
    {
        // RN-041-B revista pelo e-mail SEDUR 21/09/2026 (item 5): o Decreto
        // classifica o CNAE como baixo_a, mas a pergunta condicional P3 (modo
        // artesanal = NÃO) eleva o ramo a ID3-11 — ALTO na planilha
        // (industrial). Com o Quadro 10 proibindo o grupo na zona, o processo
        // é INDEFERIDO expresso: a legislação impossibilita o deferimento e
        // não se justifica a análise.
        Event::fake([ResultadoEmitido::class]);
        $this->seed([RiskTriggerSeeder::class, PropertyTypeSeeder::class]);
        $this->fakeBairroComZona('ZR-1');
        $this->classificarMunicipal('1340501', RiscoMunicipal::BaixoA);
        $this->seedTratamento('1340501', 'ID3', 'ID3-11');
        $this->seedQuadro10('ZR-1', 'ID3', Quadro10Permissao::Proibido);

        $request = $this->protocoladaComCnaes(['1340501']);
        $request->respostasTratamento = [2 => true, 3 => false];
        $request->forceFill([
            'tipo_imovel' => 'Edificação Comercial',
            'tipo_imovel_normalized' => 'edificacao_comercial',
        ])->save();

        $fresco = $request->fresh();
        $fresco->respostasTratamento = [2 => true, 3 => false];
        $this->assertSame(
            ResultadoViabilidade::NaoPermitido->value,
            app(SolicitacaoViabilityResolver::class)->resolve($fresco)->consolidado,
            'O veto locacional (Quadro 10) precisa estar presente para a prova valer.',
        );

        $result = $this->service()->decide($request);

        // E-mail SEDUR 21/09/2026 (item 5): vedado pelo Quadro 10/11A, o
        // processo é INDEFERIDO expresso mesmo com alto risco — a legislação
        // impossibilita o deferimento, não se justifica a análise.
        $this->assertSame(ViabilityRequestStatus::Indeferida, $result->status);
        $this->assertNotNull($result->decision);
        $this->assertSame(DecisionOutcome::Indeferida, $result->decision->outcome);
        $this->assertNull($result->decision->tvl_product_number);
        $this->assertTrue($result->emitted);
        Event::assertDispatched(ResultadoEmitido::class);
    }

    public function test_alto_risco_do_cnae_indefere_expresso_com_veto_locacional(): void
    {
        // RN-041-B revista pelo e-mail SEDUR 21/09/2026 (item 5): CNAE cuja
        // atividade no local é ALTO por natureza na planilha (0210-1/07 →
        // ID2-07). Com o Quadro 10 proibindo o grupo na zona, o processo é
        // INDEFERIDO expresso — o veto locacional independe do risco.
        Event::fake([ResultadoEmitido::class]);
        $this->seed([RiskTriggerSeeder::class, PropertyTypeSeeder::class]);
        $this->fakeBairroComZona('ZR-1');
        $this->classificarMunicipal('0210107', RiscoMunicipal::BaixoA);
        $this->seedTratamento('0210107', 'ID2', 'ID2-07');
        $this->seedQuadro10('ZR-1', 'ID2', Quadro10Permissao::Proibido);

        $request = $this->protocoladaComCnaes(['0210107']);
        $request->respostasTratamento = [11 => true];
        $request->forceFill([
            'tipo_imovel' => 'Edificação Comercial',
            'tipo_imovel_normalized' => 'edificacao_comercial',
        ])->save();

        $result = $this->service()->decide($request);

        $this->assertSame(ViabilityRequestStatus::Indeferida, $result->status);
        $this->assertNotNull($result->decision);
        $this->assertSame(DecisionOutcome::Indeferida, $result->decision->outcome);
        $this->assertNull($result->decision->tvl_product_number);
        $this->assertTrue($result->emitted);
        Event::assertDispatched(ResultadoEmitido::class);
    }

    public function test_alto_risco_com_ramo_expresso_da_planilha_defere_expresso(): void
    {
        // Decisão SEDUR 22/09/2026: a planilha prevalece sobre a RN-041-B.
        // Regra 51 — "Fluxo Expresso (ALTO RISCO)": P2=NÃO ≤ 1.250 m² defere
        // expresso com TVL mesmo com o CNAE alto (990018/2026).
        Event::fake([ResultadoEmitido::class]);
        $this->seed([RiskTriggerSeeder::class, PropertyTypeSeeder::class]);
        $this->fakeBairroComZona('ZR-1');
        $this->classificarMunicipal('1032501', RiscoMunicipal::BaixoA);
        $this->seedTratamentoPlanilha();
        $this->seedQuadro10('ZR-1', 'nR1', Quadro10Permissao::Permitido);

        $request = $this->protocoladaComCnaes(['1032501']);
        $request->forceFill([
            'tipo_imovel' => 'Edificação Comercial',
            'tipo_imovel_normalized' => 'edificacao_comercial',
            'used_area_m2' => 80.0,
        ])->save();

        $fresco = $request->fresh();
        $fresco->respostasTratamento = [2 => false];

        $resolvido = app(SolicitacaoViabilityResolver::class)->resolve($fresco);
        $this->assertSame('alto', $resolvido->por_cnae[0]['consulta']->risco->encaminhamento['nivel'], 'A premissa é o nível ALTO da linha 07.12.13 da regra 51.');
        $this->assertSame('expresso', $resolvido->por_cnae[0]['consulta']->risco->encaminhamento['fluxo'], 'A premissa é o ramo expresso curado da regra 51.');

        $result = $this->service()->decide($fresco);

        $this->assertSame(ViabilityRequestStatus::Deferida, $result->status);
        $this->assertTrue($result->emitted);

        $decision = $request->fresh()->decision;
        $this->assertSame(DecisionOutcome::Deferida, $decision->outcome);
        $this->assertNotNull($decision->tvl_product_number);
        Event::assertDispatched(ResultadoEmitido::class);
    }

    public function test_permitido_com_condicoes_defere(): void
    {
        // HU-074 / RN-006/009: permitido condicionado na zona → permitido_com_
        // condicoes → DEFERE (com condicionantes na fundamentação), com TVL.
        Event::fake([ResultadoEmitido::class]);
        $this->fakeBairroComZona('ZR-1');
        $this->classificarMunicipal('4712100', RiscoMunicipal::BaixoA);
        $this->seedTratamento('4712100', 'nR1', 'nR1-01');
        $this->seedQuadro10('ZR-1', 'nR1', Quadro10Permissao::PermitidoCondicionado);

        $request = $this->protocoladaComCnaes(['4712100']);

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
        $this->assertSame('4712100', $item['cnae']);
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

<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisStage;
use App\Enums\GeoLayerType;
use App\Enums\Quadro10Permissao;
use App\Enums\RiscoMunicipal;
use App\Enums\RuleDomain;
use App\Enums\ViabilityRequestStatus;
use App\Events\EncaminhadoParaAnalise;
use App\Events\ResultadoEmitido;
use App\Models\Activity;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\TestCase;

/**
 * Encaminhamento à análise técnica (HU-079): ao rotear uma solicitação para
 * em_analise, o FluxoExpressoService::encaminharAnalise — além da transição e da
 * auditoria SÍNCRONA já existentes (Fase 9) — MATERIALIZA o SLA da fila
 * (analysis_due_at, etapa distribuição, via AnalysisSlaService) e DISPARA o
 * evento de domínio EncaminhadoParaAnalise APÓS o commit (gatilho da pré-análise
 * 10-08, listener auto-descoberto). A regra de roteamento da Fase 9 NÃO muda: o
 * caminho de decisão (deferida/indeferida) segue emitindo ResultadoEmitido e
 * NUNCA EncaminhadoParaAnalise.
 *
 * Os motores rodam em SQLite com FakeSpatialRepository (sem PostGIS), com os
 * dados versionados via factory — mesma lógica do fluxo oficial.
 */
class EncaminhamentoAnaliseTest extends TestCase
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
     * Território com BAIRRO identificado e ZONA indisponível (Quadro 10 pendente
     * SEDUR) → veredito locacional pendente: o expresso encaminha à análise sem
     * decidir (anti-fachada da Fase 9).
     */
    private function fakeBairroSemZona(): void
    {
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Bairro, 'version' => 'bairro-2024']);

        $fake = new FakeSpatialRepository;
        $fake->setContaining(GeoLayerType::Bairro, ['id' => 1, 'properties' => ['NOME_BAIRRO' => 'Comércio']]);

        $this->app->instance(SpatialRepository::class, $fake);
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

    /**
     * Solicitação deferível: CNAE de baixo risco (expresso) e permitido na zona
     * (Quadro 7/10) — o caminho de DECISÃO, que NÃO encaminha à análise.
     */
    private function protocoladaDeferivel(string $cnae = '8888881'): ViabilityRequest
    {
        $this->fakeBairroComZona('ZR-1');

        RiskClassification::factory()->create([
            'rule_version_id' => $this->versaoRiscoMunicipal()->id,
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

        $solicitacao = ViabilityRequest::factory()->protocoled()->create(['used_area_m2' => 120.0]);
        $cnaeModel = Cnae::factory()->create(['code' => $cnae]);
        $solicitacao->cnaes()->attach($cnaeModel->id, ['is_primary' => true]);

        return $solicitacao;
    }

    public function test_encaminhamento_materializa_o_sla_na_etapa_de_distribuicao(): void
    {
        // HU-079 + HU-144: ao encaminhar, o prazo da etapa de distribuição é
        // materializado (analysis_due_at ≈ agora + 2 dias) e a etapa/início ficam
        // registrados — base da fila (10-14) e do badge (10-16).
        Carbon::setTestNow('2026-03-10 09:00:00');
        Event::fake([EncaminhadoParaAnalise::class]);

        $this->fakeBairroSemZona();
        $this->classificarMunicipal('2222222', RiscoMunicipal::BaixoA);
        $request = $this->protocoladaComCnaes(['2222222']);

        $this->service()->decide($request);

        $fresh = $request->fresh();
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $fresh->status);
        $this->assertSame(AnalysisStage::Distribuicao, $fresh->analysis_stage);
        $this->assertNotNull($fresh->analysis_stage_started_at);
        $this->assertNotNull($fresh->analysis_due_at);
        $this->assertSame(
            Carbon::now()->addDays(2)->toDateTimeString(),
            $fresh->analysis_due_at->toDateTimeString(),
        );
        // A distribuição (setor/analista) é a Task 2 — ainda não atribuído.
        $this->assertNull($fresh->sector_id);
        $this->assertNull($fresh->assigned_user_id);

        Carbon::setTestNow();
    }

    public function test_encaminhamento_dispara_evento_e_mantem_auditoria_sincrona(): void
    {
        // HU-079/140: o EncaminhadoParaAnalise é o gatilho (after-commit) da
        // pré-análise (10-08). A auditoria síncrona 'expresso'/'decisao' (result
        // 'analise') é gravada na própria transação e NÃO depende do evento.
        Event::fake([EncaminhadoParaAnalise::class]);

        $this->fakeBairroSemZona();
        $this->classificarMunicipal('2222222', RiscoMunicipal::BaixoA);
        $request = $this->protocoladaComCnaes(['2222222']);

        $this->service()->decide($request);

        Event::assertDispatched(
            EncaminhadoParaAnalise::class,
            fn (EncaminhadoParaAnalise $event): bool => $event->request->is($request),
        );

        $activity = Activity::query()
            ->where('log_name', 'expresso')
            ->where('event', 'decisao')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity, 'A auditoria síncrona do encaminhamento deve ser gravada mesmo com o evento fakeado.');
        $this->assertSame('analise', $activity->result);
        $this->assertSame($request->id, $activity->properties['viability_request_id']);
    }

    public function test_decisao_deferida_nao_dispara_encaminhado_para_analise(): void
    {
        // Regressão da Fase 9: o caminho de decisão emite ResultadoEmitido e
        // NUNCA o gatilho da análise — o roteamento do expresso fica intacto.
        Event::fake([ResultadoEmitido::class, EncaminhadoParaAnalise::class]);

        $request = $this->protocoladaDeferivel();

        $this->service()->decide($request);

        $this->assertSame(ViabilityRequestStatus::Deferida, $request->fresh()->status);

        Event::assertDispatched(ResultadoEmitido::class);
        Event::assertNotDispatched(EncaminhadoParaAnalise::class);
    }
}

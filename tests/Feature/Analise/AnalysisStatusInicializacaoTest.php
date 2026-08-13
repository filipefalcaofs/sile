<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisStage;
use App\Enums\AnalysisStatus;
use App\Enums\GeoLayerType;
use App\Enums\RiscoMunicipal;
use App\Enums\RuleDomain;
use App\Models\Cnae;
use App\Models\GeoLayer;
use App\Models\RiskClassification;
use App\Models\RuleVersion;
use App\Models\ViabilityRequest;
use App\Services\Expresso\FluxoExpressoService;
use App\Services\Geo\SpatialRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\TestCase;

/**
 * Task 5: ao encaminhar a solicitação à análise técnica (mesmo ponto do
 * forceFill que materializa analysis_stage=distribuicao, HU-144), o eixo
 * operacional (analysis_status) deve nascer em para_distribuir. Setup
 * espelha EncaminhamentoAnaliseTest — bairro identificado e ZONA
 * indisponível (Quadro 10 pendente SEDUR), o que rotina o expresso para a
 * análise técnica sem decidir (anti-fachada).
 */
class AnalysisStatusInicializacaoTest extends TestCase
{
    use RefreshDatabase;

    private function service(): FluxoExpressoService
    {
        return app(FluxoExpressoService::class);
    }

    private function fakeBairroSemZona(): void
    {
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Bairro, 'version' => 'bairro-2024']);

        $fake = new FakeSpatialRepository;
        $fake->setContaining(GeoLayerType::Bairro, ['id' => 1, 'properties' => ['NOME_BAIRRO' => 'Comércio']]);

        $this->app->instance(SpatialRepository::class, $fake);
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

    public function test_encaminhamento_inicializa_analysis_status_em_para_distribuir(): void
    {
        $this->fakeBairroSemZona();
        $this->classificarMunicipal('2222222', RiscoMunicipal::BaixoA);
        $request = $this->protocoladaComCnaes(['2222222']);

        $this->service()->decide($request);

        $fresh = $request->fresh();

        // Prova que o encaminhamento passou pelo mesmo forceFill que materializa
        // o SLA da etapa de distribuição (Fase 9/HU-144).
        $this->assertSame(AnalysisStage::Distribuicao, $fresh->analysis_stage);

        // Invariante da Task 5: o eixo operacional nasce em para_distribuir.
        $this->assertSame(AnalysisStatus::ParaDistribuir, $fresh->analysis_status);
    }
}

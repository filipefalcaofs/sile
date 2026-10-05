<?php

namespace Tests\Feature\Expresso;

use App\Enums\AnalysisStage;
use App\Enums\AnalysisStatus;
use App\Enums\GeoLayerType;
use App\Enums\Quadro10Permissao;
use App\Enums\RiscoMunicipal;
use App\Enums\RuleDomain;
use App\Enums\ViabilityRequestStatus;
use App\Events\EncaminhadoParaAnalise;
use App\Events\ResultadoEmitido;
use App\Models\Cnae;
use App\Models\ExpressoQueda;
use App\Models\GeoLayer;
use App\Models\LouosQuadro10Permissao;
use App\Models\RiskClassification;
use App\Models\RuleVersion;
use App\Models\ViabilityRequest;
use App\Services\Expresso\FluxoExpressoService;
use App\Services\Geo\SpatialRepository;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\Support\SeedsTratamentoPlanilha;
use Tests\TestCase;

/**
 * Reavaliação do veredito (spec 2026-10-05): processo protocolado decide como
 * sempre; processo em análise que ninguém assumiu refaz a resolução; análise
 * assumida nunca é sobrescrita.
 */
class FluxoExpressoReavaliarTest extends TestCase
{
    use LazilyRefreshDatabase;
    use SeedsTratamentoPlanilha;

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

    private function emAnaliseNaoAssumida(ViabilityRequest $request): ViabilityRequest
    {
        $request->forceFill([
            'status' => ViabilityRequestStatus::EmAnalise,
            'analysis_status' => AnalysisStatus::ParaDistribuir,
            'analysis_stage' => AnalysisStage::Distribuicao,
        ])->save();

        return $request;
    }

    public function test_processo_em_analise_nao_assumido_e_deferido_quando_os_dados_completam(): void
    {
        Event::fake([ResultadoEmitido::class, EncaminhadoParaAnalise::class]);
        $request = $this->emAnaliseNaoAssumida($this->setupDeferivel());

        $resultado = app(FluxoExpressoService::class)->reavaliar($request);

        $request->refresh();

        $this->assertSame(ViabilityRequestStatus::Deferida, $request->status);
        $this->assertNull($request->analysis_status);
        $this->assertNull($request->analysis_stage);
        $this->assertNotNull($request->decision);
        $this->assertTrue($resultado->emitted);
    }

    public function test_analise_ja_assumida_nao_e_reavaliada(): void
    {
        $request = $this->setupDeferivel();
        $request->forceFill([
            'status' => ViabilityRequestStatus::EmAnalise,
            'analysis_status' => AnalysisStatus::EmAnalise,
        ])->save();

        app(FluxoExpressoService::class)->reavaliar($request);

        $this->assertSame(ViabilityRequestStatus::EmAnalise, $request->fresh()->status);
        $this->assertDatabaseCount('viability_decisions', 0);
    }

    public function test_veredito_ainda_pendente_mantem_em_analise_sem_novo_encaminhamento(): void
    {
        Event::fake([EncaminhadoParaAnalise::class]);
        $request = $this->emAnaliseNaoAssumida($this->protocoladaComCnaes(['2222222']));
        $transicoesAntes = $request->transitions()->count();

        app(FluxoExpressoService::class)->reavaliar($request);

        $this->assertSame(ViabilityRequestStatus::EmAnalise, $request->fresh()->status);
        $this->assertSame($transicoesAntes, $request->transitions()->count());
        Event::assertNotDispatched(EncaminhadoParaAnalise::class);
    }

    public function test_reavaliacao_com_o_mesmo_motivo_nao_duplica_a_queda(): void
    {
        $request = $this->emAnaliseNaoAssumida($this->protocoladaComCnaes(['2222222']));
        $servico = app(FluxoExpressoService::class);

        $servico->reavaliar($request);
        $servico->reavaliar($request);

        $this->assertSame(1, ExpressoQueda::query()->where('viability_request_id', $request->id)->count());
    }

    public function test_processo_protocolado_segue_o_caminho_de_decisao_normal(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $request = $this->setupDeferivel();

        app(FluxoExpressoService::class)->reavaliar($request);

        $this->assertSame(ViabilityRequestStatus::Deferida, $request->fresh()->status);
    }
}

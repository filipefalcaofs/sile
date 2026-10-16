<?php

namespace Tests\Feature\Expresso;

use App\Enums\AnalysisStage;
use App\Enums\AnalysisStatus;
use App\Enums\DecisionOutcome;
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
use App\Models\RiskClassification;
use App\Models\RuleVersion;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Expresso\FluxoExpressoService;
use App\Services\Geo\SpatialRepository;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\Support\SeedsTratamentoPlanilha;
use Tests\TestCase;

/**
 * Pergunta da planilha necessária ao enquadramento que chega sem resposta
 * indefere no expresso (decisão SEDUR 15/10/2026), no protocolo e na
 * reavaliação de processo em análise que ninguém assumiu.
 */
class IndeferimentoPerguntaSemRespostaTest extends TestCase
{
    use LazilyRefreshDatabase;
    use SeedsTratamentoPlanilha;

    private const MOTIVO = 'Indeferido: o requerimento recebido não trouxe a resposta da pergunta 11 ("A atividade será desenvolvida no local?") da planilha de tratamento, necessária ao enquadramento da atividade 4712-1/00.';

    private function semResposta(string $cnae = '4712100'): ViabilityRequest
    {
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Bairro, 'version' => 'bairro-2024']);
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Zona, 'version' => 'zoneamento-louos-2026']);

        $fake = new FakeSpatialRepository;
        $fake->setContaining(GeoLayerType::Bairro, ['id' => 1, 'properties' => ['NOME_BAIRRO' => 'Comércio']]);
        $fake->setContaining(GeoLayerType::Zona, ['id' => 2, 'properties' => ['NOME' => 'ZR-1']]);
        $this->app->instance(SpatialRepository::class, $fake);

        $risco = RuleVersion::factory()->create([
            'domain' => RuleDomain::RiscoMunicipal,
            'version' => 'decreto-32636-2020',
            'rules_version' => 'decreto-32636-2020',
        ]);
        RiskClassification::factory()->create([
            'rule_version_id' => $risco->id,
            'cnae_code' => $cnae,
            'risco_municipal' => RiscoMunicipal::BaixoA,
        ]);

        $this->seedTratamentoPlanilha();

        $quadro10 = RuleVersion::factory()->create([
            'domain' => RuleDomain::LouosQuadro10,
            'version' => 'lei-9148-2016-quadro10',
            'rules_version' => 'lei-9148-2016-quadro10',
        ]);
        LouosQuadro10Permissao::factory()->create([
            'rule_version_id' => $quadro10->id,
            'zona' => 'ZR-1',
            'grupo_uso' => 'nR1',
            'subgrupo' => '',
            'permissao' => Quadro10Permissao::Permitido,
            'condicionante_ref' => null,
            'base_legal' => 'Quadro 10 da Lei nº 9.148/2016',
        ]);

        $solicitacao = ViabilityRequest::factory()->protocoled()->create(['used_area_m2' => 120.0]);
        $solicitacao->cnaes()->attach(Cnae::factory()->create(['code' => $cnae])->id, ['is_primary' => true]);
        $solicitacao->respostasTratamento = [];

        return $solicitacao;
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

    public function test_pergunta_necessaria_sem_resposta_indefere_no_expresso(): void
    {
        Event::fake([ResultadoEmitido::class, EncaminhadoParaAnalise::class]);
        $request = $this->semResposta();

        $resultado = app(FluxoExpressoService::class)->decide($request);

        $request->refresh();
        $this->assertSame(ViabilityRequestStatus::Indeferida, $request->status);
        $this->assertSame(DecisionOutcome::Indeferida, $request->decision->outcome);
        $this->assertSame(self::MOTIVO, $request->decision->reason);
        $this->assertTrue($resultado->emitted);
        Event::assertDispatched(ResultadoEmitido::class);
        Event::assertNotDispatched(EncaminhadoParaAnalise::class);
    }

    public function test_processo_em_analise_nao_assumido_e_indeferido_na_reavaliacao(): void
    {
        Event::fake([ResultadoEmitido::class, EncaminhadoParaAnalise::class]);
        $request = $this->emAnaliseNaoAssumida($this->semResposta());

        app(FluxoExpressoService::class)->decide($request);

        $request->refresh();
        $this->assertSame(ViabilityRequestStatus::Indeferida, $request->status);
        $this->assertNull($request->analysis_status);
        $this->assertSame(self::MOTIVO, $request->decision->reason);
    }

    public function test_analise_assumida_nao_e_indeferida(): void
    {
        $request = $this->semResposta();
        $request->forceFill([
            'status' => ViabilityRequestStatus::EmAnalise,
            'analysis_status' => AnalysisStatus::EmAnalise,
            'assigned_user_id' => User::factory()->create()->id,
        ])->save();

        app(FluxoExpressoService::class)->decide($request);

        $this->assertSame(ViabilityRequestStatus::EmAnalise, $request->fresh()->status);
        $this->assertDatabaseCount('viability_decisions', 0);
    }

    public function test_regra_desligada_mantem_o_encaminhamento_a_analise(): void
    {
        config(['viabiliza.expresso.indeferir_pergunta_sem_resposta' => false]);
        Event::fake([ResultadoEmitido::class, EncaminhadoParaAnalise::class]);
        $request = $this->semResposta();

        app(FluxoExpressoService::class)->decide($request);

        $this->assertSame(ViabilityRequestStatus::EmAnalise, $request->fresh()->status);
        $this->assertStringContainsString('Pergunta 11 sem resposta', (string) $request->transitions()->latest('id')->firstOrFail()->reason);
        $this->assertDatabaseCount('viability_decisions', 0);
    }
}

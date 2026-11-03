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
use App\Events\ResultadoEmitido;
use App\Models\Cnae;
use App\Models\GeoLayer;
use App\Models\LouosQuadro10Permissao;
use App\Models\RiskClassification;
use App\Models\RuleVersion;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Geo\SpatialRepository;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\Support\SeedsTratamentoPlanilha;
use Tests\TestCase;

class IndeferirPerguntaSemRespostaCommandTest extends TestCase
{
    use LazilyRefreshDatabase;
    use SeedsTratamentoPlanilha;

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

    private function naCaixaDaAnalista(ViabilityRequest $request, AnalysisStatus $status): ViabilityRequest
    {
        // O helper da planilha reanexa a pergunta 11 em todo modelo recarregado;
        // o comando recarrega do banco, então aqui as respostas voltam a vazio.
        ViabilityRequest::retrieved(function (ViabilityRequest $recarregado): void {
            $recarregado->respostasTratamento = [];
        });

        $request->forceFill([
            'status' => ViabilityRequestStatus::EmAnalise,
            'analysis_status' => $status,
            'analysis_stage' => AnalysisStage::Distribuicao,
            'assigned_user_id' => User::factory()->create()->id,
        ])->save();

        return $request;
    }

    public function test_indefere_o_processo_da_caixa_cuja_analise_nao_comecou(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $request = $this->naCaixaDaAnalista($this->semResposta(), AnalysisStatus::Analisar);

        $this->artisan('processos:indeferir-pergunta-sem-resposta')
            ->expectsOutputToContain('sem resposta — indeferido')
            ->assertSuccessful();

        $request->refresh();
        $this->assertSame(ViabilityRequestStatus::Indeferida, $request->status);
        $this->assertNull($request->analysis_status);
        $this->assertSame(DecisionOutcome::Indeferida, $request->decision->outcome);
        $this->assertStringContainsString('não trouxe a resposta da pergunta', (string) $request->decision->reason);
        Event::assertDispatched(ResultadoEmitido::class);
    }

    public function test_dry_run_nao_grava(): void
    {
        $request = $this->naCaixaDaAnalista($this->semResposta(), AnalysisStatus::Analisar);

        $this->artisan('processos:indeferir-pergunta-sem-resposta --dry-run')->assertSuccessful();

        $this->assertSame(ViabilityRequestStatus::EmAnalise, $request->fresh()->status);
        $this->assertDatabaseCount('viability_decisions', 0);
    }

    public function test_analise_ja_iniciada_nao_e_tocada(): void
    {
        $request = $this->naCaixaDaAnalista($this->semResposta(), AnalysisStatus::EmAnalise);

        $this->artisan('processos:indeferir-pergunta-sem-resposta')->assertSuccessful();

        $this->assertSame(ViabilityRequestStatus::EmAnalise, $request->fresh()->status);
        $this->assertDatabaseCount('viability_decisions', 0);
    }
}

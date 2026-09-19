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
use App\Models\RiskClassification;
use App\Models\RuleVersion;
use App\Models\ViabilityRequest;
use App\Services\Geo\SpatialRepository;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\Support\SeedsTratamentoPlanilha;
use Tests\TestCase;

/**
 * Comando de EVIDÊNCIA do fluxo expresso (`expresso:decidir {solicitacao}`):
 * roda o motor REAL (FluxoExpressoService::decide) sobre uma solicitação
 * protocolada e imprime o desfecho de ponta a ponta — status final, desfecho
 * (deferida/indeferida) e o número TVL quando defere; ou o motivo honesto
 * quando encaminha à análise (sem zona, semi-expresso, toggle off). É a
 * evidência manual/reprocesso da fase, espelhando solicitacao:protocolar.
 *
 * Sem fachada: em_analise é desfecho LEGÍTIMO (degradação honesta), sai com
 * exit 0 — não é erro; só a solicitação inexistente sai com exit 1. Os motores
 * rodam em SQLite com FakeSpatialRepository + dados versionados via factory.
 */
class ExpressoDecidirCommandTest extends TestCase
{
    use LazilyRefreshDatabase;
    use SeedsTratamentoPlanilha;

    public function test_deferimento_imprime_status_final_e_numero_tvl(): void
    {
        // HU-076 RN-007: defere → imprime DEFERIDA + o número TVL gerado.
        Event::fake([ResultadoEmitido::class]);
        $request = $this->setupDeferivel();

        $this->artisan('expresso:decidir', ['solicitacao' => $request->id])
            ->expectsOutputToContain('DEFERIDA')
            ->expectsOutputToContain('TVL-')
            ->assertExitCode(0);

        $decision = $request->fresh()->decision;
        $this->assertSame(DecisionOutcome::Deferida, $decision->outcome);
        $this->assertNotNull($decision->tvl_product_number);
    }

    public function test_indeferimento_imprime_status_sem_tvl(): void
    {
        // HU-075: indefere (zona proíbe o grupo) → imprime INDEFERIDA, sem TVL.
        Event::fake([ResultadoEmitido::class]);
        $this->fakeBairroComZona('ZR-1');
        $this->classificarMunicipal('4712100', RiscoMunicipal::BaixoA);
        $this->seedTratamento('4712100', 'nR1', 'nR1-01');
        $this->seedQuadro10('ZR-1', 'nR1', Quadro10Permissao::Proibido);
        $request = $this->protocoladaComCnaes(['4712100']);

        $this->artisan('expresso:decidir', ['solicitacao' => $request->id])
            ->expectsOutputToContain('INDEFERIDA')
            ->assertExitCode(0);

        $this->assertSame(ViabilityRequestStatus::Indeferida, $request->fresh()->status);
        $this->assertNull($request->fresh()->decision->tvl_product_number);
    }

    public function test_sem_zona_encaminha_a_analise_com_exit_zero_honesto(): void
    {
        // ANTI-FACHADA: sem a zona (Quadro 10 indisponível) o motor NÃO decide —
        // encaminha à análise (em_analise) com o motivo honesto e sai com exit 0
        // (não é erro). Nenhuma ViabilityDecision é criada.
        $this->fakeBairroSemZona();
        $this->classificarMunicipal('4712100', RiscoMunicipal::BaixoA);
        $this->seedTratamento('4712100', 'nR1', 'nR1-01');
        $request = $this->protocoladaComCnaes(['4712100']);

        $this->artisan('expresso:decidir', ['solicitacao' => $request->id])
            ->expectsOutputToContain('EM ANÁLISE')
            ->expectsOutputToContain('zona urbanística pendente')
            ->assertExitCode(0);

        $this->assertSame(ViabilityRequestStatus::EmAnalise, $request->fresh()->status);
        $this->assertDatabaseCount('viability_decisions', 0);
    }

    public function test_solicitacao_inexistente_sai_com_erro(): void
    {
        $this->artisan('expresso:decidir', ['solicitacao' => 999999])
            ->expectsOutputToContain('não encontrada')
            ->assertExitCode(1);
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

    private function setupDeferivel(string $cnae = '4712100'): ViabilityRequest
    {
        $this->fakeBairroComZona('ZR-1');
        $this->classificarMunicipal($cnae, RiscoMunicipal::BaixoA);
        $this->seedTratamento($cnae, 'nR1', 'nR1-01');
        $this->seedQuadro10('ZR-1', 'nR1', Quadro10Permissao::Permitido);

        return $this->protocoladaComCnaes([$cnae]);
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

    private function fakeBairroSemZona(): void
    {
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Bairro, 'version' => 'bairro-2024']);

        $fake = new FakeSpatialRepository;
        $fake->setContaining(GeoLayerType::Bairro, ['id' => 1, 'properties' => ['NOME_BAIRRO' => 'Comércio']]);

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
}

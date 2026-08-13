<?php

namespace Tests\Feature\Expresso;

use App\Enums\DecisionOutcome;
use App\Enums\GeoLayerType;
use App\Enums\Quadro10Permissao;
use App\Enums\RiscoMunicipal;
use App\Enums\RuleDomain;
use App\Enums\ViabilityRequestStatus;
use App\Events\ResultadoEmitido;
use App\Jobs\DecidirFluxoExpressoJob;
use App\Models\Cnae;
use App\Models\GeoLayer;
use App\Models\LouosQuadro10Permissao;
use App\Models\LouosQuadro7Faixa;
use App\Models\RiskClassification;
use App\Models\RuleVersion;
use App\Models\ViabilityRequest;
use App\Services\Expresso\FluxoExpressoService;
use App\Services\Geo\SpatialRepository;
use App\Support\Audit\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Mockery;
use RuntimeException;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\TestCase;

/**
 * DecidirFluxoExpressoJob (HU-076 FA-03) — envelopa o FluxoExpressoService::decide
 * (motor real, 09-05) num job de fila com retry/timeout/backoff parametrizados
 * (config/sile.php). A decisão roda FORA do request do protocolo (resiliência):
 *
 *  - executa de verdade (anti-fachada): com a zona disponível, decide e grava a
 *    ViabilityDecision via o service real — não simula;
 *  - idempotente: solicitação que já saiu de protocolada é no-op (o guard do job
 *    nem chama o service, que de qualquer forma re-checa sob lock);
 *  - falha esgotada vai para failed e AUDITA (RN-002), nunca silenciosa.
 */
class DecidirFluxoExpressoJobTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_define_retry_timeout_e_backoff_a_partir_da_config(): void
    {
        // FA-03: a resiliência é parametrizada (config/sile.php expresso.job.*),
        // não hardcoded — espelha o ImportRedesimJob.
        config([
            'sile.expresso.job.tries' => 5,
            'sile.expresso.job.timeout' => 90,
            'sile.expresso.job.backoff' => [10, 20, 40],
        ]);

        $job = new DecidirFluxoExpressoJob(1);

        $this->assertSame(5, $job->tries);
        $this->assertSame(90, $job->timeout);
        $this->assertSame([10, 20, 40], $job->backoff);
    }

    public function test_executa_a_decisao_real_via_service(): void
    {
        // ANTI-FACHADA: o job realmente decide pelo motor real — com a zona
        // disponível e o CNAE expresso permitido, DEFERE de verdade (cria a
        // ViabilityDecision imutável e transiciona a solicitação).
        Event::fake([ResultadoEmitido::class]);
        $request = $this->setupDeferivel();

        (new DecidirFluxoExpressoJob($request->id))->handle(app(FluxoExpressoService::class));

        $this->assertDatabaseCount('viability_decisions', 1);
        $decision = $request->fresh()->decision;
        $this->assertSame(DecisionOutcome::Deferida, $decision->outcome);
        $this->assertSame(ViabilityRequestStatus::Deferida, $request->fresh()->status);
    }

    public function test_no_op_quando_solicitacao_ja_saiu_de_protocolada(): void
    {
        // Idempotência: uma solicitação que já saiu de protocolada (ex.: já em
        // análise) não é redecidida — o guard do job nem invoca o service.
        $request = $this->protocoladaComCnaes(['2222222']);
        $request->forceFill(['status' => ViabilityRequestStatus::EmAnalise])->save();

        $service = Mockery::mock(FluxoExpressoService::class);
        $service->shouldNotReceive('decide');

        (new DecidirFluxoExpressoJob($request->id))->handle($service);

        $this->assertDatabaseCount('viability_decisions', 0);
    }

    public function test_no_op_quando_solicitacao_nao_existe_mais(): void
    {
        // Robustez: id que não existe mais (apagado/limpeza) → no-op sem erro,
        // sem chamar o service.
        $service = Mockery::mock(FluxoExpressoService::class);
        $service->shouldNotReceive('decide');

        (new DecidirFluxoExpressoJob(999999))->handle($service);

        $this->assertDatabaseCount('viability_decisions', 0);
    }

    public function test_failed_audita_a_falha_sem_silenciar(): void
    {
        // RN-002 (HU-076 FA-03): job que esgota as tentativas NÃO falha em
        // silêncio — registra auditoria 'expresso' result 'falha' com o id e o erro.
        $request = $this->protocoladaComCnaes(['2222222']);

        (new DecidirFluxoExpressoJob($request->id))->failed(new RuntimeException('falha simulada na decisão'));

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'expresso',
            'event' => 'decisao-falha',
            'result' => 'falha',
        ]);

        $activity = DB::table('activity_log')->where('log_name', 'expresso')->where('result', 'falha')->first();
        $this->assertNotNull($activity);
        $props = json_decode((string) $activity->properties, true);
        $this->assertSame($request->id, $props['viability_request_id']);
        $this->assertStringContainsString('falha simulada na decisão', (string) $props['erro']);
    }

    public function test_failed_audita_via_audit_service(): void
    {
        // A auditoria da falha passa pelo AuditService (RN-002), não por escrita
        // crua — garante o enriquecimento de origem padrão.
        $spy = Mockery::spy(AuditService::class);
        $this->app->instance(AuditService::class, $spy);

        (new DecidirFluxoExpressoJob(123))->failed(new RuntimeException('erro'));

        $spy->shouldHaveReceived('log')->once();
    }
}

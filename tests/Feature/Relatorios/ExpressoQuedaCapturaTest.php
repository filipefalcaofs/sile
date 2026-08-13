<?php

namespace Tests\Feature\Relatorios;

use App\Enums\GeoLayerType;
use App\Enums\RiscoMunicipal;
use App\Enums\RuleDomain;
use App\Enums\TipoGatilho;
use App\Enums\ViabilityRequestStatus;
use App\Events\EncaminhadoParaAnalise;
use App\Events\ResultadoEmitido;
use App\Models\Activity;
use App\Models\Cnae;
use App\Models\ExpressoQueda;
use App\Models\GeoLayer;
use App\Models\RiskClassification;
use App\Models\RuleVersion;
use App\Models\ViabilityRequest;
use App\Services\Expresso\FluxoExpressoService;
use App\Services\Geo\SpatialRepository;
use App\Services\Solicitacao\ResolvedViability;
use App\Services\Solicitacao\SolicitacaoViabilityResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\TestCase;

/**
 * Captura ESTRUTURADA da queda ao analista (HU-145), ADITIVA em
 * {@see FluxoExpressoService::encaminharAnalise}: no momento do encaminhamento à
 * análise técnica, cada CNAE que caiu vira uma {@see ExpressoQueda} com o gatilho
 * semi-expresso e a dimensão decisiva REAIS do motor de risco.
 *
 * Anti-fachada (RN-001): quando o motor degradou (toggle off / $resolved null),
 * grava-se 1 linha de nível-processo com tipo_gatilho/cnae NULL e o motivo —
 * NUNCA um gatilho inventado. E a captura é ADITIVA: a transição, a auditoria e
 * o roteamento das Fases 9/10 permanecem intactos (anti-regressão).
 */
class ExpressoQuedaCapturaTest extends TestCase
{
    use RefreshDatabase;

    private function service(): FluxoExpressoService
    {
        return app(FluxoExpressoService::class);
    }

    /**
     * Substitui o resolver por um stub que devolve um {@see ResolvedViability}
     * cravado — a forma honesta de exercitar a EXTRAÇÃO do gatilho (o wiring do
     * gatilhosContexto vem no EP07; hoje o resolver sempre o envia vazio).
     */
    private function fakeResolver(ResolvedViability $resolved): void
    {
        $this->app->instance(SolicitacaoViabilityResolver::class, new class($resolved) extends SolicitacaoViabilityResolver
        {
            public function __construct(private ResolvedViability $stub) {}

            public function resolve(ViabilityRequest $request): ResolvedViability
            {
                return $this->stub;
            }
        });
    }

    public function test_queda_por_gatilho_grava_tipo_gatilho_estruturado(): void
    {
        // HU-145: 1 CNAE caindo por gatilho semi-expresso real → grava a queda
        // com o TipoGatilho, a dimensão decisiva e o motivo do encaminhamento
        // (lidos de consulta_array.risco.encaminhamento). O EncaminhadoParaAnalise
        // é faketado: a pré-análise (10-08) re-resolve o resolver e não é o alvo
        // deste teste — a captura ocorre ANTES do dispatch, dentro da transação.
        Event::fake([ResultadoEmitido::class, EncaminhadoParaAnalise::class]);

        $resolved = new ResolvedViability(
            por_cnae: [[
                'cnae' => '4721102',
                'cnae_formatado' => '4721-1/02',
                'is_primary' => true,
                'tendencia' => 'permitido',
                'tendencia_label' => 'Permitido',
                'fluxo' => 'analise',
                'consulta_array' => [
                    'risco' => [
                        'encaminhamento' => [
                            'fluxo' => 'analise',
                            'dimensao_decisiva' => 'municipal',
                            'motivo' => 'CNAE em ZEIS especial encaminhado à análise técnica',
                            'gatilhos_acionados' => [
                                ['codigo' => TipoGatilho::ZeisEspecial->value, 'motivo' => 'ZEIS especial'],
                            ],
                        ],
                    ],
                ],
            ]],
            consolidado: 'permitido',
            rules_versions: [],
            ponto: null,
            area_m2: null,
        );
        $this->fakeResolver($resolved);

        $request = ViabilityRequest::factory()->protocoled()->create(['used_area_m2' => 120.0]);

        $result = $this->service()->decide($request);

        $this->assertSame(ViabilityRequestStatus::EmAnalise, $result->status);
        $this->assertDatabaseCount('expresso_quedas', 1);

        $queda = ExpressoQueda::query()->firstOrFail();
        $this->assertSame($request->id, $queda->viability_request_id);
        $this->assertSame('4721102', $queda->cnae);
        $this->assertSame(TipoGatilho::ZeisEspecial->value, $queda->tipo_gatilho);
        $this->assertSame('municipal', $queda->dimensao);
        $this->assertSame('CNAE em ZEIS especial encaminhado à análise técnica', $queda->motivo);
    }

    public function test_queda_com_motor_degradado_grava_linha_nivel_processo_sem_gatilho(): void
    {
        // ANTI-FACHADA (RN-001): toggle do expresso desligado → $resolved null. O
        // motor não classificou nada; grava 1 linha de nível-processo com
        // cnae/tipo_gatilho/dimensao NULL e o motivo — gatilho NUNCA inventado.
        config(['sile.features.fluxo_expresso' => false]);
        Event::fake([ResultadoEmitido::class]);

        $request = ViabilityRequest::factory()->protocoled()->create();

        $result = $this->service()->decide($request);

        $this->assertSame(ViabilityRequestStatus::EmAnalise, $result->status);
        $this->assertDatabaseCount('expresso_quedas', 1);

        $queda = ExpressoQueda::query()->firstOrFail();
        $this->assertSame($request->id, $queda->viability_request_id);
        $this->assertNull($queda->cnae);
        $this->assertNull($queda->tipo_gatilho);
        $this->assertNull($queda->dimensao);
        $this->assertSame('fluxo expresso desativado', $queda->motivo);
    }

    public function test_queda_real_inelegivel_grava_cnae_sem_gatilho_inventado_e_preserva_encaminhamento(): void
    {
        // Resolver REAL (motores de risco/LOUOS): um CNAE de alto risco cai à
        // análise (sem gatilho de contexto) → grava a queda com o cnae e
        // tipo_gatilho NULL (honesto: não há gatilho, só nível de risco). E a
        // captura é ADITIVA — a transição em_analise e a auditoria de decisão
        // das Fases 9/10 PERMANECEM (anti-regressão).
        $this->fakeBairroSemZona();
        $this->classificarMunicipal('3333333', RiscoMunicipal::Alto);
        Event::fake([ResultadoEmitido::class]);

        $request = $this->protocoladaComCnaes(['3333333']);

        $result = $this->service()->decide($request);

        // Comportamento das Fases 9/10 intacto (aditividade):
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $result->status);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $request->fresh()->status);
        $this->assertSame(1, $request->transitions()->where('to_status', ViabilityRequestStatus::EmAnalise)->count());
        $this->assertDatabaseCount('viability_decisions', 0);
        $this->assertNotNull(
            Activity::query()->where('log_name', 'expresso')->where('event', 'decisao')->first(),
        );
        Event::assertNotDispatched(ResultadoEmitido::class);

        // A NOVA captura coexiste com o encaminhamento, honesta (sem gatilho):
        $this->assertDatabaseCount('expresso_quedas', 1);
        $queda = ExpressoQueda::query()->firstOrFail();
        $this->assertSame('3333333', $queda->cnae);
        $this->assertNull($queda->tipo_gatilho);
        $this->assertSame('municipal', $queda->dimensao);
        $this->assertStringContainsString('análise', mb_strtolower($queda->motivo));
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

    private function classificarMunicipal(string $cnae, RiscoMunicipal $nivel): void
    {
        $version = RuleVersion::vigente(RuleDomain::RiscoMunicipal)->first()
            ?? RuleVersion::factory()->create([
                'domain' => RuleDomain::RiscoMunicipal,
                'version' => 'decreto-32636-2020',
                'rules_version' => 'decreto-32636-2020',
            ]);

        RiskClassification::factory()->create([
            'rule_version_id' => $version->id,
            'cnae_code' => $cnae,
            'risco_municipal' => $nivel,
        ]);
    }

    private function fakeBairroSemZona(): void
    {
        GeoLayer::factory()->vigente()->create([
            'type' => GeoLayerType::Bairro,
            'version' => 'bairro-2024',
        ]);

        $fake = new FakeSpatialRepository;
        $fake->setContaining(GeoLayerType::Bairro, [
            'id' => 1,
            'properties' => ['NOME_BAIRRO' => 'Comércio'],
        ]);

        $this->app->instance(SpatialRepository::class, $fake);
    }
}

<?php

namespace Tests\Feature\Expresso;

use App\Enums\GeoLayerType;
use App\Enums\RiscoMunicipal;
use App\Enums\RuleDomain;
use App\Enums\ViabilityRequestStatus;
use App\Events\ResultadoEmitido;
use App\Models\Activity;
use App\Models\Cnae;
use App\Models\GeoLayer;
use App\Models\RiskClassification;
use App\Models\RuleVersion;
use App\Models\ViabilityRequest;
use App\Services\Expresso\DecisionResult;
use App\Services\Expresso\FluxoExpressoService;
use App\Services\Geo\SpatialRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\TestCase;

/**
 * Elegibilidade e roteamento do motor de decisão expressa (HU-073): o
 * FluxoExpressoService::decide reexecuta o resolver FRESCO sob Cache::lock e
 * re-check de status (idempotência), e roteia para análise técnica sem emitir
 * resultado quando a lei NÃO permite o deferimento/indeferimento automático:
 *
 *  - toggle features.fluxo_expresso desligado → em_analise (comunicado);
 *  - algum CNAE encaminhado à análise (semi-expresso) → em_analise (motivo
 *    auditado), independentemente do veredito locacional;
 *  - CRÍTICO/anti-fachada: CNAE expresso porém SEM zona (veredito consolidado
 *    pendente) → em_analise, SEM criar ViabilityDecision e SEM disparar
 *    ResultadoEmitido — o motor NÃO defere/indefere sem a base oficial (Quadro
 *    10); liga sozinho quando a zona entrar (muda a carga, não a lógica);
 *  - idempotência lógica: decide() numa solicitação fora de protocolada é no-op.
 *
 * Os motores rodam em SQLite com FakeSpatialRepository (sem PostGIS), com os
 * dados versionados via factory — mesma lógica do fluxo oficial.
 */
class FluxoExpressoElegibilidadeTest extends TestCase
{
    use RefreshDatabase;

    private function service(): FluxoExpressoService
    {
        return app(FluxoExpressoService::class);
    }

    /**
     * Solicitação PROTOCOLADA com os CNAEs informados (o primeiro como
     * principal) e o polígono default (centroide em Salvador) — o estado de
     * entrada do motor de decisão.
     *
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
     * Território com BAIRRO identificado e ZONA indisponível (base de zoneamento
     * pendente SEDUR) → veredito locacional pendente (propagado do motor LOUOS).
     */
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

    public function test_toggle_desativado_encaminha_para_analise_sem_evento(): void
    {
        // HU-014 / RN-008: a funcionalidade acoplável degrada de forma
        // comunicada — desligada, nada é deferido/indeferido automaticamente.
        config(['sile.features.fluxo_expresso' => false]);
        Event::fake([ResultadoEmitido::class]);

        $request = $this->protocoladaComCnaes(['2222222']);

        $result = $this->service()->decide($request);

        $this->assertInstanceOf(DecisionResult::class, $result);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $result->status);
        $this->assertFalse($result->emitted);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $request->fresh()->status);
        $this->assertDatabaseCount('viability_decisions', 0);

        Event::assertNotDispatched(ResultadoEmitido::class);
    }

    public function test_cnae_em_analise_encaminha_semi_expresso_auditado_sem_evento(): void
    {
        // HU-073 RN-008: basta um CNAE de alto risco (encaminhado à análise) para
        // o conjunto sair do expresso (semi-expresso) — motivo auditado, sem
        // decisão e sem evento.
        $this->fakeBairroSemZona();
        $this->classificarMunicipal('3333333', RiscoMunicipal::Alto);
        Event::fake([ResultadoEmitido::class]);

        $request = $this->protocoladaComCnaes(['3333333']);

        $result = $this->service()->decide($request);

        $this->assertSame(ViabilityRequestStatus::EmAnalise, $result->status);
        $this->assertFalse($result->emitted);
        $this->assertDatabaseCount('viability_decisions', 0);

        $activity = Activity::query()
            ->where('log_name', 'expresso')
            ->where('event', 'decisao')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame('analise', $activity->result);
        $this->assertStringContainsString('análise', mb_strtolower((string) $activity->properties['motivo']));

        Event::assertNotDispatched(ResultadoEmitido::class);
    }

    public function test_critico_cnae_expresso_sem_zona_vai_para_analise_sem_decisao_nem_evento(): void
    {
        // ANTI-FACHADA (HU-073 CA-03): o CNAE é elegível ao expresso (baixo
        // risco), MAS sem a zona (Quadro 10 indisponível) o veredito consolidado
        // é pendente. O motor NÃO inventa deferimento/indeferimento: encaminha à
        // análise, sem criar ViabilityDecision e sem disparar ResultadoEmitido.
        $this->fakeBairroSemZona();
        $this->classificarMunicipal('2222222', RiscoMunicipal::BaixoA);
        Event::fake([ResultadoEmitido::class]);

        $request = $this->protocoladaComCnaes(['2222222']);

        $result = $this->service()->decide($request);

        $this->assertSame(ViabilityRequestStatus::EmAnalise, $result->status);
        $this->assertNull($result->decision);
        $this->assertFalse($result->emitted);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $request->fresh()->status);
        $this->assertDatabaseCount('viability_decisions', 0);

        Event::assertNotDispatched(ResultadoEmitido::class);
    }

    public function test_idempotencia_logica_nao_redecide_fora_de_protocolada(): void
    {
        // Idempotência (re-check sob o lock): uma solicitação que já saiu de
        // protocolada (ex.: já em análise) não é redecidida — sem 2ª transição
        // nem decisão.
        $this->fakeBairroSemZona();
        $this->classificarMunicipal('2222222', RiscoMunicipal::BaixoA);
        Event::fake([ResultadoEmitido::class]);

        $request = $this->protocoladaComCnaes(['2222222']);
        $request->forceFill(['status' => ViabilityRequestStatus::EmAnalise])->save();

        $result = $this->service()->decide($request);

        $this->assertSame(ViabilityRequestStatus::EmAnalise, $result->status);
        $this->assertFalse($result->emitted);
        $this->assertSame(0, $request->transitions()->count());
        $this->assertDatabaseCount('viability_decisions', 0);

        Event::assertNotDispatched(ResultadoEmitido::class);
    }
}

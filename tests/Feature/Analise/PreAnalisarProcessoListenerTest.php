<?php

namespace Tests\Feature\Analise;

use App\Enums\GeoLayerType;
use App\Enums\RiscoMunicipal;
use App\Enums\RuleDomain;
use App\Enums\ViabilityRequestStatus;
use App\Events\EncaminhadoParaAnalise;
use App\Listeners\PreAnalisarProcesso;
use App\Models\Cnae;
use App\Models\GeoLayer;
use App\Models\RiskClassification;
use App\Models\RuleVersion;
use App\Models\ViabilityRequest;
use App\Services\Expresso\FluxoExpressoService;
use App\Services\Geo\SpatialRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\TestCase;

/**
 * Pré-análise acionada pelo encaminhamento (HU-140): o listener AUTO-DESCOBERTO
 * PreAnalisarProcesso reage ao evento EncaminhadoParaAnalise (type-hint no handle,
 * NUNCA Event::listen — lição das Fases 8/9) e dispara o PreAnaliseService, que
 * cria a analysis_records revisão 1.
 *
 * CRÍTICO (anti-duplicação): a não-duplicação é travada por CONTAGEM — EXATAMENTE
 * 1 listener reage a EncaminhadoParaAnalise. Se alguém registrar via Event::listen
 * (além da auto-descoberta), a contagem quebra o teste.
 *
 * Anti-fachada: o gatilho REAL é o FluxoExpressoService::encaminharAnalise (Fase
 * 9), que passa a dispatch o evento após a transição em_analise — provado ponta a
 * ponta (encaminhar → revisão 1 criada). Os motores rodam em SQLite com
 * FakeSpatialRepository.
 */
class PreAnalisarProcessoListenerTest extends TestCase
{
    use RefreshDatabase;

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
     * SEDUR) → veredito pendente, sem PostGIS.
     */
    private function fakeBairroSemZona(): void
    {
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Bairro, 'version' => 'bairro-2024']);

        $fake = new FakeSpatialRepository;
        $fake->setContaining(GeoLayerType::Bairro, ['id' => 1, 'properties' => ['NOME_BAIRRO' => 'Comércio']]);

        $this->app->instance(SpatialRepository::class, $fake);
    }

    /**
     * @param  list<string>  $cnaeCodes
     */
    private function comCnaes(ViabilityRequest $solicitacao, array $cnaeCodes): ViabilityRequest
    {
        foreach (array_values($cnaeCodes) as $indice => $code) {
            $cnae = Cnae::factory()->create(['code' => $code]);
            $solicitacao->cnaes()->attach($cnae->id, ['is_primary' => $indice === 0]);
        }

        return $solicitacao;
    }

    public function test_exatamente_um_listener_auto_descoberto_reage_ao_evento(): void
    {
        // Lição das Fases 8/9: o listener é registrado SÓ por auto-descoberta
        // (type-hint do evento no handle). A CONTAGEM trava a não-duplicação — se
        // alguém adicionar um Event::listen, passam a existir 2 listeners.
        $listeners = Event::getListeners(EncaminhadoParaAnalise::class);

        $this->assertCount(1, $listeners);
    }

    public function test_disparo_do_evento_cria_a_revisao_1(): void
    {
        // O gatilho é o EVENTO: disparar EncaminhadoParaAnalise(request) aciona o
        // listener auto-descoberto, que cria a revisão 1 da ficha.
        $this->fakeBairroSemZona();
        $this->classificarMunicipal('2222222', RiscoMunicipal::BaixoA);

        $request = ViabilityRequest::factory()->protocoled()->create(['used_area_m2' => 120.0]);
        $request->forceFill(['status' => ViabilityRequestStatus::EmAnalise])->save();
        $this->comCnaes($request, ['2222222']);

        EncaminhadoParaAnalise::dispatch($request);

        $this->assertSame(1, $request->analysisRecords()->where('revision', 1)->count());
    }

    public function test_reprocessar_o_encaminhamento_nao_duplica_a_revisao(): void
    {
        // Idempotência (via service): disparar o evento 2x para o mesmo processo
        // mantém UMA revisão 1 — recalcular é ação explícita (10-09).
        $this->fakeBairroSemZona();
        $this->classificarMunicipal('2222222', RiscoMunicipal::BaixoA);

        $request = ViabilityRequest::factory()->protocoled()->create(['used_area_m2' => 120.0]);
        $request->forceFill(['status' => ViabilityRequestStatus::EmAnalise])->save();
        $this->comCnaes($request, ['2222222']);

        EncaminhadoParaAnalise::dispatch($request);
        EncaminhadoParaAnalise::dispatch($request);

        $this->assertSame(1, $request->analysisRecords()->count());
    }

    public function test_encaminhar_para_analise_cria_a_revisao_1_ponta_a_ponta(): void
    {
        // Anti-fachada: o caminho REAL — FluxoExpressoService::decide encaminha à
        // análise (CNAE expresso sem zona → veredito pendente), DISPARA o evento
        // após a transição em_analise e o listener cria a revisão 1.
        $this->fakeBairroSemZona();
        $this->classificarMunicipal('2222222', RiscoMunicipal::BaixoA);

        $request = ViabilityRequest::factory()->protocoled()->create(['used_area_m2' => 120.0]);
        $this->comCnaes($request, ['2222222']);

        app(FluxoExpressoService::class)->decide($request);

        $this->assertSame(ViabilityRequestStatus::EmAnalise, $request->fresh()->status);
        $this->assertSame(1, $request->analysisRecords()->where('revision', 1)->count());
    }

    public function test_listener_faz_type_hint_do_evento_no_handle(): void
    {
        // Garante o contrato da auto-descoberta: o handle recebe o evento por
        // type-hint (base do registro automático — sem Event::listen).
        $parametros = (new \ReflectionMethod(PreAnalisarProcesso::class, 'handle'))->getParameters();

        $this->assertCount(1, $parametros);
        $this->assertSame(
            EncaminhadoParaAnalise::class,
            $parametros[0]->getType()?->getName(),
        );
    }
}

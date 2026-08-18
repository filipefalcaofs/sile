<?php

namespace Tests\Feature\Expresso;

use App\Enums\GeoLayerType;
use App\Enums\Quadro10Permissao;
use App\Enums\RiscoMunicipal;
use App\Enums\RuleDomain;
use App\Enums\ViabilityRequestStatus;
use App\Events\ResultadoEmitido;
use App\Models\Cnae;
use App\Models\GeoLayer;
use App\Models\LouosQuadro10Permissao;
use App\Models\LouosQuadro7Faixa;
use App\Models\RiskClassification;
use App\Models\RuleVersion;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Notifications\ResultadoExpressoNotification;
use App\Services\Geo\SpatialRepository;
use App\Services\Solicitacao\ProtocolarSolicitacaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\TestCase;

/**
 * Smoke end-to-end REAL do fluxo expresso AUTOMÁTICO (HU-073 a HU-078 + HU-104/110):
 * dirige a CADEIA COMPLETA pelos serviços/eventos reais — protocolar
 * (ProtocolarSolicitacaoService) → evento SolicitacaoProtocolada → listener
 * AvaliarFluxoExpresso → DecidirFluxoExpressoJob → FluxoExpressoService::decide →
 * decisão + ResultadoEmitido + efeitos. Por isso DESLIGA o fake do job de decisão
 * ($fakeExpressoDecisionJob = false): o worker roda de verdade (fila sync), provando
 * que o gatilho decide automaticamente no protocolo, NÃO em chamada manual.
 *
 * Três caminhos reais + a degradação honesta (anti-fachada): protocolar sobre a
 * zona → DEFERE (decisão + TVL + notificação + Regin/SEFAZ pendência auditada);
 * protocolar SEM zona → em_analise SEM ViabilityDecision e SEM ResultadoEmitido
 * (assertNotDispatched) e sem notificação; protocolar com zona que PROÍBE →
 * INDEFERE (sem TVL, Regin comunica, SEFAZ ignora RN-003). Os motores rodam em
 * SQLite com FakeSpatialRepository + dados versionados via factory.
 */
class ExpressoSmokeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Worker REAL: o fake parcial do DecidirFluxoExpressoJob (TestCase base)
     * impediria a cadeia automática protocolar→decidir. Aqui exercitamos o
     * caminho real de ponta a ponta (fila sync roda o job inline).
     */
    protected bool $fakeExpressoDecisionJob = false;

    public function test_protocolo_sobre_zona_defere_automaticamente_e_dispara_efeitos(): void
    {
        // Caminho FELIZ (core value): a zona permite o CNAE expresso → o gatilho
        // decide AUTOMATICAMENTE no protocolo: DEFERE com TVL, notifica o cidadão
        // (sem anexo) e registra a pendência Regin/SEFAZ (bloqueada — Fase 13).
        Notification::fake();
        $this->fakeBairroComZona('ZR-1');
        $this->classificarMunicipal('8888881', RiscoMunicipal::BaixoA);
        $this->seedQuadro7('8888881', 'nR1', 'nR1-01');
        $this->seedQuadro10('ZR-1', 'nR1', Quadro10Permissao::Permitido);

        [$solicitacao, $requerente] = $this->protocolar('8888881');

        $solicitacao->refresh();
        $this->assertSame(ViabilityRequestStatus::Deferida, $solicitacao->status);

        $decision = $solicitacao->decision;
        $this->assertNotNull($decision, 'A solicitação sobre a zona deveria ter sido decidida automaticamente.');
        $this->assertNotNull($decision->tvl_product_number);
        $this->assertStringStartsWith('TVL-', (string) $decision->tvl_product_number);

        // Auditoria SÍNCRONA autoritativa da decisão (HU-078).
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'expresso',
            'event' => 'decisao',
            'result' => 'deferida',
        ]);

        // HU-077: notificação do resultado enviada ao requerente (sem anexo TVL).
        Notification::assertSentTo($requerente, ResultadoExpressoNotification::class);

        // HU-104/110: Regin e SEFAZ ficam como pendência auditada (bloqueado —
        // Fase 13), NUNCA "enviado" fictício.
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'integracoes',
            'event' => 'regin-parecer',
            'result' => 'bloqueado',
        ]);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'integracoes',
            'event' => 'sefaz-viabilidade',
            'result' => 'bloqueado',
        ]);
    }

    public function test_protocolo_sem_zona_vai_para_analise_sem_decisao_nem_evento(): void
    {
        // ANTI-FACHADA (o teste mais importante): sem a zona oficial, o motor NÃO
        // decide — encaminha à análise. NENHUMA ViabilityDecision, NENHUM
        // ResultadoEmitido (assertNotDispatched) e NENHUMA notificação. A
        // degradação honesta que vale em produção até a zona oficial entrar.
        Event::fake([ResultadoEmitido::class]);
        Notification::fake();
        $this->fakeBairroSemZona();
        $this->classificarMunicipal('8888881', RiscoMunicipal::BaixoA);
        $this->seedQuadro7('8888881', 'nR1', 'nR1-01');

        [$solicitacao] = $this->protocolar('8888881');

        $solicitacao->refresh();
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $solicitacao->status);
        $this->assertNull($solicitacao->decision, 'Sem zona NÃO pode haver decisão (anti-fachada).');
        $this->assertDatabaseCount('viability_decisions', 0);

        Event::assertNotDispatched(ResultadoEmitido::class);
        Notification::assertNothingSent();
    }

    public function test_protocolo_com_zona_proibida_indefere_e_sefaz_ignora(): void
    {
        // HU-075 / RN-003: a zona PROÍBE o grupo → INDEFERE (sem TVL). O Regin
        // também é comunicado (parecer nos dois casos), mas a SEFAZ IGNORA o
        // indeferimento de forma auditável (não envia).
        Notification::fake();
        $this->fakeBairroComZona('ZR-1');
        $this->classificarMunicipal('8888883', RiscoMunicipal::BaixoA);
        $this->seedQuadro7('8888883', 'nR3', 'nR3-01');
        $this->seedQuadro10('ZR-1', 'nR3', Quadro10Permissao::Proibido);

        [$solicitacao, $requerente] = $this->protocolar('8888883');

        $solicitacao->refresh();
        $this->assertSame(ViabilityRequestStatus::Indeferida, $solicitacao->status);

        $decision = $solicitacao->decision;
        $this->assertNotNull($decision);
        $this->assertNull($decision->tvl_product_number, 'Indeferimento não gera TVL.');

        Notification::assertSentTo($requerente, ResultadoExpressoNotification::class);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'integracoes',
            'event' => 'regin-parecer',
            'result' => 'bloqueado',
        ]);
        // RN-003: SEFAZ ignora o indeferimento (auditável), não envia.
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'integracoes',
            'event' => 'sefaz-viabilidade',
            'result' => 'ignorado',
        ]);
    }

    /**
     * Protocola DE VERDADE um rascunho instruído pelo serviço real — o caminho
     * que dispara SolicitacaoProtocolada e, com o worker real, a decisão.
     *
     * @return array{0: ViabilityRequest, 1: User}
     */
    private function protocolar(string $cnaeCode): array
    {
        $requerente = User::factory()->create();

        $solicitacao = ViabilityRequest::factory()->draft()->create([
            'requester_user_id' => $requerente->id,
            'created_by_user_id' => $requerente->id,
            'used_area_m2' => 120.0,
        ]);

        $cnae = Cnae::factory()->create(['code' => $cnaeCode]);
        $solicitacao->cnaes()->attach($cnae->id, ['is_primary' => true]);

        app(ProtocolarSolicitacaoService::class)->protocol($solicitacao, $requerente);

        return [$solicitacao, $requerente];
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
}

<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisPendencyStatus;
use App\Enums\DecisionOutcome;
use App\Enums\GeoLayerType;
use App\Enums\Quadro10Permissao;
use App\Enums\RiscoMunicipal;
use App\Enums\RuleDomain;
use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisRecord;
use App\Models\Cnae;
use App\Models\GeoLayer;
use App\Models\LouosQuadro10Permissao;
use App\Models\LouosQuadro7Faixa;
use App\Models\RiskClassification;
use App\Models\RuleVersion;
use App\Models\Sector;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Analise\AnaliseTecnicaDecisionService;
use App\Services\Analise\AnalysisRecordService;
use App\Services\Analise\DistribuicaoService;
use App\Services\Analise\MalhaFinaService;
use App\Services\Analise\PendenciaService;
use App\Services\Expresso\FluxoExpressoService;
use App\Services\Geo\SpatialRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\TestCase;

/**
 * Smoke end-to-end REAL do fluxo HUMANO da análise técnica (HU-079 a HU-089 +
 * HU-132/136/140): dirige a cadeia COMPLETA pelos serviços/eventos reais —
 * FluxoExpressoService::decide encaminha à análise (semi-expresso/sem zona) → o
 * listener PreAnalisarProcesso cria a ficha rev 1 → DistribuicaoService distribui/
 * assume → AnalysisRecordService preenche/finaliza (gravando a divergência
 * analista×motor) → AnaliseTecnicaDecisionService DECIDE (ViabilityDecision flow
 * 'analise_tecnica' + TVL no deferimento) → ResultadoEmitido reusa os listeners
 * da Fase 9 (Regin/SEFAZ bloqueados honestos → Fase 13). Cobre ainda o ciclo de
 * pendência (HU-083/084), a malha fina sobre um deferido (HU-136) e a degradação
 * honesta (FA-01: sem motor, o humano decide assim mesmo).
 *
 * Os motores rodam em SQLite com FakeSpatialRepository + dados versionados via
 * factory — a mesma lógica do fluxo oficial, sem PostGIS.
 */
class AnaliseSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_fluxo_humano_defere_com_divergencia_e_emite_tvl(): void
    {
        // Caminho HUMANO completo: semi-expresso (uma CNAE de risco alto torna
        // inelegível) com a zona identificada → a ficha vem PRÉ-ANALISADA (sugere
        // 4712100=deferida, 4731800=indeferida). O analista DIVERGE da sugestão
        // de indeferimento da 2ª (defere com justificativa) → DEFERE o processo
        // com TVL, gravando a divergência analista×motor.
        Notification::fake();
        $this->fakeBairroComZona('ZR-1');

        $this->classificarMunicipal('4712100', RiscoMunicipal::Alto); // alto risco → semi-expresso
        $this->seedQuadro7('4712100', 'nR1', 'nR1-01');
        $this->seedQuadro10('ZR-1', 'nR1', Quadro10Permissao::Permitido);

        $this->classificarMunicipal('4731800', RiscoMunicipal::BaixoA);
        $this->seedQuadro7('4731800', 'nR3', 'nR3-01');
        $this->seedQuadro10('ZR-1', 'nR3', Quadro10Permissao::Proibido);

        $request = $this->protocoladaComCnaes(['4712100', '4731800']);

        // 1) ENCAMINHAR: o motor REAL roteia à análise (semi-expresso) e a ficha
        // rev 1 nasce pré-analisada pelo listener.
        app(FluxoExpressoService::class)->decide($request);
        $request->refresh();
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $request->status);

        $ficha = $request->currentAnalysisRecord()->first();
        $this->assertNotNull($ficha);
        $this->assertSame(1, $ficha->revision);
        $this->assertTrue($ficha->engine_available, 'Com zona, a ficha deveria vir pré-analisada.');
        $this->assertSame('deferida', $this->sugestao($ficha, '4712100'));
        $this->assertSame('indeferida', $this->sugestao($ficha, '4731800'));

        // 2) DISTRIBUIR/ASSUMIR: o processo entra na caixa do setor e o analista
        // assume (HU-080/081).
        [$sector, $analista] = $this->analistaNoSetor();
        $request->forceFill(['sector_id' => $sector->id])->save();
        app(DistribuicaoService::class)->assumir($request->fresh(), $analista);

        // 3) PREENCHER/FINALIZAR com DIVERGÊNCIA: o analista DEFERE a 2ª CNAE
        // (motor sugeriu indeferida) com justificativa própria.
        $service = app(AnalysisRecordService::class);
        $service->autosave($ficha, [
            'per_cnae' => [
                ['cnae' => '4712100', 'status_escolhido' => 'deferida'],
                ['cnae' => '4731800', 'status_escolhido' => 'deferida', 'justificativa' => 'Atividade acessória compatível com o local.'],
            ],
            'parecer' => 'Parecer técnico favorável às duas atividades.',
        ]);
        $finalizada = $service->finalizar($ficha->fresh(), $analista);

        $this->assertDatabaseHas('analysis_divergences', [
            'analysis_record_id' => $finalizada->id,
            'cnae' => '4731800',
            'field' => 'status',
            'suggested_value' => 'indeferida',
            'final_value' => 'deferida',
            'justification' => 'Atividade acessória compatível com o local.',
        ]);

        // 4) DECIDIR: deferimento real (flow analise_tecnica + TVL); ResultadoEmitido
        // reusa os listeners da Fase 9.
        $result = app(AnaliseTecnicaDecisionService::class)->decide($finalizada, $analista);
        $this->assertSame(DecisionOutcome::Deferida, $result->outcome);

        $request->refresh();
        $this->assertSame(ViabilityRequestStatus::Deferida, $request->status);

        $decision = $request->decision;
        $this->assertNotNull($decision);
        $this->assertSame('analise_tecnica', $decision->flow);
        $this->assertSame($analista->id, $decision->decided_by_user_id);
        $this->assertNotNull($decision->tvl_product_number);
        $this->assertStringStartsWith('TVL-', (string) $decision->tvl_product_number);

        // Auditoria SÍNCRONA da decisão humana.
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'analise',
            'event' => 'decisao',
            'result' => 'deferida',
        ]);

        // HU-104/110: Regin e SEFAZ ficam como pendência auditada (bloqueado →
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

    public function test_fluxo_humano_indefere_sem_tvl(): void
    {
        // INDEFERIR: semi-expresso com a zona PROIBINDO o grupo → o analista
        // concorda (indeferida) → INDEFERE sem TVL; a SEFAZ ignora (RN-003).
        Notification::fake();
        $this->fakeBairroComZona('ZR-1');
        $this->classificarMunicipal('4731800', RiscoMunicipal::Alto); // alto → semi-expresso
        $this->seedQuadro7('4731800', 'nR3', 'nR3-01');
        $this->seedQuadro10('ZR-1', 'nR3', Quadro10Permissao::Proibido);

        $request = $this->protocoladaComCnaes(['4731800']);

        app(FluxoExpressoService::class)->decide($request);
        $request->refresh();
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $request->status);

        $ficha = $request->currentAnalysisRecord()->first();
        $this->assertSame('indeferida', $this->sugestao($ficha, '4731800'));

        [$sector, $analista] = $this->analistaNoSetor();
        $request->forceFill(['sector_id' => $sector->id])->save();
        app(DistribuicaoService::class)->assumir($request->fresh(), $analista);

        $service = app(AnalysisRecordService::class);
        $service->autosave($ficha, [
            'per_cnae' => [['cnae' => '4731800', 'status_escolhido' => 'indeferida']],
            'parecer' => 'Atividade não admitida na zona.',
        ]);
        $finalizada = $service->finalizar($ficha->fresh(), $analista);

        $result = app(AnaliseTecnicaDecisionService::class)->decide($finalizada, $analista);

        $this->assertSame(DecisionOutcome::Indeferida, $result->outcome);
        $request->refresh();
        $this->assertSame(ViabilityRequestStatus::Indeferida, $request->status);
        $this->assertNull($request->decision->tvl_product_number, 'Indeferimento não gera TVL.');

        // RN-003: a SEFAZ ignora o indeferimento (auditável), não envia.
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'integracoes',
            'event' => 'sefaz-viabilidade',
            'result' => 'ignorado',
        ]);
    }

    public function test_ciclo_de_pendencia_ida_e_volta(): void
    {
        // HU-083/084 (parcial): o analista abre uma pendência (em_analise→
        // em_pendencia) e o requerente responde pelo portal (em_pendencia→
        // em_analise) — ciclo interno real.
        $request = $this->emAnaliseSemZona('2222222');
        $requerente = $request->requester;
        [$sector, $analista] = $this->analistaNoSetor();
        $request->forceFill(['sector_id' => $sector->id])->save();

        $pendency = app(PendenciaService::class)->abrir(
            $request->fresh(),
            $analista,
            'Apresentar planta de situação atualizada.',
        );

        $this->assertSame(ViabilityRequestStatus::EmPendencia, $request->fresh()->status);
        $this->assertSame(AnalysisPendencyStatus::Aberta, $pendency->status);

        // O requerente responde pelo portal (reabre a análise).
        $this->actingAs($requerente);
        app(PendenciaService::class)->responder($pendency, 'Planta anexada conforme solicitado.');

        $this->assertSame(ViabilityRequestStatus::EmAnalise, $request->fresh()->status);
        $this->assertSame(AnalysisPendencyStatus::Respondida, $pendency->fresh()->status);
    }

    public function test_malha_fina_atinge_um_deferido_sem_mudar_o_status(): void
    {
        // HU-136 RN-001: a malha fina é ORTOGONAL ao status — encaminhar um
        // DEFERIDO liga a flag e grava o referral SEM mudar o desfecho (corrige o
        // bug legado que recusava deferidos).
        $request = ViabilityRequest::factory()->protocoled()->create();
        $request->forceFill(['status' => ViabilityRequestStatus::Deferida])->save();
        ViabilityDecision::factory()->create([
            'viability_request_id' => $request->id,
            'flow' => 'analise_tecnica',
        ]);
        $gestor = User::factory()->create();

        $referral = app(MalhaFinaService::class)->encaminhar(
            $request,
            $gestor,
            'Revisão de amostragem (malha fina).',
        );

        $request->refresh();
        $this->assertTrue($request->in_fine_mesh);
        $this->assertSame(ViabilityRequestStatus::Deferida, $request->status, 'A malha fina NÃO muda o status.');
        $this->assertNull($referral->resolved_at);
        $this->assertDatabaseHas('fine_mesh_referrals', [
            'viability_request_id' => $request->id,
            'reason' => 'Revisão de amostragem (malha fina).',
        ]);
    }

    public function test_degradacao_sem_motor_o_humano_decide_em_modo_manual(): void
    {
        // FA-01: sem a zona oficial (Quadro 10 pendente SEDUR) a ficha nasce em
        // modo MANUAL (engine_available=false) — e o analista DECIDE assim mesmo
        // (defere o caso pendente com fundamentação própria). É o caso que exige
        // o humano.
        $request = $this->emAnaliseSemZona('2222222');

        $ficha = $request->currentAnalysisRecord()->first();
        $this->assertNotNull($ficha);
        $this->assertFalse($ficha->engine_available, 'Sem zona, a ficha nasce em modo manual.');
        $this->assertNull($ficha->per_cnae);

        [$sector, $analista] = $this->analistaNoSetor();
        $request->forceFill(['sector_id' => $sector->id])->save();
        app(DistribuicaoService::class)->assumir($request->fresh(), $analista);

        $service = app(AnalysisRecordService::class);
        $service->autosave($ficha, [
            'per_cnae' => [[
                'cnae' => '2222222',
                'status_escolhido' => 'deferida',
                'justificativa' => 'Uso compatível com a vizinhança (decisão técnica do analista).',
            ]],
            'parecer' => 'Deferido pela análise técnica, sem zona oficial disponível.',
        ]);
        $finalizada = $service->finalizar($ficha->fresh(), $analista);

        $result = app(AnaliseTecnicaDecisionService::class)->decide($finalizada, $analista);

        $this->assertSame(DecisionOutcome::Deferida, $result->outcome);
        $request->refresh();
        $this->assertSame(ViabilityRequestStatus::Deferida, $request->status);
        $this->assertSame('analise_tecnica', $request->decision->flow);
        $this->assertNotNull($request->decision->tvl_product_number);
    }

    /**
     * Solicitação levada a em_analise pelo caminho real (sem zona → veredito
     * pendente → encaminhada), com a ficha rev 1 em modo manual.
     */
    private function emAnaliseSemZona(string $cnae): ViabilityRequest
    {
        $this->fakeBairroSemZona();
        $this->classificarMunicipal($cnae, RiscoMunicipal::BaixoA);

        $request = $this->protocoladaComCnaes([$cnae]);
        app(FluxoExpressoService::class)->decide($request);

        $fresh = $request->fresh();
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $fresh->status);

        return $fresh;
    }

    /**
     * Status sugerido pelo motor para um CNAE na ficha pré-analisada.
     */
    private function sugestao(AnalysisRecord $ficha, string $cnae): ?string
    {
        foreach ($ficha->per_cnae ?? [] as $item) {
            if (($item['cnae'] ?? null) === $cnae) {
                return $item['status_sugerido'] ?? null;
            }
        }

        return null;
    }

    /**
     * @return array{0: Sector, 1: User}
     */
    private function analistaNoSetor(): array
    {
        $sector = Sector::factory()->create();
        $analista = User::factory()->create();
        $analista->sectors()->attach($sector->id);

        return [$sector, $analista];
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

<?php

namespace Tests\Feature\Analise;

use App\Enums\GeoLayerType;
use App\Enums\Quadro10Permissao;
use App\Enums\RiscoMunicipal;
use App\Enums\RuleDomain;
use App\Enums\TipoGatilho;
use App\Enums\ViabilityRequestStatus;
use App\Http\Resources\AnalysisRecordResource;
use App\Models\Activity;
use App\Models\AnalysisRecord;
use App\Models\Cnae;
use App\Models\ExpressoQueda;
use App\Models\GeoLayer;
use App\Models\LouosQuadro10Permissao;
use App\Models\RiskClassification;
use App\Models\RuleVersion;
use App\Models\ViabilityRequest;
use App\Services\Analise\PreAnaliseService;
use App\Services\Geo\SpatialRepository;
use App\Services\Solicitacao\SolicitacaoViabilityResolver;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\Geo\FakeSpatialRepository;
use Tests\Support\SeedsTratamentoPlanilha;
use Tests\TestCase;

/**
 * Pré-análise pelo motor (HU-140): ao encaminhar à análise, o PreAnaliseService
 * roda o SolicitacaoViabilityResolver FRESCO (mesmo motor da Fase 9 — sem lógica
 * de decisão paralela, RN-001) e cria a analysis_records revisão 1 (rascunho)
 * pré-preenchida: engine_snapshot INTEGRAL (a zona fica aninhada em
 * por_cnae[i].consulta.territorio.zona), engine_rules_versions e per_cnae com o
 * status sugerido por CNAE (deferida/indeferida/análise mapeado da tendência —
 * sugestão, nunca decisão).
 *
 * É idempotente (RN-004: reabrir/reprocessar não reexecuta — recalcular é ação
 * explícita em 10-09) e degrada honesto (FA-01/CA-03): motor indisponível ou
 * veredito pendente (zona urbanística pendente SEDUR) → revisão 1 em modo manual
 * com engine_available=false, sem sugestão inventada, com o evento auditado
 * (RN-005). Roda em SQLite com o FakeSpatialRepository injetado — mesmo padrão do
 * SolicitacaoViabilityResolverTest.
 */
class PreAnaliseServiceTest extends TestCase
{
    use LazilyRefreshDatabase;
    use SeedsTratamentoPlanilha;

    private function service(): PreAnaliseService
    {
        return app(PreAnaliseService::class);
    }

    /**
     * Solicitação em_analise com os CNAEs informados (o primeiro como principal)
     * e o polígono default (centroide em Salvador) — o estado em que a
     * pré-análise é disparada.
     *
     * @param  list<string>  $cnaeCodes
     */
    private function emAnaliseComCnaes(array $cnaeCodes): ViabilityRequest
    {
        $solicitacao = ViabilityRequest::factory()->protocoled()->create(['used_area_m2' => 120.0]);
        $solicitacao->forceFill(['status' => ViabilityRequestStatus::EmAnalise])->save();

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

    /**
     * Território com BAIRRO e ZONA identificados (zoneamento oficial disponível):
     * o motor LOUOS consolida permitido/não permitido a partir do Quadro 10.
     */
    private function fakeBairroComZona(string $zona): void
    {
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Bairro, 'version' => 'bairro-2024']);
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Zona, 'version' => 'zoneamento-louos-2026']);

        $fake = new FakeSpatialRepository;
        $fake->setContaining(GeoLayerType::Bairro, ['id' => 1, 'properties' => ['NOME_BAIRRO' => 'Comércio']]);
        $fake->setContaining(GeoLayerType::Zona, ['id' => 2, 'properties' => ['NOME' => $zona]]);

        $this->app->instance(SpatialRepository::class, $fake);
    }

    /**
     * Território com BAIRRO identificado e ZONA indisponível (base de zoneamento
     * pendente SEDUR) → veredito locacional pendente (propagado do motor LOUOS).
     */
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

    public function test_cria_revisao_1_pre_preenchida_pelo_motor_real(): void
    {
        // HU-140 CA-01: com zona oficial e CNAE permitido, a pré-análise cria a
        // revisão 1 (rascunho) com engine_available=true, engine_snapshot integral
        // (zona ANINHADA em por_cnae.consulta.territorio.zona, nunca top-level),
        // engine_rules_versions e per_cnae com o status sugerido (deferida).
        $this->fakeBairroComZona('ZR-1');
        $this->classificarMunicipal('4712100', RiscoMunicipal::BaixoA);
        $this->seedTratamento('4712100', 'nR1', 'nR1-01');
        $this->seedQuadro10('ZR-1', 'nR1', Quadro10Permissao::Permitido);

        $request = $this->emAnaliseComCnaes(['4712100']);

        $record = $this->service()->preAnalisar($request);

        $this->assertNotNull($record);
        $this->assertSame(1, $record->revision);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $request->fresh()->status);
        $this->assertTrue($record->engine_available);
        $this->assertFalse($record->isFinalizada());

        // engine_rules_versions presentes (RN-004 — reprodução por época).
        $this->assertNotEmpty($record->engine_rules_versions);

        // engine_snapshot INTEGRAL: a zona NÃO é top-level — fica aninhada em
        // por_cnae[i].consulta.territorio.zona (chave que o PrecedentService lê).
        $this->assertArrayHasKey('por_cnae', $record->engine_snapshot);
        $this->assertArrayNotHasKey('zona', $record->engine_snapshot);
        $zona = $record->engine_snapshot['por_cnae'][0]['consulta']['territorio']['zona'];
        $this->assertSame('identificado', $zona['status']);
        $this->assertSame('ZR-1', $zona['nome']);

        // per_cnae com o status sugerido por CNAE mapeado da tendência (permitido
        // → deferida) — sugestão, não decisão (RN-001).
        $this->assertCount(1, $record->per_cnae);
        $this->assertSame('4712100', $record->per_cnae[0]['cnae']);
        $this->assertSame('permitido', $record->per_cnae[0]['tendencia']);
        $this->assertSame('deferida', $record->per_cnae[0]['status_sugerido']);
        $this->assertSame('deferida', $record->per_cnae[0]['status_escolhido']);
        $this->assertSame('nR1', $record->per_cnae[0]['grupo_uso']);
        $this->assertNotEmpty($record->per_cnae[0]['justificativa']);
        $this->assertStringContainsString('Lei nº 9.148/2016', (string) $record->per_cnae[0]['justificativa']);
        $this->assertStringContainsString('planilha vigente', (string) $record->per_cnae[0]['justificativa']);
        $this->assertStringContainsString('Quadro 10', (string) $record->per_cnae[0]['justificativa']);
        $this->assertStringContainsString('deferimento', mb_strtolower((string) $record->per_cnae[0]['justificativa']));
        $this->assertNotEmpty($record->parecer);
        $this->assertStringContainsString('Quadro 10', (string) $record->parecer);
        $this->assertStringNotContainsString('Rascunho do motor', (string) $record->parecer);
        $this->assertStringNotContainsString('A decisão continua sendo do analista', (string) $record->parecer);
        $this->assertSame('permitido', $record->engine_snapshot['consolidado']);

        // RN-005: a pré-análise é auditada (analise/pre-analise) com a versão das
        // regras aplicadas.
        $activity = Activity::query()
            ->where('log_name', 'analise')
            ->where('event', 'pre-analise')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame('sucesso', $activity->result);
        $this->assertNotNull($activity->rules_version);
        $this->assertSame($request->id, $activity->properties['viability_request_id']);
    }

    public function test_status_sugerido_indeferida_quando_zona_proibe(): void
    {
        // RN-001: a sugestão espelha a semântica do motor (não permitido →
        // indeferida) — nunca uma decisão paralela.
        $this->fakeBairroComZona('ZR-1');
        $this->classificarMunicipal('4712100', RiscoMunicipal::BaixoA);
        $this->seedTratamento('4712100', 'nR1', 'nR1-01');
        $this->seedQuadro10('ZR-1', 'nR1', Quadro10Permissao::Proibido);

        $request = $this->emAnaliseComCnaes(['4712100']);

        $record = $this->service()->preAnalisar($request);

        $this->assertNotNull($record);
        $this->assertTrue($record->engine_available);
        $this->assertSame('nao_permitido', $record->per_cnae[0]['tendencia']);
        $this->assertSame('indeferida', $record->per_cnae[0]['status_sugerido']);
        $this->assertSame('indeferida', $record->per_cnae[0]['status_escolhido']);
        $this->assertNotEmpty($record->per_cnae[0]['justificativa']);
        $this->assertStringContainsString('indeferimento', mb_strtolower((string) $record->per_cnae[0]['justificativa']));
        $this->assertStringContainsString('proibido', mb_strtolower((string) $record->per_cnae[0]['justificativa']));
        $this->assertStringContainsString('indeferimento', mb_strtolower((string) $record->parecer));
    }

    public function test_idempotente_nao_cria_duas_revisoes_1(): void
    {
        // HU-140 RN-004: reprocessar o mesmo encaminhamento NÃO cria nova revisão
        // — recalcular é ação explícita (10-09), nunca automática.
        $this->fakeBairroComZona('ZR-1');
        $this->classificarMunicipal('4712100', RiscoMunicipal::BaixoA);
        $this->seedTratamento('4712100', 'nR1', 'nR1-01');
        $this->seedQuadro10('ZR-1', 'nR1', Quadro10Permissao::Permitido);

        $request = $this->emAnaliseComCnaes(['4712100']);

        $primeira = $this->service()->preAnalisar($request);
        $segunda = $this->service()->preAnalisar($request);

        $this->assertNotNull($primeira);
        $this->assertNotNull($segunda);
        $this->assertSame($primeira->id, $segunda->id);
        $this->assertSame(1, $request->analysisRecords()->count());
    }

    public function test_fa01_motor_indisponivel_degrada_em_modo_manual(): void
    {
        // HU-140 CA-03/FA-01: exceção do motor → revisão 1 em modo manual
        // (engine_available=false, ficha vazia), NUNCA falha silenciosa nem
        // sugestão inventada; o evento é auditado como degradado (RN-005).
        $this->mock(SolicitacaoViabilityResolver::class, function ($mock): void {
            $mock->shouldReceive('resolve')->andThrow(new \RuntimeException('motor fora do ar'));
        });

        $request = $this->emAnaliseComCnaes(['4712100']);

        $record = $this->service()->preAnalisar($request);

        $this->assertNotNull($record);
        $this->assertSame(1, $record->revision);
        $this->assertFalse($record->engine_available);
        $this->assertNull($record->engine_snapshot);
        $this->assertNull($record->per_cnae);

        $activity = Activity::query()
            ->where('log_name', 'analise')
            ->where('event', 'pre-analise')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame('degradado', $activity->result);
    }

    public function test_veredito_pendente_traz_o_que_o_motor_sabe_sem_inventar_desfecho(): void
    {
        // Sem zona oficial o Quadro 10 não decide — status fica em análise.
        // A ficha NÃO nasce vazia: risco, CNAE e o que o motor apurou vêm
        // preenchidos para o analista só confirmar ou alterar.
        $this->fakeBairroSemZona();
        $this->classificarMunicipal('2222222', RiscoMunicipal::BaixoA);

        $request = $this->emAnaliseComCnaes(['2222222']);

        $record = $this->service()->preAnalisar($request);

        $this->assertNotNull($record);
        $this->assertTrue($record->engine_available);
        $this->assertNotEmpty($record->per_cnae);
        $this->assertSame('pendente', $record->per_cnae[0]['tendencia']);
        $this->assertNull($record->per_cnae[0]['status_sugerido']);
        $this->assertNull($record->per_cnae[0]['status_escolhido']);
        $this->assertSame('pendente', $record->engine_snapshot['consolidado']);
        $this->assertNotEmpty($record->parecer);
        $this->assertStringContainsString('pendente', mb_strtolower((string) $record->parecer));

        $activity = Activity::query()
            ->where('log_name', 'analise')
            ->where('event', 'pre-analise')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame('sucesso', $activity->result);
        $this->assertSame('pendente', $activity->properties['consolidado']);
    }

    public function test_condicionante_do_quadro_10_ja_vem_marcada_na_ficha(): void
    {
        $this->fakeBairroComZona('ZR-1');
        $this->classificarMunicipal('4712100', RiscoMunicipal::BaixoA);
        $this->seedTratamento('4712100', 'nR1', 'nR1-01');

        $version = RuleVersion::vigente(RuleDomain::LouosQuadro10)->first()
            ?? RuleVersion::factory()->create([
                'domain' => RuleDomain::LouosQuadro10,
                'version' => 'lei-9148-2016-quadro10',
                'rules_version' => 'lei-9148-2016-quadro10',
            ]);

        LouosQuadro10Permissao::factory()->create([
            'rule_version_id' => $version->id,
            'zona' => 'ZR-1',
            'grupo_uso' => 'nR1',
            'subgrupo' => '',
            'permissao' => Quadro10Permissao::PermitidoCondicionado,
            'condicionante_ref' => 'C-10',
            'base_legal' => 'Quadro 10 da Lei nº 9.148/2016',
        ]);

        $request = $this->emAnaliseComCnaes(['4712100']);

        $record = $this->service()->preAnalisar($request);

        $this->assertNotNull($record);
        $this->assertSame('permitido_com_condicoes', $record->per_cnae[0]['tendencia']);
        $this->assertSame('deferida', $record->per_cnae[0]['status_escolhido']);
        $this->assertNotEmpty($record->conditions);
        $this->assertNotEmpty($record->per_cnae[0]['condicionantes']);
        $this->assertTrue(
            collect($record->conditions)->contains(fn (string $texto): bool => str_contains(mb_strtolower($texto), 'quadro 10')),
        );
    }

    public function test_per_cnae_inclui_codigo_louos_e_codigo_tll_como_pendencia_explicita(): void
    {
        $this->fakeBairroComZona('ZR-1');
        $this->classificarMunicipal('4712100', RiscoMunicipal::BaixoA);
        $this->seedTratamento('4712100', 'nR1', 'nR1-01');
        $this->seedQuadro10('ZR-1', 'nR1', Quadro10Permissao::Permitido);

        $request = $this->emAnaliseComCnaes(['4712100']);

        $record = $this->service()->preAnalisar($request);

        $this->assertNotNull($record);
        $this->assertNotEmpty($record->per_cnae);
        $this->assertSame('07.01.05', $record->per_cnae[0]['codigo_louos']);
        $this->assertSame('2.02', $record->per_cnae[0]['codigo_tll']);
    }

    public function test_per_cnae_traz_a_resposta_da_pergunta_desenvolvida_no_local(): void
    {
        $this->fakeBairroComZona('ZR-1');
        $this->classificarMunicipal('4712100', RiscoMunicipal::BaixoA);
        $this->seedTratamento('4712100', 'nR1', 'nR1-01');
        $this->seedQuadro10('ZR-1', 'nR1', Quadro10Permissao::Permitido);

        $request = $this->emAnaliseComCnaes(['4712100']);
        $request->respostasTratamento = [11 => true];

        $record = $this->service()->preAnalisar($request);
        $local = $record->per_cnae[0]['pergunta_local'] ?? null;

        $this->assertIsArray($local);
        $this->assertFalse($local['pendente']);
        $this->assertSame(11, $local['numero']);
        $this->assertStringContainsString('desenvolvida no local', mb_strtolower((string) $local['pergunta']));
        $this->assertSame('Sim, a atividade será desenvolvida no local.', $local['resposta']);
    }

    public function test_ficha_repete_a_resposta_da_simulacao_mesmo_em_registro_antigo(): void
    {
        $this->seedTratamentoPlanilha();

        $request = ViabilityRequest::factory()->protocoled()->create([
            'status' => ViabilityRequestStatus::EmAnalise,
            'simulation_snapshot' => [
                'respostas_tratamento_por_cnae' => [
                    '6622300' => [11 => false],
                ],
            ],
        ]);
        $cnae = Cnae::factory()->create(['code' => '6622300']);
        $request->cnaes()->attach($cnae->id, ['is_primary' => true]);

        $record = AnalysisRecord::factory()->create([
            'viability_request_id' => $request->id,
            'revision' => 1,
            'per_cnae' => [[
                'cnae' => '6622300',
                'cnae_formatado' => '6622-3/00',
                'status_sugerido' => 'deferida',
                'status_escolhido' => 'deferida',
            ]],
        ]);
        $record->load('viabilityRequest');
        $record->viabilityRequest->respostasTratamento = [];

        $payload = (new AnalysisRecordResource($record))->resolve();
        $local = $payload['per_cnae'][0]['pergunta_local'] ?? null;

        $this->assertIsArray($local);
        $this->assertFalse($local['pendente']);
        $this->assertSame(11, $local['numero']);
        $this->assertSame('Não, no local funcionará o escritório da empresa.', $local['resposta']);
    }

    public function test_refaz_rascunho_vazio_degradado_com_o_motor(): void
    {
        $this->fakeBairroComZona('ZR-1');
        $this->classificarMunicipal('4712100', RiscoMunicipal::BaixoA);
        $this->seedTratamento('4712100', 'nR1', 'nR1-01');
        $this->seedQuadro10('ZR-1', 'nR1', Quadro10Permissao::Permitido);

        $request = $this->emAnaliseComCnaes(['4712100']);

        AnalysisRecord::factory()->semMotor()->create([
            'viability_request_id' => $request->id,
            'revision' => 1,
            'parecer' => null,
        ]);

        $record = $this->service()->preAnalisar($request);

        $this->assertNotNull($record);
        $this->assertTrue($record->engine_available);
        $this->assertSame('deferida', $record->per_cnae[0]['status_escolhido']);
        $this->assertNotEmpty($record->parecer);
        $this->assertSame(1, $request->analysisRecords()->count());
    }

    public function test_grava_motivo_da_queda_do_expresso_na_ficha(): void
    {
        $this->fakeBairroComZona('ZR-1');
        $this->classificarMunicipal('4712100', RiscoMunicipal::BaixoA);
        $this->seedTratamento('4712100', 'nR1', 'nR1-01');
        $this->seedQuadro10('ZR-1', 'nR1', Quadro10Permissao::Permitido);

        $request = $this->emAnaliseComCnaes(['4712100']);
        $request->forceFill([
            'tipo_imovel' => 'Galpão',
            'tipo_imovel_normalized' => 'galpao',
        ])->save();
        $request->respostasTratamento = [11 => true];

        ExpressoQueda::factory()->create([
            'viability_request_id' => $request->id,
            'cnae' => '4712100',
            'tipo_gatilho' => TipoGatilho::DadosDoProcesso->value,
            'dimensao' => 'municipal',
            'motivo' => 'Nível alto (municipal) encaminhado para análise técnica',
        ]);

        $record = $this->service()->preAnalisar($request);
        $motivos = implode("\n", $record->analysis_reasons ?? []);

        $this->assertStringContainsString('4712-1/00', $motivos);
        $this->assertStringContainsString('Nível alto (municipal)', $motivos);
        $this->assertStringContainsString('Galpão', $motivos);
        $this->assertStringContainsString('ZR-1', $motivos);
        $this->assertStringContainsString('Quadro 10', $motivos);
        $this->assertGreaterThanOrEqual(2, count($record->analysis_reasons ?? []));
    }

    public function test_resource_preenche_justificativa_vazia_com_motivo_do_snapshot(): void
    {
        $ficha = AnalysisRecord::factory()->create([
            'per_cnae' => [[
                'cnae' => '4771701',
                'justificativa' => null,
                'status_sugerido' => 'deferida',
                'status_escolhido' => 'deferida',
            ]],
            'engine_snapshot' => [
                'por_cnae' => [[
                    'cnae' => '4771701',
                    'consulta' => $this->consultaSnapshotPermitida(),
                ]],
            ],
        ]);

        $payload = (new AnalysisRecordResource($ficha))->resolve();

        $this->assertStringContainsString('Lei nº 9.148/2016', $payload['per_cnae'][0]['justificativa']);
        $this->assertStringContainsString('planilha vigente', $payload['per_cnae'][0]['justificativa']);
        $this->assertStringContainsString('Quadro 10', $payload['per_cnae'][0]['justificativa']);
        $this->assertStringContainsString('deferimento', mb_strtolower($payload['per_cnae'][0]['justificativa']));
    }

    public function test_resource_nao_sobrescreve_justificativa_do_analista(): void
    {
        $ficha = AnalysisRecord::factory()->create([
            'per_cnae' => [[
                'cnae' => '4771701',
                'justificativa' => 'Decisão técnica do analista.',
                'status_sugerido' => 'deferida',
                'status_escolhido' => 'indeferida',
            ]],
            'engine_snapshot' => [
                'por_cnae' => [[
                    'cnae' => '4771701',
                    'consulta' => [
                        'enquadramento' => [
                            'consolidado' => [
                                'motivo' => 'Permitido pelo Quadro 10.',
                            ],
                        ],
                    ],
                ]],
            ],
        ]);

        $payload = (new AnalysisRecordResource($ficha))->resolve();

        $this->assertSame('Decisão técnica do analista.', $payload['per_cnae'][0]['justificativa']);
    }

    /**
     * @return array<string, mixed>
     */
    private function consultaSnapshotPermitida(): array
    {
        return [
            'entrada' => [
                'cnae' => '4771701',
                'cnae_formatado' => '4771-7/01',
                'area' => 75,
            ],
            'territorio' => [
                'bairro' => ['status' => 'identificado', 'nome' => 'Comércio'],
                'via' => ['status' => 'nao_encontrado'],
                'zona' => ['status' => 'identificado', 'nome' => 'ZR-1'],
            ],
            'enquadramento' => [
                'enquadramento' => [
                    'status' => 'identificado',
                    'grupo' => 'nR1',
                    'subgrupo' => 'nR1-01',
                ],
                'quadro10' => [
                    'status' => 'identificado',
                    'permissao' => 'permitido',
                ],
                'quadro11a' => ['status' => 'nao_encontrado'],
                'consolidado' => [
                    'resultado' => 'permitido',
                    'motivo' => 'Permitido pelo Quadro 10.',
                    'fundamentacao' => [
                        'Lei nº 9.148/2016 (LOUOS) — nR1-01',
                        'Quadro 10 da Lei nº 9.148/2016',
                    ],
                    'condicionantes' => [],
                ],
            ],
            'risco' => [
                'municipal' => [
                    'status' => 'classificado',
                    'nivel' => 'baixo_a',
                    'nivel_label' => 'Baixo',
                ],
                'sanitario' => ['status' => 'nao_classificado'],
                'encaminhamento' => [
                    'fluxo' => 'analise',
                    'motivo' => 'Encaminhado para análise técnica',
                ],
                'fundamentacao' => ['Decreto Municipal nº 32.636/2020'],
            ],
            'fundamentacao' => [
                'Lei nº 9.148/2016 (LOUOS) — nR1-01',
                'Quadro 10 da Lei nº 9.148/2016',
                'Decreto Municipal nº 32.636/2020',
            ],
        ];
    }
}

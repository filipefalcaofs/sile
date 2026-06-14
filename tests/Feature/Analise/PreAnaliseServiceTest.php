<?php

namespace Tests\Feature\Analise;

use App\Enums\GeoLayerType;
use App\Enums\Quadro10Permissao;
use App\Enums\RiscoMunicipal;
use App\Enums\RuleDomain;
use App\Enums\ViabilityRequestStatus;
use App\Models\Activity;
use App\Models\Cnae;
use App\Models\GeoLayer;
use App\Models\LouosQuadro10Permissao;
use App\Models\LouosQuadro7Faixa;
use App\Models\RiskClassification;
use App\Models\RuleVersion;
use App\Models\ViabilityRequest;
use App\Services\Analise\PreAnaliseService;
use App\Services\Geo\SpatialRepository;
use App\Services\Solicitacao\SolicitacaoViabilityResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Geo\FakeSpatialRepository;
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
    use RefreshDatabase;

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

    public function test_cria_revisao_1_pre_preenchida_pelo_motor_real(): void
    {
        // HU-140 CA-01: com zona oficial e CNAE permitido, a pré-análise cria a
        // revisão 1 (rascunho) com engine_available=true, engine_snapshot integral
        // (zona ANINHADA em por_cnae.consulta.territorio.zona, nunca top-level),
        // engine_rules_versions e per_cnae com o status sugerido (deferida).
        $this->fakeBairroComZona('ZR-1');
        $this->classificarMunicipal('8888881', RiscoMunicipal::BaixoA);
        $this->seedQuadro7('8888881', 'nR1', 'nR1-01');
        $this->seedQuadro10('ZR-1', 'nR1', Quadro10Permissao::Permitido);

        $request = $this->emAnaliseComCnaes(['8888881']);

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
        $this->assertSame('8888881', $record->per_cnae[0]['cnae']);
        $this->assertSame('permitido', $record->per_cnae[0]['tendencia']);
        $this->assertSame('deferida', $record->per_cnae[0]['status_sugerido']);

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
        $this->classificarMunicipal('8888883', RiscoMunicipal::BaixoA);
        $this->seedQuadro7('8888883', 'nR3', 'nR3-01');
        $this->seedQuadro10('ZR-1', 'nR3', Quadro10Permissao::Proibido);

        $request = $this->emAnaliseComCnaes(['8888883']);

        $record = $this->service()->preAnalisar($request);

        $this->assertNotNull($record);
        $this->assertTrue($record->engine_available);
        $this->assertSame('nao_permitido', $record->per_cnae[0]['tendencia']);
        $this->assertSame('indeferida', $record->per_cnae[0]['status_sugerido']);
    }

    public function test_idempotente_nao_cria_duas_revisoes_1(): void
    {
        // HU-140 RN-004: reprocessar o mesmo encaminhamento NÃO cria nova revisão
        // — recalcular é ação explícita (10-09), nunca automática.
        $this->fakeBairroComZona('ZR-1');
        $this->classificarMunicipal('8888881', RiscoMunicipal::BaixoA);
        $this->seedQuadro7('8888881', 'nR1', 'nR1-01');
        $this->seedQuadro10('ZR-1', 'nR1', Quadro10Permissao::Permitido);

        $request = $this->emAnaliseComCnaes(['8888881']);

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

        $request = $this->emAnaliseComCnaes(['8888881']);

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

    public function test_fa01_veredito_pendente_sem_zona_degrada_sem_sugestao_inventada(): void
    {
        // HU-140 FA-01 / anti-fachada: sem a zona oficial (Quadro 10 pendente
        // SEDUR) o veredito é pendente — o motor NÃO inventa sugestão. A revisão 1
        // nasce em modo manual (engine_available=false), auditada.
        $this->fakeBairroSemZona();
        $this->classificarMunicipal('2222222', RiscoMunicipal::BaixoA);

        $request = $this->emAnaliseComCnaes(['2222222']);

        $record = $this->service()->preAnalisar($request);

        $this->assertNotNull($record);
        $this->assertFalse($record->engine_available);
        $this->assertNull($record->per_cnae);

        $activity = Activity::query()
            ->where('log_name', 'analise')
            ->where('event', 'pre-analise')
            ->where('result', 'degradado')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
    }
}

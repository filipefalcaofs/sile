<?php

namespace Tests\Feature\Expresso;

use App\Enums\GeoLayerType;
use App\Enums\Quadro10Permissao;
use App\Enums\RiscoMunicipal;
use App\Enums\RuleDomain;
use App\Events\ResultadoEmitido;
use App\Models\Activity;
use App\Models\Cnae;
use App\Models\GeoLayer;
use App\Models\LouosQuadro10Permissao;
use App\Models\LouosQuadro7Faixa;
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
 * Auditoria SÍNCRONA da decisão expressa (HU-078, RN-005): a trilha
 * autoritativa é gravada DENTRO da transação da decisão e NÃO depende do evento
 * ResultadoEmitido (lição da Fase 8 — o evento só carrega efeitos colaterais
 * desacoplados). Mesmo com o evento fakeado, o log de auditoria 'expresso'/
 * 'decisao' (com a versão de regra e o por-CNAE) e a auditoria da transição
 * (solicitacoes/transicao) continuam gravados.
 */
class FluxoExpressoAuditoriaTest extends TestCase
{
    use RefreshDatabase;

    private function service(): FluxoExpressoService
    {
        return app(FluxoExpressoService::class);
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
     * Solicitação protocolada deferível: CNAE de baixo risco (expresso) e
     * permitido na zona (Quadro 7/10), com risco municipal vigente.
     */
    private function protocoladaDeferivel(string $cnae = '8888881'): ViabilityRequest
    {
        $this->fakeBairroComZona('ZR-1');

        RiskClassification::factory()->create([
            'rule_version_id' => $this->versaoRiscoMunicipal()->id,
            'cnae_code' => $cnae,
            'risco_municipal' => RiscoMunicipal::BaixoA,
        ]);

        $versaoQuadro7 = RuleVersion::vigente(RuleDomain::LouosQuadro7)->first()
            ?? RuleVersion::factory()->create([
                'domain' => RuleDomain::LouosQuadro7,
                'version' => 'lei-9148-2016-quadro7',
                'rules_version' => 'lei-9148-2016-quadro7',
            ]);
        LouosQuadro7Faixa::factory()->create([
            'rule_version_id' => $versaoQuadro7->id,
            'cnae_code' => $cnae,
            'grupo' => 'nR1',
            'subgrupo' => 'nR1-01',
            'area_min' => 0,
            'area_max' => null,
        ]);

        $versaoQuadro10 = RuleVersion::vigente(RuleDomain::LouosQuadro10)->first()
            ?? RuleVersion::factory()->create([
                'domain' => RuleDomain::LouosQuadro10,
                'version' => 'lei-9148-2016-quadro10',
                'rules_version' => 'lei-9148-2016-quadro10',
            ]);
        LouosQuadro10Permissao::factory()->create([
            'rule_version_id' => $versaoQuadro10->id,
            'zona' => 'ZR-1',
            'grupo_uso' => 'nR1',
            'subgrupo' => '',
            'permissao' => Quadro10Permissao::Permitido,
            'condicionante_ref' => null,
            'base_legal' => 'Quadro 10 da Lei nº 9.148/2016',
        ]);

        $solicitacao = ViabilityRequest::factory()->protocoled()->create(['used_area_m2' => 120.0]);
        $cnaeModel = Cnae::factory()->create(['code' => $cnae]);
        $solicitacao->cnaes()->attach($cnaeModel->id, ['is_primary' => true]);

        return $solicitacao;
    }

    public function test_auditoria_da_decisao_e_sincrona_e_independe_do_evento(): void
    {
        // Fakeando ResultadoEmitido, seus LISTENERS (notificação/Regin/SEFAZ) não
        // rodam. A auditoria da decisão, porém, é gravada na própria transação
        // (não por listener) — então continua presente. É a prova de que a
        // auditoria autoritativa NÃO depende do evento (HU-078, lição da Fase 8).
        Event::fake([ResultadoEmitido::class]);
        $request = $this->protocoladaDeferivel();

        $this->service()->decide($request);

        $activity = Activity::query()
            ->where('log_name', 'expresso')
            ->where('event', 'decisao')
            ->where('result', 'deferida')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity, 'A auditoria da decisão deve ser gravada mesmo com o evento fakeado (HU-078).');
        $this->assertNotNull($activity->rules_version);
        $this->assertNotSame('', $activity->rules_version);
        $this->assertSame($request->id, $activity->properties['viability_request_id']);
        $this->assertArrayHasKey('por_cnae', $activity->properties);
        $this->assertNotEmpty($activity->properties['por_cnae']);

        // O evento É disparado (após o commit), mas seus efeitos ficam isolados
        // pelo fake — a trilha de auditoria já está gravada independentemente.
        Event::assertDispatched(ResultadoEmitido::class);
    }

    public function test_transicao_da_decisao_tambem_e_auditada(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $request = $this->protocoladaDeferivel();

        $this->service()->decide($request);

        $transicao = Activity::query()
            ->where('log_name', 'solicitacoes')
            ->where('event', 'transicao')
            ->latest('id')
            ->first();

        $this->assertNotNull($transicao);
        $this->assertSame('protocolada', $transicao->properties['from']);
        $this->assertSame('deferida', $transicao->properties['to']);
    }
}

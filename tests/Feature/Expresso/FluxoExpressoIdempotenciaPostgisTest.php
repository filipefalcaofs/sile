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
use App\Models\TvlSequence;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Expresso\FluxoExpressoService;
use App\Services\Geo\SpatialRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Group;
use Tests\PostgisTestCase;
use Tests\Support\Geo\FakeSpatialRepository;

/**
 * Idempotência da emissão da decisão sob Postgres REAL (HU-076): duas chamadas
 * de decide() para a MESMA solicitação produzem exatamente 1 ViabilityDecision,
 * 1 número TVL (sequência avança 1, sem buraco) e 1 ResultadoEmitido. A 1ª
 * chamada decide; a 2ª recai no re-check de status dentro do Cache::lock
 * (status !== protocolada) e devolve a decisão existente sem redecidir nem
 * redisparar o evento.
 *
 * A defesa final contra corrida que escape do lock é o unique(viability_request_id)
 * + unique(tvl_product_number), serializados de verdade no pgsql (no-op em
 * SQLite — por isso este teste é @group postgis). O Cache::lock real exige cache
 * compartilhado em produção (Redis); em teste, o re-check de status é a garantia
 * adicional que torna a emissão idempotente mesmo num único processo.
 *
 * Espelha o ProtocolarConcorrenciaPostgisTest. O território é injetado por FAKE
 * (foco na concorrência da emissão, não na geometria PostGIS); os motores
 * LOUOS/risco leem os dados versionados reais do pgsql.
 */
#[Group('postgis')]
class FluxoExpressoIdempotenciaPostgisTest extends PostgisTestCase
{
    public function test_duas_decisoes_da_mesma_solicitacao_geram_uma_decisao_um_tvl_um_evento(): void
    {
        // Evento fakeado para isolar a concorrência da emissão; a transição
        // síncrona (timeline + auditoria) e a decisão rodam de verdade no pgsql.
        Event::fake([ResultadoEmitido::class]);

        $request = $this->protocoladaDeferivel();
        $service = app(FluxoExpressoService::class);

        // 1ª decisão: defere de verdade (cria decisão + TVL + transição + evento).
        $primeira = $service->decide($request);
        // 2ª decisão: recai no re-check (status !== protocolada) — no-op idempotente.
        $segunda = $service->decide($request);

        $this->assertSame(ViabilityRequestStatus::Deferida, $primeira->status);
        $this->assertTrue($primeira->emitted);
        $this->assertSame(ViabilityRequestStatus::Deferida, $segunda->status);
        $this->assertFalse($segunda->emitted, 'A 2ª chamada não pode redisparar o evento.');

        // Exatamente 1 decisão para a solicitação (unique 1:1 honrado).
        $this->assertSame(1, ViabilityDecision::query()->where('viability_request_id', $request->id)->count());
        $this->assertTrue($primeira->decision->is($segunda->decision));

        // A sequência do TVL avançou exatamente 1 — sem buraco, sem duplicidade.
        $sequence = TvlSequence::query()->where('year', (int) now()->year)->sole();
        $this->assertSame(1, (int) $sequence->last_number);

        $this->assertSame(ViabilityRequestStatus::Deferida, $request->fresh()->status);

        // O evento foi disparado UMA única vez (a 2ª chamada não redispara).
        Event::assertDispatchedTimes(ResultadoEmitido::class, 1);

        // Prova que o engine é o Postgres real (FOR UPDATE/unique efetivos).
        $this->assertSame('pgsql', DB::connection()->getDriverName());
    }

    /**
     * Solicitação protocolada DEFERÍVEL: CNAE de baixo risco (expresso) permitido
     * na zona (Quadro 7/10), com território injetado por fake (bairro + zona).
     */
    private function protocoladaDeferivel(string $cnae = '8888881'): ViabilityRequest
    {
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Bairro, 'version' => 'bairro-2024']);
        GeoLayer::factory()->vigente()->create(['type' => GeoLayerType::Zona, 'version' => 'zoneamento-louos-2026']);

        $fake = new FakeSpatialRepository;
        $fake->setContaining(GeoLayerType::Bairro, ['id' => 1, 'properties' => ['NOME_BAIRRO' => 'Comércio']]);
        $fake->setContaining(GeoLayerType::Zona, ['id' => 2, 'properties' => ['NOME' => 'ZR-1']]);
        $this->app->instance(SpatialRepository::class, $fake);

        $versaoRisco = RuleVersion::vigente(RuleDomain::RiscoMunicipal)->first()
            ?? RuleVersion::factory()->create([
                'domain' => RuleDomain::RiscoMunicipal,
                'version' => 'decreto-32636-2020',
                'rules_version' => 'decreto-32636-2020',
            ]);
        RiskClassification::factory()->create([
            'rule_version_id' => $versaoRisco->id,
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
}

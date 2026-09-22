<?php

namespace Tests\Feature\Analise;

use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisRecord;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Analise\PostgisPrecedentRepository;
use App\Services\Solicitacao\PropertyGeometryWriter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\PostgisTestCase;

/**
 * SQL espacial/agregado REAL dos precedentes (HU-142) provado contra PostGIS — a
 * implementação por trás do PrecedentRepository (os consumidores rodam com fake
 * em SQLite). Espelha o InformarImovelPostgisTest / FluxoExpressoIdempotenciaPostgisTest.
 *
 * - propertyPrecedents: ST_Intersects entre a geometry derivada property_polygon
 *   (Fase 8) e o GeoJSON atual — só os imóveis que INTERCEPTAM, ordenados por
 *   decided_at desc, sem dados pessoais (LGPD, RN-004).
 * - cnaeZoneStats: agrega viability_decisions por desfecho na janela, resolvendo
 *   a ZONA pela ficha vigente (engine_snapshot.por_cnae[].consulta.territorio.zona,
 *   status 'identificado') — sem zona identificada não conta.
 *
 * #[Group('postgis')] declarado explicitamente (além de herdar de PostgisTestCase)
 * para que `--group=postgis` descubra a classe: roda no pgsql_testing, com skip
 * HONESTO só sem servidor (nunca passa sem rodar SQL espacial de verdade).
 */
#[Group('postgis')]
class PrecedentRepositoryPostgisTest extends PostgisTestCase
{
    private function repository(): PostgisPrecedentRepository
    {
        return app(PostgisPrecedentRepository::class);
    }

    /**
     * Quadrilátero do imóvel dos precedentes (caixa em Salvador).
     *
     * @return array<string, mixed>
     */
    private function poligonoNoLocal(): array
    {
        return [
            'type' => 'Polygon',
            'coordinates' => [[
                [-38.5108, -12.9711],
                [-38.5108, -12.9709],
                [-38.5106, -12.9709],
                [-38.5106, -12.9711],
                [-38.5108, -12.9711],
            ]],
        ];
    }

    /**
     * Caixa atual que COBRE o imóvel local (intercepta os precedentes do local).
     *
     * @return array<string, mixed>
     */
    private function poligonoAtual(): array
    {
        return [
            'type' => 'Polygon',
            'coordinates' => [[
                [-38.5110, -12.9713],
                [-38.5110, -12.9707],
                [-38.5104, -12.9707],
                [-38.5104, -12.9713],
                [-38.5110, -12.9713],
            ]],
        ];
    }

    /**
     * Imóvel distante (NÃO intercepta a caixa atual).
     *
     * @return array<string, mixed>
     */
    private function poligonoDistante(): array
    {
        return [
            'type' => 'Polygon',
            'coordinates' => [[
                [-38.4000, -12.8000],
                [-38.4000, -12.7998],
                [-38.3998, -12.7998],
                [-38.3998, -12.8000],
                [-38.4000, -12.8000],
            ]],
        ];
    }

    /**
     * Solicitação decidida com geometry derivada (write driver-aware) e uma
     * ViabilityDecision na data informada.
     *
     * @param  array<string, mixed>  $geojson
     */
    private function solicitacaoDecidida(string $protocol, array $geojson, Carbon $decidedAt, bool $deferida = true, ?User $analyst = null): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::Protocolada,
            'protocol_number' => $protocol,
            'protocoled_at' => now(),
            'property_polygon_geojson' => $geojson,
        ]);

        app(PropertyGeometryWriter::class)->write($request);

        $factory = $deferida ? ViabilityDecision::factory() : ViabilityDecision::factory()->indeferida();
        $factory->create([
            'viability_request_id' => $request->id,
            'decided_by_user_id' => $analyst?->id,
            'decided_at' => $decidedAt,
            // tvl_product_number da factory é HARDCODED (não único) — derivar do
            // protocolo evita colisão na unique ao criar várias deferidas.
            'tvl_product_number' => $deferida ? str_replace('VIA-', 'TVL-', $protocol) : null,
        ]);

        return $request;
    }

    /**
     * Solicitação decidida com a ZONA no engine_snapshot da ficha (insumo do
     * cnaeZoneStats) — sem geometry (a estatística não usa ST_Intersects).
     */
    private function decisaoComZonaNoSnapshot(string $protocol, string $cnae, ?string $zonaNome, string $zonaStatus, bool $deferida, Carbon $decidedAt): void
    {
        $request = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::Protocolada,
            'protocol_number' => $protocol,
            'protocoled_at' => now(),
        ]);

        AnalysisRecord::factory()->create([
            'viability_request_id' => $request->id,
            'engine_snapshot' => [
                'por_cnae' => [[
                    'cnae' => $cnae,
                    'is_primary' => true,
                    'consulta' => ['territorio' => ['zona' => ['status' => $zonaStatus, 'nome' => $zonaNome]]],
                ]],
            ],
        ]);

        $factory = $deferida ? ViabilityDecision::factory() : ViabilityDecision::factory()->indeferida();
        $factory->create([
            'viability_request_id' => $request->id,
            'decided_at' => $decidedAt,
            'tvl_product_number' => $deferida ? str_replace('VIA-', 'TVL-', $protocol) : null,
        ]);
    }

    public function test_precedentes_do_imovel_por_interseccao_ordenados_por_decisao(): void
    {
        $analista = User::factory()->create(['name' => 'Maria Analista']);

        // Dois imóveis no MESMO local (interceptam a caixa atual), decididos em
        // datas distintas; um imóvel distante que NÃO intercepta.
        $this->solicitacaoDecidida('VIA-2026-000201', $this->poligonoNoLocal(), Carbon::parse('2025-01-10 10:00:00'), analyst: $analista);
        $this->solicitacaoDecidida('VIA-2026-000202', $this->poligonoNoLocal(), Carbon::parse('2025-09-10 10:00:00'), deferida: false, analyst: $analista);
        $this->solicitacaoDecidida('VIA-2026-000203', $this->poligonoDistante(), Carbon::parse('2025-05-10 10:00:00'));

        $result = $this->repository()->propertyPrecedents($this->poligonoAtual(), 10);

        // SQL espacial REAL — não fachada.
        $this->assertSame('pgsql', DB::connection()->getDriverName());

        // CA-01: só os dois do local, ordenados por decided_at desc (o mais novo primeiro).
        $this->assertCount(2, $result);
        $this->assertSame('VIA-2026-000202', $result[0]['protocol_number']);
        $this->assertSame('indeferida', $result[0]['outcome']);
        $this->assertSame('VIA-2026-000201', $result[1]['protocol_number']);
        $this->assertSame('Maria Analista', $result[1]['analyst']);

        // LGPD (RN-004): a projeção tem EXATAMENTE as chaves do contrato — sem CPF.
        $this->assertSame(
            ['viability_request_id', 'protocol_number', 'outcome', 'decided_at', 'service_type', 'analyst'],
            array_keys($result[0]),
        );
    }

    public function test_limite_parametrizado_corta_a_lista(): void
    {
        $this->solicitacaoDecidida('VIA-2026-000211', $this->poligonoNoLocal(), Carbon::parse('2025-01-10 10:00:00'));
        $this->solicitacaoDecidida('VIA-2026-000212', $this->poligonoNoLocal(), Carbon::parse('2025-09-10 10:00:00'));

        $result = $this->repository()->propertyPrecedents($this->poligonoAtual(), 1);

        // O LIMIT do SQL respeita o parâmetro (analise.precedentes.max_itens).
        $this->assertCount(1, $result);
        $this->assertSame('VIA-2026-000212', $result[0]['protocol_number']);
    }

    public function test_estatistica_do_cnae_na_zona_agrega_por_desfecho_na_janela(): void
    {
        $since = Carbon::now()->subMonths(12);

        // Zona ZR-1, CNAE 4712100, dentro da janela: 1 deferida + 1 indeferida.
        $this->decisaoComZonaNoSnapshot('VIA-2026-000301', '4712100', 'ZR-1', 'identificado', true, Carbon::now()->subMonths(1));
        $this->decisaoComZonaNoSnapshot('VIA-2026-000302', '4712100', 'ZR-1', 'identificado', false, Carbon::now()->subMonths(2));
        // Zona diferente — não conta.
        $this->decisaoComZonaNoSnapshot('VIA-2026-000303', '4712100', 'ZR-2', 'identificado', true, Carbon::now()->subMonths(1));
        // Fora da janela — não conta.
        $this->decisaoComZonaNoSnapshot('VIA-2026-000304', '4712100', 'ZR-1', 'identificado', true, Carbon::now()->subMonths(18));
        // Zona pendente (indisponível) — não conta (sem zona identificada).
        $this->decisaoComZonaNoSnapshot('VIA-2026-000305', '4712100', null, 'indisponivel', true, Carbon::now()->subMonths(1));

        $stats = $this->repository()->cnaeZoneStats('4712100', 'ZR-1', $since);

        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertSame(1, $stats['deferidos']);
        $this->assertSame(1, $stats['indeferidos']);
        $this->assertSame(2, $stats['total']);
    }
}

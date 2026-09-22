<?php

namespace Tests\Feature\Analise;

use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisRecord;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Analise\PrecedentService;
use App\Services\Solicitacao\PropertyGeometryWriter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\PostgisTestCase;

/**
 * Painel de precedentes da ficha (HU-142) provado de ponta a ponta contra PostGIS:
 * o PrecedentService::forRecord orquestra sobre o PostgisPrecedentRepository (a
 * implementação real por trás do contrato; os consumidores rodam com fake em
 * SQLite). Aqui a busca espacial (ST_Intersects sobre a geometry derivada
 * property_polygon) e a agregação por zona são SQL REAL.
 *
 * - imovel: decisões anteriores que INTERCEPTAM o polígono atual, ordenadas por
 *   decided_at desc, SEM dados pessoais (LGPD, RN-004).
 * - cnae_zona: agrega por desfecho na janela, resolvendo a zona do engine_snapshot
 *   da ficha vigente.
 *
 * #[Group('postgis')] explícito (além de herdar de PostgisTestCase) para que
 * `--group=postgis` descubra a classe; skip HONESTO só sem container.
 */
#[Group('postgis')]
class AnalisePrecedentesPostgisTest extends PostgisTestCase
{
    public function test_painel_de_precedentes_retorna_decisoes_reais_por_interseccao_e_zona(): void
    {
        $analista = User::factory()->create(['name' => 'Maria Analista']);

        // Dois precedentes no MESMO local (interceptam o polígono atual), na zona
        // ZR-1 e dentro da janela de 12 meses (entram no imovel e na estatística do
        // CNAE na zona); um distante, em outra zona, que NÃO conta em nenhum.
        $this->decisaoNoLocal('VIA-2026-000401', $this->poligonoNoLocal(), Carbon::now()->subMonths(4), deferida: true, analyst: $analista);
        $this->decisaoNoLocal('VIA-2026-000402', $this->poligonoNoLocal(), Carbon::now()->subMonths(2), deferida: false, analyst: $analista);
        $this->decisaoNoLocal('VIA-2026-000403', $this->poligonoDistante(), Carbon::now()->subMonths(3), deferida: true, analyst: $analista, zona: 'ZR-9');

        // Processo ATUAL em análise: polígono que cobre os precedentes locais +
        // ficha com a zona identificada (ZR-1) e o CNAE principal 4712100.
        $record = $this->fichaAtual($this->poligonoAtual());

        $painel = app(PrecedentService::class)->forRecord($record);

        // SQL espacial/agregado REAL — não fachada.
        $this->assertSame('pgsql', DB::connection()->getDriverName());

        // CA-01: só os dois do local, ordenados por decided_at desc (mais novo 1º).
        $this->assertCount(2, $painel['imovel']);
        $this->assertSame('VIA-2026-000402', $painel['imovel'][0]['protocol_number']);
        $this->assertSame('indeferida', $painel['imovel'][0]['outcome']);
        $this->assertSame('VIA-2026-000401', $painel['imovel'][1]['protocol_number']);
        $this->assertSame('Maria Analista', $painel['imovel'][1]['analyst']);

        // LGPD (RN-004): a projeção tem EXATAMENTE as chaves do contrato (sem CPF).
        $this->assertSame(
            ['viability_request_id', 'protocol_number', 'outcome', 'decided_at', 'service_type', 'analyst'],
            array_keys($painel['imovel'][0]),
        );

        // CA-02: estatística do CNAE na zona disponível (1 deferida + 1 indeferida).
        $this->assertTrue($painel['cnae_zona']['disponivel']);
        $this->assertSame('ZR-1', $painel['cnae_zona']['zona']);
        $this->assertSame('4712100', $painel['cnae_zona']['cnae']);
        $this->assertSame(1, $painel['cnae_zona']['deferidos']);
        $this->assertSame(1, $painel['cnae_zona']['indeferidos']);
        $this->assertSame(2, $painel['cnae_zona']['total']);
    }

    public function test_painel_degrada_honesto_sem_zona_identificada(): void
    {
        // Sem zona identificada no snapshot (Quadro 10 pendente SEDUR) a
        // estatística do CNAE é "indisponível" — nunca inventada (CA-03).
        $record = $this->fichaAtual($this->poligonoAtual(), zonaIdentificada: false);

        $painel = app(PrecedentService::class)->forRecord($record);

        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertSame([], $painel['imovel']);
        $this->assertFalse($painel['cnae_zona']['disponivel']);
    }

    /**
     * Solicitação decidida com geometry derivada (driver-aware), uma
     * ViabilityDecision na data informada e a ficha com zona ZR-1 / CNAE 4712100
     * no snapshot (insumo do cnaeZoneStats).
     *
     * @param  array<string, mixed>  $geojson
     */
    private function decisaoNoLocal(string $protocol, array $geojson, Carbon $decidedAt, bool $deferida, User $analyst, string $zona = 'ZR-1'): void
    {
        $request = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::Protocolada,
            'protocol_number' => $protocol,
            'protocoled_at' => now(),
            'property_polygon_geojson' => $geojson,
        ]);

        app(PropertyGeometryWriter::class)->write($request);

        AnalysisRecord::factory()->create([
            'viability_request_id' => $request->id,
            'engine_snapshot' => [
                'por_cnae' => [[
                    'cnae' => '4712100',
                    'is_primary' => true,
                    'consulta' => ['territorio' => ['zona' => ['status' => 'identificado', 'nome' => $zona]]],
                ]],
            ],
        ]);

        $factory = $deferida ? ViabilityDecision::factory() : ViabilityDecision::factory()->indeferida();
        $factory->create([
            'viability_request_id' => $request->id,
            'decided_by_user_id' => $analyst->id,
            'decided_at' => $decidedAt,
            'tvl_product_number' => $deferida ? str_replace('VIA-', 'TVL-', $protocol) : null,
        ]);
    }

    /**
     * Ficha (rev 1) do processo ATUAL em análise com o polígono informado e a
     * zona identificada (ou não) no engine_snapshot.
     *
     * @param  array<string, mixed>  $geojson
     */
    private function fichaAtual(array $geojson, bool $zonaIdentificada = true): AnalysisRecord
    {
        $request = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::EmAnalise,
            'protocol_number' => 'VIA-2026-000999',
            'protocoled_at' => now(),
            'property_polygon_geojson' => $geojson,
        ]);

        $zona = $zonaIdentificada
            ? ['status' => 'identificado', 'nome' => 'ZR-1']
            : ['status' => 'indisponivel', 'nome' => null];

        return AnalysisRecord::factory()->create([
            'viability_request_id' => $request->id,
            'revision' => 1,
            'engine_snapshot' => [
                'por_cnae' => [[
                    'cnae' => '4712100',
                    'is_primary' => true,
                    'consulta' => ['territorio' => ['zona' => $zona]],
                ]],
            ],
        ]);
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
}

<?php

namespace Tests\Feature\Relatorios;

use App\Enums\DecisionOutcome;
use App\Enums\ViabilityRequestStatus;
use App\Models\Cnae;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Analise\ProcessoQueryService;
use App\Services\Relatorios\GeoBairroIndicadorService;
use App\Services\Relatorios\IndicadoresViabilidadeService;
use App\Services\Relatorios\ReportFilters;
use App\Services\Relatorios\SaturacaoService;
use App\Support\FusoNegocio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Group;
use Tests\PostgisTestCase;

/**
 * No PostgreSQL o dia local sai de AT TIME ZONE (base de fusos do banco), não
 * do deslocamento fixo usado no SQLite: o mesmo recorte e a mesma série têm
 * de valer nos dois.
 */
#[Group('postgis')]
class RecorteDiaDeNegocioPostgisTest extends PostgisTestCase
{
    private int $seq = 0;

    private function protocoladaEm(string $local, array $attrs = []): ViabilityRequest
    {
        return ViabilityRequest::factory()->create(array_merge([
            'status' => ViabilityRequestStatus::Protocolada,
            'protocol_number' => 'VIA-2026-'.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT),
            'protocoled_at' => Carbon::parse($local, FusoNegocio::NOME)->utc(),
        ], $attrs));
    }

    public function test_serie_e_recorte_usam_o_dia_local_no_postgres(): void
    {
        $this->protocoladaEm('2026-03-31 22:00:00');
        $this->protocoladaEm('2026-03-31 23:30:00');
        $this->protocoladaEm('2026-04-01 00:10:00');

        $marco = ReportFilters::fromArray(['data_de' => '2026-03-01', 'data_ate' => '2026-03-31']);

        $this->assertSame([['dia' => '2026-03-31', 'total' => 2]], app(IndicadoresViabilidadeService::class)->porPeriodo($marco));
        $this->assertSame(2, app(ProcessoQueryService::class)->filtered($marco->toProcessoFiltros())->count());
    }

    public function test_geo_e_saturacao_agrupam_pelo_bairro_oficial_no_postgres(): void
    {
        Cache::flush();
        config(['viabiliza.relatorios.saturacao.capacidades' => ['4712100' => 2]]);

        $comercio = Cnae::factory()->create(['code' => '4712100']);

        foreach (['Pitubinha', null] as $digitado) {
            $request = $this->protocoladaEm('2026-03-10 10:00:00', ['bairro_oficial' => 'PITUBA', 'address_neighborhood' => $digitado]);
            $request->cnaes()->attach($comercio->id, ['is_primary' => true]);
            ViabilityDecision::factory()->create([
                'viability_request_id' => $request->id,
                'outcome' => DecisionOutcome::Deferida,
                'decided_at' => now(),
                'tvl_product_number' => null,
            ]);
        }

        $filtros = ReportFilters::fromArray(['bairro' => 'pituba', 'cnae' => '4712-1/00']);

        $resumo = app(GeoBairroIndicadorService::class)->resumo($filtros);
        $this->assertSame(['total' => 2, 'bairros_distintos' => 1, 'sem_bairro' => 0], $resumo);

        $painel = app(SaturacaoService::class)->painel($filtros);
        $this->assertSame(1, $painel['resumo']['pares_avaliados']);
        $this->assertSame(100.0, $painel['maioresSaturacoes'][0]['percentual']);
    }
}

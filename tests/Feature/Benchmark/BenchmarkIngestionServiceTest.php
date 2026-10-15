<?php

namespace Tests\Feature\Benchmark;

use App\Models\BenchmarkActivityMetric;
use App\Models\BenchmarkIngestion;
use App\Models\BenchmarkMunicipalityMetric;
use App\Services\Benchmark\BenchmarkIngestionService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Ingestão de uma competência: baixa, agrega e grava só o resultado.
 *
 * Os valores esperados saem da fixture real da Bahia de agosto de 2026 —
 * Salvador com 6 solicitações: 4, 6, 24, 44, 72 e 73 horas úteis.
 */
class BenchmarkIngestionServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function service(): BenchmarkIngestionService
    {
        return app(BenchmarkIngestionService::class);
    }

    private function fingirDownloadDaFixture(): void
    {
        config(['viabiliza.features.benchmark_redesim' => true]);

        Http::fake([
            '*' => Http::response(
                (string) file_get_contents(base_path('tests/Fixtures/Benchmark/redesim-ba-202608.xlsx')),
                200,
            ),
        ]);
    }

    public function test_agrega_o_tempo_de_viabilidade_por_municipio_na_competencia(): void
    {
        $this->fingirDownloadDaFixture();

        $this->service()->ingerir('BA', 2026, 8);

        $salvador = BenchmarkMunicipalityMetric::query()
            ->where(['uf' => 'BA', 'municipio' => 'SALVADOR', 'ano' => 2026, 'mes' => 8])
            ->firstOrFail();

        $this->assertSame(6, $salvador->n);
        $this->assertEqualsWithDelta(37.17, $salvador->media, 0.01);
        $this->assertEqualsWithDelta(34.0, $salvador->p50, 0.01);
        $this->assertEqualsWithDelta(72.5, $salvador->p90, 0.01);
        $this->assertEqualsWithDelta(73.0, $salvador->maximo, 0.01);
    }

    public function test_grava_um_agregado_por_municipio_presente_no_arquivo(): void
    {
        $this->fingirDownloadDaFixture();

        $this->service()->ingerir('BA', 2026, 8);

        $this->assertSame(3, BenchmarkMunicipalityMetric::query()->count());
    }

    public function test_registra_a_ingestao_com_evidencia_do_arquivo(): void
    {
        $this->fingirDownloadDaFixture();

        $ingestao = $this->service()->ingerir('BA', 2026, 8);

        $this->assertSame(BenchmarkIngestion::STATUS_SUCESSO, $ingestao->status);
        $this->assertSame(11, $ingestao->linhas_lidas);
        $this->assertSame(1, $ingestao->linhas_descartadas);
        $this->assertNotNull($ingestao->hash);
        $this->assertGreaterThan(0, $ingestao->bytes);
    }

    public function test_reprocessar_a_mesma_competencia_substitui_sem_duplicar(): void
    {
        $this->fingirDownloadDaFixture();

        $this->service()->ingerir('BA', 2026, 8);
        $this->service()->ingerir('BA', 2026, 8);

        $this->assertSame(1, BenchmarkIngestion::query()->count());
        $this->assertSame(3, BenchmarkMunicipalityMetric::query()->count());
    }

    public function test_grava_o_agregado_por_cnae_de_salvador_e_do_brasil(): void
    {
        $this->fingirDownloadDaFixture();

        $this->service()->ingerir('BA', 2026, 8);

        $this->assertGreaterThan(0, BenchmarkActivityMetric::query()
            ->where('escopo', BenchmarkActivityMetric::ESCOPO_SALVADOR)->count());

        $this->assertGreaterThan(0, BenchmarkActivityMetric::query()
            ->where('escopo', BenchmarkActivityMetric::ESCOPO_BRASIL)->count());

        $this->assertSame(
            ['brasil', 'salvador'],
            BenchmarkActivityMetric::query()->distinct()->orderBy('escopo')->pluck('escopo')->all(),
        );
    }

    public function test_soma_dos_n_por_cnae_do_brasil_bate_com_as_linhas_lidas(): void
    {
        $this->fingirDownloadDaFixture();

        $this->service()->ingerir('BA', 2026, 8);

        $this->assertSame(11, (int) BenchmarkActivityMetric::query()
            ->where('escopo', BenchmarkActivityMetric::ESCOPO_BRASIL)->sum('n'));
    }

    public function test_falha_no_download_registra_a_competencia_sem_gravar_agregado(): void
    {
        config(['viabiliza.features.benchmark_redesim' => true]);

        Http::fake(['*' => Http::response('', 500)]);

        $ingestao = $this->service()->ingerir('BA', 2026, 8);

        $this->assertSame(BenchmarkIngestion::STATUS_FALHA, $ingestao->status);
        $this->assertStringContainsString('500', (string) $ingestao->motivo);
        $this->assertSame(0, BenchmarkMunicipalityMetric::query()->count());
    }

    public function test_integracao_desligada_registra_falha_sem_chamar(): void
    {
        Http::fake();

        $ingestao = $this->service()->ingerir('BA', 2026, 8);

        $this->assertSame(BenchmarkIngestion::STATUS_FALHA, $ingestao->status);

        Http::assertNothingSent();
    }

    public function test_audita_a_ingestao(): void
    {
        $this->fingirDownloadDaFixture();

        $this->service()->ingerir('BA', 2026, 8);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'benchmark',
            'event' => 'ingestao',
            'result' => 'sucesso',
        ]);
    }

    public function test_audita_a_falha_da_ingestao(): void
    {
        config(['viabiliza.features.benchmark_redesim' => true]);

        Http::fake(['*' => Http::response('', 500)]);

        $this->service()->ingerir('BA', 2026, 8);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'benchmark',
            'event' => 'ingestao',
            'result' => 'falha',
        ]);
    }
}

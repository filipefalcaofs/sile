<?php

namespace Tests\Feature\Benchmark;

use App\Models\BenchmarkIngestion;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Comando de importação do benchmark. O backfill percorre 27 UFs por quase
 * cem competências, então o comando precisa ser idempotente, retomável e
 * explícito sobre o que falhou.
 */
class ImportarBenchmarkRedesimCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

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

    public function test_importa_uma_uf_e_competencia(): void
    {
        $this->fingirDownloadDaFixture();

        $this->artisan('benchmark:importar-redesim --ano=2026 --mes=8 --uf=BA')->assertSuccessful();

        $this->assertDatabaseHas('benchmark_ingestions', [
            'uf' => 'BA', 'ano' => 2026, 'mes' => 8, 'status' => BenchmarkIngestion::STATUS_SUCESSO,
        ]);
    }

    public function test_sem_uf_percorre_as_vinte_e_sete(): void
    {
        $this->fingirDownloadDaFixture();

        $this->artisan('benchmark:importar-redesim --ano=2026 --mes=8')->assertSuccessful();

        $this->assertSame(27, BenchmarkIngestion::query()->where(['ano' => 2026, 'mes' => 8])->count());
    }

    /**
     * Primeira chamada entrega a fixture, segunda devolve erro. Assim o mesmo
     * cenário prova as duas coisas: se o comando pular, o erro não é
     * consumido e a competência segue com sucesso; se reimportar, o erro
     * aparece.
     */
    private function fingirFixtureDepoisErro(): void
    {
        config(['viabiliza.features.benchmark_redesim' => true]);

        Http::fakeSequence()
            ->push((string) file_get_contents(base_path('tests/Fixtures/Benchmark/redesim-ba-202608.xlsx')), 200)
            ->push('', 500);
    }

    public function test_pula_competencia_ja_importada_com_sucesso(): void
    {
        $this->fingirFixtureDepoisErro();

        $this->artisan('benchmark:importar-redesim --ano=2026 --mes=8 --uf=BA')->assertSuccessful();
        $this->artisan('benchmark:importar-redesim --ano=2026 --mes=8 --uf=BA')->assertSuccessful();

        $this->assertDatabaseHas('benchmark_ingestions', [
            'uf' => 'BA', 'status' => BenchmarkIngestion::STATUS_SUCESSO,
        ]);
    }

    public function test_forcar_reimporta_competencia_ja_importada(): void
    {
        $this->fingirFixtureDepoisErro();

        $this->artisan('benchmark:importar-redesim --ano=2026 --mes=8 --uf=BA')->assertSuccessful();
        $this->artisan('benchmark:importar-redesim --ano=2026 --mes=8 --uf=BA --forcar')->assertFailed();

        $this->assertDatabaseHas('benchmark_ingestions', [
            'uf' => 'BA', 'status' => BenchmarkIngestion::STATUS_FALHA,
        ]);
    }

    public function test_competencia_que_falha_devolve_codigo_de_erro(): void
    {
        config(['viabiliza.features.benchmark_redesim' => true]);

        Http::fake(['*' => Http::response('', 500)]);

        $this->artisan('benchmark:importar-redesim --ano=2026 --mes=8 --uf=BA')->assertFailed();
    }

    public function test_desde_percorre_todas_as_competencias_do_intervalo(): void
    {
        Carbon::setTestNow('2026-03-10');

        $this->fingirDownloadDaFixture();

        $this->artisan('benchmark:importar-redesim --desde=2026-01 --uf=BA')->assertSuccessful();

        // Janeiro e fevereiro: março ainda não fechou.
        $this->assertSame(2, BenchmarkIngestion::query()->where('uf', 'BA')->count());
    }

    public function test_verificar_atualizacao_baixa_de_novo_mas_nao_regrava_arquivo_igual(): void
    {
        $this->fingirDownloadDaFixture();

        $this->artisan('benchmark:importar-redesim --ano=2026 --mes=8 --uf=BA')->assertSuccessful();
        $antes = BenchmarkIngestion::query()->firstOrFail();

        Carbon::setTestNow(now()->addDay());
        $this->artisan('benchmark:importar-redesim --ano=2026 --mes=8 --uf=BA --verificar-atualizacao')->assertSuccessful();

        Http::assertSentCount(2);
        $this->assertTrue($antes->updated_at->equalTo(BenchmarkIngestion::query()->firstOrFail()->updated_at));
    }

    public function test_verificar_atualizacao_regrava_quando_a_origem_mudou(): void
    {
        BenchmarkIngestion::create([
            'uf' => 'BA', 'ano' => 2026, 'mes' => 8, 'status' => BenchmarkIngestion::STATUS_SUCESSO,
            'url' => 'https://exemplo', 'bytes' => 10, 'hash' => 'hash-antigo',
            'linhas_lidas' => 5, 'linhas_descartadas' => 0, 'motivo' => null,
        ]);

        $this->fingirDownloadDaFixture();

        $this->artisan('benchmark:importar-redesim --ano=2026 --mes=8 --uf=BA --verificar-atualizacao')->assertSuccessful();

        $this->assertNotSame('hash-antigo', BenchmarkIngestion::query()->firstOrFail()->hash);
    }

    public function test_agendamento_roda_todo_dia_ate_o_dia_10_apenas(): void
    {
        $evento = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains((string) $e->command, 'benchmark:importar-redesim'));

        $this->assertNotNull($evento);
        $this->assertStringContainsString('--verificar-atualizacao', (string) $evento->command);
        $this->assertSame('0 3 * * *', $evento->expression);

        Carbon::setTestNow('2026-11-10 03:00:00');
        $this->assertTrue($evento->filtersPass($this->app));

        Carbon::setTestNow('2026-11-11 03:00:00');
        $this->assertFalse($evento->filtersPass($this->app));
    }
}

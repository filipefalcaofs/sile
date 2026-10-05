<?php

namespace Tests\Feature\Benchmark;

use App\Models\BenchmarkIngestion;
use App\Models\BenchmarkMunicipalityMetric;
use App\Services\Benchmark\BenchmarkRankingService;
use App\Support\Capitais;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Posição de Salvador, ranking das capitais e série histórica.
 *
 * A regra que governa tudo: posição só existe quando a competência tem as 27
 * UFs ingeridas com sucesso. Cobertura parcial produziria um ranking falso —
 * melhor dizer que não dá para posicionar.
 */
class BenchmarkRankingServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function service(): BenchmarkRankingService
    {
        return app(BenchmarkRankingService::class);
    }

    private function coberturaCompleta(int $ano = 2026, int $mes = 8): void
    {
        foreach (Capitais::ufs() as $uf) {
            BenchmarkIngestion::factory()->create(['uf' => $uf, 'ano' => $ano, 'mes' => $mes]);
        }
    }

    private function municipio(string $uf, string $nome, float $media, int $ano = 2026, int $mes = 8): void
    {
        BenchmarkMunicipalityMetric::factory()->create([
            'uf' => $uf, 'municipio' => $nome, 'ano' => $ano, 'mes' => $mes, 'media' => $media,
        ]);
    }

    private function salvadorCom(float $media, int $ano = 2026, int $mes = 8): void
    {
        $this->municipio(Capitais::UF_SALVADOR, Capitais::MUNICIPIO_SALVADOR, $media, $ano, $mes);
    }

    public function test_posicao_nacional_conta_os_municipios_mais_rapidos_mais_um(): void
    {
        $this->coberturaCompleta();
        $this->municipio('SP', 'CAMPINAS', 5.0);
        $this->municipio('MG', 'UBERLANDIA', 10.0);
        $this->salvadorCom(20.0);
        $this->municipio('RJ', 'NITEROI', 30.0);

        $posicao = $this->service()->posicaoNacional(2026, 8);

        $this->assertSame(3, $posicao['posicao']);
        $this->assertSame(4, $posicao['total']);
        $this->assertEqualsWithDelta(20.0, $posicao['media'], 0.01);
    }

    public function test_empate_produz_a_mesma_posicao(): void
    {
        $this->coberturaCompleta();
        $this->municipio('SP', 'CAMPINAS', 5.0);
        $this->municipio('MG', 'UBERLANDIA', 20.0);
        $this->salvadorCom(20.0);

        $this->assertSame(2, $this->service()->posicaoNacional(2026, 8)['posicao']);
    }

    public function test_cobertura_incompleta_nao_produz_posicao_e_lista_as_ufs_ausentes(): void
    {
        BenchmarkIngestion::factory()->create(['uf' => 'BA', 'ano' => 2026, 'mes' => 8]);
        $this->salvadorCom(20.0);

        $posicao = $this->service()->posicaoNacional(2026, 8);

        $this->assertNull($posicao['posicao']);
        $this->assertContains('SP', $posicao['ufs_ausentes']);
        $this->assertNotContains('BA', $posicao['ufs_ausentes']);
    }

    public function test_falha_de_ingestao_conta_como_uf_ausente(): void
    {
        $this->coberturaCompleta();

        BenchmarkIngestion::query()->where('uf', 'SP')->update([
            'status' => BenchmarkIngestion::STATUS_FALHA,
        ]);

        $this->salvadorCom(20.0);

        $this->assertSame(['SP'], $this->service()->posicaoNacional(2026, 8)['ufs_ausentes']);
    }

    public function test_ranking_das_capitais_ignora_os_demais_municipios(): void
    {
        $this->coberturaCompleta();
        $this->municipio('BA', 'FEIRA DE SANTANA', 1.0);
        $this->salvadorCom(20.0);
        $this->municipio('SE', 'ARACAJU', 2.0);

        $ranking = $this->service()->rankingCapitais(2026, 8);

        $comDado = array_values(array_filter($ranking, static fn (array $l): bool => $l['media'] !== null));

        $this->assertCount(2, $comDado);
        $this->assertSame('ARACAJU', $comDado[0]['municipio']);
        $this->assertSame(1, $comDado[0]['posicao']);
        $this->assertSame('SALVADOR', $comDado[1]['municipio']);
        $this->assertSame(2, $comDado[1]['posicao']);
    }

    public function test_capital_sem_dado_aparece_como_lacuna(): void
    {
        $this->coberturaCompleta();
        $this->salvadorCom(20.0);

        $ranking = collect($this->service()->rankingCapitais(2026, 8))->keyBy('uf');

        $this->assertCount(27, $ranking);
        $this->assertNull($ranking['SP']['media']);
        $this->assertNull($ranking['SP']['posicao']);
    }

    public function test_serie_de_salvador_vem_em_ordem_cronologica_sem_preencher_buracos(): void
    {
        $this->salvadorCom(50.0, 2024, 3);
        $this->salvadorCom(30.0, 2025, 7);
        $this->salvadorCom(20.0, 2026, 8);

        $serie = $this->service()->serieSalvador();

        $this->assertCount(3, $serie);
        $this->assertSame(2024, $serie[0]['ano']);
        $this->assertSame(3, $serie[0]['mes']);
        $this->assertSame(2026, $serie[2]['ano']);
        $this->assertEqualsWithDelta(20.0, $serie[2]['media'], 0.01);
    }

    public function test_serie_respeita_o_ano_inicial(): void
    {
        $this->salvadorCom(50.0, 2024, 3);
        $this->salvadorCom(20.0, 2026, 8);

        $this->assertCount(1, $this->service()->serieSalvador(2026));
    }

    public function test_competencia_mais_recente_com_cobertura_completa(): void
    {
        $this->coberturaCompleta(2026, 7);
        BenchmarkIngestion::factory()->create(['uf' => 'BA', 'ano' => 2026, 'mes' => 8]);

        $this->assertSame(['ano' => 2026, 'mes' => 7], $this->service()->ultimaCompetenciaCompleta());
    }

    public function test_sem_cobertura_completa_nao_ha_competencia_de_referencia(): void
    {
        BenchmarkIngestion::factory()->create(['uf' => 'BA', 'ano' => 2026, 'mes' => 8]);

        $this->assertNull($this->service()->ultimaCompetenciaCompleta());
    }
}

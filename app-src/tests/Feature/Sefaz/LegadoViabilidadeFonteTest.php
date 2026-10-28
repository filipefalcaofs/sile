<?php

namespace Tests\Feature\Sefaz;

use App\Models\ViabilityRequest;
use App\Services\Sefaz\LegadoViabilidadeFonte;
use App\Services\Sefaz\ViabilidadeVinculada;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\Sefaz\ConfiguraLegadoSimplifica;
use Tests\TestCase;

/**
 * Viabilidade anterior no legado (Simplifica/SAPS): a TVL deferida e ativa da
 * empresa, com os CNAEs da matriz de atividades dela.
 */
class LegadoViabilidadeFonteTest extends TestCase
{
    use ConfiguraLegadoSimplifica;
    use LazilyRefreshDatabase;

    private const CNPJ = '12345678000195';

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurarLegado();
    }

    /**
     * @param  array<string, mixed>  $sobre
     * @return array<string, mixed>
     */
    private function tvl(int $numero, array $sobre = []): array
    {
        return array_merge(['numero' => $numero, 'tipo' => 'definitiva', 'deferida' => true, 'ativa' => true, 'validade' => '2027-01-10T00:00:00Z'], $sobre);
    }

    private function fonte(): LegadoViabilidadeFonte
    {
        return app(LegadoViabilidadeFonte::class);
    }

    private function consultar(): ?ViabilidadeVinculada
    {
        return $this->fonte()->consultar(ViabilityRequest::factory()->make(), self::CNPJ);
    }

    public function test_devolve_a_tvl_deferida_e_ativa_com_os_cnaes_da_matriz(): void
    {
        Http::fake([
            'legado.test/v1/provisorias*' => Http::response(['data' => [
                $this->tvl(100, ['deferida' => false]),
                $this->tvl(200, ['ativa' => false]),
                $this->tvl(300, ['validade' => '2026-12-01T00:00:00Z']),
                $this->tvl(400, ['validade' => '2028-05-01T00:00:00Z']),
            ]]),
            'legado.test/v1/tvls/400' => Http::response(['data' => $this->tvl(400) + ['atividades' => [
                ['codigo' => '4711-3/02', 'descricao' => 'Comércio'],
                ['codigo' => '5611201', 'descricao' => 'Restaurantes'],
            ]]]),
        ]);

        $viabilidade = $this->consultar();

        $this->assertSame('400', $viabilidade?->numero);
        $this->assertSame(['4711-3/02', '5611201'], $viabilidade->cnaes);
        Http::assertSent(fn (Request $r): bool => $r->hasHeader('Authorization', 'Bearer lg_teste')
            && str_contains($r->url(), '/v1/provisorias') && $r['cnpj'] === self::CNPJ);
    }

    public function test_sem_tvl_deferida_e_ativa_nao_ha_base_e_nao_consulta_o_detalhe(): void
    {
        Http::fake([
            'legado.test/v1/provisorias*' => Http::response(['data' => [$this->tvl(100, ['deferida' => false])]]),
        ]);

        $this->assertNull($this->consultar());
        Http::assertNotSent(fn (Request $r): bool => str_contains($r->url(), '/v1/tvls/'));
    }

    public function test_lista_vazia_nao_tem_base(): void
    {
        Http::fake(['legado.test/v1/provisorias*' => Http::response(['data' => []])]);

        $this->assertNull($this->consultar());
    }

    public function test_tvl_sem_atividades_nao_serve_de_base(): void
    {
        Http::fake([
            'legado.test/v1/provisorias*' => Http::response(['data' => [$this->tvl(500)]]),
            'legado.test/v1/tvls/500' => Http::response(['data' => $this->tvl(500) + ['atividades' => []]]),
        ]);

        $this->assertNull($this->consultar());
    }

    public function test_falha_do_legado_vira_ausencia_de_base_sem_excecao(): void
    {
        foreach ([401, 403, 429, 500] as $status) {
            Cache::flush();
            Http::fake(['legado.test/*' => Http::response(['mensagem' => 'erro'], $status)]);

            $this->assertNull($this->consultar(), "HTTP {$status}");
        }

        Cache::flush();
        Http::fake(fn () => throw new ConnectionException('sem rota'));

        $this->assertNull($this->consultar());
    }

    public function test_so_esta_disponivel_com_o_interruptor_ligado_e_url_e_token(): void
    {
        $this->assertTrue($this->fonte()->disponivel());

        $this->configurarLegado(token: null);
        $this->assertFalse($this->fonte()->disponivel());

        $this->configurarLegado(url: null);
        $this->assertFalse($this->fonte()->disponivel());

        $this->configurarLegado(habilitada: false);
        $this->assertFalse($this->fonte()->disponivel());
    }

    public function test_guarda_o_resultado_por_cnpj_e_nao_repete_a_chamada(): void
    {
        Http::fake([
            'legado.test/v1/provisorias*' => Http::response(['data' => [$this->tvl(600)]]),
            'legado.test/v1/tvls/600' => Http::response(['data' => $this->tvl(600) + ['atividades' => [['codigo' => '4711302', 'descricao' => 'x']]]]),
        ]);

        $this->consultar();
        $this->consultar();

        Http::assertSentCount(2);
    }

    public function test_ausencia_de_base_tambem_fica_guardada(): void
    {
        Http::fake(['legado.test/v1/provisorias*' => Http::response(['data' => []])]);

        $this->consultar();
        $this->consultar();

        Http::assertSentCount(1);
    }
}

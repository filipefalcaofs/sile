<?php

namespace Tests\Feature\Geo;

use App\Enums\LotePoligonoFonte;
use App\Models\ViabilityRequest;
use App\Services\Geo\PoligonoLoteHit;
use App\Services\Geo\SemapPoligonoLoteFonte;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Camada 18 (`SEMAP — Inscrições Geo`) do ArcGIS municipal, consultada pela
 * inscrição imobiliária. Resposta medida contra o serviço real em 2026-10-03:
 * a inscrição 344672 devolve um Polygon de 5 vértices em EPSG:4326.
 */
class SemapPoligonoLoteFonteTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sile.features.arcgis_semap' => true]);
    }

    /**
     * @param  list<array<string, mixed>>  $features
     * @return array<string, mixed>
     */
    private function colecao(array $features): array
    {
        return ['type' => 'FeatureCollection', 'features' => $features];
    }

    /**
     * @return array<string, mixed>
     */
    private function feicao(float $deslocamento = 0.0, string $tipo = 'Polygon'): array
    {
        return [
            'type' => 'Feature',
            'geometry' => [
                'type' => $tipo,
                'coordinates' => [[
                    [-38.516331 + $deslocamento, -12.977852],
                    [-38.516291 + $deslocamento, -12.977746],
                    [-38.516453 + $deslocamento, -12.977688],
                    [-38.516492 + $deslocamento, -12.977795],
                    [-38.516331 + $deslocamento, -12.977852],
                ]],
            ],
            'properties' => ['INSCRICAO' => '344672', 'CODLOG' => '212', 'PORTA' => '06'],
        ];
    }

    private function processo(?string $inscricao = '344672'): ViabilityRequest
    {
        return ViabilityRequest::factory()->draft()->create(['property_registration' => $inscricao]);
    }

    public function test_inscricao_com_uma_feicao_devolve_o_poligono(): void
    {
        Http::fake(['*' => Http::response($this->colecao([$this->feicao()]))]);

        $hit = app(SemapPoligonoLoteFonte::class)->buscar($this->processo());

        $this->assertSame(PoligonoLoteHit::ENCONTRADO, $hit->status);
        $this->assertSame(LotePoligonoFonte::Semap, $hit->fonte);
        $this->assertSame('Polygon', $hit->geojson['type']);
        $this->assertSame('212', $hit->propriedades['CODLOG']);
    }

    public function test_inscricao_sem_feicao_nao_encontra(): void
    {
        Http::fake(['*' => Http::response($this->colecao([]))]);

        $hit = app(SemapPoligonoLoteFonte::class)->buscar($this->processo('9919295'));

        $this->assertSame(PoligonoLoteHit::NAO_ENCONTRADO, $hit->status);
        $this->assertNull($hit->geojson);
    }

    public function test_processo_sem_inscricao_nao_chama_o_servico(): void
    {
        Http::fake();

        $hit = app(SemapPoligonoLoteFonte::class)->buscar($this->processo(null));

        $this->assertSame(PoligonoLoteHit::NAO_ENCONTRADO, $hit->status);
        Http::assertNothingSent();
    }

    public function test_falha_http_e_indisponivel_sem_excecao(): void
    {
        Http::fake(['*' => Http::response('erro', 500)]);

        $hit = app(SemapPoligonoLoteFonte::class)->buscar($this->processo());

        $this->assertSame(PoligonoLoteHit::INDISPONIVEL, $hit->status);
        $this->assertStringContainsString('500', (string) $hit->motivo);
    }

    public function test_conexao_recusada_e_indisponivel(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $hit = app(SemapPoligonoLoteFonte::class)->buscar($this->processo());

        $this->assertSame(PoligonoLoteHit::INDISPONIVEL, $hit->status);
    }

    public function test_toggle_desligado_nao_consulta(): void
    {
        config(['sile.features.arcgis_semap' => false]);
        Http::fake();

        $hit = app(SemapPoligonoLoteFonte::class)->buscar($this->processo());

        $this->assertSame(PoligonoLoteHit::INDISPONIVEL, $hit->status);
        Http::assertNothingSent();
    }

    public function test_geometrias_distintas_para_a_mesma_inscricao_sao_ambiguas(): void
    {
        Http::fake(['*' => Http::response($this->colecao([$this->feicao(), $this->feicao(0.003)]))]);

        $hit = app(SemapPoligonoLoteFonte::class)->buscar($this->processo());

        $this->assertSame(PoligonoLoteHit::AMBIGUO, $hit->status);
        $this->assertSame(2, $hit->candidatas);
        $this->assertNull($hit->geojson);
    }

    public function test_geometrias_identicas_contam_como_uma(): void
    {
        Http::fake(['*' => Http::response($this->colecao([$this->feicao(), $this->feicao()]))]);

        $hit = app(SemapPoligonoLoteFonte::class)->buscar($this->processo());

        $this->assertSame(PoligonoLoteHit::ENCONTRADO, $hit->status);
    }

    public function test_geometria_linear_nao_e_lote(): void
    {
        Http::fake(['*' => Http::response($this->colecao([$this->feicao(0.0, 'LineString')]))]);

        $hit = app(SemapPoligonoLoteFonte::class)->buscar($this->processo());

        $this->assertSame(PoligonoLoteHit::NAO_ENCONTRADO, $hit->status);
    }

    public function test_consulta_uma_vez_por_inscricao_na_mesma_execucao(): void
    {
        Http::fake(['*' => Http::response($this->colecao([$this->feicao()]))]);

        $fonte = app(SemapPoligonoLoteFonte::class);
        $fonte->buscar($this->processo());
        $fonte->buscar($this->processo());

        Http::assertSentCount(1);
    }
}

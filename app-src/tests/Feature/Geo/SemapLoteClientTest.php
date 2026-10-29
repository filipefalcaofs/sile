<?php

namespace Tests\Feature\Geo;

use App\Services\Geo\EnderecamentoHit;
use App\Services\Geo\SemapLoteClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Camada 18 (`SEMAP — Inscrições Geo`) do ArcGIS municipal, consultada pela
 * inscrição imobiliária. Fallback do endereçamento SEDUR: cobre endereço que
 * nunca passou por licenciamento. Resposta medida contra o serviço real em
 * 2026-10-03 — a inscrição 344672 devolve um Polygon de 5 vértices em 4326.
 */
class SemapLoteClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['viabiliza.features.arcgis_semap' => true]);
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
            'properties' => [
                'INSCRICAO' => '344672',
                'CODLOG' => '212',
                'PORTA' => '06',
                'CAT_DE_USO' => 'COMERCIAL',
                'PADRAOCONS' => 'MÉDIO',
            ],
        ];
    }

    public function test_inscricao_com_uma_feicao_devolve_o_poligono(): void
    {
        Http::fake(['*' => Http::response($this->colecao([$this->feicao()]))]);

        $hit = app(SemapLoteClient::class)->consultar('344672');

        $this->assertTrue($hit->encontrado());
        $this->assertSame('Polygon', $hit->geometria['type']);
        $this->assertSame('212', $hit->propriedades['CODLOG']);
        $this->assertSame('COMERCIAL', $hit->propriedades['CAT_DE_USO']);
        $this->assertSame('MÉDIO', $hit->propriedades['PADRAOCONS']);
        Http::assertSent(fn ($request): bool => str_contains(urldecode($request->url()), 'CAT_DE_USO')
            && str_contains(urldecode($request->url()), 'PADRAOCONS'));
    }

    public function test_token_configurado_vai_no_parametro_token_da_url(): void
    {
        config(['viabiliza.integrations.arcgis_semap.token' => 'token-semap-123']);
        Http::fake(['*' => Http::response($this->colecao([$this->feicao()]))]);

        $this->assertTrue(app(SemapLoteClient::class)->consultar('344672')->encontrado());

        Http::assertSent(function ($request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return ($query['token'] ?? null) === 'token-semap-123'
                && ! $request->hasHeader('X-Esri-Authorization');
        });
    }

    public function test_sem_token_nao_envia_parametro_nem_cabecalho(): void
    {
        Http::fake(['*' => Http::response($this->colecao([$this->feicao()]))]);

        app(SemapLoteClient::class)->consultar('344672');

        Http::assertSent(fn ($request): bool => ! str_contains($request->url(), 'token=')
            && ! $request->hasHeader('X-Esri-Authorization'));
    }

    public function test_erro_do_arcgis_aparece_no_motivo(): void
    {
        Http::fake(['*' => Http::response(['error' => ['code' => 499, 'message' => 'Token Required', 'details' => []]])]);

        $hit = app(SemapLoteClient::class)->consultar('198845-0');

        $this->assertSame(EnderecamentoHit::INDISPONIVEL, $hit->status);
        $this->assertSame('Cadastro municipal (SEMAP) recusou a consulta: 499 Token Required.', $hit->motivo);
    }

    public function test_erro_do_arcgis_com_detalhes_inclui_os_detalhes(): void
    {
        Http::fake(['*' => Http::response(['error' => ['code' => 498, 'message' => 'Invalid Token', 'details' => ['Token expirado.']]])]);

        $hit = app(SemapLoteClient::class)->consultar('344672');

        $this->assertSame('Cadastro municipal (SEMAP) recusou a consulta: 498 Invalid Token (Token expirado.).', $hit->motivo);
    }

    public function test_resposta_sem_geojson_nem_erro_continua_fora_do_formato(): void
    {
        Http::fake(['*' => Http::response(['qualquer' => 'coisa'])]);

        $hit = app(SemapLoteClient::class)->consultar('344672');

        $this->assertSame(EnderecamentoHit::INDISPONIVEL, $hit->status);
        $this->assertSame('Resposta do SEMAP fora do formato GeoJSON.', $hit->motivo);
    }

    public function test_inscricao_sem_feicao_nao_encontra(): void
    {
        Http::fake(['*' => Http::response($this->colecao([]))]);

        $hit = app(SemapLoteClient::class)->consultar('9919295');

        $this->assertSame(EnderecamentoHit::NAO_ENCONTRADO, $hit->status);
        $this->assertNull($hit->geometria);
    }

    public function test_inscricao_vazia_nao_chama_o_servico(): void
    {
        Http::fake();

        $hit = app(SemapLoteClient::class)->consultar('');

        $this->assertSame(EnderecamentoHit::NAO_ENCONTRADO, $hit->status);
        Http::assertNothingSent();
    }

    public function test_falha_http_e_indisponivel_sem_excecao(): void
    {
        Http::fake(['*' => Http::response('erro', 500)]);

        $hit = app(SemapLoteClient::class)->consultar('344672');

        $this->assertSame(EnderecamentoHit::INDISPONIVEL, $hit->status);
        $this->assertStringContainsString('500', (string) $hit->motivo);
    }

    public function test_conexao_recusada_e_indisponivel(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $hit = app(SemapLoteClient::class)->consultar('344672');

        $this->assertSame(EnderecamentoHit::INDISPONIVEL, $hit->status);
    }

    public function test_toggle_desligado_nao_consulta(): void
    {
        config(['viabiliza.features.arcgis_semap' => false]);
        Http::fake();

        $hit = app(SemapLoteClient::class)->consultar('344672');

        $this->assertSame(EnderecamentoHit::DESLIGADO, $hit->status);
        Http::assertNothingSent();
    }

    public function test_geometrias_distintas_para_a_mesma_inscricao_sao_ambiguas(): void
    {
        Http::fake(['*' => Http::response($this->colecao([$this->feicao(), $this->feicao(0.003)]))]);

        $hit = app(SemapLoteClient::class)->consultar('344672');

        $this->assertSame(EnderecamentoHit::AMBIGUO, $hit->status);
        $this->assertSame(2, $hit->candidatas);
        $this->assertNull($hit->geometria);
    }

    public function test_geometrias_identicas_contam_como_uma(): void
    {
        Http::fake(['*' => Http::response($this->colecao([$this->feicao(), $this->feicao()]))]);

        $this->assertTrue(app(SemapLoteClient::class)->consultar('344672')->encontrado());
    }

    public function test_geometria_linear_nao_e_lote(): void
    {
        Http::fake(['*' => Http::response($this->colecao([$this->feicao(0.0, 'LineString')]))]);

        $hit = app(SemapLoteClient::class)->consultar('344672');

        $this->assertSame(EnderecamentoHit::NAO_ENCONTRADO, $hit->status);
    }

    public function test_consulta_uma_vez_por_inscricao_na_mesma_execucao(): void
    {
        Http::fake(['*' => Http::response($this->colecao([$this->feicao()]))]);

        $cliente = app(SemapLoteClient::class);
        $cliente->consultar('344672');
        $cliente->consultar('344672');

        Http::assertSentCount(1);
    }
}

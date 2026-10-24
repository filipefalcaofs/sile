<?php

namespace Tests\Feature\Geo;

use App\Models\Parameter;
use App\Services\Geo\EixoViarioHit;
use App\Services\Geo\GeoServerEixoViarioClient;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeoServerEixoViarioClientTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ligarToggle('1');
    }

    private function ligarToggle(string $valor): void
    {
        Parameter::query()->updateOrCreate(
            ['key' => 'features.geoserver_via_classe'],
            [
                'group' => 'features',
                'type' => 'boolean',
                'value' => $valor,
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Consulta da classificação viária no GeoServer.',
            ],
        );

        cache()->forget('viabiliza.parameters.features.geoserver_via_classe');
    }

    /**
     * @param  list<array<string, mixed>>  $geometrias
     * @return array<string, mixed>
     */
    private function colecao(array $geometrias): array
    {
        return [
            'type' => 'FeatureCollection',
            'features' => array_map(static fn (array $geometria): array => [
                'type' => 'Feature',
                'properties' => ['CODLOG' => 2075],
                'geometry' => $geometria,
            ], $geometrias),
        ];
    }

    public function test_junta_todos_os_segmentos_da_rua(): void
    {
        Http::fake(['*' => Http::response($this->colecao([
            ['type' => 'LineString', 'coordinates' => [[-38.50, -12.97], [-38.49, -12.97]]],
            ['type' => 'MultiLineString', 'coordinates' => [
                [[-38.49, -12.97], [-38.48, -12.97]],
                [[-38.48, -12.97], [-38.47, -12.97]],
            ]],
        ]), 200)]);

        $hit = app(GeoServerEixoViarioClient::class)->consultar('2075');

        $this->assertSame(EixoViarioHit::IDENTIFICADO, $hit->status);
        $this->assertCount(3, $hit->linhas);
        $this->assertSame([-38.50, -12.97], $hit->linhas[0][0]);
    }

    public function test_pede_todos_os_segmentos_e_nao_so_um(): void
    {
        Http::fake(['*' => Http::response($this->colecao([]), 200)]);

        app(GeoServerEixoViarioClient::class)->consultar('2075');

        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return ($query['CQL_FILTER'] ?? null) === 'CODLOG=2075'
                && (int) ($query['maxFeatures'] ?? 0) > 1;
        });
    }

    public function test_codlog_sem_feicao_e_nao_encontrado(): void
    {
        Http::fake(['*' => Http::response($this->colecao([]), 200)]);

        $hit = app(GeoServerEixoViarioClient::class)->consultar('999999');

        $this->assertSame(EixoViarioHit::NAO_ENCONTRADO, $hit->status);
        $this->assertSame([], $hit->linhas);
    }

    public function test_feicao_sem_linha_valida_e_nao_encontrado(): void
    {
        Http::fake(['*' => Http::response($this->colecao([
            ['type' => 'Point', 'coordinates' => [-38.5, -12.97]],
        ]), 200)]);

        $hit = app(GeoServerEixoViarioClient::class)->consultar('2075');

        $this->assertSame(EixoViarioHit::NAO_ENCONTRADO, $hit->status);
    }

    public function test_falha_de_rede_e_indisponivel_e_nao_estoura(): void
    {
        Http::fake(fn () => throw new ConnectionException('sem rota'));

        $hit = app(GeoServerEixoViarioClient::class)->consultar('2075');

        $this->assertSame(EixoViarioHit::INDISPONIVEL, $hit->status);
    }

    public function test_http_de_erro_e_indisponivel(): void
    {
        Http::fake(['*' => Http::response('erro', 500)]);

        $this->assertSame(EixoViarioHit::INDISPONIVEL, app(GeoServerEixoViarioClient::class)->consultar('2075')->status);
    }

    public function test_toggle_desligado_nao_chama_o_servidor(): void
    {
        $this->ligarToggle('0');
        Http::fake();

        $hit = app(GeoServerEixoViarioClient::class)->consultar('2075');

        $this->assertSame(EixoViarioHit::DESLIGADO, $hit->status);
        Http::assertNothingSent();
    }

    public function test_codlog_vazio_nao_chama_o_servidor(): void
    {
        Http::fake();

        $hit = app(GeoServerEixoViarioClient::class)->consultar('  ');

        $this->assertSame(EixoViarioHit::INDISPONIVEL, $hit->status);
        Http::assertNothingSent();
    }

    public function test_rua_sem_feicao_na_camada_viaria_tenta_a_camada_de_logradouros(): void
    {
        Http::fakeSequence()
            ->push($this->colecao([]), 200)
            ->push($this->colecao([
                ['type' => 'LineString', 'coordinates' => [[-38.50, -12.97], [-38.49, -12.97]]],
            ]), 200);

        $hit = app(GeoServerEixoViarioClient::class)->consultar('2075');

        $this->assertSame(EixoViarioHit::IDENTIFICADO, $hit->status);
        $this->assertCount(1, $hit->linhas);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => str_contains(urldecode($request->url()), 'logradouros:VM_LOGRADOURO'));
    }

    public function test_rua_sem_feicao_nas_duas_camadas_e_nao_encontrada(): void
    {
        Http::fake(['*' => Http::response($this->colecao([]), 200)]);

        $this->assertSame(EixoViarioHit::NAO_ENCONTRADO, app(GeoServerEixoViarioClient::class)->consultar('2075')->status);
        Http::assertSentCount(2);
    }

    public function test_camada_de_logradouros_fora_do_ar_nao_derruba_a_primeira_resposta(): void
    {
        Http::fakeSequence()
            ->push($this->colecao([]), 200)
            ->push('erro', 500);

        $this->assertSame(EixoViarioHit::NAO_ENCONTRADO, app(GeoServerEixoViarioClient::class)->consultar('2075')->status);
    }

    public function test_codlog_nao_numerico_nao_chega_ao_filtro_cql(): void
    {
        Http::fake();

        $hit = app(GeoServerEixoViarioClient::class)->consultar('2075 OR 1=1');

        $this->assertSame(EixoViarioHit::INDISPONIVEL, $hit->status);
        Http::assertNothingSent();
    }

    public function test_segunda_consulta_do_mesmo_codlog_nao_chama_de_novo(): void
    {
        Http::fake(['*' => Http::response($this->colecao([
            ['type' => 'LineString', 'coordinates' => [[-38.50, -12.97], [-38.49, -12.97]]],
        ]), 200)]);

        app(GeoServerEixoViarioClient::class)->consultar('2075');
        $this->app->forgetInstance(GeoServerEixoViarioClient::class);
        $segunda = app(GeoServerEixoViarioClient::class)->consultar('2075');

        $this->assertSame(EixoViarioHit::IDENTIFICADO, $segunda->status);
        Http::assertSentCount(1);
    }
}

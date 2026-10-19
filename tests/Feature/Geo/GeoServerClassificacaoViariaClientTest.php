<?php

namespace Tests\Feature\Geo;

use App\Models\Parameter;
use App\Services\Geo\ClassificacaoViariaHit;
use App\Services\Geo\GeoServerClassificacaoViariaClient;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeoServerClassificacaoViariaClientTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Parameter::query()->updateOrCreate(
            ['key' => 'features.geoserver_via_classe'],
            [
                'group' => 'features',
                'type' => 'boolean',
                'value' => '1',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Consulta da classificação viária no GeoServer.',
            ],
        );

        cache()->forget('viabiliza.parameters.features.geoserver_via_classe');
    }

    /**
     * @return array<string, mixed>
     */
    private function colecao(?string $classe): array
    {
        return [
            'type' => 'FeatureCollection',
            'features' => $classe === null ? [] : [[
                'type' => 'Feature',
                'properties' => ['CODLOG' => 822, 'LOGRADOURO' => 'Avenida Joana Angélica', 'CLASSIFICACAO_VIARIA' => $classe],
            ]],
        ];
    }

    public function test_classe_do_geoserver_volta_na_grafia_da_lei(): void
    {
        Http::fake(['*' => Http::response($this->colecao('VC-I'), 200)]);

        $hit = app(GeoServerClassificacaoViariaClient::class)->consultar('822');

        $this->assertSame(ClassificacaoViariaHit::IDENTIFICADO, $hit->status);
        $this->assertSame('VC I', $hit->classe);
        $this->assertSame('VC-I', $hit->classeBruta);
    }

    public function test_codlog_vai_no_filtro_cql_e_a_consulta_e_memoizada(): void
    {
        Http::fake(['*' => Http::response($this->colecao('VA-II'), 200)]);

        $client = app(GeoServerClassificacaoViariaClient::class);
        $client->consultar('5502');
        $client->consultar('5502');

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'CODLOG=5502')
            && str_contains($request->url(), 'louos_classificacao_viaria%3AVM_L_CLASSIF_VIARIA'));
    }

    public function test_classe_fora_das_sete_oficiais_e_indisponivel(): void
    {
        Http::fake(['*' => Http::response($this->colecao('VX-9'), 200)]);

        $hit = app(GeoServerClassificacaoViariaClient::class)->consultar('999');

        $this->assertSame(ClassificacaoViariaHit::INDISPONIVEL, $hit->status);
        $this->assertNull($hit->classe);
        $this->assertSame('VX-9', $hit->classeBruta);
    }

    public function test_codlog_sem_linha_na_camada_e_nao_encontrado(): void
    {
        Http::fake(['*' => Http::response($this->colecao(null), 200)]);

        $hit = app(GeoServerClassificacaoViariaClient::class)->consultar('123');

        $this->assertSame(ClassificacaoViariaHit::NAO_ENCONTRADO, $hit->status);
        $this->assertNull($hit->classe);
    }

    public function test_falha_http_e_indisponivel_sem_inventar_classe(): void
    {
        Http::fake(['*' => Http::response('erro', 500)]);

        $hit = app(GeoServerClassificacaoViariaClient::class)->consultar('822');

        $this->assertSame(ClassificacaoViariaHit::INDISPONIVEL, $hit->status);
        $this->assertNull($hit->classe);
        $this->assertNotNull($hit->motivo);
    }

    public function test_interruptor_desligado_nao_chama_o_geoserver(): void
    {
        Parameter::query()->where('key', 'features.geoserver_via_classe')->update(['value' => '0']);
        cache()->forget('viabiliza.parameters.features.geoserver_via_classe');
        Http::fake();

        $hit = app(GeoServerClassificacaoViariaClient::class)->consultar('822');

        $this->assertSame(ClassificacaoViariaHit::DESLIGADO, $hit->status);
        Http::assertNothingSent();
    }

    public function test_consulta_identificada_e_auditada(): void
    {
        Http::fake(['*' => Http::response($this->colecao('VC-I'), 200)]);

        app(GeoServerClassificacaoViariaClient::class)->consultar('822');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'territorio',
            'event' => 'classificacao-viaria',
            'result' => 'identificado',
        ]);
    }
}

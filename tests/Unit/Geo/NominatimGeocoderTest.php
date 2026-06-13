<?php

namespace Tests\Unit\Geo;

use App\Services\Geo\AddressNotFoundException;
use App\Services\Geo\Geocoder;
use App\Services\Geo\GeocodeResult;
use App\Services\Geo\GeocoderException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Contrato Geocoder + provider real Nominatim (HU-029): tudo provado com
 * Http::fake (a chamada real ao Nominatim é evidência do fechamento — 04-08).
 * Espelha o padrão de BrasilApiCnpjLookup (contrato + cache só de sucesso +
 * retry/timeout/backoff parametrizados). Sem RefreshDatabase: Settings::get
 * cai no fallback de config sem banco (precedente do SettingsTest).
 */
class NominatimGeocoderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        // Backoff zero mantém o teste rápido — o retry real dorme em produção.
        config(['sile.integrations.geocoding.backoff_ms' => 0]);
    }

    /**
     * Resposta REAL do Nominatim para um endereço de Salvador (lat/lon como
     * string, importance como proxy de confiança, address detalhado).
     *
     * @return array<int, array<string, mixed>>
     */
    private function nominatimFixture(): array
    {
        return [[
            'lat' => '-12.9730401',
            'lon' => '-38.5122621',
            'display_name' => 'Praça da Sé, Centro Histórico, Salvador, Bahia, Brasil',
            'importance' => 0.62,
            'address' => [
                'road' => 'Praça da Sé',
                'suburb' => 'Centro Histórico',
                'city' => 'Salvador',
                'state' => 'Bahia',
                'country_code' => 'br',
            ],
        ]];
    }

    public function test_geocodifica_endereco_de_salvador_em_coordenada_e_confianca(): void
    {
        Http::fake(['*/search*' => Http::response($this->nominatimFixture(), 200)]);

        $result = app(Geocoder::class)->geocode('Praça da Sé, Salvador');

        $this->assertInstanceOf(GeocodeResult::class, $result);
        $this->assertEqualsWithDelta(-12.97, $result->latitude, 0.01);
        $this->assertEqualsWithDelta(-38.51, $result->longitude, 0.01);
        $this->assertSame(0.62, $result->confidence);
        $this->assertSame('Salvador', $result->address['city']);
        $this->assertStringContainsString('Salvador', $result->displayName);
    }

    public function test_to_array_expoe_contrato_snake_case_para_o_mapa(): void
    {
        Http::fake(['*/search*' => Http::response($this->nominatimFixture(), 200)]);

        $array = app(Geocoder::class)->geocode('Praça da Sé, Salvador')->toArray();

        $this->assertSame(
            ['latitude', 'longitude', 'display_name', 'confidence', 'address'],
            array_keys($array),
        );
        $this->assertEqualsWithDelta(-12.97, $array['latitude'], 0.01);
        $this->assertEqualsWithDelta(-38.51, $array['longitude'], 0.01);
    }

    public function test_envia_user_agent_identificavel(): void
    {
        Http::fake(['*/search*' => Http::response($this->nominatimFixture(), 200)]);

        app(Geocoder::class)->geocode('Praça da Sé, Salvador');

        // Política do Nominatim: User-Agent identificável é obrigatório
        // (libs HTTP genéricas são bloqueadas).
        Http::assertSent(fn ($request) => $request->hasHeader('User-Agent')
            && str_contains($request->header('User-Agent')[0], 'SILE'));
    }

    public function test_consulta_usa_parametros_oficiais_do_nominatim(): void
    {
        Http::fake(['*/search*' => Http::response($this->nominatimFixture(), 200)]);

        app(Geocoder::class)->geocode('Praça da Sé, Salvador');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/search')
                && $request['format'] === 'jsonv2'
                && $request['countrycodes'] === 'br'
                && (string) $request['limit'] === '1';
        });
    }

    public function test_endereco_sem_resultado_lanca_excecao_nao_encontrado(): void
    {
        // Nominatim devolve [] quando nada casa.
        Http::fake(['*/search*' => Http::response([], 200)]);

        $this->expectException(AddressNotFoundException::class);

        app(Geocoder::class)->geocode('endereço inexistente xyz');
    }

    public function test_corpo_html_de_bloqueio_e_tratado_como_nao_encontrado(): void
    {
        // User-Agent recusado pelo Nominatim retorna HTML (não-array) com 200.
        Http::fake(['*/search*' => Http::response('<html>blocked</html>', 200)]);

        $this->expectException(AddressNotFoundException::class);

        app(Geocoder::class)->geocode('Praça da Sé, Salvador');
    }

    public function test_resposta_com_erro_http_lanca_excecao_de_geocodificacao(): void
    {
        Http::fake(['*/search*' => Http::response('erro', 500)]);

        $this->expectException(GeocoderException::class);

        app(Geocoder::class)->geocode('Praça da Sé, Salvador');
    }

    public function test_indisponibilidade_de_conexao_lanca_excecao_de_geocodificacao(): void
    {
        config(['sile.integrations.geocoding.retries' => 1]);

        Http::fake(['*/search*' => Http::sequence()->pushFailedConnection()]);

        $this->expectException(GeocoderException::class);

        app(Geocoder::class)->geocode('Praça da Sé, Salvador');
    }

    public function test_retry_parametrizado_repete_antes_de_desistir(): void
    {
        config(['sile.integrations.geocoding.retries' => 2]);

        // Primeira tentativa falha na conexão; a segunda (retry) sucede.
        Http::fake([
            '*/search*' => Http::sequence()
                ->pushFailedConnection()
                ->push($this->nominatimFixture(), 200),
        ]);

        $result = app(Geocoder::class)->geocode('Praça da Sé, Salvador');

        $this->assertEqualsWithDelta(-12.97, $result->latitude, 0.01);
        Http::assertSentCount(2);
    }

    public function test_sucesso_e_cacheado(): void
    {
        Http::fake(['*/search*' => Http::response($this->nominatimFixture(), 200)]);

        $geocoder = app(Geocoder::class);
        $geocoder->geocode('Praça da Sé, Salvador');
        $geocoder->geocode('Praça da Sé, Salvador');

        Http::assertSentCount(1);
    }

    public function test_falha_nao_e_cacheada(): void
    {
        config(['sile.integrations.geocoding.retries' => 1]);

        // 1ª chamada falha (indisponibilidade) e a 2ª sucede: se a falha
        // tivesse sido cacheada, a 2ª nunca chegaria à rede.
        Http::fake([
            '*/search*' => Http::sequence()
                ->pushFailedConnection()
                ->push($this->nominatimFixture(), 200),
        ]);

        $geocoder = app(Geocoder::class);

        try {
            $geocoder->geocode('Praça da Sé, Salvador');
            $this->fail('Esperava GeocoderException na indisponibilidade.');
        } catch (GeocoderException) {
            // esperado — falha nunca é cacheada
        }

        $result = $geocoder->geocode('Praça da Sé, Salvador');

        $this->assertEqualsWithDelta(-12.97, $result->latitude, 0.01);
        Http::assertSentCount(2);
    }
}

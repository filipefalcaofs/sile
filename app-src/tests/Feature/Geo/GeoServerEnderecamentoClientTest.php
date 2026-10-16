<?php

namespace Tests\Feature\Geo;

use App\Models\Parameter;
use App\Services\Geo\EnderecamentoHit;
use App\Services\Geo\GeoServerEnderecamentoClient;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeoServerEnderecamentoClientTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ligar(true);
    }

    private function ligar(bool $ligado): void
    {
        Parameter::query()->updateOrCreate(
            ['key' => 'features.geoserver_enderecamento'],
            [
                'group' => 'features',
                'type' => 'boolean',
                'value' => $ligado ? '1' : '0',
                'default_value' => '1',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'Polígono do endereçamento.',
            ],
        );

        cache()->forget('viabiliza.parameters.features.geoserver_enderecamento');
    }

    /**
     * Quadrado de 0,0005° com canto em ($lng, $lat).
     *
     * @return array{type: string, coordinates: list<list<list<float>>>}
     */
    private function quadrado(float $lng, float $lat): array
    {
        return ['type' => 'Polygon', 'coordinates' => [[
            [$lng, $lat], [$lng + 0.0005, $lat], [$lng + 0.0005, $lat + 0.0005], [$lng, $lat + 0.0005], [$lng, $lat],
        ]]];
    }

    /**
     * O mesmo quadrado redesenhado: vértice extra no meio da base e o lado de
     * cima um pouco recuado no canto direito, sem mudar o contorno externo.
     *
     * @return array{type: string, coordinates: list<list<list<float>>>}
     */
    private function quadradoRedesenhado(float $lng, float $lat): array
    {
        return ['type' => 'Polygon', 'coordinates' => [[
            [$lng, $lat], [$lng + 0.00025, $lat], [$lng + 0.0005, $lat], [$lng + 0.0005, $lat + 0.0005],
            [$lng + 0.000499, $lat + 0.0005], [$lng, $lat + 0.0005], [$lng, $lat],
        ]]];
    }

    /**
     * Mesmo contorno externo do quadrado, mas sem o quarto superior direito.
     *
     * @return array{type: string, coordinates: list<list<list<float>>>}
     */
    private function quadradoRecortado(float $lng, float $lat): array
    {
        return ['type' => 'Polygon', 'coordinates' => [[
            [$lng, $lat], [$lng + 0.0005, $lat], [$lng + 0.0005, $lat + 0.00025], [$lng + 0.00025, $lat + 0.00025],
            [$lng + 0.00025, $lat + 0.0005], [$lng, $lat + 0.0005], [$lng, $lat],
        ]]];
    }

    /**
     * @param  list<array{complemento: ?string, geometria: array<string, mixed>, origem?: string}>  $feicoes
     */
    private function responder(array $feicoes): void
    {
        Http::fake(['*' => Http::response([
            'type' => 'FeatureCollection',
            'features' => array_map(fn (array $f): array => [
                'type' => 'Feature',
                'geometry' => $f['geometria'],
                'properties' => ['CODLOG' => 24574, 'NUMERACAO_METRICA' => 70, 'COMPLEMENTO' => $f['complemento'], 'ALVARA' => 'A-1', 'ORIGEM' => $f['origem'] ?? null],
            ], $feicoes),
        ])]);
    }

    public function test_uma_feicao_devolve_o_poligono(): void
    {
        $this->responder([['complemento' => null, 'geometria' => $this->quadrado(-38.5, -12.98)]]);

        $hit = app(GeoServerEnderecamentoClient::class)->consultar('24574', '70');

        $this->assertSame(EnderecamentoHit::ENCONTRADO, $hit->status);
        $this->assertSame($this->quadrado(-38.5, -12.98), $hit->geometria);
        $this->assertSame('A-1', $hit->propriedades['ALVARA']);
    }

    public function test_tres_geometrias_distintas_sem_complemento_e_ambiguo(): void
    {
        $this->responder([
            ['complemento' => 'BL-3', 'geometria' => $this->quadrado(-38.5, -12.98)],
            ['complemento' => 'BL-25', 'geometria' => $this->quadrado(-38.6, -12.98)],
            ['complemento' => 'BL-20', 'geometria' => $this->quadrado(-38.7, -12.98)],
        ]);

        $hit = app(GeoServerEnderecamentoClient::class)->consultar('24574', '70');

        $this->assertSame(EnderecamentoHit::AMBIGUO, $hit->status);
        $this->assertSame(3, $hit->candidatas);
        $this->assertNull($hit->geometria);
    }

    public function test_complemento_desempata(): void
    {
        $this->responder([
            ['complemento' => 'BL-3', 'geometria' => $this->quadrado(-38.5, -12.98)],
            ['complemento' => 'BL-25', 'geometria' => $this->quadrado(-38.6, -12.98)],
            ['complemento' => 'BL-20', 'geometria' => $this->quadrado(-38.7, -12.98)],
        ]);

        $hit = app(GeoServerEnderecamentoClient::class)->consultar('24574', '70', ' bl-3 ');

        $this->assertSame(EnderecamentoHit::ENCONTRADO, $hit->status);
        $this->assertSame($this->quadrado(-38.5, -12.98), $hit->geometria);
    }

    public function test_geometrias_identicas_contam_como_uma(): void
    {
        $this->responder([
            ['complemento' => null, 'geometria' => $this->quadrado(-38.5, -12.98)],
            ['complemento' => null, 'geometria' => $this->quadrado(-38.5, -12.98)],
        ]);

        $hit = app(GeoServerEnderecamentoClient::class)->consultar('4923', '7416');

        $this->assertSame(EnderecamentoHit::ENCONTRADO, $hit->status);
    }

    public function test_geometrias_quase_iguais_contam_como_uma_e_vale_a_de_origem_sedur(): void
    {
        $this->responder([
            ['complemento' => null, 'origem' => 'semut', 'geometria' => $this->quadrado(-38.5, -12.98)],
            ['complemento' => '0', 'origem' => 'sedur', 'geometria' => $this->quadradoRedesenhado(-38.5, -12.98)],
            ['complemento' => null, 'origem' => 'fmlf', 'geometria' => $this->quadrado(-38.5, -12.98)],
        ]);

        $hit = app(GeoServerEnderecamentoClient::class)->consultar('1545', '2294');

        $this->assertSame(EnderecamentoHit::ENCONTRADO, $hit->status);
        $this->assertSame(1, $hit->candidatas);
        $this->assertSame($this->quadradoRedesenhado(-38.5, -12.98), $hit->geometria);
        $this->assertSame('sedur', $hit->propriedades['ORIGEM']);
    }

    public function test_geometrias_quase_iguais_sem_origem_sedur_ficam_com_a_primeira(): void
    {
        $this->responder([
            ['complemento' => null, 'origem' => 'semut', 'geometria' => $this->quadrado(-38.5, -12.98)],
            ['complemento' => null, 'origem' => 'fmlf', 'geometria' => $this->quadradoRedesenhado(-38.5, -12.98)],
        ]);

        $hit = app(GeoServerEnderecamentoClient::class)->consultar('1545', '2294');

        $this->assertSame(EnderecamentoHit::ENCONTRADO, $hit->status);
        $this->assertSame($this->quadrado(-38.5, -12.98), $hit->geometria);
    }

    public function test_mesmo_contorno_com_area_diferente_continua_ambiguo(): void
    {
        $this->responder([
            ['complemento' => null, 'origem' => 'semut', 'geometria' => $this->quadrado(-38.5, -12.98)],
            ['complemento' => null, 'origem' => 'sedur', 'geometria' => $this->quadradoRecortado(-38.5, -12.98)],
        ]);

        $hit = app(GeoServerEnderecamentoClient::class)->consultar('1545', '2294');

        $this->assertSame(EnderecamentoHit::AMBIGUO, $hit->status);
        $this->assertSame(2, $hit->candidatas);
    }

    public function test_consulta_por_ponto_com_geometrias_quase_iguais_conta_como_uma(): void
    {
        $this->responder([
            ['complemento' => null, 'origem' => 'semut', 'geometria' => $this->quadrado(-38.5, -12.98)],
            ['complemento' => '0', 'origem' => 'sedur', 'geometria' => $this->quadradoRedesenhado(-38.5, -12.98)],
        ]);

        $hit = app(GeoServerEnderecamentoClient::class)->consultarPorPonto(-12.98, -38.5);

        $this->assertSame(EnderecamentoHit::ENCONTRADO, $hit->status);
        $this->assertSame('sedur', $hit->propriedades['ORIGEM']);
    }

    public function test_sem_feicao_e_nao_encontrado(): void
    {
        $this->responder([]);

        $this->assertSame(EnderecamentoHit::NAO_ENCONTRADO, app(GeoServerEnderecamentoClient::class)->consultar('1', '2')->status);
    }

    public function test_falha_http_e_indisponivel(): void
    {
        Http::fake(['*' => Http::response('URL Bloqueada', 500)]);

        $hit = app(GeoServerEnderecamentoClient::class)->consultar('24574', '70');

        $this->assertSame(EnderecamentoHit::INDISPONIVEL, $hit->status);
        $this->assertStringContainsString('500', (string) $hit->motivo);
    }

    public function test_desligado_nao_consulta(): void
    {
        $this->ligar(false);
        Http::fake();

        $hit = app(GeoServerEnderecamentoClient::class)->consultar('24574', '70');

        $this->assertSame(EnderecamentoHit::DESLIGADO, $hit->status);
        Http::assertNothingSent();
    }

    public function test_numero_nao_numerico_nao_consulta(): void
    {
        Http::fake();

        $hit = app(GeoServerEnderecamentoClient::class)->consultar('24574', 'S/N');

        $this->assertSame(EnderecamentoHit::NAO_ENCONTRADO, $hit->status);
        Http::assertNothingSent();
    }

    public function test_filtro_cql_usa_codlog_e_numero_e_a_consulta_e_memoizada(): void
    {
        $this->responder([['complemento' => null, 'geometria' => $this->quadrado(-38.5, -12.98)]]);
        $cliente = app(GeoServerEnderecamentoClient::class);

        $cliente->consultar('024574', '70');
        $cliente->consultar('024574', '70');

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $query['typeName'] === 'enderecamento:VM_ENDERECAMENTO'
                && $query['CQL_FILTER'] === 'CODLOG=24574 AND NUMERACAO_METRICA=70'
                && $query['srsName'] === 'EPSG:4326';
        });
    }

    public function test_consulta_por_ponto_devolve_o_poligono_unico_e_envia_o_cql_certo(): void
    {
        $this->responder([['complemento' => null, 'geometria' => $this->quadrado(-38.5, -12.98)]]);

        $hit = app(GeoServerEnderecamentoClient::class)->consultarPorPonto(-12.98, -38.5);

        $this->assertSame(EnderecamentoHit::ENCONTRADO, $hit->status);
        $this->assertSame($this->quadrado(-38.5, -12.98), $hit->geometria);

        Http::assertSent(function (Request $request): bool {
            $url = urldecode($request->url());

            return str_contains($url, 'typeName=enderecamento:VM_ENDERECAMENTO')
                && str_contains($url, 'CQL_FILTER=INTERSECTS(GEOMETRY,SRID=4326;POINT(-38.50000000 -12.98000000))');
        });
    }

    public function test_consulta_por_ponto_com_dois_poligonos_distintos_e_ambigua(): void
    {
        $this->responder([
            ['complemento' => null, 'geometria' => $this->quadrado(-38.5, -12.98)],
            ['complemento' => 'SALA 2', 'geometria' => $this->quadrado(-38.6, -12.9)],
        ]);

        $hit = app(GeoServerEnderecamentoClient::class)->consultarPorPonto(-12.98, -38.5);

        $this->assertSame(EnderecamentoHit::AMBIGUO, $hit->status);
        $this->assertSame(2, $hit->candidatas);
        $this->assertNull($hit->geometria);
        $this->assertStringContainsString('no ponto informado', (string) $hit->motivo);
    }

    public function test_consulta_por_ponto_com_subunidades_de_geometria_identica_conta_como_uma(): void
    {
        $this->responder([
            ['complemento' => null, 'geometria' => $this->quadrado(-38.5, -12.98)],
            ['complemento' => 'SALA 2', 'geometria' => $this->quadrado(-38.5, -12.98)],
        ]);

        $hit = app(GeoServerEnderecamentoClient::class)->consultarPorPonto(-12.98, -38.5);

        $this->assertSame(EnderecamentoHit::ENCONTRADO, $hit->status);
        $this->assertSame($this->quadrado(-38.5, -12.98), $hit->geometria);
    }

    public function test_consulta_por_ponto_aceita_multipolygon_unico(): void
    {
        $multi = ['type' => 'MultiPolygon', 'coordinates' => [$this->quadrado(-38.5, -12.98)['coordinates']]];
        $this->responder([['complemento' => null, 'geometria' => $multi]]);

        $hit = app(GeoServerEnderecamentoClient::class)->consultarPorPonto(-12.98, -38.5);

        $this->assertSame(EnderecamentoHit::ENCONTRADO, $hit->status);
        $this->assertSame('MultiPolygon', $hit->geometria['type']);
    }

    public function test_consulta_por_ponto_sem_feicao_nao_encontra(): void
    {
        $this->responder([]);

        $hit = app(GeoServerEnderecamentoClient::class)->consultarPorPonto(-12.98, -38.5);

        $this->assertSame(EnderecamentoHit::NAO_ENCONTRADO, $hit->status);
        $this->assertNull($hit->geometria);
    }

    public function test_consulta_por_ponto_com_http_500_e_indisponivel(): void
    {
        Http::fake(['*' => Http::response('erro', 500)]);

        $hit = app(GeoServerEnderecamentoClient::class)->consultarPorPonto(-12.98, -38.5);

        $this->assertSame(EnderecamentoHit::INDISPONIVEL, $hit->status);
        $this->assertStringContainsString('500', (string) $hit->motivo);
    }

    public function test_consulta_por_ponto_com_falha_de_conexao_e_indisponivel(): void
    {
        Http::fake(function (): never {
            throw new ConnectionException('timeout');
        });

        $hit = app(GeoServerEnderecamentoClient::class)->consultarPorPonto(-12.98, -38.5);

        $this->assertSame(EnderecamentoHit::INDISPONIVEL, $hit->status);
    }

    public function test_consulta_por_ponto_com_json_fora_do_contrato_e_indisponivel(): void
    {
        Http::fake(['*' => Http::response(['erro' => 'x'])]);

        $hit = app(GeoServerEnderecamentoClient::class)->consultarPorPonto(-12.98, -38.5);

        $this->assertSame(EnderecamentoHit::INDISPONIVEL, $hit->status);
    }

    public function test_consulta_por_ponto_desligada_nao_chama_o_geoserver(): void
    {
        $this->ligar(false);
        Http::fake();

        $hit = app(GeoServerEnderecamentoClient::class)->consultarPorPonto(-12.98, -38.5);

        $this->assertSame(EnderecamentoHit::DESLIGADO, $hit->status);
        Http::assertNothingSent();
    }

    public function test_timeout_da_consulta_por_ponto_e_de_45_segundos(): void
    {
        $this->assertSame(45, config('viabiliza.integrations.geoserver.enderecamento.timeout_ponto'));
    }
}

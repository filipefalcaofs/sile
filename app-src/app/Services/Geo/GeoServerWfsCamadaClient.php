<?php

namespace App\Services\Geo;

use App\Support\Settings;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Consulta ponto-em-polígono numa camada WFS arbitrária do GeoServer SEDUR
 * (regras territoriais RT-02/03/04 — ZEM, ZPAM, ABM, ZUE, loteamentos TAC).
 * Mesmo padrão do GeoServerWfsZonaClient (WFS 1.1, CQL INTERSECTS, GeoJSON),
 * generalizado para qualquer FeatureType, com filtro CQL adicional opcional
 * (ex.: trecho da ABM) e toggle próprio do fluxo territorial
 * (`features.geoserver_territorio`): desligado, o hit é `indisponivel` e a
 * regra degrada para análise — nunca falha silenciosa.
 */
class GeoServerWfsCamadaClient
{
    /**
     * Resultado por (camada, ponto, filtro) na mesma execução: o inventário de
     * camadas e a avaliação das regras territoriais não repetem a chamada.
     *
     * @var array<string, GeoServerCamadaHit>
     */
    private array $memo = [];

    public function consultar(string $typeName, float $lat, float $lng, ?string $filtroExtra = null): GeoServerCamadaHit
    {
        $chave = implode('|', [$typeName, sprintf('%.7f', $lat), sprintf('%.7f', $lng), (string) $filtroExtra]);

        return $this->memo[$chave] ??= $this->consultarNoGeoServer($typeName, $lat, $lng, $filtroExtra);
    }

    private function consultarNoGeoServer(string $typeName, float $lat, float $lng, ?string $filtroExtra): GeoServerCamadaHit
    {
        if (! Settings::enabled('geoserver_territorio')) {
            return new GeoServerCamadaHit(
                status: GeoServerCamadaHit::INDISPONIVEL,
                properties: null,
                typeName: $typeName,
                motivo: 'Consulta territorial ao GeoServer SEDUR desligada.',
            );
        }

        try {
            /** @var Response $response */
            $response = Http::withHeaders([
                'User-Agent' => (string) config(
                    'sile.integrations.geoserver.user_agent',
                    'Viabiliza-SEDUR-Salvador/1.0',
                ),
            ])
                ->timeout((int) config('sile.integrations.geoserver.timeout', 8))
                ->connectTimeout(3)
                ->acceptJson()
                ->get($this->url($typeName, $lat, $lng, $filtroExtra));
        } catch (Throwable $e) {
            return new GeoServerCamadaHit(
                status: GeoServerCamadaHit::INDISPONIVEL,
                properties: null,
                typeName: $typeName,
                motivo: 'GeoServer SEDUR indisponível: '.$e->getMessage(),
            );
        }

        if (! $response->successful()) {
            return new GeoServerCamadaHit(
                status: GeoServerCamadaHit::INDISPONIVEL,
                properties: null,
                typeName: $typeName,
                motivo: 'GeoServer SEDUR respondeu HTTP '.$response->status().' para '.$typeName.'.',
            );
        }

        $payload = $response->json();

        if (! is_array($payload) || ($payload['type'] ?? null) !== 'FeatureCollection') {
            return new GeoServerCamadaHit(
                status: GeoServerCamadaHit::INDISPONIVEL,
                properties: null,
                typeName: $typeName,
                motivo: 'Resposta fora do contrato WFS (FeatureCollection esperada) para '.$typeName.'.',
            );
        }

        $features = $payload['features'] ?? [];

        if (! is_array($features) || $features === []) {
            return new GeoServerCamadaHit(
                status: GeoServerCamadaHit::FORA,
                properties: null,
                typeName: $typeName,
                motivo: null,
            );
        }

        $properties = $features[0]['properties'] ?? null;

        return new GeoServerCamadaHit(
            status: GeoServerCamadaHit::DENTRO,
            properties: is_array($properties) ? $properties : [],
            typeName: $typeName,
            motivo: null,
        );
    }

    private function url(string $typeName, float $lat, float $lng, ?string $filtroExtra): string
    {
        $base = rtrim((string) Settings::get(
            'integrations.geoserver.base_url',
            config('sile.integrations.geoserver.base_url'),
        ), '/');

        $filtro = sprintf('INTERSECTS(GEOMETRY,SRID=4326;POINT(%.7f %.7f))', $lng, $lat);

        if (is_string($filtroExtra) && trim($filtroExtra) !== '') {
            $filtro = $filtro.' AND ('.trim($filtroExtra).')';
        }

        return $base.'/wfs?'.http_build_query([
            'service' => 'WFS',
            'version' => '1.1.0',
            'request' => 'GetFeature',
            'typeName' => $typeName,
            'outputFormat' => 'application/json',
            'srsName' => 'EPSG:4326',
            'maxFeatures' => 1,
            'CQL_FILTER' => $filtro,
        ]);
    }
}

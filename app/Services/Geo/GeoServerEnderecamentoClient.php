<?php

namespace App\Services\Geo;

use App\Support\Settings;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Polígono do endereço licenciado na camada enderecamento:VM_ENDERECAMENTO do
 * GeoServer SEDUR, chaveado por CODLOG + NUMERACAO_METRICA. A mesma chave pode
 * ter várias feições: o complemento desempata e geometrias idênticas
 * (subunidades) contam como uma. Sobrando mais de uma, o resultado é ambíguo e
 * nenhuma é escolhida.
 */
class GeoServerEnderecamentoClient
{
    /** @var array<string, EnderecamentoHit> */
    private array $memo = [];

    public function consultar(string $codLog, string $numero, ?string $complemento = null): EnderecamentoHit
    {
        $codLog = ltrim(trim($codLog), '0');
        $numero = trim($numero);
        $complemento = mb_strtoupper(trim((string) $complemento));
        $chave = implode('|', [$codLog, $numero, $complemento]);

        return $this->memo[$chave] ??= $this->consultarNoGeoServer($codLog, $numero, $complemento);
    }

    private function consultarNoGeoServer(string $codLog, string $numero, string $complemento): EnderecamentoHit
    {
        if (! Settings::enabled('geoserver_enderecamento')) {
            return new EnderecamentoHit(EnderecamentoHit::DESLIGADO, motivo: 'Consulta do endereçamento no GeoServer SEDUR desligada.');
        }

        if (! ctype_digit($codLog) || ! ctype_digit($numero)) {
            return new EnderecamentoHit(EnderecamentoHit::NAO_ENCONTRADO, motivo: 'CODLOG ou número métrico não numérico.');
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => (string) config('sile.integrations.geoserver.user_agent', 'Viabiliza-SEDUR-Salvador/1.0'),
            ])
                ->timeout((int) config('sile.integrations.geoserver.timeout', 8))
                ->connectTimeout(3)
                ->acceptJson()
                ->get($this->url($codLog, $numero));
        } catch (Throwable $e) {
            return new EnderecamentoHit(EnderecamentoHit::INDISPONIVEL, motivo: 'GeoServer SEDUR indisponível: '.$e->getMessage());
        }

        if (! $response->successful()) {
            return new EnderecamentoHit(EnderecamentoHit::INDISPONIVEL, motivo: 'GeoServer SEDUR respondeu HTTP '.$response->status().' no endereçamento.');
        }

        $payload = $response->json();

        if (! is_array($payload) || ($payload['type'] ?? null) !== 'FeatureCollection') {
            return new EnderecamentoHit(EnderecamentoHit::INDISPONIVEL, motivo: 'Resposta fora do contrato WFS no endereçamento.');
        }

        return $this->escolher($this->poligonais($payload['features'] ?? []), $complemento);
    }

    /**
     * @return list<array{geometry: array<string, mixed>, properties: array<string, mixed>}>
     */
    private function poligonais(mixed $features): array
    {
        if (! is_array($features)) {
            return [];
        }

        $poligonais = [];

        foreach ($features as $feature) {
            $geometria = $feature['geometry'] ?? null;

            if (is_array($geometria) && in_array($geometria['type'] ?? null, ['Polygon', 'MultiPolygon'], true)) {
                $poligonais[] = [
                    'geometry' => $geometria,
                    'properties' => is_array($feature['properties'] ?? null) ? $feature['properties'] : [],
                ];
            }
        }

        return $poligonais;
    }

    /**
     * @param  list<array{geometry: array<string, mixed>, properties: array<string, mixed>}>  $feicoes
     */
    private function escolher(array $feicoes, string $complemento): EnderecamentoHit
    {
        if ($complemento !== '') {
            $casam = array_values(array_filter(
                $feicoes,
                fn (array $f): bool => mb_strtoupper(trim((string) ($f['properties']['COMPLEMENTO'] ?? ''))) === $complemento,
            ));

            if ($casam !== []) {
                $feicoes = $casam;
            }
        }

        $distintas = [];

        foreach ($feicoes as $feicao) {
            $distintas[(string) json_encode($feicao['geometry'])] ??= $feicao;
        }

        if ($distintas === []) {
            return new EnderecamentoHit(EnderecamentoHit::NAO_ENCONTRADO);
        }

        if (count($distintas) > 1) {
            return new EnderecamentoHit(
                EnderecamentoHit::AMBIGUO,
                candidatas: count($distintas),
                motivo: count($distintas).' polígonos distintos para o mesmo CODLOG e número, sem complemento que desempate.',
            );
        }

        $unica = array_values($distintas)[0];

        return new EnderecamentoHit(EnderecamentoHit::ENCONTRADO, geometria: $unica['geometry'], propriedades: $unica['properties'], candidatas: 1);
    }

    private function url(string $codLog, string $numero): string
    {
        $base = rtrim((string) Settings::get('integrations.geoserver.base_url', config('sile.integrations.geoserver.base_url')), '/');

        return $base.'/wfs?'.http_build_query([
            'service' => 'WFS',
            'version' => '1.1.0',
            'request' => 'GetFeature',
            'typeName' => (string) config('sile.integrations.geoserver.enderecamento.type_name', 'enderecamento:VM_ENDERECAMENTO'),
            'outputFormat' => 'application/json',
            'srsName' => 'EPSG:4326',
            'maxFeatures' => 50,
            'CQL_FILTER' => sprintf('CODLOG=%s AND NUMERACAO_METRICA=%s', $codLog, $numero),
        ]);
    }
}

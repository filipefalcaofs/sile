<?php

namespace App\Services\Geo;

use App\Support\Settings;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Eixo do logradouro por CODLOG, na camada de logradouros da SEDUR (linhas) e,
 * se a rua não estiver lá, na camada viária da LOUOS, que só serve quando traz
 * linhas (hoje é de polígonos, a pista da via). Traz todos os segmentos para
 * medir a distância do ponto da inscrição até a rua.
 *
 * Sem fachada: toggle desligado, GeoServer fora, resposta fora do contrato ou
 * CODLOG sem linha nunca viram eixo inventado — quem consome degrada.
 */
class GeoServerEixoViarioClient
{
    private const MAX_SEGMENTOS = 500;

    private const CACHE_SEGUNDOS = 86_400;

    /** @var array<string, EixoViarioHit> */
    private array $memo = [];

    public function consultar(string $codLog): EixoViarioHit
    {
        $codLog = trim($codLog);

        if ($codLog === '' || ! ctype_digit($codLog)) {
            return new EixoViarioHit(EixoViarioHit::INDISPONIVEL, motivo: 'CODLOG ausente ou inválido para consultar o eixo da via.');
        }

        return $this->memo[$codLog] ??= $this->consultarComCache($codLog);
    }

    private function consultarComCache(string $codLog): EixoViarioHit
    {
        if (! Settings::enabled('geoserver_via_classe')) {
            return new EixoViarioHit(EixoViarioHit::DESLIGADO, motivo: 'Consulta da camada viária no GeoServer SEDUR desligada.');
        }

        $chave = 'viabiliza.eixo_viario.'.$codLog;
        $linhas = cache()->get($chave);

        if (is_array($linhas)) {
            return new EixoViarioHit(EixoViarioHit::IDENTIFICADO, $linhas);
        }

        $hit = $this->consultarNoGeoServer($codLog, $this->typeName('logradouro', 'logradouros:VM_LOGRADOURO'));

        if ($hit->status === EixoViarioHit::NAO_ENCONTRADO) {
            $naViaria = $this->consultarNoGeoServer($codLog, $this->typeName('classificacao_viaria', 'louos_classificacao_viaria:VM_L_CLASSIF_VIARIA'));
            $hit = $naViaria->identificado() ? $naViaria : $hit;
        }

        if ($hit->identificado()) {
            cache()->put($chave, $hit->linhas, self::CACHE_SEGUNDOS);
        }

        return $hit;
    }

    private function consultarNoGeoServer(string $codLog, string $typeName): EixoViarioHit
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => (string) config('viabiliza.integrations.geoserver.user_agent', 'Viabiliza-SEDUR-Salvador/1.0'),
            ])
                ->timeout((int) config('viabiliza.integrations.geoserver.timeout', 8))
                ->connectTimeout(3)
                ->acceptJson()
                ->get($this->url($codLog, $typeName));
        } catch (Throwable $e) {
            return new EixoViarioHit(EixoViarioHit::INDISPONIVEL, motivo: 'GeoServer SEDUR indisponível: '.$e->getMessage());
        }

        if (! $response->successful()) {
            return new EixoViarioHit(EixoViarioHit::INDISPONIVEL, motivo: 'GeoServer SEDUR respondeu HTTP '.$response->status().' no eixo da via.');
        }

        $payload = $response->json();

        if (! is_array($payload) || ($payload['type'] ?? null) !== 'FeatureCollection') {
            return new EixoViarioHit(EixoViarioHit::INDISPONIVEL, motivo: 'Resposta fora do contrato WFS no eixo da via.');
        }

        $linhas = [];

        foreach ((array) ($payload['features'] ?? []) as $feature) {
            $geometria = is_array($feature) ? ($feature['geometry'] ?? null) : null;

            foreach ($this->linhasDe($geometria) as $linha) {
                $linhas[] = $linha;
            }
        }

        if ($linhas === []) {
            return new EixoViarioHit(EixoViarioHit::NAO_ENCONTRADO, motivo: 'CODLOG sem linha na camada viária.');
        }

        return new EixoViarioHit(EixoViarioHit::IDENTIFICADO, $linhas);
    }

    /**
     * @return list<list<array{0: float, 1: float}>>
     */
    private function linhasDe(mixed $geometria): array
    {
        if (! is_array($geometria) || ! is_array($geometria['coordinates'] ?? null)) {
            return [];
        }

        $brutas = match ($geometria['type'] ?? null) {
            'LineString' => [$geometria['coordinates']],
            'MultiLineString' => $geometria['coordinates'],
            default => [],
        };

        $linhas = [];

        foreach ($brutas as $bruta) {
            $linha = [];

            foreach ((array) $bruta as $vertice) {
                if (is_array($vertice) && isset($vertice[0], $vertice[1])) {
                    $linha[] = [(float) $vertice[0], (float) $vertice[1]];
                }
            }

            if (count($linha) >= 2) {
                $linhas[] = $linha;
            }
        }

        return $linhas;
    }

    private function typeName(string $camada, string $padrao): string
    {
        return (string) config("viabiliza.integrations.geoserver.{$camada}.type_name", $padrao);
    }

    private function url(string $codLog, string $typeName): string
    {
        $base = rtrim((string) Settings::get(
            'integrations.geoserver.base_url',
            config('viabiliza.integrations.geoserver.base_url'),
        ), '/');

        return $base.'/wfs?'.http_build_query([
            'service' => 'WFS',
            'version' => '1.1.0',
            'request' => 'GetFeature',
            'typeName' => $typeName,
            'outputFormat' => 'application/json',
            'srsName' => 'EPSG:4326',
            'maxFeatures' => self::MAX_SEGMENTOS,
            'CQL_FILTER' => sprintf('CODLOG=%s', $codLog),
        ]);
    }
}

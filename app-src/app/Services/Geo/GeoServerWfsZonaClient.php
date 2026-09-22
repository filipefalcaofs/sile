<?php

namespace App\Services\Geo;

use App\Models\GeoServerLayer;
use App\Support\Louos\Quadro10Zona;
use App\Support\Settings;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Identifica zona urbanística LOUOS no GeoServer SEDUR (WFS 1.1, INTERSECTS).
 * Sem fachada: falha HTTP é `indisponivel`; FeatureCollection vazia em todas
 * as camadas consultadas com sucesso é `nao_encontrado`.
 */
class GeoServerWfsZonaClient
{
    public function identificar(float $lat, float $lng): GeoServerZonaHit
    {
        if (! Settings::enabled('geoserver_zona')) {
            return new GeoServerZonaHit(
                status: 'indisponivel',
                codigo: null,
                properties: null,
                typeName: null,
                motivo: 'Consulta ao GeoServer SEDUR desligada.',
            );
        }

        $typeNames = $this->typeNames();

        if ($typeNames === []) {
            return new GeoServerZonaHit(
                status: 'indisponivel',
                codigo: null,
                properties: null,
                typeName: null,
                motivo: 'Nenhuma FeatureType de zona configurada no GeoServer.',
            );
        }

        try {
            $responses = Http::pool(function (Pool $pool) use ($typeNames, $lat, $lng): void {
                foreach ($typeNames as $typeName) {
                    $this->prepare($pool->as($typeName))->get($this->url($typeName, $lat, $lng));
                }
            });
        } catch (Throwable $e) {
            return new GeoServerZonaHit(
                status: 'indisponivel',
                codigo: null,
                properties: null,
                typeName: null,
                motivo: 'GeoServer SEDUR indisponível: '.$e->getMessage(),
            );
        }

        $falhas = 0;

        foreach ($typeNames as $typeName) {
            $response = $responses[$typeName] ?? null;

            if (! $response instanceof Response || ! $response->successful()) {
                $falhas++;

                continue;
            }

            $hit = $this->extrair($typeName, $response->json());

            if ($hit !== null) {
                return $hit;
            }
        }

        if ($falhas > 0) {
            return new GeoServerZonaHit(
                status: 'indisponivel',
                codigo: null,
                properties: null,
                typeName: null,
                motivo: 'GeoServer SEDUR indisponível ou resposta incompleta (HTTP '.$falhas.').',
            );
        }

        return new GeoServerZonaHit(
            status: 'nao_encontrado',
            codigo: null,
            properties: null,
            typeName: null,
            motivo: null,
        );
    }

    /**
     * Catálogo DB-first (geoserver_layers, dado administrável): banco
     * alcançável manda — mesmo VAZIO, que é resposta honesta ("nenhuma camada
     * cadastrada"). O config só é fallback com o banco INALCANÇÁVEL
     * (QueryException — build Docker, CI sem migrate), padrão Settings.
     *
     * @return list<string>
     */
    private function typeNames(): array
    {
        try {
            return GeoServerLayer::query()->ativos()->get()
                ->map(fn (GeoServerLayer $camada): string => $camada->nomeCompleto())
                ->all();
        } catch (QueryException) {
            $names = config('sile.integrations.geoserver.type_names', []);

            return array_values(array_filter(
                is_array($names) ? $names : [],
                fn (mixed $name): bool => is_string($name) && $name !== '',
            ));
        }
    }

    private function prepare(mixed $pending): mixed
    {
        return $pending
            ->withHeaders([
                'User-Agent' => (string) config(
                    'sile.integrations.geoserver.user_agent',
                    'Viabiliza-SEDUR-Salvador/1.0',
                ),
            ])
            ->timeout((int) config('sile.integrations.geoserver.timeout', 8))
            ->connectTimeout(3)
            ->acceptJson();
    }

    private function url(string $typeName, float $lat, float $lng): string
    {
        $base = rtrim((string) Settings::get(
            'integrations.geoserver.base_url',
            config('sile.integrations.geoserver.base_url'),
        ), '/');

        $filtro = sprintf('INTERSECTS(GEOMETRY,SRID=4326;POINT(%.7f %.7f))', $lng, $lat);

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

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function extrair(string $typeName, mixed $payload): ?GeoServerZonaHit
    {
        if (! is_array($payload) || ($payload['type'] ?? null) !== 'FeatureCollection') {
            return null;
        }

        $features = $payload['features'] ?? [];

        if (! is_array($features) || $features === []) {
            return null;
        }

        $properties = is_array($features[0]['properties'] ?? null)
            ? $features[0]['properties']
            : [];

        $codigo = $this->codigoZona($properties, $typeName);

        if ($codigo === null) {
            return null;
        }

        return new GeoServerZonaHit(
            status: 'identificado',
            codigo: $codigo,
            properties: $properties,
            typeName: $typeName,
            motivo: null,
        );
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function codigoZona(array $properties, string $typeName): ?string
    {
        foreach (['SUBZONA', 'ZONA', 'SIGLA_ZONA', 'IDENTIFICACAO', 'TIPO_ZEIS', 'NOME_ZEIS'] as $chave) {
            $valor = $properties[$chave] ?? null;

            if (is_string($valor) && trim($valor) !== '') {
                return $this->normalizarCodigo($valor);
            }
        }

        if (preg_match('/ZPR_(\d+)$/', $typeName, $match) === 1) {
            return Quadro10Zona::oficializar('ZPR '.$match[1]);
        }

        if (preg_match('/ZDE_(\d+)$/', $typeName, $match) === 1) {
            return Quadro10Zona::oficializar('ZDE '.$match[1]);
        }

        if (str_contains($typeName, 'ZPAM')) {
            return 'ZPAM';
        }

        if (str_contains($typeName, 'ZEIS')) {
            return 'ZEIS';
        }

        return null;
    }

    public static function normalizarCodigo(string $raw): string
    {
        return Quadro10Zona::oficializar($raw) ?? trim($raw);
    }
}

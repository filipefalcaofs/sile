<?php

namespace App\Services\Geo;

use App\Support\Settings;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Polígono do endereço licenciado na camada enderecamento:VM_ENDERECAMENTO do
 * GeoServer SEDUR. Dois modos de busca:
 *
 * - por CODLOG + NUMERACAO_METRICA (rápido, ~0,2 s): a mesma chave pode ter
 *   várias feições, o complemento desempata e geometrias idênticas
 *   (subunidades) contam como uma;
 * - pelo ponto do imóvel (INTERSECTS, ~30 s medidos): só um polígono distinto
 *   no ponto vale. Sobrando mais de um, o resultado é ambíguo.
 *
 * Em ambos, geometrias quase iguais (o mesmo lote desenhado por origens
 * diferentes) contam como uma, com preferência pela de origem SEDUR. Sobrando
 * mais de um lote distinto, nenhum é escolhido.
 */
class GeoServerEnderecamentoClient
{
    /** Diferença máxima, em graus (~1 m), entre os retângulos envolventes. */
    private const TOLERANCIA_ENVOLVENTE = 0.00001;

    /** Diferença relativa de área abaixo da qual duas geometrias são o mesmo lote. */
    private const TOLERANCIA_AREA = 0.01;

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

    /**
     * Lote que contém o ponto. A consulta espacial é lenta no servidor
     * (~30 s), por isso tem timeout próprio e só deve rodar em fila.
     */
    public function consultarPorPonto(float $lat, float $lng): EnderecamentoHit
    {
        $chave = sprintf('ponto|%.8F|%.8F', $lat, $lng);

        return $this->memo[$chave] ??= $this->consultarPontoNoGeoServer($lat, $lng);
    }

    private function consultarNoGeoServer(string $codLog, string $numero, string $complemento): EnderecamentoHit
    {
        if (! Settings::enabled('geoserver_enderecamento')) {
            return new EnderecamentoHit(EnderecamentoHit::DESLIGADO, motivo: 'Consulta do endereçamento no GeoServer SEDUR desligada.');
        }

        if (! ctype_digit($codLog) || ! ctype_digit($numero)) {
            return new EnderecamentoHit(EnderecamentoHit::NAO_ENCONTRADO, motivo: 'CODLOG ou número métrico não numérico.');
        }

        return $this->buscar(
            $this->url($codLog, $numero),
            (int) config('sile.integrations.geoserver.timeout', 8),
            $complemento,
        );
    }

    private function consultarPontoNoGeoServer(float $lat, float $lng): EnderecamentoHit
    {
        if (! Settings::enabled('geoserver_enderecamento')) {
            return new EnderecamentoHit(EnderecamentoHit::DESLIGADO, motivo: 'Consulta do endereçamento no GeoServer SEDUR desligada.');
        }

        return $this->buscar(
            $this->urlPorPonto($lat, $lng),
            (int) config('sile.integrations.geoserver.enderecamento.timeout_ponto', 45),
            '',
            porPonto: true,
        );
    }

    private function buscar(string $url, int $timeout, string $complemento, bool $porPonto = false): EnderecamentoHit
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => (string) config('sile.integrations.geoserver.user_agent', 'Viabiliza-SEDUR-Salvador/1.0'),
            ])
                ->timeout($timeout)
                ->connectTimeout(3)
                ->acceptJson()
                ->get($url);
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

        return $this->escolher($this->poligonais($payload['features'] ?? []), $complemento, $porPonto);
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
    private function escolher(array $feicoes, string $complemento, bool $porPonto = false): EnderecamentoHit
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

        foreach ($this->agrupar($feicoes) as $grupo) {
            $distintas[] = $this->preferida($grupo);
        }

        if ($distintas === []) {
            return new EnderecamentoHit(EnderecamentoHit::NAO_ENCONTRADO);
        }

        if (count($distintas) > 1) {
            return new EnderecamentoHit(
                EnderecamentoHit::AMBIGUO,
                candidatas: count($distintas),
                motivo: count($distintas).($porPonto
                    ? ' polígonos distintos no ponto informado.'
                    : ' polígonos distintos para o mesmo CODLOG e número, sem complemento que desempate.'),
            );
        }

        $unica = $distintas[0];

        return new EnderecamentoHit(EnderecamentoHit::ENCONTRADO, geometria: $unica['geometry'], propriedades: $unica['properties'], candidatas: 1);
    }

    /**
     * Junta as feições que são o mesmo lote: geometria idêntica ou quase igual
     * (mesmo retângulo envolvente e área com diferença abaixo de 1%), como o
     * lote redesenhado por outra origem com vértices a mais ou a menos.
     *
     * @param  list<array{geometry: array<string, mixed>, properties: array<string, mixed>}>  $feicoes
     * @return list<list<array{geometry: array<string, mixed>, properties: array<string, mixed>}>>
     */
    private function agrupar(array $feicoes): array
    {
        $grupos = [];

        foreach ($feicoes as $feicao) {
            foreach ($grupos as $indice => $grupo) {
                if ($this->quaseIguais($grupo[0]['geometry'], $feicao['geometry'])) {
                    $grupos[$indice][] = $feicao;

                    continue 2;
                }
            }

            $grupos[] = [$feicao];
        }

        return $grupos;
    }

    /**
     * @param  list<array{geometry: array<string, mixed>, properties: array<string, mixed>}>  $grupo
     * @return array{geometry: array<string, mixed>, properties: array<string, mixed>}
     */
    private function preferida(array $grupo): array
    {
        foreach ($grupo as $feicao) {
            if (mb_strtolower(trim((string) ($feicao['properties']['ORIGEM'] ?? ''))) === 'sedur') {
                return $feicao;
            }
        }

        return $grupo[0];
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private function quaseIguais(array $a, array $b): bool
    {
        if ($a === $b) {
            return true;
        }

        $envolventeA = $this->envolvente($a);
        $envolventeB = $this->envolvente($b);

        if ($envolventeA === null || $envolventeB === null) {
            return false;
        }

        foreach ($envolventeA as $indice => $valor) {
            if (abs($valor - $envolventeB[$indice]) > self::TOLERANCIA_ENVOLVENTE) {
                return false;
            }
        }

        $areaA = $this->area($a);
        $areaB = $this->area($b);
        $maior = max($areaA, $areaB);

        return $maior > 0 && abs($areaA - $areaB) / $maior < self::TOLERANCIA_AREA;
    }

    /**
     * @param  array<string, mixed>  $geometria
     * @return list<list<list<float>>>
     */
    private function poligonosDe(array $geometria): array
    {
        $coordenadas = is_array($geometria['coordinates'] ?? null) ? $geometria['coordinates'] : [];

        return ($geometria['type'] ?? null) === 'MultiPolygon' ? $coordenadas : [$coordenadas];
    }

    /**
     * @param  array<string, mixed>  $geometria
     * @return array{0: float, 1: float, 2: float, 3: float}|null
     */
    private function envolvente(array $geometria): ?array
    {
        $xs = [];
        $ys = [];

        foreach ($this->poligonosDe($geometria) as $aneis) {
            foreach ((array) ($aneis[0] ?? []) as $ponto) {
                $xs[] = (float) ($ponto[0] ?? 0);
                $ys[] = (float) ($ponto[1] ?? 0);
            }
        }

        return $xs === [] ? null : [min($xs), min($ys), max($xs), max($ys)];
    }

    /**
     * Área plana em graus², só para comparar geometrias do mesmo lugar: anel
     * externo menos os buracos, somada por polígono.
     *
     * @param  array<string, mixed>  $geometria
     */
    private function area(array $geometria): float
    {
        $total = 0.0;

        foreach ($this->poligonosDe($geometria) as $aneis) {
            foreach (array_values((array) $aneis) as $indice => $anel) {
                $area = $this->areaDoAnel((array) $anel);
                $total += $indice === 0 ? $area : -$area;
            }
        }

        return $total;
    }

    /**
     * @param  array<int, mixed>  $anel
     */
    private function areaDoAnel(array $anel): float
    {
        $soma = 0.0;
        $pontos = array_values($anel);

        for ($i = 0, $n = count($pontos); $i < $n - 1; $i++) {
            $soma += (float) $pontos[$i][0] * (float) $pontos[$i + 1][1] - (float) $pontos[$i + 1][0] * (float) $pontos[$i][1];
        }

        return abs($soma) / 2;
    }

    private function url(string $codLog, string $numero): string
    {
        return $this->wfsUrl(sprintf('CODLOG=%s AND NUMERACAO_METRICA=%s', $codLog, $numero));
    }

    private function urlPorPonto(float $lat, float $lng): string
    {
        return $this->wfsUrl(sprintf('INTERSECTS(GEOMETRY,SRID=4326;POINT(%.8F %.8F))', $lng, $lat));
    }

    private function wfsUrl(string $cqlFilter): string
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
            'CQL_FILTER' => $cqlFilter,
        ]);
    }
}

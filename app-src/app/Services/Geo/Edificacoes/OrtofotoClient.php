<?php

namespace App\Services\Geo\Edificacoes;

use App\Support\Settings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Recorte da ortofoto da Prefeitura em volta do ponto: ImageServer 2024
 * (exportImage) e, se ele falhar, o WMS 2016/2017. O recorte fica guardado
 * no disco local para não ser pedido de novo.
 */
class OrtofotoClient
{
    public function recortar(float $lat, float $lng, ?float $ladoMetros = null): ?RecorteOrtofoto
    {
        $lado = $ladoMetros ?? (float) config('viabiliza.edificacoes.ia.lado_recorte_m', 60);
        $pixels = (int) config('viabiliza.edificacoes.ia.tamanho_px', 800);
        $meioLat = $lado / 2 / 110574;
        $meioLng = $lado / 2 / (111320 * cos(deg2rad($lat)));
        $caixa = [$lng - $meioLng, $lat - $meioLat, $lng + $meioLng, $lat + $meioLat];

        foreach ($this->servicos() as [$servico, $ano, $url]) {
            $caminho = 'ortofoto/'.sha1(implode('|', [$servico, $ano, round($lat, 6), round($lng, 6), $lado, $pixels])).'.png';

            if (! Storage::disk('local')->exists($caminho)) {
                $imagem = $this->baixar($servico, $url, $caixa, $pixels);

                if ($imagem === null) {
                    continue;
                }

                Storage::disk('local')->put($caminho, $imagem);
            }

            return new RecorteOrtofoto(Storage::disk('local')->path($caminho), $caixa[0], $caixa[1], $caixa[2], $caixa[3], $pixels, $pixels, $servico, $ano);
        }

        return null;
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function servicos(): array
    {
        return [
            ['imageserver', '2024', (string) Settings::get('integrations.edificacoes.ortofoto_2024_url')],
            ['wms', '2016-2017', (string) Settings::get('integrations.edificacoes.ortofoto_2016_url')],
        ];
    }

    /**
     * @param  array{0: float, 1: float, 2: float, 3: float}  $caixa
     */
    private function baixar(string $servico, string $url, array $caixa, int $pixels): ?string
    {
        if ($url === '') {
            return null;
        }

        $bbox = implode(',', $caixa);
        $timeout = (int) config('viabiliza.integrations.edificacoes.timeout_ortofoto', 30);

        try {
            $resposta = $servico === 'imageserver'
                ? $this->cliente($timeout)->get(rtrim($url, '/').'/exportImage', [
                    'bbox' => $bbox, 'bboxSR' => 4326, 'imageSR' => 4326,
                    'size' => "{$pixels},{$pixels}", 'format' => 'png', 'f' => 'image',
                ])
                : $this->cliente($timeout)->get($url, [
                    'SERVICE' => 'WMS', 'VERSION' => '1.1.1', 'REQUEST' => 'GetMap',
                    'LAYERS' => (string) config('viabiliza.integrations.edificacoes.ortofoto_2016_layer'),
                    'STYLES' => '', 'SRS' => 'EPSG:4326', 'BBOX' => $bbox,
                    'WIDTH' => $pixels, 'HEIGHT' => $pixels, 'FORMAT' => 'image/png',
                ]);
        } catch (Throwable) {
            return null;
        }

        $tipo = strtolower((string) $resposta->header('Content-Type'));

        return $resposta->successful() && str_starts_with($tipo, 'image/') && $resposta->body() !== ''
            ? $resposta->body()
            : null;
    }

    private function cliente(int $timeout): PendingRequest
    {
        $cliente = Http::timeout($timeout)->connectTimeout(5);
        $token = (string) Settings::get('integrations.edificacoes.ortofoto_token');

        return $token === '' ? $cliente : $cliente->withHeaders(['X-Esri-Authorization' => "Bearer {$token}"]);
    }
}

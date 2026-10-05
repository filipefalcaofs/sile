<?php

namespace App\Services\Geo;

use App\Support\Settings;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Lote por inscrição imobiliária na camada SEMAP (Inscrições Geo) do ArcGIS
 * municipal. É o FALLBACK do {@see GeoServerEnderecamentoClient}: o
 * endereçamento só tem endereço licenciado, e a inscrição cobre o resto —
 * medido em 2026-10-03, a inscrição 344672 (CODLOG 212, nº 6) não existe no
 * endereçamento e tem lote aqui.
 *
 * Devolve o mesmo {@see EnderecamentoHit} do endereçamento para o chamador não
 * conhecer a diferença entre as fontes.
 *
 * `AREA_M2` NÃO é lido (voltou 0 em feição com área real): a área sai da
 * interseção medida no PostGIS. `CAT_DE_USO` e `PADRAOCONS` viajam nas
 * propriedades para a ficha; não decidem o fluxo. A camada não tem campo pessoal.
 */
class SemapLoteClient
{
    /** @var array<string, EnderecamentoHit> */
    private array $memo = [];

    public function consultar(?string $inscricao): EnderecamentoHit
    {
        $digitos = preg_replace('/\D/', '', (string) $inscricao) ?? '';

        if ($digitos === '') {
            return new EnderecamentoHit(
                EnderecamentoHit::NAO_ENCONTRADO,
                motivo: 'Processo sem inscrição imobiliária.',
            );
        }

        return $this->memo[$digitos] ??= $this->consultarNoArcGis($digitos);
    }

    private function consultarNoArcGis(string $inscricao): EnderecamentoHit
    {
        if (! Settings::enabled('arcgis_semap')) {
            return new EnderecamentoHit(
                EnderecamentoHit::DESLIGADO,
                motivo: 'Consulta do lote por inscrição no cadastro municipal desligada.',
            );
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => (string) config('sile.integrations.arcgis_semap.user_agent', 'Viabiliza-SEDUR-Salvador/1.0'),
            ])
                // Partida a frio medida em ~15 s; em regime, ~0,2 s.
                ->timeout((int) config('sile.integrations.arcgis_semap.timeout', 20))
                ->connectTimeout(5)
                ->acceptJson()
                ->get($this->url(), [
                    'where' => "INSCRICAO='{$inscricao}'",
                    'returnGeometry' => 'true',
                    'outSR' => '4326',
                    'outFields' => 'INSCRICAO,CODLOG,PORTA,CAT_DE_USO,PADRAOCONS',
                    'f' => 'geojson',
                ]);
        } catch (Throwable $e) {
            return new EnderecamentoHit(
                EnderecamentoHit::INDISPONIVEL,
                motivo: 'Cadastro municipal (SEMAP) indisponível: '.$e->getMessage(),
            );
        }

        if (! $response->successful()) {
            return new EnderecamentoHit(
                EnderecamentoHit::INDISPONIVEL,
                motivo: 'Cadastro municipal (SEMAP) respondeu HTTP '.$response->status().'.',
            );
        }

        $payload = $response->json();

        if (! is_array($payload) || ($payload['type'] ?? null) !== 'FeatureCollection') {
            return new EnderecamentoHit(
                EnderecamentoHit::INDISPONIVEL,
                motivo: 'Resposta do SEMAP fora do formato GeoJSON.',
            );
        }

        return $this->extrair(is_array($payload['features'] ?? null) ? $payload['features'] : []);
    }

    /**
     * @param  list<mixed>  $features
     */
    private function extrair(array $features): EnderecamentoHit
    {
        $geometrias = [];
        $propriedades = null;

        foreach ($features as $feature) {
            if (! is_array($feature)) {
                continue;
            }

            $geometria = is_array($feature['geometry'] ?? null) ? $feature['geometry'] : null;

            if ($geometria === null || ! in_array($geometria['type'] ?? null, ['Polygon', 'MultiPolygon'], true)) {
                continue;
            }

            $geometrias[(string) json_encode($geometria)] = $geometria;
            $propriedades = is_array($feature['properties'] ?? null) ? $feature['properties'] : $propriedades;
        }

        if ($geometrias === []) {
            return new EnderecamentoHit(
                EnderecamentoHit::NAO_ENCONTRADO,
                motivo: 'Inscrição sem lote na camada SEMAP.',
            );
        }

        if (count($geometrias) > 1) {
            return new EnderecamentoHit(
                EnderecamentoHit::AMBIGUO,
                candidatas: count($geometrias),
                motivo: 'Mais de uma geometria para a mesma inscrição no SEMAP.',
            );
        }

        return new EnderecamentoHit(
            EnderecamentoHit::ENCONTRADO,
            geometria: reset($geometrias),
            propriedades: $propriedades,
            candidatas: 1,
        );
    }

    private function url(): string
    {
        return (string) Settings::get(
            'integrations.arcgis_semap.query_url',
            config('sile.integrations.arcgis_semap.query_url'),
        );
    }
}

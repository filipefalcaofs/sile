<?php

namespace App\Services\Geo;

use App\Enums\LotePoligonoFonte;
use App\Models\ViabilityRequest;
use App\Support\Settings;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Lote por inscrição imobiliária na camada `SEMAP — Inscrições Geo` do ArcGIS
 * municipal (GeoSalvador). Fallback do endereçamento da SEDUR: cobre endereço
 * que nunca passou por licenciamento, caso do que motivou a correção de
 * localização de setembro.
 *
 * Medições contra o serviço real (2026-10-03): resposta típica em ~0,2 s, com
 * partida a frio de ~15 s — daí o timeout largo e nenhuma tentativa extra. A
 * camada não tem campo pessoal; só a geometria e a identificação do lote são
 * lidas. `AREA_M2` NÃO é usado (voltou 0 em feição com área real): a área sai
 * da interseção medida no PostGIS.
 */
class SemapPoligonoLoteFonte implements PoligonoLoteFonte
{
    /** @var array<string, PoligonoLoteHit> */
    private array $memo = [];

    public function fonte(): LotePoligonoFonte
    {
        return LotePoligonoFonte::Semap;
    }

    public function buscar(ViabilityRequest $request): PoligonoLoteHit
    {
        $inscricao = preg_replace('/\D/', '', (string) $request->property_registration) ?? '';

        if ($inscricao === '') {
            return PoligonoLoteHit::naoEncontrado($this->fonte(), 'Processo sem inscrição imobiliária.');
        }

        return $this->memo[$inscricao] ??= $this->consultar($inscricao);
    }

    private function consultar(string $inscricao): PoligonoLoteHit
    {
        if (! Settings::enabled('arcgis_semap')) {
            return PoligonoLoteHit::indisponivel($this->fonte(), 'Consulta ao cadastro municipal (SEMAP) desligada.');
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => (string) config(
                    'sile.integrations.arcgis_semap.user_agent',
                    'Viabiliza-SEDUR-Salvador/1.0',
                ),
            ])
                ->timeout((int) config('sile.integrations.arcgis_semap.timeout', 20))
                ->connectTimeout(5)
                ->acceptJson()
                ->get($this->url(), [
                    'where' => "INSCRICAO='{$inscricao}'",
                    'returnGeometry' => 'true',
                    'outSR' => '4326',
                    'outFields' => 'INSCRICAO,CODLOG,PORTA',
                    'f' => 'geojson',
                ]);
        } catch (Throwable $exception) {
            return PoligonoLoteHit::indisponivel(
                $this->fonte(),
                'Cadastro municipal (SEMAP) indisponível: '.$exception::class,
            );
        }

        if (! $response->successful()) {
            return PoligonoLoteHit::indisponivel(
                $this->fonte(),
                'Cadastro municipal (SEMAP) respondeu HTTP '.$response->status().'.',
            );
        }

        $payload = $response->json();

        if (! is_array($payload) || ($payload['type'] ?? null) !== 'FeatureCollection') {
            return PoligonoLoteHit::indisponivel($this->fonte(), 'Resposta do SEMAP fora do formato GeoJSON.');
        }

        return $this->extrair(is_array($payload['features'] ?? null) ? $payload['features'] : []);
    }

    /**
     * @param  list<mixed>  $features
     */
    private function extrair(array $features): PoligonoLoteHit
    {
        $geometrias = [];
        $propriedades = [];

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
            return PoligonoLoteHit::naoEncontrado($this->fonte(), 'Inscrição sem lote na camada SEMAP.');
        }

        if (count($geometrias) > 1) {
            return PoligonoLoteHit::ambiguo($this->fonte(), count($geometrias));
        }

        return PoligonoLoteHit::encontrado($this->fonte(), reset($geometrias), $propriedades);
    }

    private function url(): string
    {
        return (string) Settings::get(
            'integrations.arcgis_semap.query_url',
            config('sile.integrations.arcgis_semap.query_url'),
        );
    }
}

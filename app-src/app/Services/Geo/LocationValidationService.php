<?php

namespace App\Services\Geo;

use App\Enums\GeoLayerStatus;
use App\Enums\GeoLayerType;
use App\Models\GeoLayer;
use App\Support\Settings;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Validação de localização por sobreposição com o lote oficial (HU-037 RN-004):
 * compara o polígono informado ao lote da inscrição imobiliária com
 * `ST_Area(ST_Intersection(informado, lote)) / ST_Area(informado)` (SQL real,
 * sem cálculo geométrico em PHP) e alerta quando a sobreposição fica abaixo do
 * limiar administrável `geo.validacao.sobreposicao_minima` (HU-014).
 *
 * A base de lotes é `pendente_fonte` (sem fonte vetorial pública até a SEDUR/
 * SEFAZ entregar a inscrição imobiliária): nesse estado a validação comunica
 * "indisponível — base de lotes pendente SEDUR" SEM fabricar lote e SEM
 * disparar consulta espacial (sem fachada). A lógica é dirigida pelo DADO —
 * quando o lote virar camada vigente com feições, a MESMA validação passa a
 * calcular a sobreposição (a carga muda, a lógica não). RN-005 (divergência por
 * inscrição) é bloqueada pelo mesmo motivo.
 */
class LocationValidationService
{
    /**
     * Valida o polígono informado contra o lote vigente (ou o vigente em
     * `$date`, para reprodução de uma decisão — RN-004).
     *
     * @param  array<string, mixed>  $polygonGeoJson  GeoJSON Polygon (type + coordinates)
     */
    public function validate(array $polygonGeoJson, ?CarbonInterface $date = null): LocationValidationResult
    {
        $limiar = (int) Settings::get(
            'geo.validacao.sobreposicao_minima',
            config('sile.geo.validacao.sobreposicao_minima', 50),
        );

        $layer = $this->resolveLoteLayer($date);

        if ($this->isBlocked($layer)) {
            return new LocationValidationResult(
                status: 'indisponivel',
                sobreposicaoPercentual: null,
                limiar: $limiar,
                motivo: 'Base de lotes (inscrição imobiliária) pendente SEDUR — validação de sobreposição não disponível',
                alerta: false,
            );
        }

        $percent = $this->maxOverlapPercent($polygonGeoJson, $layer);
        $decision = $this->decide($percent, $limiar);

        return new LocationValidationResult(
            status: $decision['status'],
            sobreposicaoPercentual: $percent,
            limiar: $limiar,
            motivo: null,
            alerta: $decision['alerta'],
        );
    }

    /**
     * Decisão pura do limiar (RN-004): abaixo do limiar => alerta; no limiar ou
     * acima => validado. Isolada para teste unitário sem PostGIS — a matemática
     * é provada aqui e o SQL espacial em @group postgis.
     *
     * @return array{status: string, alerta: bool}
     */
    public function decide(float $percent, int $limiar): array
    {
        $alerta = $percent < $limiar;

        return [
            'status' => $alerta ? 'alerta_sobreposicao' : 'validado',
            'alerta' => $alerta,
        ];
    }

    /**
     * Maior sobreposição (%) entre o polígono informado e as feições do lote
     * vigente — `ST_Area(ST_Intersection)/ST_Area` sobre a feição mais sobreposta.
     * Sem interseção => 0,0 (abaixo de qualquer limiar => alerta).
     *
     * @param  array<string, mixed>  $polygonGeoJson
     */
    private function maxOverlapPercent(array $polygonGeoJson, GeoLayer $layer): float
    {
        $row = DB::selectOne(
            'SELECT ST_Area(ST_Intersection(g.geom, f.geometry)) / NULLIF(ST_Area(g.geom), 0) AS ratio
               FROM geo_features f
               CROSS JOIN (SELECT ST_SetSRID(ST_GeomFromGeoJSON(?), 4326) AS geom) g
              WHERE f.geo_layer_id = ? AND ST_Intersects(f.geometry, g.geom)
              ORDER BY ratio DESC
              LIMIT 1',
            [json_encode($polygonGeoJson), $layer->id],
        );

        return $row !== null && $row->ratio !== null
            ? round((float) $row->ratio * 100, 2)
            : 0.0;
    }

    private function resolveLoteLayer(?CarbonInterface $date): ?GeoLayer
    {
        if ($date !== null) {
            return GeoLayer::naData(GeoLayerType::Lote, $date)->first();
        }

        return GeoLayer::vigente(GeoLayerType::Lote)->first();
    }

    /**
     * Bloqueada = sem camada de lote ou camada `pendente_fonte` (estado real
     * atual, sem base oficial). NUNCA dispara consulta espacial nesse estado.
     */
    private function isBlocked(?GeoLayer $layer): bool
    {
        return $layer === null || $layer->status === GeoLayerStatus::PendenteFonte;
    }
}

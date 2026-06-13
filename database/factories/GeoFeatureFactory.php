<?php

namespace Database\Factories;

use App\Models\GeoFeature;
use App\Models\GeoLayer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GeoFeature>
 *
 * Gera apenas geo_layer_id + properties. A geometria (coluna PostGIS) é
 * responsabilidade dos testes @group postgis, inseridos via SQL real
 * (ST_GeomFromText/ST_SetSRID) — não há geometria genérica válida nos dois
 * drivers para colocar como default aqui.
 */
class GeoFeatureFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'geo_layer_id' => GeoLayer::factory(),
            'properties' => ['NOME_BAIRRO' => fake()->city()],
        ];
    }
}

<?php

namespace Database\Factories;

use App\Enums\GeoLayerStatus;
use App\Enums\GeoLayerType;
use App\Models\GeoLayer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GeoLayer>
 */
class GeoLayerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => GeoLayerType::Bairro,
            'version' => 'geosalvador-'.fake()->unique()->numerify('20##-##'),
            'status' => GeoLayerStatus::Vigente,
            'valid_from' => now()->subYear()->toDateString(),
            'valid_to' => null,
            'source' => 'https://geo.salvador.ba.gov.br/arcgis/rest/services/Bairros/MapServer/0',
            'rules_version' => 'bairros-v1',
            'feature_count' => 0,
        ];
    }

    /**
     * Camada vigente (valid_to nulo).
     */
    public function vigente(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => GeoLayerStatus::Vigente,
            'valid_to' => null,
        ]);
    }

    /**
     * Camada bloqueada por ausência de fonte pública (zona/lote): registrada
     * e comunicada, sem features — nunca simulada (HU-031/HU-033).
     */
    public function pendenteFonte(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => GeoLayerStatus::PendenteFonte,
            'source' => 'pendente-sedur',
            'feature_count' => 0,
            'valid_from' => null,
            'valid_to' => null,
        ]);
    }
}

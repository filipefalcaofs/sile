<?php

namespace Database\Factories;

use App\Enums\ViabilityRequestOrigin;
use App\Enums\ViabilityRequestStatus;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityServiceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ViabilityRequest>
 */
class ViabilityRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => ViabilityRequestStatus::Rascunho,
            'origin' => ViabilityRequestOrigin::Portal,
            'service_type_id' => ViabilityServiceType::factory(),
            'company_id' => Company::factory(),
            'requester_user_id' => User::factory(),
            'created_by_user_id' => User::factory(),
            'used_area_m2' => fake()->randomFloat(2, 20, 500),
            'property_registration' => null,
            'address_street' => fake()->streetName(),
            'address_number' => (string) fake()->buildingNumber(),
            'address_complement' => null,
            'address_neighborhood' => fake()->word(),
            'address_zip' => fake()->numerify('########'),
            'address_reference' => null,
            // Polígono de 4 pontos (quadrilátero fechado) em Salvador — fonte de
            // verdade portável; a geometry derivada é gravada via ST_* no pgsql.
            'property_polygon_geojson' => [
                'type' => 'Polygon',
                'coordinates' => [[
                    [-38.5108, -12.9711],
                    [-38.5108, -12.9709],
                    [-38.5106, -12.9709],
                    [-38.5106, -12.9711],
                    [-38.5108, -12.9711],
                ]],
            ],
            'is_virtual_office' => false,
            'is_public_area' => false,
            'has_independent_access' => false,
        ];
    }

    /**
     * Rascunho (estado inicial) — explicita o default.
     */
    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => ViabilityRequestStatus::Rascunho,
            'protocol_number' => null,
            'protocoled_at' => null,
        ]);
    }

    /**
     * Solicitação protocolada (número único + data).
     */
    public function protocoled(): static
    {
        return $this->state(fn () => [
            'status' => ViabilityRequestStatus::Protocolada,
            'protocol_number' => 'VIA-'.now()->year.'-000001',
            'protocoled_at' => now(),
        ]);
    }

    /**
     * Solicitação cancelada (data + motivo).
     */
    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => ViabilityRequestStatus::Cancelada,
            'cancelled_at' => now(),
            'cancelled_reason' => 'Cancelada pelo requerente.',
        ]);
    }

    /**
     * Origem contingência (HU-148) — o canal de operador real hoje.
     */
    public function contingency(): static
    {
        return $this->state(fn () => [
            'origin' => ViabilityRequestOrigin::Contingencia,
            'contingency_reason' => 'Registro por contingência (indisponibilidade do integrador).',
        ]);
    }

    /**
     * Vincula um CNAE principal após a criação (cria um se não informado).
     */
    public function withPrimaryCnae(?Cnae $cnae = null): static
    {
        return $this->afterCreating(function (ViabilityRequest $request) use ($cnae) {
            $cnae ??= Cnae::factory()->create();
            $request->cnaes()->attach($cnae->id, ['is_primary' => true]);
        });
    }

    /**
     * Vincula N CNAEs (o primeiro como principal).
     */
    public function withCnaes(int $n): static
    {
        return $this->afterCreating(function (ViabilityRequest $request) use ($n) {
            Cnae::factory()->count($n)->create()->each(function (Cnae $cnae, int $i) use ($request) {
                $request->cnaes()->attach($cnae->id, ['is_primary' => $i === 0]);
            });
        });
    }
}

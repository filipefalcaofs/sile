<?php

namespace Database\Factories;

use App\Enums\CompanySource;
use App\Models\Cnae;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cnpj' => $this->generateCnpj(),
            'legal_name' => fake()->company(),
            'trade_name' => null,
            'legal_nature_code' => '2062',
            'legal_nature' => 'Sociedade Empresária Limitada',
            'size_code' => '01',
            'size' => 'MICRO EMPRESA',
            'street' => fake()->streetName(),
            'number' => (string) fake()->buildingNumber(),
            'complement' => null,
            'neighborhood' => fake()->word(),
            'city' => 'Salvador',
            'state' => 'BA',
            'zip_code' => fake()->numerify('########'),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->numerify('71#########'),
            'source' => CompanySource::Manual,
        ];
    }

    /**
     * Empresa importada da REDESIM (HU-022).
     */
    public function fromRedesim(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => CompanySource::Redesim,
            'redesim_protocol' => 'BAP'.fake()->numerify('##########'),
            'redesim_synced_at' => now(),
        ]);
    }

    /**
     * Vincula um CNAE principal após a criação (cria um se não informado).
     */
    public function withPrimaryCnae(?Cnae $cnae = null): static
    {
        return $this->afterCreating(function (Company $company) use ($cnae) {
            $cnae ??= Cnae::factory()->create();
            $company->cnaes()->attach($cnae->id, ['is_primary' => true]);
        });
    }

    /**
     * Gera um CNPJ numérico válido (12 dígitos aleatórios + 2 DVs) pelo
     * mesmo algoritmo módulo 11 ASCII-48 da Rule ValidCnpj.
     */
    private function generateCnpj(): string
    {
        do {
            $base = '';
            for ($i = 0; $i < 12; $i++) {
                $base .= random_int(0, 9);
            }
        } while (preg_match('/^(.)\1{11}$/', $base));

        $weights = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $cnpj = $base;

        foreach ([12, 13] as $position) {
            $slice = array_slice($weights, 13 - $position);

            $sum = 0;
            for ($i = 0; $i < $position; $i++) {
                $sum += (ord($cnpj[$i]) - 48) * $slice[$i];
            }

            $remainder = $sum % 11;
            $cnpj .= $remainder < 2 ? 0 : 11 - $remainder;
        }

        return $cnpj;
    }
}

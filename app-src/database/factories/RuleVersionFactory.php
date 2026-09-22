<?php

namespace Database\Factories;

use App\Enums\RuleDomain;
use App\Enums\RuleVersionStatus;
use App\Models\RuleVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RuleVersion>
 */
class RuleVersionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'domain' => RuleDomain::RiscoMunicipal,
            'version' => 'decreto-32636-2020',
            'status' => RuleVersionStatus::Vigente,
            'valid_from' => now()->subYear()->toDateString(),
            'valid_to' => null,
            'source' => 'docs/dados-oficiais/decreto-32636-2020-risco-municipal-unificado-cnae.csv',
            'rules_version' => 'decreto-32636-2020',
            'published_at' => now()->subYear(),
            'created_by' => null,
            'published_by' => null,
        ];
    }

    /**
     * Rascunho: coexiste com a vigente, sem vigência nem publicação (base do
     * sandbox HU-143).
     */
    public function rascunho(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RuleVersionStatus::Rascunho,
            'valid_from' => null,
            'valid_to' => null,
            'published_at' => null,
            'published_by' => null,
        ]);
    }

    /**
     * Versão substituída (fechada): mantém histórico com valid_to preenchido —
     * nunca apagada.
     */
    public function substituida(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RuleVersionStatus::Substituida,
            'valid_to' => now()->subMonth()->toDateString(),
        ]);
    }
}

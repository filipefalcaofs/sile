<?php

namespace Database\Factories;

use App\Models\Cidadao;
use App\Models\LegalTerm;
use App\Models\LegalTermAcceptance;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Cidadao>
 */
class CidadaoFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'cpf' => $this->generateCpf(),
            'phone' => fake()->numerify('(71) 9####-####'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    private function generateCpf(): string
    {
        $n = [];

        for ($i = 0; $i < 9; $i++) {
            $n[] = random_int(0, 9);
        }

        for ($t = 9; $t < 11; $t++) {
            $d = 0;

            for ($c = 0; $c < $t; $c++) {
                $d += $n[$c] * (($t + 1) - $c);
            }

            $n[$t] = ((10 * $d) % 11) % 10;
        }

        return implode('', $n);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => ['email_verified_at' => null]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['inactivated_at' => now()]);
    }

    /** Registro criado pelo servidor no atendimento presencial: sem senha e sem e-mail verificado. */
    public function semSenha(): static
    {
        return $this->state(fn (array $attributes) => [
            'password' => null,
            'email_verified_at' => null,
            'remember_token' => null,
        ]);
    }

    public function withAcceptedLgpdTerm(): static
    {
        return $this->afterCreating(function (Cidadao $cidadao) {
            $term = LegalTerm::current('lgpd')
                ?? LegalTerm::factory()->published()->create(['type' => 'lgpd', 'version' => 1]);

            LegalTermAcceptance::create([
                'accepted_by_type' => Cidadao::class,
                'accepted_by_id' => $cidadao->id,
                'legal_term_id' => $term->id,
                'ip_address' => '127.0.0.1',
                'accepted_at' => now(),
            ]);
        });
    }
}

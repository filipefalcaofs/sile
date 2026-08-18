<?php

namespace Database\Factories;

use App\Models\EmailLog;
use App\Notifications\VerifyEmailQueued;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailLog>
 */
class EmailLogFactory extends Factory
{
    protected $model = EmailLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recipient_email' => fake()->unique()->safeEmail(),
            'recipient_name' => fake()->name(),
            'notification_class' => VerifyEmailQueued::class,
            'status' => 'na_fila',
            'error_message' => null,
            'queued_at' => now(),
            'sent_at' => null,
            'failed_at' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => 'enviado',
            'sent_at' => now(),
        ]);
    }

    public function failed(string $error = 'Connection could not be established'): static
    {
        return $this->state(fn () => [
            'status' => 'falhou',
            'error_message' => $error,
            'failed_at' => now(),
        ]);
    }
}

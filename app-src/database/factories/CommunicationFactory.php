<?php

namespace Database\Factories;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationStatus;
use App\Enums\CommunicationType;
use App\Models\Communication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Communication>
 */
class CommunicationFactory extends Factory
{
    protected $model = Communication::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'viability_request_id' => null,
            'recipient_user_id' => null,
            'channel' => CommunicationChannel::Email,
            'type' => CommunicationType::PendenciaAberta,
            'status' => CommunicationStatus::NaFila,
            'title' => fake()->sentence(4),
            'summary' => fake()->sentence(8),
            'error_message' => null,
            'meta' => [],
            'queued_at' => now(),
            'sent_at' => null,
            'failed_at' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => CommunicationStatus::Enviado,
            'sent_at' => now(),
        ]);
    }

    public function failed(string $error = 'Connection could not be established'): static
    {
        return $this->state(fn () => [
            'status' => CommunicationStatus::Falhou,
            'error_message' => $error,
            'failed_at' => now(),
        ]);
    }

    public function blocked(?string $motivo = 'Canal indisponível no disparo'): static
    {
        return $this->state(fn () => [
            'status' => CommunicationStatus::Bloqueado,
            'error_message' => $motivo,
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn () => [
            'status' => CommunicationStatus::Desativado,
        ]);
    }

    public function inApp(): static
    {
        return $this->state(fn () => [
            'channel' => CommunicationChannel::InApp,
        ]);
    }

    public function whatsapp(): static
    {
        return $this->state(fn () => [
            'channel' => CommunicationChannel::Whatsapp,
        ]);
    }
}

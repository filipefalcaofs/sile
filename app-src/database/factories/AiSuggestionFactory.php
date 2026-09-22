<?php

namespace Database\Factories;

use App\Enums\AiSuggestionStatus;
use App\Enums\AiSuggestionType;
use App\Models\AiSuggestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiSuggestion>
 *
 * Default = sugestão de OCR recém-gerada, revisável (status Sugerida — JAMAIS
 * "decidida"), com proveniência preenchida e custo nulo (sem preço configurado,
 * nunca inventado). States por tipo/status.
 */
class AiSuggestionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => AiSuggestionType::Ocr,
            'viability_request_id' => null,
            'input_ref' => ['document_id' => fake()->numberBetween(1, 999)],
            'input_hash' => fake()->unique()->sha256(),
            'output' => [
                'texto' => fake()->sentence(),
                'confianca' => 'alta',
                'fonte' => 'documento anexado',
            ],
            'provider' => 'openai',
            'model' => 'gpt-5.4-mini',
            'prompt_version' => 'ocr-v1',
            'prompt_tokens' => fake()->numberBetween(50, 500),
            'completion_tokens' => fake()->numberBetween(10, 200),
            'cost_estimated' => null,
            'confidence' => 'alta',
            'status' => AiSuggestionStatus::Sugerida,
            'created_by_user_id' => null,
        ];
    }

    public function escaladaHumano(): static
    {
        return $this->state(fn (): array => ['status' => AiSuggestionStatus::EscaladaHumano]);
    }

    public function classificacao(): static
    {
        return $this->state(fn (): array => ['type' => AiSuggestionType::Classificacao, 'prompt_version' => 'classificacao-v1']);
    }
}

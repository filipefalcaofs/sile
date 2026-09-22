<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use App\Enums\AiSuggestionStatus;
use App\Enums\AiSuggestionType;
use Database\Factories\AiSuggestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sugestão de IA (Fase 14) — saída SEMPRE revisável (AI-SPEC Failure Mode #1),
 * nunca decisão. Criada APENAS após uma chamada de IA bem-sucedida e validada
 * pelos guardrails (RunAiAgentJob). Guarda a proveniência da chamada para a
 * auditoria RN-002 (provider/modelo/versão do prompt/tokens/custo) sem nunca a
 * api_key nem PII além do necessário. A própria criação é auditada (HasAuditoria).
 */
#[Fillable([
    'type',
    'viability_request_id',
    'input_ref',
    'input_hash',
    'output',
    'provider',
    'model',
    'prompt_version',
    'prompt_tokens',
    'completion_tokens',
    'cost_estimated',
    'confidence',
    'status',
    'created_by_user_id',
])]
class AiSuggestion extends Model
{
    use HasAuditoria;

    /** @use HasFactory<AiSuggestionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AiSuggestionType::class,
            'status' => AiSuggestionStatus::class,
            'input_ref' => 'array',
            'output' => 'array',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'cost_estimated' => 'decimal:6',
        ];
    }

    /**
     * Processo de viabilidade ao qual a sugestão se refere (opcional).
     *
     * @return BelongsTo<ViabilityRequest, $this>
     */
    public function viabilityRequest(): BelongsTo
    {
        return $this->belongsTo(ViabilityRequest::class);
    }

    /**
     * Usuário que disparou a geração da sugestão (opcional).
     *
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}

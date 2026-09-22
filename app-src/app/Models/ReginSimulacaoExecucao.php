<?php

namespace App\Models;

use Database\Factories\ReginSimulacaoExecucaoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['codigo', 'relatorio', 'user_id', 'viability_request_id'])]
class ReginSimulacaoExecucao extends Model
{
    /** @use HasFactory<ReginSimulacaoExecucaoFactory> */
    use HasFactory;

    protected $table = 'regin_simulacao_execucoes';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'relatorio' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ViabilityRequest, $this>
     */
    public function viabilityRequest(): BelongsTo
    {
        return $this->belongsTo(ViabilityRequest::class);
    }
}

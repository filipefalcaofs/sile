<?php

namespace App\Models;

use App\Enums\ParameterProposalStatus;
use Database\Factories\ParameterProposalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Proposta de alteração de um parâmetro decisório. O valor vigente permanece
 * intacto até a aprovação por um segundo usuário (quatro olhos).
 */
#[Fillable(['parameter_id', 'proposed_value', 'created_by', 'status', 'reviewed_by', 'reviewed_at'])]
class ParameterProposal extends Model
{
    /** @use HasFactory<ParameterProposalFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ParameterProposalStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Parameter, $this>
     */
    public function parameter(): BelongsTo
    {
        return $this->belongsTo(Parameter::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

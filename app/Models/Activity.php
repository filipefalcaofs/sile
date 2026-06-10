<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * Activity estendida com as colunas SILE da RN-002: origem (ip_address,
 * user_agent, channel), resultado (result), versão de regras (rules_version)
 * e atuação "em nome de" (acting_for_user_id).
 *
 * O model pai usa $guarded = [] — todas as colunas, incluindo as novas,
 * já são mass-assignable sem redefinir $fillable.
 */
class Activity extends SpatieActivity
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'acting_for_user_id' => 'integer',
        ]);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actingFor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acting_for_user_id');
    }
}

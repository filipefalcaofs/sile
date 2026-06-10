<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use Database\Factories\LegalTermFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['type', 'version', 'title', 'content', 'published_at'])]
class LegalTerm extends Model
{
    /** @use HasFactory<LegalTermFactory> */
    use HasAuditoria, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    /**
     * Latest published version in force for the given term type.
     */
    public static function current(string $type): ?self
    {
        return static::query()
            ->where('type', $type)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('version')
            ->first();
    }
}

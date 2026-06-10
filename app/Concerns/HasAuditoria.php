<?php

namespace App\Concerns;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Padrão SILE de auditoria de models de domínio (RN-002): loga apenas
 * atributos fillable efetivamente alterados e nunca campos sensíveis.
 */
trait HasAuditoria
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->logExcept(['password', 'remember_token']);
    }
}

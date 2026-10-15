<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Contagem de servidores por perfil sem `Role::users()`: o Spatie resolve esse model pelo
 * guard padrão, e no guard `web` (job, comando) ele é `Cidadao`, o que zeraria a contagem.
 */
final class ServidoresPorPerfil
{
    /** Subconsulta correlacionada a `roles.id`, para `addSelect(['users_count' => ...])`. */
    public static function contagem(): Builder
    {
        return DB::table('model_has_roles')
            ->selectRaw('count(*)')
            ->whereColumn('model_has_roles.role_id', 'roles.id')
            ->where('model_has_roles.model_type', User::class);
    }
}

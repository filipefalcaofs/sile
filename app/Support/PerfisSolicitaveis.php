<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

/**
 * Perfis que o servidor pode pedir no primeiro acesso: os que dão acesso à
 * gestão e não são protegidos. O administrador pode aprovar com outro perfil.
 */
final class PerfisSolicitaveis
{
    /**
     * @return Collection<int, Role>
     */
    public static function roles(): Collection
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->whereHas('permissions', fn ($permissions) => $permissions->where('name', 'acessar-gestao'))
            ->orderBy('name')
            ->get()
            ->reject(fn (Role $role) => RoleProtection::isProtected($role))
            ->values();
    }
}

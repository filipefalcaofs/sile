<?php

namespace App\Support;

use Spatie\Permission\Models\Role;

final class RoleProtection
{
    public static function isProtected(Role $role): bool
    {
        return (bool) $role->protegido || $role->name === PerfilCidadao::nome();
    }
}

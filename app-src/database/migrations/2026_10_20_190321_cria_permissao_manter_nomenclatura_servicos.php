<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Parametrização da nomenclatura dos serviços: vai para todo perfil que já
 * cadastra os tipos de serviço.
 */
return new class extends Migration
{
    public function up(): void
    {
        $perfis = Role::query()
            ->where('guard_name', 'web')
            ->whereHas('permissions', fn ($q) => $q->where('name', 'manter-tipos-servico'))
            ->get();

        // Banco novo: o seeder cria as permissões e os perfis.
        if ($perfis->isEmpty()) {
            return;
        }

        Permission::firstOrCreate(['name' => 'manter-nomenclatura-servicos', 'guard_name' => 'web']);

        foreach ($perfis as $perfil) {
            $perfil->givePermissionTo('manter-nomenclatura-servicos');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Concessão aditiva: o administrador pode ter ajustado os perfis depois.
    }
};

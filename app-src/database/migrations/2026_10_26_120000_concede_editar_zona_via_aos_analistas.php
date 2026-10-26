<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const array ANALISTAS = ['Analista Servidor', 'Analista Terceirizado'];

    /**
     * Zona e via passam a ser editáveis na ficha também pelos analistas, não só
     * pelas chefias. Aditiva: não retira a permissão de nenhum perfil.
     */
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'editar-zona-via', 'guard_name' => 'web']);

        Role::query()->whereIn('name', self::ANALISTAS)->where('guard_name', 'web')->get()
            ->each(fn (Role $perfil) => $perfil->givePermissionTo('editar-zona-via'));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::query()->whereIn('name', self::ANALISTAS)->where('guard_name', 'web')->get()
            ->each(fn (Role $perfil) => $perfil->revokePermissionTo('editar-zona-via'));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

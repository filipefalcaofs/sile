<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'acessar-gestao',
            'consultar-acessos-de-qualquer-conta',
            'gerenciar-procuracoes-proprias',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        Role::firstOrCreate(['name' => 'cidadao', 'guard_name' => 'web'])
            ->syncPermissions(['gerenciar-procuracoes-proprias']);
        Role::firstOrCreate(['name' => 'analista', 'guard_name' => 'web'])
            ->syncPermissions(['acessar-gestao']);
        Role::firstOrCreate(['name' => 'gestor', 'guard_name' => 'web'])
            ->syncPermissions(['acessar-gestao']);
        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web'])
            ->syncPermissions(['acessar-gestao', 'consultar-acessos-de-qualquer-conta']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

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
            'manter-cnaes',
            'manter-usuarios',
            'manter-perfis',
            'manter-parametros',
            'consultar-cnaes',
            'monitorar-emails',
            'consultar-territorio',
            'consultar-risco',
            'manter-risco',
            'consultar-louos',
            'manter-louos',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Atribuição aditiva (givePermissionTo, nunca sync): re-seed em produção
        // não pode remover permissões ajustadas pelo administrador via interface (HU-013).
        Role::firstOrCreate(['name' => 'cidadao', 'guard_name' => 'web'])
            ->givePermissionTo(['gerenciar-procuracoes-proprias']);
        Role::firstOrCreate(['name' => 'analista', 'guard_name' => 'web'])
            ->givePermissionTo(['acessar-gestao', 'consultar-cnaes', 'consultar-territorio', 'consultar-risco', 'consultar-louos']);
        Role::firstOrCreate(['name' => 'gestor', 'guard_name' => 'web'])
            ->givePermissionTo(['acessar-gestao', 'consultar-cnaes', 'consultar-territorio', 'consultar-risco', 'consultar-louos']);
        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web'])
            ->givePermissionTo([
                'acessar-gestao',
                'consultar-acessos-de-qualquer-conta',
                'manter-cnaes',
                'manter-usuarios',
                'manter-perfis',
                'manter-parametros',
                'consultar-cnaes',
                'monitorar-emails',
                'consultar-territorio',
                'consultar-risco',
                'manter-risco',
                'consultar-louos',
                'manter-louos',
            ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

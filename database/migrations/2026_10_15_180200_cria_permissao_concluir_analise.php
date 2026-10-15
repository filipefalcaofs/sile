<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const string PERMISSAO = 'concluir-analise';

    /**
     * Deferir/indeferir passa a exigir permissão própria. Quem já analisa
     * continua concluindo; o administrador retira dos perfis que não devem.
     */
    public function up(): void
    {
        $permissao = Permission::firstOrCreate(['name' => self::PERMISSAO, 'guard_name' => 'web']);

        Role::query()
            ->where('guard_name', 'web')
            ->whereHas('permissions', fn ($query) => $query->where('name', 'analisar-processos'))
            ->get()
            ->each(fn (Role $perfil) => $perfil->givePermissionTo($permissao));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('name', self::PERMISSAO)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

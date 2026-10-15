<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const string PERMISSAO = 'concluir-analise';

    private const array PERFIS = ['Coordenador', 'Subcoordenador', 'Chefe de setor', 'Analista Servidor', 'administrador'];

    /**
     * Só as chefias, o Analista Servidor e o administrador deferem e
     * indeferem. Os demais perfis perdem a permissão concedida na criação.
     */
    public function up(): void
    {
        $permissao = Permission::query()->where('name', self::PERMISSAO)->where('guard_name', 'web')->first();

        if ($permissao === null) {
            return;
        }

        Role::query()
            ->where('guard_name', 'web')
            ->whereNotIn('name', self::PERFIS)
            ->get()
            ->each(fn (Role $perfil) => $perfil->revokePermissionTo($permissao));

        Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', self::PERFIS)
            ->get()
            ->each(fn (Role $perfil) => $perfil->givePermissionTo($permissao));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Ajuste de concessão: o administrador pode ter reconfigurado os perfis depois.
    }
};

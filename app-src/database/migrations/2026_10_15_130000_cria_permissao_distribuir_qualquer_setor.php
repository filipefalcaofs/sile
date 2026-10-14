<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const array PERFIS = ['administrador', 'apoio'];

    private const array PERMISSOES = ['distribuir-processos', 'distribuir-qualquer-setor'];

    public function up(): void
    {
        $perfis = Role::query()->whereIn('name', self::PERFIS)->where('guard_name', 'web')->get();

        // Banco novo: o seeder cria as permissões e os perfis.
        if ($perfis->isEmpty()) {
            return;
        }

        foreach (self::PERMISSOES as $nome) {
            Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']);
        }

        foreach ($perfis as $perfil) {
            $perfil->givePermissionTo(self::PERMISSOES);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Concessão aditiva: o administrador pode ter ajustado os perfis depois.
    }
};

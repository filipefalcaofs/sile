<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const array PERMISSOES = [
        'acessar-gestao',
        'consultar-cnaes',
        'consultar-territorio',
        'consultar-louos',
        'consultar-solicitacoes',
        'analisar-processos',
        'reprocessar-processos',
        'encaminhar-malha-fina',
        'emitir-tvl',
        'enviar-tvl-analise',
        'preencher-ficha-vistoria',
    ];

    public function up(): void
    {
        $perfil = Role::query()->where('name', 'Analista Servidor')->where('guard_name', 'web')->first();

        // Banco novo: o seeder cria o perfil já com as permissões.
        if ($perfil === null) {
            return;
        }

        foreach (self::PERMISSOES as $nome) {
            Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']);
        }

        $perfil->givePermissionTo(self::PERMISSOES);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Concessão aditiva: o administrador pode ter ajustado o perfil depois.
    }
};

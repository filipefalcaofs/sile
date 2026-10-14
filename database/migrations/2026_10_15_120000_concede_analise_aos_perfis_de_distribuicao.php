<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const array PERFIS = ['Chefe de setor', 'Coordenador', 'Subcoordenador', 'Analista Terceirizado', 'Analista Servidor'];

    private const array PERMISSOES_ANALISE = [
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

    private const array PERMISSOES_CHEFIA = [
        'distribuir-processos',
        'analisar-malha-fina',
        'consultar-relatorios',
        'relatorios.produtividade.nominal',
        'forcar-transicao-analise',
        'receber-escalonamento-sla',
    ];

    private const array CHEFIAS = ['Chefe de setor', 'Coordenador', 'Subcoordenador'];

    public function up(): void
    {
        $perfis = Role::query()->whereIn('name', self::PERFIS)->where('guard_name', 'web')->get();

        // Banco novo: o seeder cria os perfis já com as permissões.
        if ($perfis->isEmpty()) {
            return;
        }

        foreach ([...self::PERMISSOES_ANALISE, ...self::PERMISSOES_CHEFIA] as $nome) {
            Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']);
        }

        foreach ($perfis as $perfil) {
            $perfil->givePermissionTo(self::PERMISSOES_ANALISE);

            if (in_array($perfil->name, self::CHEFIAS, true)) {
                $perfil->givePermissionTo(self::PERMISSOES_CHEFIA);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Concessão aditiva: o administrador pode ter ajustado os perfis depois.
    }
};

<?php

namespace Database\Seeders;

use App\Support\Roles;
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
            'manter-gatilhos-risco',
            'manter-usuarios',
            'manter-perfis',
            'manter-parametros',
            'consultar-cnaes',
            'monitorar-emails',
            'consultar-territorio',
            'manter-territorio',
            'consultar-louos',
            'manter-louos',
            'registrar-contingencia',
            'atendimento-presencial',
            'consultar-solicitacoes',
            'manter-tipos-servico',
            'manter-tipos-imovel',
            'manter-requisitos-documentais',
            'analisar-processos',
            'distribuir-processos',
            'emitir-tvl',
            'encaminhar-malha-fina',
            'analisar-malha-fina',
            'reprocessar-processos',
            'enviar-tvl-analise',
            'preencher-ficha-vistoria',
            'manter-setores',
            'consultar-auditoria',
            'monitorar-lgpd',
            'gerenciar-alertas-abuso',
            'manter-config-email',
            'manter-config-ia',
            'consultar-relatorios',
            'relatorios.produtividade.nominal',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Atribuição aditiva (givePermissionTo, nunca sync): re-seed em produção
        // não pode remover permissões ajustadas pelo administrador via interface (HU-013).
        Role::firstOrCreate(['name' => 'cidadao', 'guard_name' => 'web'], ['protegido' => true])
            ->givePermissionTo(['gerenciar-procuracoes-proprias']);
        Role::firstOrCreate(['name' => 'analista', 'guard_name' => 'web'])
            ->givePermissionTo([
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
            ]);
        // Apoio (tramitação): distribui os processos da caixa do setor para um
        // analista específico, sem analisar — a tramitação é dele, a análise não.
        Role::firstOrCreate(['name' => 'apoio', 'guard_name' => 'web'])
            ->givePermissionTo([
                'acessar-gestao',
                'consultar-cnaes',
                'consultar-territorio',
                'consultar-louos',
                'consultar-solicitacoes',
                'distribuir-processos',
            ]);
        Role::firstOrCreate(['name' => 'gestor', 'guard_name' => 'web'])
            ->givePermissionTo([
                'acessar-gestao',
                'consultar-cnaes',
                'consultar-territorio',
                'consultar-louos',
                'registrar-contingencia',
                'atendimento-presencial',
                'consultar-solicitacoes',
                'manter-tipos-servico',
                'manter-tipos-imovel',
                'manter-gatilhos-risco',
                'manter-requisitos-documentais',
                'analisar-processos',
                'distribuir-processos',
                'encaminhar-malha-fina',
                'analisar-malha-fina',
                'emitir-tvl',
                'enviar-tvl-analise',
                'preencher-ficha-vistoria',
                'consultar-relatorios',
                'relatorios.produtividade.nominal',
            ]);
        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web'], ['protegido' => true])
            ->givePermissionTo([
                'acessar-gestao',
                'consultar-acessos-de-qualquer-conta',
                'manter-cnaes',
                'manter-gatilhos-risco',
                'manter-usuarios',
                'manter-perfis',
                'manter-parametros',
                'consultar-cnaes',
                'monitorar-emails',
                'consultar-territorio',
                'manter-territorio',
                'consultar-louos',
                'manter-louos',
                'registrar-contingencia',
                'atendimento-presencial',
                'consultar-solicitacoes',
                'manter-tipos-servico',
                'manter-tipos-imovel',
                'manter-requisitos-documentais',
                'analisar-processos',
                'reprocessar-processos',
                'distribuir-processos',
                'emitir-tvl',
                'encaminhar-malha-fina',
                'analisar-malha-fina',
                'enviar-tvl-analise',
                'preencher-ficha-vistoria',
                'manter-setores',
                'consultar-auditoria',
                'monitorar-lgpd',
                'gerenciar-alertas-abuso',
                'manter-config-email',
                'manter-config-ia',
                'consultar-relatorios',
                'relatorios.produtividade.nominal',
            ]);

        foreach (Roles::LEGACY as $name) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        Role::query()
            ->where('guard_name', 'web')
            ->whereNotIn('name', Roles::catalog())
            ->get()
            ->each(fn (Role $role) => $role->delete());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

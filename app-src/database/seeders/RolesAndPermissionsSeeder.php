<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /** Conjunto de análise: o que cada perfil que trabalha processos precisa. */
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

    /** Acrescidas às chefias de análise. */
    private const array PERMISSOES_CHEFIA = [
        'distribuir-processos',
        'analisar-malha-fina',
        'consultar-relatorios',
        'relatorios.produtividade.nominal',
        'forcar-transicao-analise',
        'receber-escalonamento-sla',
    ];

    private const array PERFIS_ANALISE = ['Analista Terceirizado'];

    private const array PERFIS_CHEFIA = ['Coordenador', 'Subcoordenador', 'Chefe de setor'];

    /**
     * Perfis herdados do sistema antigo da SEDUR: nascem sem permissão e o
     * administrador configura cada um na tela de Perfis.
     */
    private const array PERFIS_SEM_PERMISSAO = [
        'Analista Servidor',
        'Analista Vistoriador',
        'Atendimento Sedur',
        'Diretor',
        'Fiscal',
        'Gerente',
        'GR',
        'Padrão',
        'Secretário',
        'SEFAZ',
        'Servidor',
        'Vistoriador',
    ];

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
            'distribuir-qualquer-setor',
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
            'forcar-transicao-analise',
            'receber-escalonamento-sla',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Atribuição aditiva (givePermissionTo, nunca sync): re-seed em produção
        // não pode remover permissões ajustadas pelo administrador via interface (HU-013).
        $this->perfil('cidadao', protegido: true)
            ->givePermissionTo(['gerenciar-procuracoes-proprias']);

        foreach (self::PERFIS_ANALISE as $nome) {
            $this->perfil($nome)->givePermissionTo(self::PERMISSOES_ANALISE);
        }

        foreach (self::PERFIS_CHEFIA as $nome) {
            $this->perfil($nome)->givePermissionTo([...self::PERMISSOES_ANALISE, ...self::PERMISSOES_CHEFIA]);
        }

        // Apoio (tramitação): distribui os processos da caixa para um analista
        // específico, de qualquer setor, sem analisar — a tramitação é dele, a
        // análise não.
        $this->perfil('apoio')->givePermissionTo([
            'acessar-gestao',
            'consultar-cnaes',
            'consultar-territorio',
            'consultar-louos',
            'consultar-solicitacoes',
            'distribuir-processos',
            'distribuir-qualquer-setor',
        ]);

        $this->perfil('administrador', protegido: true)->givePermissionTo([
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
            'reprocessar-processos',
            'distribuir-processos',
            'distribuir-qualquer-setor',
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

        foreach (self::PERFIS_SEM_PERMISSAO as $nome) {
            $this->perfil($nome);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function perfil(string $nome, bool $protegido = false): Role
    {
        $role = Role::firstOrCreate(['name' => $nome, 'guard_name' => 'web'], ['protegido' => $protegido]);

        if ($protegido && ! $role->protegido) {
            $role->forceFill(['protegido' => true])->save();
        }

        return $role;
    }
}

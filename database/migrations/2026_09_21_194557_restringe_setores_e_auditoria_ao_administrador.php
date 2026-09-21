<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Relatório de usabilidade SEDUR 19/09/2026 (itens 03, 04 e 06): Setores e o
 * grupo Auditoria ficam restritos ao administrador. O seeder é aditivo por
 * desenho (nunca remove em re-seed), então a revogação do papel gestor nos
 * bancos existentes acontece aqui. Idempotente: revokePermissionTo sobre
 * permissão ausente é no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        $gestor = Role::query()->where('name', 'gestor')->where('guard_name', 'web')->first();

        if ($gestor === null) {
            return;
        }

        $gestor->revokePermissionTo(['manter-setores', 'consultar-auditoria', 'gerenciar-alertas-abuso']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $gestor = Role::query()->where('name', 'gestor')->where('guard_name', 'web')->first();

        if ($gestor === null) {
            return;
        }

        $gestor->givePermissionTo(['manter-setores', 'consultar-auditoria', 'gerenciar-alertas-abuso']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

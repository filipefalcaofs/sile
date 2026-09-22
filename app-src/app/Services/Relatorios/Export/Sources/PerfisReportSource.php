<?php

namespace App\Services\Relatorios\Export\Sources;

use App\Http\Controllers\Gestao\RoleController;
use App\Services\Relatorios\Export\ReportDefinition;
use App\Services\Relatorios\Export\ReportExporter;
use App\Services\Relatorios\Export\ReportSource;
use App\Services\Relatorios\ReportFilters;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

/**
 * Fonte da listagem de perfis (HU-013) para o contrato único de exportação
 * (HU-131/RN-009): o index do {@see RoleController}
 * ganha um branch ?formato= delegando ao {@see ReportExporter}
 * sem rota nova. A listagem não tem filtros; o conjunto exportado é o de perfis
 * ordenado por nome (RN-005 — mesma ordem da tela), com os totais de permissões
 * e de usuários vinculados. personalData=false; reconstrutível só pelo bag.
 */
final class PerfisReportSource implements ReportSource
{
    public function definition(ReportFilters $filtros): ReportDefinition
    {
        return new ReportDefinition(
            titulo: 'Perfis',
            colunas: [
                ['key' => 'name', 'label' => 'Perfil'],
                ['key' => 'permissions_total', 'label' => 'Permissões'],
                ['key' => 'users_total', 'label' => 'Usuários'],
            ],
            // RN-005: mesma ordem por nome da tela; totais agregados no banco.
            builder: fn (): Builder => Role::query()
                ->withCount(['permissions', 'users'])
                ->orderBy('name'),
            mapRow: fn (Role $role): array => [
                $role->name,
                $role->permissions_count,
                $role->users_count,
            ],
            filtrosAplicados: $filtros->aplicados(),
            logName: 'perfis',
            event: 'exporta-perfis',
            personalData: false,
            arquivoBase: 'perfis',
        );
    }
}

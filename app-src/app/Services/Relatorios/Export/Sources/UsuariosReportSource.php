<?php

namespace App\Services\Relatorios\Export\Sources;

use App\Http\Controllers\Gestao\UserManagementController;
use App\Models\User;
use App\Services\Relatorios\Export\ReportDefinition;
use App\Services\Relatorios\Export\ReportExporter;
use App\Services\Relatorios\Export\ReportSource;
use App\Services\Relatorios\ReportFilters;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

/**
 * Fonte da listagem de usuários (HU-012) para o contrato único de exportação
 * (HU-131/RN-009): o index do {@see UserManagementController}
 * ganha um branch ?formato= delegando ao {@see ReportExporter}
 * sem rota nova. O conjunto exportado é EXATAMENTE o filtrado da tela (RN-005) —
 * aba (equipe SEDUR × portal), busca por nome/e-mail (case-insensitive) e ordem,
 * tudo reproduzido a partir do BAG do {@see ReportFilters}.
 *
 * RN-007 (LGPD): o CPF sai MASCARADO (cpf_masked — só os dígitos verificadores)
 * por default; o número completo só quando o gate de PII vem liberado no bag
 * (`pii`), decisão que o controller toma a partir de uma permissão de PII. O gate
 * viaja no BAG (não no construtor): a fonte é reconstrutível só pelo bag
 * (INVARIANTE do {@see ReportSource}), então o caminho assíncrono honra o MESMO
 * gate. personalData=true (HU-101).
 */
final class UsuariosReportSource implements ReportSource
{
    /** Abas da listagem (HU-012): equipe SEDUR × usuários do portal. */
    private const TABS = ['gestao', 'portal'];

    public function definition(ReportFilters $filtros): ReportDefinition
    {
        $search = (string) ($filtros->get('search') ?? '');

        $tab = (string) ($filtros->get('tab') ?? '');
        $tab = in_array($tab, self::TABS, true) ? $tab : 'gestao';

        // RN-007: PII (CPF completo) só sob liberação explícita no bag.
        $pii = filter_var($filtros->get('pii'), FILTER_VALIDATE_BOOLEAN);

        $gestaoRoleIds = Role::query()
            ->whereHas('permissions', fn ($query) => $query->where('name', 'acessar-gestao'))
            ->pluck('id');

        return new ReportDefinition(
            titulo: 'Usuários',
            colunas: [
                ['key' => 'name', 'label' => 'Nome'],
                ['key' => 'email', 'label' => 'E-mail'],
                ['key' => 'roles', 'label' => 'Papel'],
                ['key' => 'cpf', 'label' => 'CPF'],
                ['key' => 'status', 'label' => 'Situação'],
                ['key' => 'created_at', 'label' => 'Criado em'],
            ],
            // RN-005: a MESMA query (aba + busca + ordem) do index, lida do bag.
            builder: fn (): Builder => User::query()
                ->when(
                    $tab === 'gestao',
                    fn ($query) => $query->whereHas('roles', fn ($inner) => $inner->whereIn('roles.id', $gestaoRoleIds)),
                    fn ($query) => $query->whereDoesntHave('roles', fn ($inner) => $inner->whereIn('roles.id', $gestaoRoleIds)),
                )
                ->with('roles:id,name')
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(fn ($inner) => $inner
                        ->whereLike('name', "%{$search}%", caseSensitive: false)
                        ->orWhereLike('email', "%{$search}%", caseSensitive: false));
                })
                ->orderBy('name'),
            mapRow: fn (User $user): array => [
                $user->name,
                $user->email,
                $user->getRoleNames()->implode(', '),
                // RN-007: cpf_masked por default; número completo só com PII liberado.
                $pii ? $user->cpf : '***.***.***-'.substr((string) $user->cpf, -2),
                $user->inactivated_at !== null ? 'Inativo' : 'Ativo',
                $user->created_at?->toIso8601String(),
            ],
            filtrosAplicados: $filtros->aplicados(),
            logName: 'usuarios',
            event: 'exporta-usuarios',
            personalData: true,
            arquivoBase: 'usuarios',
        );
    }
}

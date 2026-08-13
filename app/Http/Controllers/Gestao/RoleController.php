<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\StoreRoleRequest;
use App\Http\Requests\Gestao\UpdateRoleRequest;
use App\Services\Relatorios\Export\ReportExporter;
use App\Services\Relatorios\Export\Sources\PerfisReportSource;
use App\Services\Relatorios\ReportFilters;
use App\Support\Audit\AuditService;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class RoleController extends Controller
{
    /**
     * Perfis com permissões e vínculos (HU-013 CA-01). O flag structural
     * comunica à tela quais papéis o código referencia e protege.
     */
    public function index(Request $request): Response|HttpResponse
    {
        // HU-131/RN-009: com ?formato=, exporta os perfis pelo contrato único —
        // sem rota nova. A listagem não tem filtros (catálogo de perfis).
        if (in_array($request->string('formato')->lower()->toString(), ['csv', 'xlsx', 'pdf'], true)) {
            return app(ReportExporter::class)->export(
                app(PerfisReportSource::class),
                ReportFilters::fromArray([]),
                $request->string('formato')->lower()->toString(),
                $request->user(),
            );
        }

        $roles = Role::query()
            ->withCount('users')
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->all(),
                'users_count' => $role->users_count,
                'structural' => in_array($role->name, Roles::STRUCTURAL, true),
            ]);

        return Inertia::render('gestao/perfis/index', [
            'roles' => $roles,
            'permissions' => Permission::orderBy('name')->pluck('name'),
        ]);
    }

    /**
     * spatie v8 reseta o cache de permissões nos métodos built-in
     * (create/syncPermissions/delete) — sem forget manual.
     */
    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $role = Role::create(['name' => $validated['name'], 'guard_name' => 'web']);
        $role->syncPermissions($validated['permissions'] ?? []);

        app(AuditService::class)->log(
            'perfis',
            'perfil-criado',
            "Perfil {$role->name} criado",
            ['permissoes' => $validated['permissions'] ?? []],
            $role,
        );

        return back()->with('status', 'Perfil criado com sucesso.');
    }

    /**
     * syncPermissions aplica o conjunto exato marcado na tela (intenção do
     * admin) — diferente do seeder, que é aditivo por decisão do 02-01.
     */
    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $validated = $request->validated();
        $before = $role->permissions->pluck('name')->all();

        if (! in_array($role->name, Roles::STRUCTURAL, true)) {
            $role->update(['name' => $validated['name']]);
        }

        $role->syncPermissions($validated['permissions'] ?? []);

        app(AuditService::class)->log(
            'perfis',
            'perfil-atualizado',
            "Perfil {$role->name} atualizado",
            [
                'permissoes_antes' => $before,
                'permissoes_depois' => $validated['permissions'] ?? [],
            ],
            $role,
        );

        return back()->with('status', 'Perfil atualizado com sucesso.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if (in_array($role->name, Roles::STRUCTURAL, true)) {
            throw ValidationException::withMessages([
                'role' => __('Papéis estruturais do sistema não podem ser excluídos.'),
            ]);
        }

        if ($role->users()->exists()) {
            throw ValidationException::withMessages([
                'role' => __('Este perfil possui usuários vinculados e não pode ser excluído.'),
            ]);
        }

        $name = $role->name;

        $role->delete();

        app(AuditService::class)->log(
            'perfis',
            'perfil-excluido',
            "Perfil {$name} excluído",
            ['perfil' => $name],
        );

        return back()->with('status', 'Perfil excluído.');
    }
}

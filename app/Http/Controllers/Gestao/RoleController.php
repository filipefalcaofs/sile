<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\StoreRoleRequest;
use App\Http\Requests\Gestao\UpdateRoleRequest;
use App\Services\Relatorios\Export\ReportExporter;
use App\Services\Relatorios\Export\Sources\PerfisReportSource;
use App\Services\Relatorios\ReportFilters;
use App\Support\Audit\AuditService;
use App\Support\PermissionCatalog;
use App\Support\RoleProtection;
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
    private const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    private const DEFAULT_PER_PAGE = 15;

    /** @var list<string> */
    private const SORTABLE_COLUMNS = ['name'];

    /**
     * Perfis com permissões e vínculos (HU-013 CA-01). O flag structural
     * comunica à tela quais perfis são protegidos. Busca e paginação são
     * server-driven: o termo e a página viajam na query string.
     */
    public function index(Request $request): Response|HttpResponse
    {
        $search = $request->string('search')->trim()->toString();

        // HU-131/RN-009: com ?formato=, exporta o conjunto filtrado pelo
        // contrato único — sem rota nova. A busca da tela entra no bag (RN-005).
        if (in_array($request->string('formato')->lower()->toString(), ['csv', 'xlsx', 'pdf'], true)) {
            return app(ReportExporter::class)->export(
                app(PerfisReportSource::class),
                ReportFilters::fromArray($request->only(['search'])),
                $request->string('formato')->lower()->toString(),
                $request->user(),
            );
        }

        $sort = $request->string('sort')->toString();
        $sort = in_array($sort, self::SORTABLE_COLUMNS, true) ? $sort : 'name';

        $direction = $request->string('direction')->toString();
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'asc';

        $perPage = (int) $request->input('per_page');
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::DEFAULT_PER_PAGE;

        $roles = Role::query()
            ->withCount('users')
            ->with('permissions:id,name')
            ->when($search !== '', function ($query) use ($search) {
                // whereLike sem case: LIKE do PostgreSQL é case-sensitive.
                $query->whereLike('name', "%{$search}%", caseSensitive: false);
            })
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->all(),
                'users_count' => $role->users_count,
                'structural' => RoleProtection::isProtected($role),
            ]);

        $permissionNames = Permission::query()->orderBy('name')->pluck('name');

        return Inertia::render('gestao/perfis/index', [
            'roles' => $roles,
            'permissions' => $permissionNames,
            'permissionCatalog' => PermissionCatalog::forNames($permissionNames),
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
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

        if (! RoleProtection::isProtected($role)) {
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
        if (RoleProtection::isProtected($role)) {
            throw ValidationException::withMessages([
                'role' => __('Perfis protegidos do sistema não podem ser excluídos.'),
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

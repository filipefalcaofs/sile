<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\ToggleUserActivationRequest;
use App\Http\Requests\Gestao\UpdateUserRoleRequest;
use App\Models\User;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    /**
     * Listagem com busca por nome ou e-mail (HU-012 CA-01). CPF nunca
     * exposto em claro (LGPD) — apenas os dígitos verificadores mascarados.
     */
    public function index(Request $request): Response
    {
        $users = User::query()
            ->with('roles:id,name')
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request) {
                $term = (string) $request->string('search')->trim();

                $query->where(fn ($inner) => $inner
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%"));
            })
            ->orderBy('name')
            ->paginate((int) Settings::get('ui.users.per_page', 15))
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                'inactivated_at' => $user->inactivated_at?->toIso8601String(),
                'cpf_masked' => '***.***.***-'.substr($user->cpf, -2),
            ]);

        return Inertia::render('gestao/usuarios/index', [
            'users' => $users,
            'filters' => ['search' => $request->string('search')->toString()],
            'roles' => Role::orderBy('name')->pluck('name'),
        ]);
    }

    /**
     * Alterna a situação da conta. inactivated_at está fora do fillable
     * por decisão do 02-03 — gravação só por forceFill em fluxo autorizado.
     * Auditoria explícita: HasAuditoria não cobre atributos fora do fillable.
     */
    public function toggleActivation(ToggleUserActivationRequest $request, User $user): RedirectResponse
    {
        if ($user->isInactive()) {
            $user->forceFill(['inactivated_at' => null])->save();

            app(AuditService::class)->log(
                'usuarios',
                'usuario-reativado',
                "Conta de {$user->email} reativada",
                ['target_user_id' => $user->id],
                $user,
            );
        } else {
            $user->forceFill(['inactivated_at' => now()])->save();

            app(AuditService::class)->log(
                'usuarios',
                'usuario-inativado',
                "Conta de {$user->email} inativada",
                ['target_user_id' => $user->id],
                $user,
            );
        }

        return back()->with('status', 'Situação da conta atualizada.');
    }

    /**
     * Vincula um papel ao usuário (um papel por conta — modelo da Fase 1),
     * com auditoria de papel anterior/novo (HU-012 CA-02).
     */
    public function updateRole(UpdateUserRoleRequest $request, User $user): RedirectResponse
    {
        $previous = $user->getRoleNames()->all();

        $user->syncRoles([$request->validated('role')]);

        app(AuditService::class)->log(
            'usuarios',
            'papel-alterado',
            "Papel de {$user->email} alterado",
            [
                'target_user_id' => $user->id,
                'papel_anterior' => $previous,
                'papel_novo' => $request->validated('role'),
            ],
            $user,
        );

        return back()->with('status', 'Papel atualizado com sucesso.');
    }
}

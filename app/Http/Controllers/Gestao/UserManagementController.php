<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\ToggleUserActivationRequest;
use App\Http\Requests\Gestao\UpdateUserRoleRequest;
use App\Models\User;
use App\Services\Relatorios\Export\ReportExporter;
use App\Services\Relatorios\Export\Sources\UsuariosReportSource;
use App\Services\Relatorios\ReportFilters;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class UserManagementController extends Controller
{
    private const TABS = ['gestao', 'portal'];

    /**
     * Listagem com busca por nome ou e-mail (HU-012 CA-01), separada em
     * abas: equipe SEDUR (papéis com a permissão acessar-gestao, inclusive
     * perfis customizados da HU-013) e usuários do portal (os demais).
     * CPF nunca exposto em claro (LGPD) — apenas os dígitos verificadores.
     */
    public function index(Request $request): Response|HttpResponse
    {
        // HU-131/RN-009: com ?formato=, exporta o conjunto filtrado (aba + busca)
        // pelo contrato único — sem rota nova. RN-007: o CPF sai mascarado; o gate
        // de PII NÃO é setado (não há permissão de PII no projeto — pendência DPO),
        // então o número completo nunca é emitido.
        if (in_array($request->string('formato')->lower()->toString(), ['csv', 'xlsx', 'pdf'], true)) {
            return app(ReportExporter::class)->export(
                app(UsuariosReportSource::class),
                ReportFilters::fromArray($request->only(['search', 'tab'])),
                $request->string('formato')->lower()->toString(),
                $request->user(),
            );
        }

        $tab = $request->string('tab')->toString();

        if (! in_array($tab, self::TABS, true)) {
            $tab = 'gestao';
        }

        $gestaoRoleIds = Role::query()
            ->whereHas('permissions', fn ($query) => $query->where('name', 'acessar-gestao'))
            ->pluck('id');

        $scopeTab = function ($query, string $which) use ($gestaoRoleIds) {
            return $which === 'gestao'
                ? $query->whereHas('roles', fn ($inner) => $inner->whereIn('roles.id', $gestaoRoleIds))
                : $query->whereDoesntHave('roles', fn ($inner) => $inner->whereIn('roles.id', $gestaoRoleIds));
        };

        $users = $scopeTab(User::query(), $tab)
            ->with('roles:id,name')
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request) {
                $term = (string) $request->string('search')->trim();

                // whereLike sem case: LIKE do PostgreSQL é case-sensitive.
                $query->where(fn ($inner) => $inner
                    ->whereLike('name', "%{$term}%", caseSensitive: false)
                    ->orWhereLike('email', "%{$term}%", caseSensitive: false));
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
            'filters' => [
                'search' => $request->string('search')->toString(),
                'tab' => $tab,
            ],
            'counts' => [
                'gestao' => $scopeTab(User::query(), 'gestao')->count(),
                'portal' => $scopeTab(User::query(), 'portal')->count(),
            ],
            'roles' => Role::orderBy('name')->pluck('name'),
        ]);
    }

    /**
     * Alterna a situação da conta. inactivated_at está fora do fillable
     * por decisão do 02-03 — gravação só por forceFill em fluxo autorizado.
     * Auditoria explícita: HasAuditoria não cobre atributos fora do fillable.
     * personalData: gestão da conta de um terceiro = acesso a dado pessoal
     * (LGPD HU-102), medido no painel. Marcação ADITIVA à auditoria existente.
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
                personalData: true,
            );
        } else {
            $user->forceFill(['inactivated_at' => now()])->save();

            app(AuditService::class)->log(
                'usuarios',
                'usuario-inativado',
                "Conta de {$user->email} inativada",
                ['target_user_id' => $user->id],
                $user,
                personalData: true,
            );
        }

        return back()->with('status', 'Situação da conta atualizada.');
    }

    /**
     * Vincula um papel ao usuário (um papel por conta — modelo da Fase 1),
     * com auditoria de papel anterior/novo (HU-012 CA-02). personalData:
     * gestão da conta de um terceiro = acesso a dado pessoal (LGPD HU-102),
     * medido no painel. Marcação ADITIVA à auditoria existente.
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
            personalData: true,
        );

        return back()->with('status', 'Papel atualizado com sucesso.');
    }
}

<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\AccessRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\RejectAccessRequestRequest;
use App\Http\Requests\Gestao\StoreAccessRequestRequest;
use App\Http\Requests\Gestao\UpdateUserRoleRequest;
use App\Models\AccessRequest;
use App\Support\Audit\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Primeiro acesso AD: o servidor sem papel pede um perfil e o administrador
 * decide em Gestão > Usuários (aprova com o perfil pedido ou outro, ou recusa).
 */
class SolicitacaoAcessoController extends Controller
{
    public function store(StoreAccessRequestRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->roles()->exists()) {
            return redirect()->route('gestao.dashboard');
        }

        if (AccessRequest::query()->pendentes()->where('user_id', $user->id)->exists()) {
            return back()->with('error', 'Você já tem uma solicitação de acesso aguardando decisão.');
        }

        $role = Role::findByName($request->validated('role'), 'web');

        $accessRequest = AccessRequest::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'justificativa' => $request->validated('justificativa'),
            'status' => AccessRequestStatus::Pendente,
        ]);

        app(AuditService::class)->log(
            'usuarios',
            'solicitacao-acesso-criada',
            "Solicitação de acesso de {$user->email}",
            ['perfil_solicitado' => $role->name],
            $accessRequest,
        );

        return back()->with('status', 'Solicitação enviada. Um administrador vai analisar o pedido.');
    }

    public function approve(UpdateUserRoleRequest $request, AccessRequest $accessRequest): RedirectResponse
    {
        if ($accessRequest->status !== AccessRequestStatus::Pendente) {
            return back()->with('error', 'Esta solicitação já foi decidida.');
        }

        $user = $accessRequest->user;
        $papel = $request->validated('role');

        DB::transaction(function () use ($accessRequest, $user, $papel, $request): void {
            $user->syncRoles([$papel]);

            $accessRequest->update([
                'status' => AccessRequestStatus::Aprovada,
                'decided_by_user_id' => $request->user()->id,
                'decided_at' => now(),
            ]);
        });

        app(AuditService::class)->log(
            'usuarios',
            'solicitacao-acesso-aprovada',
            "Solicitação de acesso de {$user->email} aprovada",
            [
                'target_user_id' => $user->id,
                'perfil_solicitado' => $accessRequest->role->name,
                'perfil_concedido' => $papel,
            ],
            $user,
            personalData: true,
        );

        return back()->with('status', 'Solicitação aprovada e papel atribuído.');
    }

    public function reject(RejectAccessRequestRequest $request, AccessRequest $accessRequest): RedirectResponse
    {
        if ($accessRequest->status !== AccessRequestStatus::Pendente) {
            return back()->with('error', 'Esta solicitação já foi decidida.');
        }

        $accessRequest->update([
            'status' => AccessRequestStatus::Recusada,
            'decided_by_user_id' => $request->user()->id,
            'decided_at' => now(),
            'decision_note' => $request->validated('motivo'),
        ]);

        app(AuditService::class)->log(
            'usuarios',
            'solicitacao-acesso-recusada',
            "Solicitação de acesso de {$accessRequest->user->email} recusada",
            [
                'target_user_id' => $accessRequest->user_id,
                'perfil_solicitado' => $accessRequest->role->name,
                'motivo' => $request->validated('motivo'),
            ],
            $accessRequest->user,
            personalData: true,
        );

        return back()->with('status', 'Solicitação recusada.');
    }
}

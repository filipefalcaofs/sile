<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Models\Cidadao;
use App\Support\Audit\AuditService;
use Illuminate\Http\RedirectResponse;

class CidadaoManagementController extends Controller
{
    /**
     * Alterna a situação da conta do cidadão. A autorização é o middleware
     * permission:manter-usuarios da rota. Auditoria explícita: `inactivated_at`
     * fica fora do fillable. Acesso a dado pessoal de terceiro (LGPD HU-102).
     */
    public function toggleActivation(Cidadao $cidadao): RedirectResponse
    {
        $reativando = $cidadao->isInactive();

        $cidadao->forceFill(['inactivated_at' => $reativando ? null : now()])->save();

        app(AuditService::class)->log(
            'usuarios',
            $reativando ? 'cidadao-reativado' : 'cidadao-inativado',
            'Conta de '.$cidadao->email.($reativando ? ' reativada' : ' inativada'),
            ['target_cidadao_id' => $cidadao->id],
            $cidadao,
            personalData: true,
        );

        return back()->with('status', 'Situação da conta atualizada.');
    }
}

<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\EndCompanyLinkRequest;
use App\Models\Company;
use App\Support\Audit\AuditService;
use App\Support\Representation\CurrentRepresentation;
use Illuminate\Http\RedirectResponse;

/**
 * Encerramento do PRÓPRIO vínculo do usuário com a empresa (HU-028). Nunca
 * delete físico: o vínculo recebe ended_at/ended_reason e o histórico é
 * preservado. A proteção do último responsável ativo está no FormRequest
 * (after, [02-05]). O 403 do não vinculado é auditado globalmente (CA-04).
 */
class CompanyLinkController extends Controller
{
    public function destroy(EndCompanyLinkRequest $request, Company $company): RedirectResponse
    {
        $effectiveUser = app(CurrentRepresentation::class)->grantor() ?? $request->user();

        $link = $company->activeLinks()
            ->where('user_id', $effectiveUser->id)
            ->firstOrFail();

        $reason = $request->validated('ended_reason');

        $link->update([
            'ended_at' => now(),
            'ended_reason' => $reason,
        ]);

        // Evento de negócio explícito (o HasAuditoria do CompanyUser ainda
        // gera o 'updated' técnico; este é o registro do encerramento, [02-05]).
        app(AuditService::class)->log(
            'empresas',
            'encerramento-vinculo',
            'Vínculo com a empresa encerrado pelo usuário',
            [
                'empresa_id' => $company->id,
                'vinculo_id' => $link->id,
                'motivo' => $reason,
            ],
            $company,
        );

        return redirect()
            ->route('portal.empresas.index')
            ->with('status', 'Vínculo com a empresa encerrado.');
    }
}

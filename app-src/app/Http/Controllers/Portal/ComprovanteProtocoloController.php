<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ViabilityRequest;
use App\Services\Solicitacao\ComprovanteProtocoloService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Download do comprovante de protocolo em PDF (documento formal do cidadão).
 * Controller FINO: a regra (só protocolada), a geração real do PDF e a
 * auditoria vivem no ComprovanteProtocoloService. Autorização pela
 * ViabilityRequestPolicy::view — só o dono/representado baixa (LGPD); o 403
 * de terceiro é auditado no ponto único. Solicitação sem protocolo → 422.
 */
class ComprovanteProtocoloController extends Controller
{
    public function __construct(private readonly ComprovanteProtocoloService $comprovante) {}

    public function show(Request $request, ViabilityRequest $solicitacao): Response
    {
        Gate::authorize('view', $solicitacao);

        try {
            $pdf = $this->comprovante->generate($solicitacao, $request->user());
        } catch (DomainException $e) {
            abort(422, $e->getMessage());
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="comprovante-'.$solicitacao->protocol_number.'.pdf"',
        ]);
    }
}

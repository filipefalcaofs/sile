<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\Painel\PainelCidadaoService;
use App\Support\Representation\CurrentRepresentation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Meu Painel (portal do cidadão): agrega ação necessária, indicadores e
 * solicitações recentes do usuário. O usuário efetivo (representado quando "em
 * nome de") escopa solicitações/empresas; o logado escopa consultas/notificações.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, PainelCidadaoService $painel): Response
    {
        $logado = $request->user();
        $efetivo = app(CurrentRepresentation::class)->grantor() ?? $logado;

        return Inertia::render('portal/dashboard', $painel->build($efetivo, $logado));
    }
}

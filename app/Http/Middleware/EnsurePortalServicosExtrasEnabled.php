<?php

namespace App\Http\Middleware;

use App\Support\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serviços do portal que não são renovar, consultar processo ou responder
 * convite. Todo processo começa na JUCEB; com features.portal_servicos_extras
 * desligada, essas rotas não existem para o cidadão.
 */
class EnsurePortalServicosExtrasEnabled
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Settings::enabled('portal_servicos_extras'), 404);

        return $next($request);
    }
}

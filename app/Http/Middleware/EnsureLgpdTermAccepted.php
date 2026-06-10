<?php

namespace App\Http\Middleware;

use App\Models\LegalTerm;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate de consentimento LGPD (HU-006): exige o aceite da versão vigente do
 * termo antes do uso do sistema. Sem termo publicado, o gate desarma.
 */
class EnsureLgpdTermAccepted
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $term = LegalTerm::current('lgpd');

        if ($term !== null && ! $request->user()->hasAcceptedTerm($term)) {
            return redirect()->route('portal.termo-lgpd.show');
        }

        return $next($request);
    }
}

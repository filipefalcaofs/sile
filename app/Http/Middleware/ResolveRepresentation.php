<?php

namespace App\Http\Middleware;

use App\Models\Procuration;
use App\Support\Representation\CurrentRepresentation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

/**
 * Revalida a procuração da sessão em TODA request do portal (HU-009 CA-01:
 * revogação tem efeito imediato) e alimenta o "em nome de" da auditoria via
 * Context, consumido pelo enriquecimento central (RecordActivityAction).
 */
class ResolveRepresentation
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $procurationId = $request->session()->get('acting_procuration_id');

        if ($procurationId !== null) {
            $procuration = Procuration::query()->with('grantor')->find($procurationId);

            if ($procuration === null
                || $procuration->attorney_user_id !== $request->user()->id
                || ! $procuration->isActive()) {
                $request->session()->forget('acting_procuration_id');
                $request->session()->flash('status', 'A representação foi encerrada porque a procuração não está mais ativa.');
                $this->endRepresentation();
            } else {
                Context::add('acting_for_user_id', $procuration->grantor_user_id);
                app(CurrentRepresentation::class)->set($procuration);
            }
        } else {
            $this->endRepresentation();
        }

        return $next($request);
    }

    /**
     * Zera o estado compartilhado da representação. Necessário porque Context
     * e o serviço scoped sobrevivem entre requests em runtimes sem flush por
     * request (testes na mesma instância, queue workers).
     */
    private function endRepresentation(): void
    {
        Context::forget('acting_for_user_id');
        app(CurrentRepresentation::class)->clear();
    }
}

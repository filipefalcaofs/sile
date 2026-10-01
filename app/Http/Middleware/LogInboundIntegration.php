<?php

namespace App\Http\Middleware;

use App\Services\Integracao\IntegrationCallRecorder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Entrada de terceiros na API municipal. O ack do REGIN sai mesmo se o
 * log falhar — quem engole a exceção de gravação é o recorder.
 */
class LogInboundIntegration
{
    public function __construct(private IntegrationCallRecorder $recorder) {}

    public function handle(Request $request, Closure $next): Response
    {
        $inicio = hrtime(true);

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->recorder->entrada($request, null, $inicio, $exception);

            throw $exception;
        }

        $this->recorder->entrada($request, $response, $inicio);

        return $response;
    }
}

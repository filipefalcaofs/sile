<?php

namespace App\Support\Auth;

use Illuminate\Contracts\Routing\Registrar;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Resolve o destino pós-login do console sem redirecionar para rotas que
 * responderiam 404 (ex.: processo apagado ainda guardado em url.intended).
 * Valida caminho interno /gestao, resolve bindings de rota sem despachar o
 * controller — evita efeitos colaterais (auditoria) de um GET simulado.
 */
class GestaoIntendedRedirect
{
    public function __construct(private Registrar $router) {}

    public function resolveTarget(Request $request, string $fallback): string
    {
        $intended = $request->session()->pull('url.intended', $fallback);

        if ($this->isAccessible($request, (string) $intended)) {
            return (string) $intended;
        }

        return $fallback;
    }

    private function isAccessible(Request $request, string $target): bool
    {
        $path = $this->normalizeToPath($request, $target);

        if ($path === null) {
            return false;
        }

        if ($path === '/gestao/login' || str_starts_with($path, '/gestao/forgot-password')) {
            return false;
        }

        if (! str_starts_with($path, '/gestao')) {
            return false;
        }

        try {
            $probe = Request::create($path, 'GET');
            $route = $this->router->getRoutes()->match($probe);
            $probe->setRouteResolver(fn () => $route);

            $this->router->substituteBindings($route);
            $this->router->substituteImplicitBindings($route);
        } catch (ModelNotFoundException|NotFoundHttpException) {
            return false;
        }

        return true;
    }

    private function normalizeToPath(Request $request, string $target): ?string
    {
        if ($target === '') {
            return null;
        }

        if (str_starts_with($target, '/')) {
            return $target;
        }

        $parts = parse_url($target);

        if ($parts === false) {
            return null;
        }

        $host = $parts['host'] ?? null;

        if ($host !== null && $host !== $request->getHost()) {
            return null;
        }

        $port = $parts['port'] ?? null;

        if ($port !== null && (int) $port !== $request->getPort()) {
            return null;
        }

        $path = $parts['path'] ?? '/';

        if (isset($parts['query']) && $parts['query'] !== '') {
            $path .= '?'.$parts['query'];
        }

        return $path;
    }
}

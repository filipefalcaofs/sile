<?php

use App\Http\Middleware\EnsureLgpdTermAccepted;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Support\Audit\AuditService;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            SecurityHeaders::class,
            EnsureUserIsActive::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'lgpd.accepted' => EnsureLgpdTermAccepted::class,
        ]);

        // Logins separados por contexto: retaguarda usa /gestao/login,
        // portal público usa /portal/login (Fortify).
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('gestao', 'gestao/*')
                ? route('gestao.login')
                : route('login'),
        );

        // Autenticado em rota guest vai para o próprio painel, por perfil.
        $middleware->redirectUsersTo(
            fn (Request $request) => $request->user()?->can('acessar-gestao')
                ? route('gestao.dashboard')
                : route('portal.dashboard'),
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Endpoints JSON do portal (ex.: consulta de CNPJ — HU-021) precisam de
        // respostas JSON para erros (401/422/404), não redirect. api/* mantém o
        // comportamento existente; o portal só renderiza JSON quando o cliente
        // explicitamente o pede (Accept: application/json / XHR).
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
                || ($request->is('portal/empresas/consultar-cnpj') && $request->expectsJson()),
        );

        // CA-04 transversal: todo 403 de autorização é auditado num ponto único.
        // Callbacks de render (não report): AuthorizationException está no
        // dontReport interno do framework. Retornar null mantém o 403 padrão.
        // O Handler converte AuthorizationException em AccessDeniedHttpException
        // (prepareException) ANTES dos render callbacks — por isso o tipo aqui
        // é a exceção preparada, com a original em getPrevious().
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            app(AuditService::class)->logBlocked('seguranca', 'Tentativa de acesso sem permissão', [
                'rota' => $request->path(),
                'metodo' => $request->method(),
            ]);

            return null;
        });

        // Separado de propósito: UnauthorizedException do spatie estende
        // HttpException, não AuthorizationException.
        $exceptions->render(function (UnauthorizedException $e, Request $request) {
            app(AuditService::class)->logBlocked('seguranca', 'Tentativa de acesso sem permissão', [
                'rota' => $request->path(),
                'metodo' => $request->method(),
            ]);

            return null;
        });
    })->create();

<?php

use App\Http\Middleware\EnsureLgpdTermAccepted;
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
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'lgpd.accepted' => EnsureLgpdTermAccepted::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
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

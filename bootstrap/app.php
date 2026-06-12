<?php

use App\Http\Middleware\EnsureLgpdTermAccepted;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RedirectGestaoIfAuthenticated;
use App\Http\Middleware\SecurityHeaders;
use App\Support\Audit\AuditService;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
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
            'gestao.guest' => RedirectGestaoIfAuthenticated::class,
        ]);

        // Logins separados por contexto: retaguarda usa /gestao/login,
        // portal público usa /portal/login (Fortify).
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('gestao', 'gestao/*')
                ? route('gestao.login')
                : route('login'),
        );

        // Autenticado no portal em rota guest volta ao painel do cidadão.
        // (As rotas guest da gestão usam o middleware gestao.guest próprio.)
        $middleware->redirectUsersTo(fn () => route('portal.dashboard'));
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

        // Link assinado de verificação de e-mail inválido/expirado: em vez do
        // 403 cru, volta ao aviso de verificação, onde o reenvio está a um
        // clique. Outras rotas assinadas mantêm o 403 padrão.
        $exceptions->render(function (InvalidSignatureException $e, Request $request) {
            if ($request->routeIs('verification.verify') && $request->user() !== null) {
                return redirect()
                    ->route('verification.notice')
                    ->with('error', __('Link de confirmação inválido ou expirado. Reenvie o e-mail e tente novamente.'));
            }

            return null;
        });

        // Sessão/CSRF expirado (419) em navegação web volta à página anterior
        // com aviso amigável (padrão recomendado pelo Inertia) em vez da
        // página de erro crua. JSON (api/*) mantém o status original.
        $exceptions->respond(function (SymfonyResponse $response, Throwable $e, Request $request) {
            if ($response->getStatusCode() === 419 && ! $request->expectsJson()) {
                return back()->with('error', __('Sua sessão expirou. Tente novamente.'));
            }

            return $response;
        });
    })->create();

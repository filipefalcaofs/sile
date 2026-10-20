<?php

use App\Http\Middleware\ConfigureEnvironmentSession;
use App\Http\Middleware\EnsureConsultaViabilidadeEnabled;
use App\Http\Middleware\EnsureLgpdTermAccepted;
use App\Http\Middleware\EnsurePortalServicosEnabled;
use App\Http\Middleware\EnsurePortalSolicitacaoEnabled;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RedirectGestaoIfAuthenticated;
use App\Http\Middleware\SecurityHeaders;
use App\Support\Audit\AuditService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Define cookie/path de sessão por ambiente (console x portal) ANTES do
        // roteamento — base do isolamento de autenticação. Precisa ser GLOBAL:
        // o Router resolve o controller (e o guard injetado do Fortify) na coleta
        // de middleware, antes da pilha web, fixando o nome do cookie cedo.
        $middleware->prepend(ConfigureEnvironmentSession::class);

        // O HTTPS termina no proxy da prefeitura. Sem isso os redirects saem
        // em http:// e o navegador esconde o header x-inertia da resposta.
        // X-Forwarded-For fica de fora: o IP do cliente não vem do header.
        $middleware->trustProxies(
            at: '*',
            headers: SymfonyRequest::HEADER_X_FORWARDED_PROTO
                | SymfonyRequest::HEADER_X_FORWARDED_HOST
                | SymfonyRequest::HEADER_X_FORWARDED_PORT,
        );

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
            'portal.solicitacao' => EnsurePortalSolicitacaoEnabled::class,
            'portal.servicos' => EnsurePortalServicosEnabled::class,
            'consulta.viabilidade' => EnsureConsultaViabilidadeEnabled::class,
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

        // JUCEB/REGIN não envia token CSRF do Laravel.
        $middleware->validateCsrfTokens(except: [
            'api_integracao/recebe',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Endpoints JSON do portal (ex.: consulta de CNPJ — HU-021) precisam de
        // respostas JSON para erros (401/422/404), não redirect. api/* mantém o
        // comportamento existente; o portal só renderiza JSON quando o cliente
        // explicitamente o pede (Accept: application/json / XHR). A ficha de
        // análise (gestao/processos/*) idem — sem isso o 422 vira a página
        // genérica "Oops" em vez de erro inline (relatório SEDUR 21/09, item 07).
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
                || ($request->is('portal/empresas/consultar-cnpj') && $request->expectsJson())
                || ($request->is('portal/viabilidade/*') && $request->expectsJson())
                || ($request->is('gestao/territorio/*') && $request->expectsJson())
                || ($request->is('gestao/contingencia/consultar-*') && $request->expectsJson())
                || ($request->is('gestao/processos/*') && $request->expectsJson())
                // Login assíncrono do console (cena de alinhamento): a falha de
                // credencial precisa voltar 422 em JSON, não redirect, para o
                // front animar a recusa antes de mostrar o erro.
                || ($request->is('gestao/login') && $request->expectsJson()),
        );

        // CA-04 transversal: todo 403 de autorização é auditado num ponto único.
        // Callbacks de render (não report): AuthorizationException está no
        // dontReport interno do framework. Retornar null mantém o 403 padrão.
        // O Handler converte AuthorizationException em AccessDeniedHttpException
        // (prepareException) ANTES dos render callbacks — por isso o tipo aqui
        // é a exceção preparada, com a original em getPrevious().
        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if (! $request->expectsJson() || ! $exception->getPrevious() instanceof ModelNotFoundException) {
                return null;
            }

            return response()->json(['message' => 'Recurso não encontrado.'], 404);
        });

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

        // Erro técnico no recebimento REGIN: o guia só define 3 e 5. Não há
        // código de negócio para falha de banco, então o HTTP continua 500
        // (o REGIN reenvia) e o corpo sai em texto puro, sem HTML, SQL ou
        // stack. 4xx (throttle, protocolo ausente) seguem o tratamento deles.
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api_integracao/recebe')) {
                return null;
            }

            if ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() < 500) {
                return null;
            }

            Log::error('regin.recebe', [
                'correlacao' => $request->headers->get('X-Request-Id'),
                'protocolo' => $request->input('protocolo'),
                'cod_funcao' => $request->input('codFuncao'),
                'servico' => $request->input('servico'),
                'etapa' => 'recebe',
                'excecao' => $exception::class,
            ]);

            return response('erro interno', 500, ['Content-Type' => 'text/plain; charset=UTF-8']);
        });

        // Sessão/CSRF expirado (419) em navegação web volta à página anterior
        // com aviso amigável (padrão recomendado pelo Inertia) em vez da
        // página de erro crua. JSON (api/*) mantém o status original. O mesmo
        // vale para a recusa de regra de negócio (422 fora de validação) numa
        // visita Inertia: volta com a mensagem em vez da página "Oops".
        $exceptions->respond(function (SymfonyResponse $response, Throwable $e, Request $request) {
            if ($response->getStatusCode() === 419 && ! $request->expectsJson()) {
                return back()->with('error', __('Sua sessão expirou. Tente novamente.'));
            }

            if ($response->getStatusCode() === 422 && $request->header('X-Inertia') && ! $request->expectsJson()) {
                $mensagem = $e instanceof HttpExceptionInterface && $e->getMessage() !== ''
                    ? $e->getMessage()
                    : __('Não foi possível concluir a operação.');

                return back()->with('error', $mensagem);
            }

            return $response;
        });
    })->create();

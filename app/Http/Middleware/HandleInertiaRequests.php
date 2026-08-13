<?php

namespace App\Http\Middleware;

use App\Support\Representation\CurrentRepresentation;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()?->only('id', 'name', 'email'),
                'roles' => $request->user()?->getRoleNames() ?? [],
                'permissions' => $request->user()?->getAllPermissions()->pluck('name') ?? [],
            ],
            // Closure: avaliada na serialização da resposta, depois de o
            // ResolveRepresentation (middleware de rota) resolver o estado.
            'actingFor' => fn () => app(CurrentRepresentation::class)->grantor()?->only('id', 'name'),
            // Banner do atendimento presencial assistido (HU-150): preenchido
            // pelo ResolveAssistedAttendance nas rotas do atendimento (console).
            'attendingFor' => fn () => app(CurrentRepresentation::class)->attendance()?->citizen?->only('id', 'name'),
            // Badge do sininho (central in-app, HU-090): contagem REAL de
            // notificações não-lidas do canal database nativo do usuário
            // autenticado. Closure: avaliada na serialização (count barato) e
            // só com usuário logado — guest/visita pública resolve para 0.
            'notificacoes' => [
                'nao_lidas' => fn (): int => $request->user()?->unreadNotifications()->count() ?? 0,
            ],
            'flash' => [
                'status' => $request->session()->get('status'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }
}

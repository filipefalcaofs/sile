<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Central de notificações in-app (HU-090) sobre o canal database NATIVO do
 * Laravel (tabela notifications). Serve os DOIS ambientes com o MESMO User
 * notifiable: o portal (guard web) e a gestão (guard gestao) — o ambiente é
 * derivado da rota para escolher a página Inertia, mas o dado é sempre do
 * `$request->user()` autenticado.
 *
 * Escopo do DONO em toda ação: a leitura e a marcação operam pela relação
 * `notifications()` do próprio usuário, então um usuário NUNCA lê nem marca a
 * notificação de outro (anti-IDOR: findOrFail na relação → 404 fora do escopo).
 * O badge do sininho (contagem de não-lidas) é shared prop em
 * HandleInertiaRequests — aqui ficam a lista paginada e as marcações.
 */
class NotificationCenterController extends Controller
{
    /**
     * Lista as notificações do usuário autenticado (lidas + não-lidas), das mais
     * recentes para as mais antigas, paginadas no servidor. Server-driven: a
     * página Inertia (sininho/listagem) é construída no 11-09.
     */
    public function index(Request $request): Response
    {
        $ambiente = $request->routeIs('gestao.*') ? 'gestao' : 'portal';

        $perPage = (int) Settings::get('ui.notificacoes.per_page', 15);

        $lista = $request->user()
            ->notifications()
            ->paginate($perPage)
            ->through(fn (DatabaseNotification $notification): array => (new NotificationResource($notification))->resolve());

        return Inertia::render("{$ambiente}/notificacoes/index", [
            'lista' => $lista,
        ]);
    }

    /**
     * Marca UMA notificação do próprio usuário como lida. A notificação é
     * resolvida pela relação `notifications()` do usuário autenticado: uma
     * notificação de outro usuário (ou inexistente) cai em 404, sem vazar a
     * existência alheia (anti-IDOR).
     */
    public function markAsRead(Request $request, string $notification): RedirectResponse
    {
        $request->user()
            ->notifications()
            ->findOrFail($notification)
            ->markAsRead();

        return back();
    }

    /**
     * Marca TODAS as não-lidas do usuário autenticado como lidas (zera o badge).
     */
    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }
}

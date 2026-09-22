<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Models\LegalTerm;
use App\Models\LegalTermAcceptance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Aceite do termo LGPD (HU-006) isolado no console (guard gestao): o servidor
 * aceita pelo próprio ambiente, sem ser redirecionado ao portal do cidadão.
 */
class TermoLgpdController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $term = LegalTerm::current('lgpd');

        if ($term === null || $request->user('gestao')->hasAcceptedTerm($term)) {
            return redirect()->route('gestao.dashboard');
        }

        return Inertia::render('gestao/termo-lgpd', [
            'term' => $term->only('id', 'version', 'title', 'content'),
        ]);
    }

    public function accept(Request $request): RedirectResponse
    {
        $request->validate(['accepted' => ['accepted']], [], ['accepted' => 'aceite']);

        $term = LegalTerm::current('lgpd');

        if ($term === null) {
            return redirect()->route('gestao.dashboard');
        }

        LegalTermAcceptance::firstOrCreate(
            [
                'user_id' => $request->user('gestao')->id,
                'legal_term_id' => $term->id,
            ],
            [
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'accepted_at' => now(),
            ],
        );

        return redirect()->route('gestao.dashboard')->with('status', 'Termo aceito com sucesso.');
    }
}

<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\LegalTerm;
use App\Models\LegalTermAcceptance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LgpdTermController extends Controller
{
    /**
     * Exibe a versão vigente do termo para leitura e aceite.
     */
    public function show(Request $request): Response|RedirectResponse
    {
        $term = LegalTerm::current('lgpd');

        if ($term === null || $request->user()->hasAcceptedTerm($term)) {
            return redirect()->intended(route('portal.dashboard'));
        }

        return Inertia::render('portal/termo-lgpd', [
            'term' => $term->only('id', 'version', 'title', 'content'),
        ]);
    }

    /**
     * Registra o consentimento do usuário à versão vigente (CA-01/CA-02).
     */
    public function accept(Request $request): RedirectResponse
    {
        $request->validate(
            ['accepted' => ['accepted']],
            [],
            ['accepted' => 'aceite'],
        );

        $term = LegalTerm::current('lgpd');

        if ($term === null) {
            return redirect()->intended(route('portal.dashboard'));
        }

        LegalTermAcceptance::firstOrCreate(
            [
                'user_id' => $request->user()->id,
                'legal_term_id' => $term->id,
            ],
            [
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'accepted_at' => now(),
            ],
        );

        return redirect()
            ->intended(route('portal.dashboard'))
            ->with('status', 'Termo aceito com sucesso.');
    }
}

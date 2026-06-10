<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProcurationRequest;
use App\Models\Procuration;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProcurationController extends Controller
{
    /**
     * Lista procurações outorgadas e recebidas pelo usuário (HU-008).
     */
    public function index(Request $request): Response
    {
        $userId = $request->user()->id;

        $granted = Procuration::query()
            ->with('attorney')
            ->where('grantor_user_id', $userId)
            ->latest()
            ->get()
            ->map(fn (Procuration $procuration) => [
                'id' => $procuration->id,
                'name' => $procuration->attorney->name,
                'email' => $procuration->attorney->email,
                'starts_at' => $procuration->starts_at->toISOString(),
                'expires_at' => $procuration->expires_at?->toISOString(),
                'revoked_at' => $procuration->revoked_at?->toISOString(),
                'is_active' => $procuration->isActive(),
            ]);

        $received = Procuration::query()
            ->with('grantor')
            ->where('attorney_user_id', $userId)
            ->latest()
            ->get()
            ->map(fn (Procuration $procuration) => [
                'id' => $procuration->id,
                'name' => $procuration->grantor->name,
                'email' => $procuration->grantor->email,
                'starts_at' => $procuration->starts_at->toISOString(),
                'expires_at' => $procuration->expires_at?->toISOString(),
                'revoked_at' => $procuration->revoked_at?->toISOString(),
                'is_active' => $procuration->isActive(),
            ]);

        return Inertia::render('portal/procuracoes/index', [
            'granted' => $granted,
            'received' => $received,
        ]);
    }

    /**
     * Vincula procurador localizado por e-mail de conta existente (CA-01).
     */
    public function store(StoreProcurationRequest $request): RedirectResponse
    {
        $attorney = User::query()->where('email', $request->validated('attorney_email'))->firstOrFail();

        Procuration::create([
            'grantor_user_id' => $request->user()->id,
            'attorney_user_id' => $attorney->id,
            'starts_at' => now(),
            'expires_at' => $request->validated('expires_at'),
        ]);

        return back()->with('status', 'Procurador vinculado com sucesso.');
    }

    /**
     * Revoga a procuração com efeito imediato (HU-009 CA-01).
     */
    public function destroy(Request $request, Procuration $procuration): RedirectResponse
    {
        Gate::authorize('delete', $procuration);

        $procuration->update([
            'revoked_at' => now(),
            'revoked_by_user_id' => $request->user()->id,
        ]);

        return back()->with('status', 'Procuração revogada.');
    }
}

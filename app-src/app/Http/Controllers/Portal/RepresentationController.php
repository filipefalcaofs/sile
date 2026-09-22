<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Procuration;
use App\Support\Audit\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Validation\ValidationException;

class RepresentationController extends Controller
{
    /**
     * Inicia a atuação "em nome de" a partir de uma procuração ativa
     * recebida pelo usuário (HU-008 CA-01/CA-02).
     */
    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $validated = $request->validate(['procuration_id' => ['required', 'integer']]);

        $procuration = Procuration::query()
            ->active()
            ->with('grantor')
            ->where('id', $validated['procuration_id'])
            ->where('attorney_user_id', $request->user()->id)
            ->first();

        if ($procuration === null) {
            throw ValidationException::withMessages([
                'procuration_id' => 'Esta procuração não está ativa.',
            ]);
        }

        $request->session()->put('acting_procuration_id', $procuration->id);
        Context::add('acting_for_user_id', $procuration->grantor_user_id);

        $audit->log('procuracao', 'representacao-iniciada', 'Início de atuação em nome do outorgante', [
            'procuration_id' => $procuration->id,
        ]);

        return redirect()
            ->route('portal.dashboard')
            ->with('status', "Você está atuando em nome de {$procuration->grantor->name}.");
    }

    /**
     * Encerra a atuação "em nome de" na sessão corrente (HU-009).
     */
    public function destroy(Request $request, AuditService $audit): RedirectResponse
    {
        $request->session()->forget('acting_procuration_id');

        $audit->log('procuracao', 'representacao-encerrada', 'Fim da atuação em nome do outorgante');

        return back()->with('status', 'Representação encerrada.');
    }
}

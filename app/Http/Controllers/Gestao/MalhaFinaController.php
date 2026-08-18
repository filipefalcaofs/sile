<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\EncaminharMalhaFinaRequest;
use App\Models\ViabilityRequest;
use App\Services\Analise\MalhaFinaException;
use App\Services\Analise\MalhaFinaService;
use Illuminate\Http\RedirectResponse;

/**
 * Encaminhamento à malha fina (HU-136) — single e lote. Controller FINO: valida a
 * estrutura (EncaminharMalhaFinaRequest) e delega ao MalhaFinaService::encaminharLote
 * (10-12); single é um lote de um. O serviço liga in_fine_mesh + grava
 * fine_mesh_referrals e audita por processo SEM transicionar o status (ortogonal —
 * RN-001), isolando falhas no lote. Gated por encaminhar-malha-fina (403 auditado
 * — CA-04); motivo só de espaços é recusado pelo serviço (MalhaFinaException → 422).
 */
class MalhaFinaController extends Controller
{
    public function __construct(private MalhaFinaService $malhaFina) {}

    public function store(EncaminharMalhaFinaRequest $request): RedirectResponse
    {
        $processos = ViabilityRequest::query()
            ->whereIn('id', $request->input('request_ids'))
            ->get();

        try {
            $resumo = $this->malhaFina->encaminharLote($processos, $request->user(), $request->validated('motivo'));
        } catch (MalhaFinaException $e) {
            abort(422, $e->getMessage());
        }

        $response = back()->with('status', "{$resumo['ok']} processo(s) encaminhado(s) à malha fina.");

        if ($resumo['falhas'] !== []) {
            $response->with('warning', count($resumo['falhas']).' processo(s) não encaminhado(s) à malha fina.');
        }

        return $response;
    }
}

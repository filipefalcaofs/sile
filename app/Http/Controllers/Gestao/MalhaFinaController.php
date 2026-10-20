<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\ConclusionOutcome;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\ConcluirMalhaFinaRequest;
use App\Http\Requests\Gestao\EncaminharMalhaFinaRequest;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Analise\ConclusaoAnaliseService;
use App\Services\Analise\MalhaFinaException;
use App\Services\Analise\MalhaFinaService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * Encaminhamento à malha fina (HU-127) — single e lote. Controller FINO: valida a
 * estrutura (EncaminharMalhaFinaRequest) e delega ao MalhaFinaService::encaminharLote
 * (10-12); single é um lote de um. O serviço liga in_fine_mesh + grava
 * fine_mesh_referrals e audita por processo SEM transicionar o status (ortogonal —
 * RN-001), isolando falhas no lote. Gated por encaminhar-malha-fina (403 auditado
 * — CA-04); motivo só de espaços é recusado pelo serviço (MalhaFinaException → 422).
 */
class MalhaFinaController extends Controller
{
    public function __construct(
        private MalhaFinaService $malhaFina,
        private ConclusaoAnaliseService $conclusao,
    ) {}

    public function store(EncaminharMalhaFinaRequest $request): RedirectResponse
    {
        $processos = ViabilityRequest::query()
            ->whereIn('id', $request->input('request_ids'))
            ->get();

        try {
            $setorId = $request->validated('sector_id');
            $resumo = $this->malhaFina->encaminharLote(
                $processos,
                $request->user(),
                $request->validated('motivo'),
                $setorId !== null ? (int) $setorId : null,
            );
        } catch (MalhaFinaException $e) {
            abort(422, $e->getMessage());
        }

        $response = back()->with('status', "{$resumo['ok']} processo(s) encaminhado(s) à malha fina.");

        if ($resumo['falhas'] !== []) {
            $response->with('warning', count($resumo['falhas']).' processo(s) não encaminhado(s) à malha fina.');
        }

        return $response;
    }

    /**
     * Conclusão pela Caixa de Malha Fina: aplica o mesmo resultado da
     * conclusão de análise e só então baixa os encaminhamentos abertos.
     * Processo sem encaminhamento aberto volta com aviso, sem mudar o status.
     * Gated por analisar-malha-fina na rota.
     */
    public function concluir(ConcluirMalhaFinaRequest $request, ViabilityRequest $viabilityRequest): RedirectResponse
    {
        $ator = $request->user();

        abort_if(! $ator instanceof User, 401, 'É preciso estar autenticado para concluir a malha fina.');

        $aberto = $viabilityRequest->fineMeshReferrals()->whereNull('resolved_at')->exists();

        if (! $aberto) {
            return back()->with('warning', 'O processo não tinha encaminhamento aberto na malha fina.');
        }

        $resultado = ConclusionOutcome::from($request->validated('resultado'));

        try {
            $this->conclusao->concluir(
                $viabilityRequest,
                $ator,
                $resultado,
                $request->validated('observacao'),
                validade: $request->validadeDeferimento(),
            );
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['conclusao' => $e->getMessage()]);
        }

        $this->malhaFina->resolverAbertos(
            $viabilityRequest,
            $ator,
            $request->validated('observacao'),
            $resultado,
        );

        return back()->with('status', 'Análise da malha fina concluída: '.$resultado->label().'.');
    }
}

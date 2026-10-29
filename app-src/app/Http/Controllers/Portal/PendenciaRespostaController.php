<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\ResponderPendenciaRequest;
use App\Models\AnalysisPendency;
use App\Models\AnalysisPendencyAttachment;
use App\Models\ViabilityRequest;
use App\Services\Analise\PendenciaInvalidaException;
use App\Services\Analise\PendenciaService;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Resposta da pendência pelo portal (HU-084 — "Minhas solicitações"). Escopo do
 * dono/representado reusando a ViabilityRequestPolicy::view (mecanismo "em nome
 * de" da Fase 1): só o requerente acessa/responde a própria pendência — terceiro
 * recebe 403 auditado no ponto único (bootstrap/app.php). O vínculo da pendência
 * com a solicitação é validado (anti-IDOR). A resposta é delegada ao
 * PendenciaService (que reabre a análise e audita); o bloqueio de estado
 * (PendenciaInvalidaException) vira flash.error — aviso comunicado, nunca
 * silencioso (CA-03).
 */
class PendenciaRespostaController extends Controller
{
    public function __construct(private PendenciaService $pendencias) {}

    public function show(Request $request, ViabilityRequest $solicitacao): Response
    {
        Gate::authorize('view', $solicitacao);

        $pendencias = $solicitacao->pendencies()
            ->whereIn('status', ['aberta'])
            ->latest()
            ->get()
            ->map(fn (AnalysisPendency $pendency): array => [
                'id' => $pendency->id,
                'description' => $pendency->description,
                'status' => $pendency->status->value,
                'status_label' => $pendency->status->label(),
                'due_at' => $pendency->due_at?->toIso8601String(),
                'created_at' => $pendency->created_at?->toIso8601String(),
            ])
            ->all();

        return Inertia::render('portal/solicitacoes/pendencias', [
            'solicitacao' => [
                'id' => $solicitacao->id,
                'protocol_number' => $solicitacao->protocol_number,
                'status' => [
                    'value' => $solicitacao->status->value,
                    'label' => $solicitacao->status->label(),
                    'public_label' => $solicitacao->status->publicLabel(),
                ],
            ],
            'pendencias' => $pendencias,
            'anexo_max_mb' => (int) Settings::get('solicitacao.anexos.max_mb', config('viabiliza.solicitacao.anexos.max_mb', 10)),
        ]);
    }

    public function responder(
        ResponderPendenciaRequest $request,
        ViabilityRequest $solicitacao,
        AnalysisPendency $pendency,
    ): RedirectResponse {
        Gate::authorize('view', $solicitacao);

        abort_if($pendency->viability_request_id !== $solicitacao->id, 404);

        try {
            $this->pendencias->responder($pendency, $request->validated('response'), $request->file('attachment'));
        } catch (PendenciaInvalidaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('portal.solicitacoes.show', $solicitacao)
            ->with('status', 'Resposta enviada. Sua solicitação voltou para análise.');
    }

    public function anexo(ViabilityRequest $solicitacao, AnalysisPendency $pendency, AnalysisPendencyAttachment $anexo): StreamedResponse
    {
        Gate::authorize('view', $solicitacao);

        abort_unless($pendency->viability_request_id === $solicitacao->id, 404);
        abort_unless($anexo->analysis_pendency_id === $pendency->id, 404);

        return response()->streamDownload(function () use ($anexo): void {
            $stream = Storage::disk($anexo->disk)->readStream($anexo->path);
            fpassthru($stream);
            fclose($stream);
        }, $anexo->original_name, ['Content-Type' => $anexo->mime_type]);
    }
}

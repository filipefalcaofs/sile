<?php

namespace App\Http\Controllers;

use App\Http\Resources\CommunicationResource;
use App\Models\Communication;
use App\Models\ViabilityRequest;
use App\Support\Audit\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Histórico unificado de comunicações por processo (HU-096). A fonte de verdade
 * é ÚNICA — o ledger `communications` (11-01) — em vez de unir EmailLog
 * (genérico de conta, sem viability_request_id) e notifications (morph por
 * usuário) de forma frágil. Lista TODOS os canais (email/in_app/whatsapp) e
 * tipos com status HONESTO, ordenados por data.
 *
 * Dois ambientes, escopos distintos (sem permissão nova):
 * - portal (`portal`): só o dono/representado do processo (policy view); o
 *   error_message interno do canal NÃO é exposto (LGPD — diagnóstico fica só na
 *   retaguarda).
 * - gestão (`gestao`): gated por consultar-solicitacoes na rota (REUSO — a
 *   comunicação é parte da solicitação); a retaguarda VÊ o error_message.
 *
 * Toda consulta é AUDITADA (RN-002). Os dois métodos públicos existem por causa
 * dos nomes de binding distintos das rotas ({solicitacao} no portal,
 * {viabilityRequest} na gestão) e do escopo/LGPD distintos; ambos delegam ao
 * mesmo renderizador.
 */
class ComunicacaoHistoricoController extends Controller
{
    public function __construct(private AuditService $audit) {}

    /**
     * Portal: o dono/representado consulta o histórico do próprio processo.
     */
    public function portal(Request $request, ViabilityRequest $solicitacao): Response
    {
        Gate::authorize('view', $solicitacao);

        return $this->render($solicitacao, ambiente: 'portal', comErroInterno: false);
    }

    /**
     * Gestão: a retaguarda (consultar-solicitacoes) consulta o histórico de
     * qualquer processo, com o diagnóstico interno (error_message) visível.
     */
    public function gestao(Request $request, ViabilityRequest $viabilityRequest): Response
    {
        return $this->render($viabilityRequest, ambiente: 'gestao', comErroInterno: true);
    }

    /**
     * Monta a linha do tempo unificada do processo (ledger communications,
     * todos os canais/tipos), audita a consulta e devolve as props do Inertia.
     * A página (sininho/histórico) é construída no 11-09.
     */
    private function render(ViabilityRequest $processo, string $ambiente, bool $comErroInterno): Response
    {
        $comunicacoes = Communication::query()
            ->where('viability_request_id', $processo->id)
            ->with('recipient')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Communication $comunicacao): array => (new CommunicationResource($comunicacao))
                ->withErrorMessage($comErroInterno)
                ->resolve())
            ->all();

        $this->audit->log(
            logName: 'notificacoes',
            event: 'historico-consultado',
            description: "Consulta do histórico de comunicações do processo #{$processo->id}",
            properties: [
                'viability_request_id' => $processo->id,
                'protocol_number' => $processo->protocol_number,
                'origem' => $ambiente,
                'total' => count($comunicacoes),
            ],
            subject: $processo,
        );

        $component = $ambiente === 'gestao'
            ? 'gestao/processos/comunicacoes'
            : 'portal/solicitacoes/comunicacoes';

        return Inertia::render($component, [
            'processo' => [
                'id' => $processo->id,
                'protocol_number' => $processo->protocol_number,
            ],
            'comunicacoes' => $comunicacoes,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ViabilityRequest;
use App\Services\Solicitacao\TimelineSolicitacao;
use App\Support\Audit\AuditService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Consulta PÚBLICA do protocolo por link assinado (HU-069) — SEM login. A rota
 * usa o middleware `signed` (TTL parametrizável, gerado no controller autenticado)
 * + `throttle:consulta-protocolo`. Mostra SÓ o número de protocolo, o status
 * amigável (publicLabel), a timeline pública e o prazo estimado com ressalva —
 * NUNCA dados sensíveis (CPF, CNPJ completo, endereço detalhado) nem anexos
 * (LGPD). Auditada com causer null + IP (RN-002, padrão da consulta pública
 * [07-06], resolvido no AuditService/RecordActivityAction).
 */
class ConsultaProtocoloPublicaController extends Controller
{
    public function __construct(
        private TimelineSolicitacao $timeline,
        private AuditService $audit,
    ) {}

    public function show(ViabilityRequest $solicitacao): Response
    {
        $this->audit->log(
            'solicitacoes',
            'consulta-protocolo-publica',
            "Consulta pública (link assinado) do protocolo da solicitação #{$solicitacao->id}",
            properties: [
                'viability_request_id' => $solicitacao->id,
                'protocol_number' => $solicitacao->protocol_number,
            ],
            subject: $solicitacao,
        );

        return Inertia::render('portal/solicitacoes/protocolo-publico', [
            // Payload MÍNIMO (LGPD): nada de empresa/CNPJ/endereço/CNAEs/anexos.
            'solicitacao' => [
                'protocol_number' => $solicitacao->protocol_number,
                'status' => [
                    'value' => $solicitacao->status->value,
                    'public_label' => $solicitacao->status->publicLabel(),
                ],
                'protocoled_at' => $solicitacao->protocoled_at?->toIso8601String(),
            ],
            'timeline' => $this->timeline->build($solicitacao, publico: true),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Models\ViabilityRequest;
use App\Services\Analise\AnalysisRecordService;
use App\Services\Analise\PrecedentService;
use App\Support\Audit\AuditService;
use Illuminate\Http\JsonResponse;

/**
 * Endpoint de precedentes da ficha de análise (HU-142): serve o painel de apoio à
 * decisão consumindo o PrecedentService (10-06) sobre a revisão vigente —
 * decisões anteriores no mesmo imóvel (CA-01) e estatística do CNAE na zona
 * (CA-02), com degradação honesta sem zona/histórico (CA-03) e sem dados pessoais
 * (LGPD RN-004). Gated por analisar-processos (403 auditado no ponto único) e a
 * consulta é auditada (RN-002). O painel é construído em 10-17.
 */
class PrecedenteController extends Controller
{
    public function __construct(
        private PrecedentService $precedents,
        private AnalysisRecordService $records,
        private AuditService $audit,
    ) {}

    /**
     * Precedentes da revisão vigente do processo: {imovel, cnae_zona}.
     */
    public function show(ViabilityRequest $viabilityRequest): JsonResponse
    {
        $record = $this->records->current($viabilityRequest);

        $payload = $this->precedents->forRecord($record);

        $this->audit->log(
            logName: 'analise',
            event: 'precedentes',
            description: "Consulta de precedentes do processo #{$viabilityRequest->id}",
            properties: [
                'viability_request_id' => $viabilityRequest->id,
                'protocol_number' => $viabilityRequest->protocol_number,
                'revision' => $record->revision,
            ],
            subject: $viabilityRequest,
        );

        return response()->json($payload);
    }
}

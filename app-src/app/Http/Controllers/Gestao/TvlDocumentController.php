<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Models\TvlDocument;
use App\Models\ViabilityRequest;
use App\Services\Analise\TvlPdfService;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * TVL em PDF no backoffice (HU-132). Controller FINO: emite o documento de uma
 * decisão DEFERIDA via TvlPdfService::generate (10-13) — a regra (FA-01), o
 * disco não público e a auditoria vivem no serviço — e devolve o link de
 * download. O download é por URL TEMPORÁRIA ASSINADA (middleware signed, TTL
 * analise.tvl.download.ttl_minutos) servindo o arquivo do disco NÃO público por
 * streaming: nunca uma URL pública, nunca ao cidadão (CA-02). Ambos gated por
 * emitir-tvl (403 auditado — CA-04). Decisão não deferida → 422 (FA-01).
 */
class TvlDocumentController extends Controller
{
    public function __construct(
        private TvlPdfService $tvlPdf,
        private AuditService $audit,
    ) {}

    public function store(Request $request, ViabilityRequest $viabilityRequest): JsonResponse
    {
        $decision = $viabilityRequest->decision;

        abort_if($decision === null, 422, 'O processo ainda não possui decisão para emitir o TVL.');

        try {
            $document = $this->tvlPdf->generate($decision, $request->user());
        } catch (DomainException $e) {
            abort(422, $e->getMessage());
        }

        return response()->json([
            'document' => [
                'id' => $document->id,
                'verification_code' => $document->verification_code,
                'generated_at' => $document->generated_at?->toIso8601String(),
            ],
            'download_url' => $this->downloadUrl($document),
        ]);
    }

    /**
     * Faz o streaming do PDF do disco NÃO público. Só chega aqui com a assinatura
     * válida (middleware signed) e o perfil emitir-tvl — o disco nunca é exposto
     * por URL pública (CA-02/LGPD). O acesso ao documento é auditado (RN-002).
     */
    public function download(TvlDocument $tvlDocument): StreamedResponse
    {
        $this->audit->log(
            'analise',
            'tvl-download',
            "Download do TVL {$tvlDocument->verification_code}",
            [
                'tvl_document_id' => $tvlDocument->id,
                'viability_decision_id' => $tvlDocument->viability_decision_id,
                'verification_code' => $tvlDocument->verification_code,
            ],
            $tvlDocument,
        );

        return Storage::disk($tvlDocument->disk)->download(
            $tvlDocument->path,
            "tvl-{$tvlDocument->verification_code}.pdf",
        );
    }

    /**
     * Link de download por URL temporária assinada (HU-132): TTL parametrizável
     * (analise.tvl.download.ttl_minutos), efeito sem deploy. A assinatura + o gate
     * emitir-tvl + o disco não público garantem que o TVL não vaza por URL pública.
     */
    private function downloadUrl(TvlDocument $document): string
    {
        $ttl = (int) Settings::get(
            'analise.tvl.download.ttl_minutos',
            config('sile.analise.tvl.download.ttl_minutos', 5),
        );

        return URL::temporarySignedRoute(
            'gestao.processos.tvl.download',
            now()->addMinutes($ttl),
            ['tvlDocument' => $document->id],
        );
    }
}

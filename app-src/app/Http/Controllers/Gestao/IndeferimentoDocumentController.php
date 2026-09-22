<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Models\IndeferimentoDocument;
use App\Models\ViabilityRequest;
use App\Services\Analise\IndeferimentoPdfService;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Documento de indeferimento fundamentado em PDF no backoffice — espelho do
 * TvlDocumentController: emite o documento de uma decisão INDEFERIDA via
 * IndeferimentoPdfService::generate e devolve o link de download por URL
 * TEMPORÁRIA ASSINADA (middleware signed, TTL analise.tvl.download.ttl_minutos)
 * servindo o arquivo do disco NÃO público por streaming: nunca URL pública,
 * nunca ao cidadão. Ambos gated por emitir-tvl (403 auditado). Decisão não
 * indeferida (ou inexistente) → 422.
 */
class IndeferimentoDocumentController extends Controller
{
    public function __construct(
        private IndeferimentoPdfService $indeferimentoPdf,
        private AuditService $audit,
    ) {}

    public function store(Request $request, ViabilityRequest $viabilityRequest): JsonResponse
    {
        $decision = $viabilityRequest->decision;

        abort_if($decision === null, 422, 'O processo ainda não possui decisão para emitir o documento de indeferimento.');

        try {
            $document = $this->indeferimentoPdf->generate($decision, $request->user());
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
     * Streaming do PDF do disco NÃO público. Só chega aqui com a assinatura
     * válida (middleware signed) e o perfil emitir-tvl — o disco nunca é
     * exposto por URL pública (LGPD). O acesso ao documento é auditado (RN-002).
     */
    public function download(IndeferimentoDocument $indeferimentoDocument): StreamedResponse
    {
        $this->audit->log(
            'analise',
            'indeferimento-download',
            "Download do documento de indeferimento {$indeferimentoDocument->verification_code}",
            [
                'indeferimento_document_id' => $indeferimentoDocument->id,
                'viability_decision_id' => $indeferimentoDocument->viability_decision_id,
                'verification_code' => $indeferimentoDocument->verification_code,
            ],
            $indeferimentoDocument,
        );

        return Storage::disk($indeferimentoDocument->disk)->download(
            $indeferimentoDocument->path,
            "indeferimento-{$indeferimentoDocument->verification_code}.pdf",
        );
    }

    /**
     * Link de download por URL temporária assinada: TTL parametrizável
     * (analise.tvl.download.ttl_minutos — o mesmo dos documentos de decisão),
     * efeito sem deploy.
     */
    private function downloadUrl(IndeferimentoDocument $document): string
    {
        $ttl = (int) Settings::get(
            'analise.tvl.download.ttl_minutos',
            config('sile.analise.tvl.download.ttl_minutos', 5),
        );

        return URL::temporarySignedRoute(
            'gestao.processos.indeferimento.download',
            now()->addMinutes($ttl),
            ['indeferimentoDocument' => $document->id],
        );
    }
}

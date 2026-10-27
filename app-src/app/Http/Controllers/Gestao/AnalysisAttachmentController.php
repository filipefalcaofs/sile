<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\AnalysisRecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\AnalysisAttachmentRequest;
use App\Models\AnalysisAttachment;
use App\Models\ViabilityRequest;
use App\Services\Analise\AnalysisRecordService;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Documentos que o analista anexa à análise do processo. Anexar e remover
 * valem enquanto a ficha vigente é rascunho do próprio analista; consultar vale
 * a quem analisa. Disk parametrizado (NUNCA público) e download por streaming
 * autenticado (LGPD); tudo auditado (RN-002).
 */
class AnalysisAttachmentController extends Controller
{
    public function __construct(
        private AnalysisRecordService $records,
        private AuditService $audit,
    ) {}

    public function store(AnalysisAttachmentRequest $request, ViabilityRequest $viabilityRequest): RedirectResponse
    {
        $this->garantirEditavel($viabilityRequest, $request);

        $disk = $this->disk();
        $arquivos = $request->hasFile('files') ? array_values($request->file('files')) : [$request->file('file')];

        foreach ($arquivos as $file) {
            // Metadados capturados ANTES do store() (a movimentação do temporário
            // invalida getRealPath/getSize). sha256 REAL do conteúdo.
            $sha256 = hash_file('sha256', $file->getRealPath());
            $originalName = $file->getClientOriginalName();
            $mimeType = $file->getMimeType();
            $size = $file->getSize();

            $anexo = DB::transaction(function () use ($viabilityRequest, $file, $disk, $sha256, $originalName, $mimeType, $size, $request): AnalysisAttachment {
                $path = $file->store('analises/'.$viabilityRequest->id, $disk);

                return $viabilityRequest->analysisAttachments()->create([
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => $originalName,
                    'mime_type' => $mimeType,
                    'size' => $size,
                    'sha256' => $sha256,
                    'uploaded_by_user_id' => $request->user()->id,
                ]);
            });

            $this->audit->log(
                logName: 'analise',
                event: 'anexo-adicionado',
                description: "Anexo adicionado à análise do processo #{$viabilityRequest->id}",
                properties: [
                    'viability_request_id' => $viabilityRequest->id,
                    'anexo_id' => $anexo->id,
                    'sha256' => $sha256,
                ],
                subject: $viabilityRequest,
            );
        }

        return back()->with('status', count($arquivos) === 1 ? 'Anexo enviado com sucesso.' : count($arquivos).' anexos enviados com sucesso.');
    }

    /**
     * Download por STREAMING autenticado a partir do disk gravado — nunca URL pública.
     */
    public function download(Request $request, ViabilityRequest $viabilityRequest, AnalysisAttachment $attachment): StreamedResponse
    {
        // Anti-IDOR: o anexo tem de pertencer ao processo da URL.
        abort_unless($attachment->viability_request_id === $viabilityRequest->id, 404);

        $this->audit->log(
            logName: 'analise',
            event: 'anexo-download',
            description: "Download de anexo da análise do processo #{$viabilityRequest->id}",
            properties: [
                'viability_request_id' => $viabilityRequest->id,
                'anexo_id' => $attachment->id,
            ],
            subject: $viabilityRequest,
            personalData: true,
        );

        $disk = $attachment->disk;
        $path = $attachment->path;

        return response()->streamDownload(function () use ($disk, $path): void {
            $stream = Storage::disk($disk)->readStream($path);
            fpassthru($stream);
            fclose($stream);
        }, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type,
        ]);
    }

    public function destroy(Request $request, ViabilityRequest $viabilityRequest, AnalysisAttachment $attachment): RedirectResponse
    {
        $this->garantirEditavel($viabilityRequest, $request);

        abort_unless($attachment->viability_request_id === $viabilityRequest->id, 404);

        DB::transaction(function () use ($viabilityRequest, $attachment): void {
            Storage::disk($attachment->disk)->delete($attachment->path);

            $this->audit->log(
                logName: 'analise',
                event: 'anexo-removido',
                description: "Anexo removido da análise do processo #{$viabilityRequest->id}",
                properties: [
                    'viability_request_id' => $viabilityRequest->id,
                    'anexo_id' => $attachment->id,
                ],
                subject: $viabilityRequest,
            );

            $attachment->delete();
        });

        return back()->with('status', 'Anexo removido com sucesso.');
    }

    /**
     * Ficha finalizada é imutável (422); ficha de outro analista não se altera (403).
     */
    private function garantirEditavel(ViabilityRequest $viabilityRequest, Request $request): void
    {
        $record = $this->records->current($viabilityRequest, $request->user());

        if ($record->status !== AnalysisRecordStatus::Rascunho) {
            throw ValidationException::withMessages(['ficha' => 'A ficha já foi finalizada: não é possível alterar os anexos.']);
        }

        abort_if(
            $record->analyst_user_id !== null && $record->analyst_user_id !== $request->user()?->id,
            403,
            'A ficha pertence a outro analista.',
        );
    }

    private function disk(): string
    {
        return (string) Settings::get(
            'storage.documentos.disk',
            config('viabiliza.storage.documentos.disk', 'local'),
        );
    }
}

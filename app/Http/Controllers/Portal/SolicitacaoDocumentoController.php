<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StoreSolicitacaoDocumentoRequest;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestDocument;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Anexos da solicitação (HU-066). Upload via Storage com disk PARAMETRIZADO
 * (storage.documentos.disk — NUNCA público), gravando disk/path/sha256 REAL e
 * metadados; download por STREAMING só do dono autenticado (LGPD — nunca URL
 * pública); substituição/remoção só enquanto a solicitação é rascunho. Tudo
 * auditado (RN-002). Só o dono edita, e apenas em rascunho
 * (ViabilityRequestPolicy::update / ::view, CA-04 com 403 auditado).
 */
class SolicitacaoDocumentoController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function store(StoreSolicitacaoDocumentoRequest $request, ViabilityRequest $solicitacao): RedirectResponse
    {
        Gate::authorize('update', $solicitacao);

        $disk = $this->documentsDisk();
        $file = $request->file('file');

        $requirementId = $request->validated('requirement_id');
        $requirementId = $requirementId !== null ? (int) $requirementId : null;

        // Metadados capturados ANTES do store() (a movimentação do arquivo
        // temporário invalida getRealPath/getSize). sha256 REAL do conteúdo.
        $sha256 = hash_file('sha256', $file->getRealPath());
        $originalName = $file->getClientOriginalName();
        $mimeType = $file->getMimeType();
        $size = $file->getSize();

        DB::transaction(function () use ($solicitacao, $file, $disk, $requirementId, $sha256, $originalName, $mimeType, $size, $request): void {
            // Substituição antes do protocolo: remove o anexo anterior do MESMO
            // requisito (disk + linha) — mantém um documento por requisito.
            if ($requirementId !== null) {
                $solicitacao->documents()
                    ->where('requirement_id', $requirementId)
                    ->get()
                    ->each(function (ViabilityRequestDocument $existing): void {
                        Storage::disk($existing->disk)->delete($existing->path);
                        $existing->delete();
                    });
            }

            $path = $file->store('solicitacoes/'.$solicitacao->id, $disk);

            $document = $solicitacao->documents()->create([
                'requirement_id' => $requirementId,
                'disk' => $disk,
                'path' => $path,
                'original_name' => $originalName,
                'mime_type' => $mimeType,
                'size' => $size,
                'sha256' => $sha256,
                'uploaded_by_user_id' => $request->user()->id,
            ]);

            $this->audit->log('solicitacoes', 'documento-anexado', 'Documento anexado à solicitação', [
                'solicitacao_id' => $solicitacao->id,
                'documento_id' => $document->id,
                'requirement_id' => $requirementId,
                'disk' => $disk,
                'sha256' => $sha256,
            ], $solicitacao);
        });

        return back()->with('status', 'Documento anexado com sucesso.');
    }

    public function download(ViabilityRequest $solicitacao, ViabilityRequestDocument $documento): StreamedResponse
    {
        Gate::authorize('view', $solicitacao);

        // Anti-IDOR: o documento tem de pertencer à solicitação da URL.
        abort_unless($documento->viability_request_id === $solicitacao->id, 404);

        $this->audit->log('solicitacoes', 'documento-download', 'Download de documento da solicitação', [
            'solicitacao_id' => $solicitacao->id,
            'documento_id' => $documento->id,
        ], $solicitacao);

        $disk = $documento->disk;
        $path = $documento->path;

        // Streaming a partir do disk gravado (nunca URL pública — LGPD).
        return response()->streamDownload(function () use ($disk, $path): void {
            $stream = Storage::disk($disk)->readStream($path);
            fpassthru($stream);
            fclose($stream);
        }, $documento->original_name, [
            'Content-Type' => $documento->mime_type,
        ]);
    }

    public function destroy(ViabilityRequest $solicitacao, ViabilityRequestDocument $documento): RedirectResponse
    {
        // Remoção só em rascunho e pelo dono (anexo substituível antes do protocolo).
        Gate::authorize('update', $solicitacao);

        abort_unless($documento->viability_request_id === $solicitacao->id, 404);

        DB::transaction(function () use ($solicitacao, $documento): void {
            Storage::disk($documento->disk)->delete($documento->path);

            $this->audit->log('solicitacoes', 'documento-removido', 'Documento removido da solicitação', [
                'solicitacao_id' => $solicitacao->id,
                'documento_id' => $documento->id,
            ], $solicitacao);

            $documento->delete();
        });

        return back()->with('status', 'Documento removido com sucesso.');
    }

    /**
     * Disk dos documentos (HU-014): banco→cache→config, default 'local'. NUNCA
     * um disk público — o acesso é sempre por streaming autenticado (LGPD).
     */
    private function documentsDisk(): string
    {
        return (string) Settings::get(
            'storage.documentos.disk',
            config('sile.storage.documentos.disk', 'local'),
        );
    }
}

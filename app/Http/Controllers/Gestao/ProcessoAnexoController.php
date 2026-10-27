<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Models\InspectionAttachment;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestDocument;
use App\Support\Audit\AuditService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Anexos do processo para quem o consulta (SEDUR, 22/10): os documentos do
 * processo e os anexos das fichas de vistoria. O download é auditado e o anexo
 * tem de pertencer ao processo da URL.
 */
class ProcessoAnexoController extends Controller
{
    public function __construct(private AuditService $audit) {}

    /**
     * @return list<array{id: int, tipo: string, nome: string, origem: string, tamanho: ?int, enviado_em: ?string, url: string}>
     */
    public static function lista(ViabilityRequest $processo): array
    {
        $documentos = $processo->documents()->orderBy('id')->get()
            ->map(fn (ViabilityRequestDocument $documento): array => [
                'id' => $documento->id,
                'tipo' => 'documento',
                'nome' => $documento->original_name,
                'origem' => 'Documento do processo',
                'tamanho' => $documento->size,
                'enviado_em' => $documento->created_at?->toIso8601String(),
                'url' => route('gestao.processos.anexos.documento', [$processo, $documento], false),
            ]);

        $daVistoria = InspectionAttachment::query()
            ->whereHas('inspection', fn ($ficha) => $ficha->where('viability_request_id', $processo->id))
            ->orderBy('id')
            ->get()
            ->map(fn (InspectionAttachment $anexo): array => [
                'id' => $anexo->id,
                'tipo' => 'vistoria',
                'nome' => $anexo->original_name,
                'origem' => 'Ficha de vistoria',
                'tamanho' => $anexo->size,
                'enviado_em' => $anexo->created_at?->toIso8601String(),
                'url' => route('gestao.processos.anexos.vistoria', [$processo, $anexo], false),
            ]);

        return [...$documentos->all(), ...$daVistoria->all()];
    }

    public function documento(ViabilityRequest $viabilityRequest, ViabilityRequestDocument $documento): StreamedResponse
    {
        abort_unless((int) $documento->viability_request_id === $viabilityRequest->id, 404);

        return $this->baixar($viabilityRequest, $documento->disk, $documento->path, $documento->original_name, $documento->mime_type, 'documento', $documento->id);
    }

    public function vistoria(ViabilityRequest $viabilityRequest, InspectionAttachment $anexo): StreamedResponse
    {
        abort_unless((int) $anexo->inspection?->viability_request_id === $viabilityRequest->id, 404);

        return $this->baixar($viabilityRequest, $anexo->disk, $anexo->path, $anexo->original_name, $anexo->mime_type, 'vistoria', $anexo->id);
    }

    private function baixar(ViabilityRequest $processo, string $disk, string $path, string $nome, ?string $mime, string $tipo, int $id): StreamedResponse
    {
        abort_unless(Storage::disk($disk)->exists($path), 404);

        $this->audit->log(
            logName: 'analise',
            event: 'anexo-download',
            description: "Download do anexo {$nome} do processo #{$processo->id}",
            properties: ['viability_request_id' => $processo->id, 'tipo' => $tipo, 'anexo_id' => $id],
            subject: $processo,
            personalData: true,
        );

        return response()->streamDownload(function () use ($disk, $path): void {
            $stream = Storage::disk($disk)->readStream($path);
            fpassthru($stream);
            fclose($stream);
        }, $nome, ['Content-Type' => $mime ?? 'application/octet-stream']);
    }
}

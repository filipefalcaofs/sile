<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Models\ExportFile;
use App\Support\Audit\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Download das exportações assíncronas (HU-131). Controller FINO: o arquivo
 * gerado pelo GerarExportacaoJob (15-02) é servido por streaming do disco NÃO
 * público — espelhando o TvlDocumentController (HU-132). Só se chega aqui com a
 * assinatura válida (middleware signed) e a permissão consultar-relatorios da
 * rota; o disco nunca é exposto por URL pública (LGPD/RN-007). O acesso é
 * auditado (RN-008). Arquivo podado por retenção (15-15) → 404 honesto, sem
 * fabricar. É o alvo do link da Notification ExportacaoPronta.
 */
class ExportacaoController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function download(Request $request, ExportFile $exportFile): StreamedResponse
    {
        // Guarda anti-'public': uma exportação (potencial PII) nunca é servida de
        // disco público por este endpoint, mesmo com assinatura válida.
        abort_if($exportFile->disk === 'public', 404);

        // Defesa em profundidade: além do gate da rota (consultar-relatorios), só
        // o dono ou quem tem consultar-relatorios baixa o arquivo.
        abort_unless(
            $exportFile->user_id === $request->user()?->id
                || (bool) $request->user()?->can('consultar-relatorios'),
            403,
        );

        // Arquivo ausente (retenção/pruning de 15-15) → 404 honesto, nunca um
        // arquivo vazio fabricado.
        abort_unless(Storage::disk($exportFile->disk)->exists($exportFile->path), 404);

        $this->audit->log(
            logName: 'relatorios',
            event: 'baixa-exportacao',
            description: "Download da exportação {$exportFile->filename}",
            properties: [
                'export_file_id' => $exportFile->id,
                'format' => $exportFile->format,
                'row_count' => $exportFile->row_count,
            ],
            subject: $exportFile,
            personalData: true,
        );

        return Storage::disk($exportFile->disk)->download($exportFile->path, $exportFile->filename);
    }
}

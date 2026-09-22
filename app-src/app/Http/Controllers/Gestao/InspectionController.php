<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\EncaminharVistoriaRequest;
use App\Http\Requests\Gestao\InspectionAttachmentRequest;
use App\Http\Requests\Gestao\InspectionConcludeRequest;
use App\Http\Requests\Gestao\InspectionPolygonRequest;
use App\Http\Requests\Gestao\InspectionUpdateRequest;
use App\Http\Resources\InspectionResource;
use App\Models\Inspection;
use App\Models\InspectionAttachment;
use App\Models\PropertyType;
use App\Models\Sector;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Vistoria\EncaminharVistoriaException;
use App\Services\Vistoria\EncaminharVistoriaService;
use App\Services\Vistoria\InspectionAtribuidaException;
use App\Services\Vistoria\InspectionAutoriaException;
use App\Services\Vistoria\InspectionConcluidaException;
use App\Services\Vistoria\InspectionService;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ficha de vistoria do processo. Abertura com identificação automática (tipo,
 * data, vistoriador) e snapshot da localização; rascunho parcial; redesenho e
 * validação do polígono; anexos com disk parametrizado (nunca público) e
 * streaming autenticado; conclusão que exige parecer e sela a ficha
 * (imutável). Tudo gated por preencher-ficha-vistoria e auditado (RN-002).
 */
class InspectionController extends Controller
{
    public function __construct(
        private InspectionService $inspections,
        private EncaminharVistoriaService $encaminhamento,
        private AuditService $audit,
    ) {}

    /**
     * Encaminhamento à vistoria (handoff real — NÃO é malha fina): o processo
     * vai à caixa do setor de vistoria, desatribuído, com o eixo operacional
     * em Vistoriar, para o apoio distribuir a um vistoriador. A origem fica
     * registrada para o retorno automático na conclusão da ficha. Gated por
     * analisar-processos (quem encaminha é o analista) — a rota vive no grupo
     * da análise, não no grupo da ficha.
     */
    public function encaminhar(EncaminharVistoriaRequest $request, ViabilityRequest $viabilityRequest): RedirectResponse
    {
        $setor = Sector::query()->findOrFail($request->integer('setor_vistoria_id'));

        try {
            $this->encaminhamento->encaminhar(
                $viabilityRequest,
                $setor,
                trim((string) $request->input('motivo')),
                $request->user(),
            );
        } catch (EncaminharVistoriaException $e) {
            throw ValidationException::withMessages(['motivo' => $e->getMessage()]);
        }

        return back()->with('status', "Processo encaminhado à vistoria ({$setor->name}) — aguardando distribuição ao vistoriador.");
    }

    /**
     * Abre a ficha (criando na primeira vez) ou retoma a existente.
     */
    public function show(Request $request, ViabilityRequest $viabilityRequest): Response
    {
        $vistoriador = $request->user();
        abort_unless($vistoriador instanceof User, 401);

        try {
            $ficha = $this->inspections->abrirOuRetomar($viabilityRequest, $vistoriador);
        } catch (InspectionAtribuidaException $e) {
            abort(403, $e->getMessage());
        }

        $ficha->loadMissing('vistoriador');

        $this->audit->log(
            logName: 'vistoria',
            event: $ficha->wasRecentlyCreated ? 'ficha-abertura' : 'ficha-consulta',
            description: $ficha->wasRecentlyCreated
                ? "Abertura da ficha de vistoria do processo #{$viabilityRequest->id}"
                : "Consulta da ficha de vistoria do processo #{$viabilityRequest->id}",
            properties: [
                'viability_request_id' => $viabilityRequest->id,
                'inspection_id' => $ficha->id,
            ],
            subject: $ficha,
        );

        return Inertia::render('gestao/vistoria/show', [
            'ficha' => (new InspectionResource($ficha))->resolve(),
            'processo' => [
                'id' => $viabilityRequest->id,
                'protocol_number' => $viabilityRequest->protocol_number,
                'status' => $viabilityRequest->status->value,
                'status_label' => $viabilityRequest->status->label(),
            ],
            // Tipo de imóvel é dado administrável (cadastro de tipos ativos).
            'tiposImovel' => PropertyType::query()
                ->active()
                ->orderBy('label')
                ->get(['code', 'label'])
                ->map(fn (PropertyType $tipo): array => ['value' => $tipo->code, 'label' => $tipo->label])
                ->all(),
            'opcoes' => Inspection::rotulos(),
            'anexos' => $this->anexosDa($ficha),
        ]);
    }

    /**
     * Salva o rascunho (parcial). Ficha concluída recusa com 422 — imutável.
     */
    public function update(InspectionUpdateRequest $request, ViabilityRequest $viabilityRequest): JsonResponse
    {
        $ficha = $this->fichaDo($viabilityRequest);

        try {
            $ficha = $this->inspections->salvarRascunho($ficha, $request->validated(), $request->user());
        } catch (InspectionConcluidaException $e) {
            throw ValidationException::withMessages(['ficha' => $e->getMessage()]);
        } catch (InspectionAutoriaException $e) {
            abort(403, $e->getMessage());
        }

        $this->audit->log(
            logName: 'vistoria',
            event: 'ficha-rascunho',
            description: "Rascunho da ficha de vistoria do processo #{$viabilityRequest->id} salvo",
            properties: [
                'viability_request_id' => $viabilityRequest->id,
                'inspection_id' => $ficha->id,
            ],
            subject: $ficha,
        );

        $ficha->loadMissing('vistoriador');

        return response()->json([
            'ficha' => (new InspectionResource($ficha))->resolve(),
            'status' => 'Rascunho salvo.',
        ]);
    }

    /**
     * Conclui a ficha: o parecer é OBRIGATÓRIO (InspectionConcludeRequest) e a
     * ficha se torna imutável.
     */
    public function concluir(InspectionConcludeRequest $request, ViabilityRequest $viabilityRequest): JsonResponse
    {
        $ficha = $this->fichaDo($viabilityRequest);

        try {
            $ficha = $this->inspections->concluir($ficha, $request->validated(), $request->user());
        } catch (InspectionConcluidaException $e) {
            throw ValidationException::withMessages(['ficha' => $e->getMessage()]);
        } catch (InspectionAutoriaException $e) {
            abort(403, $e->getMessage());
        }

        $this->audit->log(
            logName: 'vistoria',
            event: 'ficha-conclusao',
            description: "Conclusão da ficha de vistoria do processo #{$viabilityRequest->id}",
            properties: [
                'viability_request_id' => $viabilityRequest->id,
                'inspection_id' => $ficha->id,
            ],
            subject: $ficha,
        );

        $ficha->loadMissing('vistoriador');

        return response()->json([
            'ficha' => (new InspectionResource($ficha))->resolve(),
            'status' => 'Vistoria concluída.',
        ]);
    }

    /**
     * Persiste o polígono redesenhado e validado pelo vistoriador (com área
     * calculada e marca de validação).
     */
    public function validarPoligono(InspectionPolygonRequest $request, ViabilityRequest $viabilityRequest): JsonResponse
    {
        $ficha = $this->fichaDo($viabilityRequest);

        try {
            $ficha = $this->inspections->validarPoligono($ficha, $request->validated('polygon'), $request->user());
        } catch (InspectionConcluidaException $e) {
            throw ValidationException::withMessages(['ficha' => $e->getMessage()]);
        } catch (InspectionAutoriaException $e) {
            abort(403, $e->getMessage());
        }

        $this->audit->log(
            logName: 'vistoria',
            event: 'poligono-validado',
            description: "Polígono redesenhado e validado na ficha de vistoria do processo #{$viabilityRequest->id}",
            properties: [
                'viability_request_id' => $viabilityRequest->id,
                'inspection_id' => $ficha->id,
                'area_m2' => $ficha->polygon_area_m2 !== null ? (float) $ficha->polygon_area_m2 : null,
            ],
            subject: $ficha,
        );

        $ficha->loadMissing('vistoriador');

        return response()->json([
            'ficha' => (new InspectionResource($ficha))->resolve(),
            'status' => 'Polígono validado e associado à ficha.',
        ]);
    }

    /**
     * Anexa foto/documento à ficha: disk parametrizado (NUNCA público), sha256
     * real do conteúdo e metadados — espelha os anexos da solicitação (HU-066).
     */
    public function storeAnexo(InspectionAttachmentRequest $request, ViabilityRequest $viabilityRequest): RedirectResponse
    {
        $ficha = $this->fichaDo($viabilityRequest);
        $this->garantirEditavel($ficha);

        try {
            $this->inspections->garantirAutoria($ficha, $request->user());
        } catch (InspectionAutoriaException $e) {
            abort(403, $e->getMessage());
        }

        $disk = $this->anexosDisk();
        $file = $request->file('file');

        // Metadados capturados ANTES do store() (a movimentação do temporário
        // invalida getRealPath/getSize). sha256 REAL do conteúdo.
        $sha256 = hash_file('sha256', $file->getRealPath());
        $originalName = $file->getClientOriginalName();
        $mimeType = $file->getMimeType();
        $size = $file->getSize();

        $anexo = DB::transaction(function () use ($ficha, $file, $disk, $sha256, $originalName, $mimeType, $size, $request): InspectionAttachment {
            $path = $file->store('vistorias/'.$ficha->id, $disk);

            return $ficha->attachments()->create([
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
            logName: 'vistoria',
            event: 'anexo-adicionado',
            description: "Anexo adicionado à ficha de vistoria do processo #{$viabilityRequest->id}",
            properties: [
                'viability_request_id' => $viabilityRequest->id,
                'inspection_id' => $ficha->id,
                'anexo_id' => $anexo->id,
                'sha256' => $sha256,
            ],
            subject: $ficha,
        );

        return back()->with('status', 'Anexo enviado com sucesso.');
    }

    /**
     * Download do anexo por STREAMING autenticado a partir do disk gravado —
     * nunca URL pública (LGPD).
     */
    public function downloadAnexo(ViabilityRequest $viabilityRequest, InspectionAttachment $attachment): StreamedResponse
    {
        $ficha = $this->fichaDo($viabilityRequest);

        // Anti-IDOR: o anexo tem de pertencer à ficha do processo da URL.
        abort_unless($attachment->inspection_id === $ficha->id, 404);

        $this->audit->log(
            logName: 'vistoria',
            event: 'anexo-download',
            description: "Download de anexo da ficha de vistoria do processo #{$viabilityRequest->id}",
            properties: [
                'viability_request_id' => $viabilityRequest->id,
                'inspection_id' => $ficha->id,
                'anexo_id' => $attachment->id,
            ],
            subject: $ficha,
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

    /**
     * Remove o anexo (arquivo + linha) — só enquanto a ficha está em
     * preenchimento.
     */
    public function destroyAnexo(ViabilityRequest $viabilityRequest, InspectionAttachment $attachment, Request $request): RedirectResponse
    {
        $ficha = $this->fichaDo($viabilityRequest);
        $this->garantirEditavel($ficha);

        try {
            $this->inspections->garantirAutoria($ficha, $request->user());
        } catch (InspectionAutoriaException $e) {
            abort(403, $e->getMessage());
        }

        abort_unless($attachment->inspection_id === $ficha->id, 404);

        DB::transaction(function () use ($viabilityRequest, $ficha, $attachment): void {
            Storage::disk($attachment->disk)->delete($attachment->path);

            $this->audit->log(
                logName: 'vistoria',
                event: 'anexo-removido',
                description: "Anexo removido da ficha de vistoria do processo #{$viabilityRequest->id}",
                properties: [
                    'viability_request_id' => $viabilityRequest->id,
                    'inspection_id' => $ficha->id,
                    'anexo_id' => $attachment->id,
                ],
                subject: $ficha,
            );

            $attachment->delete();
        });

        return back()->with('status', 'Anexo removido com sucesso.');
    }

    /**
     * Ficha EXISTENTE do processo — mutações nunca criam ficha implicitamente
     * (a abertura é o show, que registra a identificação).
     */
    private function fichaDo(ViabilityRequest $viabilityRequest): Inspection
    {
        return Inspection::query()
            ->where('viability_request_id', $viabilityRequest->id)
            ->latest('id')
            ->firstOrFail();
    }

    private function garantirEditavel(Inspection $ficha): void
    {
        if ($ficha->isConcluida()) {
            throw ValidationException::withMessages([
                'ficha' => 'A ficha de vistoria está concluída e não aceita mais alterações.',
            ]);
        }
    }

    /**
     * Disk dos anexos (HU-014): banco→cache→config, default 'local'. NUNCA um
     * disk público — o acesso é sempre por streaming autenticado (LGPD).
     */
    private function anexosDisk(): string
    {
        return (string) Settings::get(
            'storage.documentos.disk',
            config('sile.storage.documentos.disk', 'local'),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function anexosDa(Inspection $ficha): array
    {
        return $ficha->attachments()
            ->latest('id')
            ->get()
            ->map(fn (InspectionAttachment $anexo): array => [
                'id' => $anexo->id,
                'original_name' => $anexo->original_name,
                'mime_type' => $anexo->mime_type,
                'size' => $anexo->size,
                'created_at' => $anexo->created_at?->toIso8601String(),
                'download_url' => route('gestao.processos.vistoria.anexos.download', [
                    'viabilityRequest' => $ficha->viability_request_id,
                    'attachment' => $anexo->id,
                ]),
            ])
            ->all();
    }
}

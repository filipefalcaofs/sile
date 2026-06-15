<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\AnalysisRecordRequest;
use App\Http\Resources\AnalysisRecordResource;
use App\Models\StandardText;
use App\Models\ViabilityRequest;
use App\Services\Analise\AnalysisRecordDiff;
use App\Services\Analise\AnalysisRecordImutavelException;
use App\Services\Analise\AnalysisRecordService;
use App\Support\Audit\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ficha de análise (HU-135) — superfície da análise humana na retaguarda. Abre a
 * revisão vigente (pré-analisada em 10-08), faz o autosave do rascunho (RN-008),
 * finaliza tornando a revisão imutável (RN-003) e materializando as divergências
 * (HU-140), cria uma nova revisão para reedição/recálculo e compara duas revisões
 * (diff — RN-007). Toda a superfície é gated por analisar-processos (403 auditado
 * no ponto único) e auditada (RN-002). A decisão (deferir/indeferir) NÃO está aqui
 * — é 10-10, a partir da ficha finalizada. A página é construída em 10-17.
 */
class AnalysisRecordController extends Controller
{
    public function __construct(
        private AnalysisRecordService $records,
        private AuditService $audit,
    ) {}

    /**
     * Abre a ficha na revisão vigente, com a biblioteca de textos-padrão ativos
     * para o picker do parecer (HU-085 RN-004) e o debounce do autosave (config).
     */
    public function show(ViabilityRequest $viabilityRequest): Response
    {
        $record = $this->records->current($viabilityRequest);
        $record->loadMissing('analyst');

        $this->audit->log(
            logName: 'analise',
            event: 'ficha-consulta',
            description: "Abertura da ficha de análise do processo #{$viabilityRequest->id}",
            properties: [
                'viability_request_id' => $viabilityRequest->id,
                'protocol_number' => $viabilityRequest->protocol_number,
                'revision' => $record->revision,
            ],
            subject: $record,
        );

        return Inertia::render('gestao/ficha-analise/show', [
            'ficha' => (new AnalysisRecordResource($record))->resolve(),
            'processo' => [
                'id' => $viabilityRequest->id,
                'protocol_number' => $viabilityRequest->protocol_number,
                'status' => $viabilityRequest->status->value,
                'status_label' => $viabilityRequest->status->label(),
            ],
            'localizacao' => $this->localizacaoDoImovel($viabilityRequest),
            'textosPadrao' => $this->textosPadraoAtivos(),
            'autosaveDebounceMs' => (int) config('sile.analise.autosave.debounce_ms', 1500),
        ]);
    }

    /**
     * Autosave do rascunho (RN-008): atualiza só os campos enviados. Numa revisão
     * finalizada (RN-003), recusa com 422 — não edita silenciosamente.
     */
    public function autosave(AnalysisRecordRequest $request, ViabilityRequest $viabilityRequest): JsonResponse
    {
        $record = $this->records->current($viabilityRequest);

        try {
            $record = $this->records->autosave($record, $request->validated());
        } catch (AnalysisRecordImutavelException $e) {
            abort(422, $e->getMessage());
        }

        $this->audit->log(
            logName: 'analise',
            event: 'ficha-autosave',
            description: "Autosave da ficha de análise do processo #{$viabilityRequest->id}",
            properties: [
                'viability_request_id' => $viabilityRequest->id,
                'revision' => $record->revision,
            ],
            subject: $record,
        );

        return response()->json([
            'ficha' => (new AnalysisRecordResource($record))->resolve(),
            'status' => 'Rascunho salvo.',
        ]);
    }

    /**
     * Finaliza a revisão vigente (RN-003): torna-a imutável e grava as divergências
     * analista×motor (HU-140). Finalizar uma revisão já finalizada recusa com 422.
     */
    public function finalizar(Request $request, ViabilityRequest $viabilityRequest): JsonResponse
    {
        $record = $this->records->current($viabilityRequest);

        try {
            $record = $this->records->finalizar($record, $request->user());
        } catch (AnalysisRecordImutavelException $e) {
            abort(422, $e->getMessage());
        }

        return response()->json([
            'ficha' => (new AnalysisRecordResource($record))->resolve(),
            'status' => 'Ficha finalizada.',
        ]);
    }

    /**
     * Cria a próxima revisão (rascunho) copiando a anterior para reedição/recálculo
     * após a finalização — a revisão finalizada permanece intacta (append-only).
     */
    public function novaRevisao(Request $request, ViabilityRequest $viabilityRequest): JsonResponse
    {
        $record = $this->records->novaRevisao($viabilityRequest, $request->user());

        return response()->json([
            'ficha' => (new AnalysisRecordResource($record))->resolve(),
            'status' => 'Nova revisão criada.',
        ]);
    }

    /**
     * Compara duas revisões da ficha (RN-007) e devolve só o que mudou — serve o
     * painel de histórico. 404 quando uma das revisões informadas não existe.
     */
    public function diff(Request $request, ViabilityRequest $viabilityRequest, AnalysisRecordDiff $diff): JsonResponse
    {
        $de = $request->integer('de');
        $para = $request->integer('para');

        $revisoes = $viabilityRequest->analysisRecords()
            ->whereIn('revision', array_unique([$de, $para]))
            ->get()
            ->keyBy('revision');

        $a = $revisoes->get($de);
        $b = $revisoes->get($para);

        abort_if($a === null || $b === null, 404, 'Revisão da ficha não encontrada.');

        $this->audit->log(
            logName: 'analise',
            event: 'ficha-diff',
            description: "Comparação de revisões da ficha do processo #{$viabilityRequest->id} ({$de} → {$para})",
            properties: [
                'viability_request_id' => $viabilityRequest->id,
                'de' => $de,
                'para' => $para,
            ],
            subject: $viabilityRequest,
        );

        return response()->json([
            'de' => $de,
            'para' => $para,
            'diff' => $diff->between($a, $b),
        ]);
    }

    /**
     * Localização REAL do imóvel para o mini-mapa permanente da ficha (HU-142):
     * o polígono cadastrado (GeoJSON, fonte única `property_polygon_geojson`) e o
     * endereço formatado. Honesto por construção — devolve `null` em cada campo
     * sem dado, nunca coordenada inventada. A zona/via oficiais seguem pendentes
     * SEDUR (Quadro 10) e são comunicadas como tal na própria tela.
     *
     * @return array{poligono: array<string, mixed>|null, endereco: string|null}
     */
    private function localizacaoDoImovel(ViabilityRequest $request): array
    {
        $partes = array_filter([
            trim((string) ($request->address_street ?? '')),
            trim((string) ($request->address_number ?? '')),
            trim((string) ($request->address_neighborhood ?? '')),
        ], fn (string $parte): bool => $parte !== '');

        $endereco = $partes === [] ? null : implode(', ', $partes);

        return [
            'poligono' => $request->property_polygon_geojson,
            'endereco' => $endereco,
        ];
    }

    /**
     * Trechos pré-aprovados ativos da biblioteca de textos-padrão (HU-085) para o
     * picker do parecer — leitura gated por analisar-processos (a rota já gate).
     *
     * @return list<array<string, mixed>>
     */
    private function textosPadraoAtivos(): array
    {
        return StandardText::query()
            ->where('active', true)
            ->orderBy('category')
            ->orderBy('id')
            ->get(['id', 'category', 'content', 'version'])
            ->map(fn (StandardText $texto): array => [
                'id' => $texto->id,
                'category' => $texto->category,
                'content' => $texto->content,
                'version' => $texto->version,
            ])
            ->all();
    }
}

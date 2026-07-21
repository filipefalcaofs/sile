<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\AnalysisRecordRequest;
use App\Http\Resources\AnalysisRecordResource;
use App\Models\AiSuggestion;
use App\Models\AnalysisRecord;
use App\Models\StandardText;
use App\Models\ViabilityRequest;
use App\Models\VirtualOfficeInscriptionLock;
use App\Services\Ai\ResumoProcessoService;
use App\Services\Ai\SugestaoParecerService;
use App\Services\Analise\AnalysisRecordDiff;
use App\Services\Analise\AnalysisRecordImutavelException;
use App\Services\Analise\AnalysisRecordService;
use App\Services\Expresso\SedeEscritorioVirtualGatilho;
use App\Services\Relatorios\RelatorioSedeEscritorioVirtualService;
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
        private ResumoProcessoService $resumos,
        private SedeEscritorioVirtualGatilho $sedeGatilho,
        private RelatorioSedeEscritorioVirtualService $relatorioSede,
    ) {}

    /**
     * Abre a ficha na revisão vigente, com a biblioteca de textos-padrão ativos
     * para o picker do parecer (HU-085 RN-004) e o debounce do autosave (config).
     */
    public function show(Request $request, ViabilityRequest $viabilityRequest): Response
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
            // Escritório virtual (T02): flag do gatilho (RN-EV-01), a inscrição e o
            // painel de abrigados quando a solicitação é a SEDE ativa da inscrição.
            'escritorioVirtual' => $this->escritorioVirtual($viabilityRequest, $record),
            'textosPadrao' => $this->textosPadraoAtivos(),
            'autosaveDebounceMs' => (int) config('sile.analise.autosave.debounce_ms', 1500),
            // Sugestões de IA (HU-115 alertas + HU-117 resumo do processo) — prop
            // DEFERIDA (carregada sob demanda pelo card, fora do load inicial).
            // Ao resolver, dispara o resumo do processo (HU-117) de forma gated e
            // idempotente (toggle ia_resumo off ou sem provedor ⇒ no-op; dedup por
            // entrada evita reprocessar) e então LÊ o ledger. APENAS LEITURA do
            // AnalysisRecord (ficha finalizada é imutável, RN-003) e da decisão:
            // são sugestões para revisão, jamais decisão (RN-001/004).
            'sugestoesIa' => Inertia::optional(function () use ($request, $viabilityRequest): array {
                $this->resumos->processar($viabilityRequest, $request->user()?->id);

                return $this->sugestoesIa($viabilityRequest);
            }),
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
     * Solicita à IA uma SUGESTÃO de minuta de parecer (HU-118) — Failure Mode #1:
     * apoio, NUNCA decisão. Delega ao SugestaoParecerService, que degrada
     * honestamente sem o toggle features.ia_parecer, sem provedor de texto ou sem
     * a pré-análise do motor (engine_snapshot). A minuta nasce no ledger
     * ai_suggestions como sugestão revisável e aparece nos alertas de IA da ficha;
     * este endpoint NUNCA grava o parecer nem decide o processo. Recusa numa
     * revisão finalizada (RN-003), onde a redação é imutável.
     */
    public function sugerirParecer(Request $request, ViabilityRequest $viabilityRequest, SugestaoParecerService $parecer): JsonResponse
    {
        $record = $this->records->current($viabilityRequest);

        if ($record->isFinalizada()) {
            abort(422, 'A revisão está finalizada (RN-003): crie uma nova revisão para trabalhar uma minuta.');
        }

        $despachou = $parecer->processar($viabilityRequest, $request->user()?->id);

        $this->audit->log(
            logName: 'analise',
            event: 'ficha-sugerir-parecer',
            description: "Solicitação de minuta de parecer por IA do processo #{$viabilityRequest->id}",
            properties: [
                'viability_request_id' => $viabilityRequest->id,
                'revision' => $record->revision,
                'despachou' => $despachou,
            ],
            subject: $record,
        );

        return response()->json([
            'despachou' => $despachou,
            'status' => $despachou
                ? 'Minuta solicitada à IA. A sugestão aparecerá nos alertas de IA para revisão.'
                : 'Sugestão de minuta indisponível (função desativada ou processo sem pré-análise do motor). Redija o parecer manualmente.',
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
     * Bloco de escritório virtual da ficha (T02). Expõe:
     *  - `gatilho`: o gatilho de SEDE disparou (RN-EV-01 — CNAE 8211-3/00 +
     *    requerente "quero ser sede = Sim"), para o banner de destaque;
     *  - `is_sede`: o analista marcou a sede nesta ficha (flag da revisão);
     *  - `inscricao`: a inscrição imobiliária do processo;
     *  - `abrigados`: painel dos ABRIGADOS da inscrição quando a solicitação é a
     *    SEDE ATIVA (lock ativo — RN-EV-03/05), reusando o recorte único do
     *    relatório R1 (RelatorioSedeEscritorioVirtualService). Cada linha traz o
     *    nº TVL e a razão social; a VALIDADE do produto não é modelada (desfecho
     *    spec-2) e degrada para null → "—" na tela, jamais inventada. Vazio quando
     *    não há abrigado (CA-F-03).
     *
     * @return array{gatilho: bool, is_sede: bool, inscricao: string|null, abrigados: list<array{tvl: string|null, razao_social: string|null, validade: null}>}
     */
    private function escritorioVirtual(ViabilityRequest $request, AnalysisRecord $record): array
    {
        $inscricao = $request->property_registration;

        $ehSedeAtiva = $inscricao !== null && $inscricao !== ''
            && VirtualOfficeInscriptionLock::query()
                ->where('active', true)
                ->where('sede_viability_request_id', $request->id)
                ->exists();

        $abrigados = [];

        if ($ehSedeAtiva) {
            $abrigados = collect($this->relatorioSede->consultar(['inscricao' => $inscricao], 100)->items())
                ->map(fn (ViabilityRequest $r): array => $this->relatorioSede->linha($r))
                ->filter(fn (array $linha): bool => $linha['tipo'] === 'abrigado')
                ->map(fn (array $linha): array => [
                    'tvl' => $linha['tvl'],
                    'razao_social' => $linha['razao_social'],
                    // Validade do produto não modelada (desfecho spec-2) → "—".
                    'validade' => null,
                ])
                ->values()
                ->all();
        }

        return [
            'gatilho' => $this->sedeGatilho->aplica($request),
            'is_sede' => (bool) $record->is_virtual_office_hq,
            'inscricao' => $inscricao,
            'abrigados' => $abrigados,
        ];
    }

    /**
     * Sugestões de IA do processo (HU-115) para o card "Alertas de IA" — APENAS
     * LEITURA do ledger ai_suggestions, mais recentes primeiro. Cada item carrega
     * o tipo/estado rotulados e a saída estruturada (no caso de inconsistências:
     * campo/declarado/documento/severidade/fonte). Não decide nada: a ficha trata
     * como sinal revisável (RN-004).
     *
     * @return list<array<string, mixed>>
     */
    private function sugestoesIa(ViabilityRequest $request): array
    {
        return AiSuggestion::query()
            ->where('viability_request_id', $request->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (AiSuggestion $sugestao): array => [
                'id' => $sugestao->id,
                'type' => $sugestao->type->value,
                'type_label' => $sugestao->type->label(),
                'status' => $sugestao->status->value,
                'status_label' => $sugestao->status->label(),
                'confianca' => $sugestao->confidence,
                'output' => $sugestao->output,
                'created_at' => $sugestao->created_at?->toIso8601String(),
            ])
            ->all();
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

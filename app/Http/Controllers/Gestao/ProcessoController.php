<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\ViabilityRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProcessoResource;
use App\Models\ViabilityRequest;
use App\Services\Analise\ProcessoQueryService;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Consulta de processos (HU-082) e fila do analista (HU-144) na retaguarda,
 * server-driven (espelha o ResultadoExpressoController/CaixaSetorController). O
 * index aplica os filtros completos do SAPS + analista + categoria via
 * ProcessoQueryService, pagina no servidor e exporta CSV simples do conjunto
 * filtrado (?formato=csv; export pleno XLSX/PDF → HU-131/Fase 15). O detalhe
 * (show) carrega dados/decisão/ficha/timeline como props (mini-mapa/abas
 * renderizados na UI 10-16). Tudo gated por consultar-solicitacoes (reuso —
 * decisão de 10-01) e auditado (RN-002); o 403 é auditado no ponto único
 * (bootstrap/app.php).
 */
class ProcessoController extends Controller
{
    /** Itens por página aceitos — reusa o padrão do console (ui.cnaes.per_page). */
    private const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    public function __construct(
        private ProcessoQueryService $processos,
        private AuditService $audit,
    ) {}

    /**
     * Consulta filtrável e paginada (HU-082). Com ?formato=csv, devolve o CSV
     * simples do conjunto filtrado (RN-011 — versão simples; export pleno é
     * HU-131). A consulta é auditada (CA-02).
     */
    public function index(Request $request): Response|StreamedResponse
    {
        $filtros = $this->filtros($request);
        $query = $this->processos->filtered($filtros);

        if ($request->string('formato')->lower()->toString() === 'csv') {
            return $this->exportarCsv($query, $filtros);
        }

        $perPage = $this->perPage($request);

        $processos = $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (ViabilityRequest $processo): array => (new ProcessoResource($processo))->resolve());

        $this->audit->log('analise', 'consulta-processos', 'Consulta de processos da análise técnica', [
            'filtros' => $this->filtrosPreenchidos($filtros),
        ]);

        return Inertia::render('gestao/processos/index', [
            'processos' => $processos,
            'filtros' => $filtros + ['per_page' => $perPage],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'statusOptions' => $this->statusOptions(),
            'categoriaOptions' => $this->categoriaOptions(),
        ]);
    }

    /**
     * Fila de trabalho do analista (HU-144): "meus processos" (atribuídos) ou
     * "caixa do setor" (processos do(s) setor(es) do usuário — respeita o
     * vínculo), ordenada por prazo (analysis_due_at) com semáforo on-the-fly e
     * contadores por status. O gestor (distribuir-processos) ganha a visão
     * agregada do setor (carga por analista + processos em vermelho — CA-03). O
     * acesso é auditado.
     */
    public function fila(Request $request): Response
    {
        $modo = $request->string('modo')->toString();
        $modo = in_array($modo, ['meus', 'setor'], true) ? $modo : 'meus';

        $user = $request->user();

        $processos = $this->processos->fila($user, $modo)
            ->get()
            ->map(fn (ViabilityRequest $processo): array => (new ProcessoResource($processo))->resolve())
            ->all();

        $this->audit->log('analise', 'consulta-fila', 'Consulta da fila de trabalho do analista', [
            'modo' => $modo,
        ]);

        return Inertia::render('gestao/processos/fila', [
            'modo' => $modo,
            'processos' => $processos,
            'contadores' => $this->processos->contadores($user, $modo),
            // A visão agregada do setor é só do gestor (distribuir-processos);
            // o analista recebe null e a UI (10-16) não a renderiza.
            'visaoSetor' => $user->can('distribuir-processos') ? $this->processos->visaoSetor($user) : null,
        ]);
    }

    /**
     * Detalhe do processo (HU-082 RN-006/007): dados, decisão, ficha vigente e
     * timeline como props para a UI (mini-mapa/abas em 10-16). A consulta é
     * auditada. 404 honesto via route model binding (processo inexistente).
     */
    public function show(ViabilityRequest $viabilityRequest): Response
    {
        $viabilityRequest->load([
            'company',
            'sector:id,name',
            'assignedTo:id,name',
            'decision',
            'currentAnalysisRecord',
            'transitions',
        ]);

        $this->audit->log(
            logName: 'analise',
            event: 'consulta-processo',
            description: "Consulta do processo #{$viabilityRequest->id}",
            properties: [
                'viability_request_id' => $viabilityRequest->id,
                'protocol_number' => $viabilityRequest->protocol_number,
            ],
            subject: $viabilityRequest,
        );

        return Inertia::render('gestao/processos/show', [
            'processo' => (new ProcessoResource($viabilityRequest))->resolve(),
            'timeline' => $viabilityRequest->transitions->map(fn ($transicao): array => [
                'from' => $transicao->from_status?->value,
                'from_label' => $transicao->from_status?->label(),
                'to' => $transicao->to_status->value,
                'to_label' => $transicao->to_status->label(),
                'reason' => $transicao->reason,
                'em' => $transicao->created_at?->toIso8601String(),
            ])->all(),
            // Geometria do imóvel para o mini-mapa Leaflet (RN-006): o polígono
            // real do processo, exposto SÓ no detalhe (não infla a lista/fila). A
            // zona/via oficiais (Quadro 10) seguem pendentes na SEDUR — o mapa
            // mostra o que existe e degrada honestamente quando não há polígono.
            'geo' => [
                'poligono' => $viabilityRequest->property_polygon_geojson,
            ],
        ]);
    }

    /**
     * CSV simples do conjunto filtrado (RN-011): cabeçalho + uma linha por
     * processo, em streaming (chunk) para não materializar tudo em memória. A
     * exportação é auditada. Export pleno (XLSX/PDF, layout/colunas configuráveis)
     * fica para a HU-131 (Fase 15).
     *
     * @param  Builder<ViabilityRequest>  $query
     * @param  array<string, mixed>  $filtros
     */
    private function exportarCsv(Builder $query, array $filtros): StreamedResponse
    {
        $this->audit->log('analise', 'exporta-processos-csv', 'Exportação CSV da consulta de processos', [
            'filtros' => $this->filtrosPreenchidos($filtros),
        ]);

        $colunas = ['Processo', 'BAP', 'Produto TVL', 'Empresa', 'CNPJ', 'Status', 'Categoria', 'Analista', 'Prazo'];

        return response()->streamDownload(function () use ($query, $colunas): void {
            $saida = fopen('php://output', 'w');
            fputcsv($saida, $colunas);

            $query->chunk(200, function ($processos) use ($saida): void {
                foreach ($processos as $processo) {
                    $dados = (new ProcessoResource($processo))->resolve();

                    fputcsv($saida, [
                        $dados['protocol_number'],
                        $dados['bap'],
                        $dados['tvl_product_number'],
                        $dados['empresa'],
                        $dados['cnpj'],
                        $dados['status_label'],
                        $dados['categoria'],
                        $dados['analista'],
                        $dados['analysis_due_at'],
                    ]);
                }
            });

            fclose($saida);
        }, 'processos.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Coleta os filtros do SAPS + analista + categoria da query string (strings
     * cruas; o ProcessoQueryService normaliza e ignora os vazios).
     *
     * @return array<string, string>
     */
    private function filtros(Request $request): array
    {
        $chaves = [
            'grupo', 'status', 'protocolo', 'bap', 'produto_tvl', 'servico', 'setor',
            'analista', 'categoria', 'inscricao', 'nome', 'cnpj', 'cep', 'logradouro',
            'bairro', 'data_de', 'data_ate',
        ];

        $filtros = [];

        foreach ($chaves as $chave) {
            $filtros[$chave] = $request->string($chave)->toString();
        }

        return $filtros;
    }

    /**
     * Só os filtros efetivamente informados (para a trilha de auditoria).
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function filtrosPreenchidos(array $filtros): array
    {
        return array_filter($filtros, fn ($valor): bool => $valor !== null && $valor !== '');
    }

    private function perPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page');

        return in_array($perPage, self::PER_PAGE_OPTIONS, true)
            ? $perPage
            : (int) Settings::get('ui.cnaes.per_page', 15);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(
            fn (ViabilityRequestStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
            ViabilityRequestStatus::cases(),
        );
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function categoriaOptions(): array
    {
        $opcoes = [];

        foreach (ProcessoQueryService::CATEGORIAS as $value => $label) {
            $opcoes[] = ['value' => $value, 'label' => $label];
        }

        return $opcoes;
    }
}

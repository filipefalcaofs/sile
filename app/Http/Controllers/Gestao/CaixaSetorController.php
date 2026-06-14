<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\ViabilityRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\DistribuirProcessoRequest;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Analise\DistribuicaoException;
use App\Services\Analise\DistribuicaoService;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Caixa do setor da análise técnica (HU-080/081): a fila da distribuição. O
 * analista vê e ASSUME os processos em_analise do(s) seu(s) setor(es)
 * (analisar-processos); o gestor DISTRIBUI — single ou lote (RN-007) — a um
 * analista do setor (distribuir-processos). A caixa NÃO tira o processo do setor
 * (RN-004): distribuir/assumir só fixam o responsável. A delegação real é do
 * DistribuicaoService (SLA recalculado + auditoria síncrona por processo); 403
 * é auditado no ponto único (bootstrap/app.php). Index server-driven espelhando
 * o ResultadoExpressoController; a tela é construída em 10-16.
 */
class CaixaSetorController extends Controller
{
    /** Itens por página aceitos — reusa o padrão do console (ui.cnaes.per_page). */
    private const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    public function __construct(
        private DistribuicaoService $distribuicao,
        private AuditService $audit,
    ) {}

    /**
     * Lista os processos em_analise dos setores do usuário (RN-004 — visibilidade
     * por setor), ordenados pelo prazo (analysis_due_at, índice de 10-02) e
     * paginados no servidor. A consulta é auditada (RN-002).
     */
    public function index(Request $request): Response
    {
        $perPage = (int) $request->input('per_page');
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true)
            ? $perPage
            : (int) Settings::get('ui.cnaes.per_page', 15);

        $sectorIds = $request->user()->sectors()->pluck('sectors.id');

        $processos = ViabilityRequest::query()
            ->whereIn('sector_id', $sectorIds)
            ->where('status', ViabilityRequestStatus::EmAnalise->value)
            ->with(['company', 'sector:id,name', 'assignedTo:id,name'])
            ->orderBy('analysis_due_at')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (ViabilityRequest $processo): array => [
                'id' => $processo->id,
                'protocol_number' => $processo->protocol_number,
                'empresa' => $processo->company?->trade_name ?: $processo->company?->legal_name,
                'cnpj' => $processo->company?->formatted_cnpj,
                'status' => $processo->status->value,
                'status_label' => $processo->status->label(),
                'analysis_stage' => $processo->analysis_stage?->value,
                'analysis_stage_label' => $processo->analysis_stage?->label(),
                'sector' => $processo->sector?->name,
                'assigned_user_id' => $processo->assigned_user_id,
                'assigned_to' => $processo->assignedTo?->name,
                'analysis_due_at' => $processo->analysis_due_at?->toIso8601String(),
            ]);

        $this->audit->log('analise', 'consulta-caixa', 'Consulta da caixa do setor', [
            'setores' => $sectorIds->all(),
        ]);

        return Inertia::render('gestao/caixa-setor/index', [
            'processos' => $processos,
            'filtros' => [
                'per_page' => $perPage,
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    /**
     * Distribui um ou mais processos (lote — RN-007) a um analista do setor. A
     * validação estrutural é do DistribuirProcessoRequest; o vínculo de setor de
     * cada item é regra do DistribuicaoService, que isola falhas no lote (uma
     * atribuição inválida não derruba as demais).
     */
    public function distribuir(DistribuirProcessoRequest $request): RedirectResponse
    {
        /** @var User $analista */
        $analista = User::query()->findOrFail($request->integer('analista_id'));

        $processos = ViabilityRequest::query()
            ->whereIn('id', $request->input('request_ids'))
            ->where('status', ViabilityRequestStatus::EmAnalise->value)
            ->get();

        $resumo = $this->distribuicao->distribuirLote($processos, $analista, $request->user());

        $response = back()->with('status', "{$resumo['ok']} processo(s) distribuído(s) a {$analista->name}.");

        if ($resumo['falhas'] !== []) {
            $response->with('warning', count($resumo['falhas']).' processo(s) não distribuído(s): analista não vinculado ao setor.');
        }

        return $response;
    }

    /**
     * O analista assume um processo da caixa do seu setor (HU-081). Fora dos seus
     * setores, a ação é negada de forma controlada (aviso), nunca silenciosa.
     */
    public function assumir(Request $request, ViabilityRequest $viabilityRequest): RedirectResponse
    {
        try {
            $this->distribuicao->assumir($viabilityRequest, $request->user());
        } catch (DistribuicaoException) {
            return back()->with('error', 'Você só pode assumir processos da caixa dos seus setores.');
        }

        return back()->with('status', 'Processo assumido.');
    }
}

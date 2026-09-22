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
 * (analisar-processos); o gestor e o apoio (tramitação) DISTRIBUEM — single ou
 * lote (RN-007) — a um analista do setor (distribuir-processos). A caixa NÃO
 * tira o processo do setor (RN-004): distribuir/assumir só fixam o responsável.
 * A delegação real é do DistribuicaoService (SLA recalculado + auditoria
 * síncrona por processo); 403 é auditado no ponto único (bootstrap/app.php).
 * Index server-driven espelhando o ResultadoExpressoController; a tela é 10-16.
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
     * por setor), em duas visões: "para_distribuir" (padrão — sem responsável) e
     * "distribuidos" (já atribuídos, com a flag pode_redistribuir). Ordenados
     * pelo prazo (analysis_due_at) e paginados no servidor. A consulta é
     * auditada (RN-002).
     */
    public function index(Request $request): Response
    {
        $perPage = (int) $request->input('per_page');
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true)
            ? $perPage
            : (int) Settings::get('ui.cnaes.per_page', 15);

        $sectorIds = $request->user()->sectors()->pluck('sectors.id');

        // Visões da caixa (abas): "para_distribuir" (padrão — sem responsável)
        // e "distribuidos" (acompanhamento, com a analista atribuída).
        $visao = $request->input('visao') === 'distribuidos' ? 'distribuidos' : 'para_distribuir';

        $base = ViabilityRequest::query()
            ->whereIn('sector_id', $sectorIds)
            ->where('status', ViabilityRequestStatus::EmAnalise->value);

        $contadores = [
            'para_distribuir' => (clone $base)->whereNull('assigned_user_id')->count(),
            'distribuidos' => (clone $base)->whereNotNull('assigned_user_id')->count(),
        ];

        $processos = $base
            ->when(
                $visao === 'para_distribuir',
                fn ($query) => $query->whereNull('assigned_user_id'),
                fn ($query) => $query->whereNotNull('assigned_user_id'),
            )
            ->with(['company', 'sector:id,name', 'assignedTo:id,name'])
            ->orderBy('analysis_due_at')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (ViabilityRequest $processo): array => [
                'id' => $processo->id,
                'protocol_number' => $processo->protocol_number,
                // Processo SEDUR e endereço (usabilidade SEDUR 19/09, item 05).
                'bap' => $processo->external_reference,
                'imovel' => implode(' - ', array_filter([
                    trim(implode(', ', array_filter([$processo->address_street, $processo->address_number]))),
                    $processo->address_neighborhood,
                ])),
                'empresa' => $processo->company?->trade_name ?: $processo->company?->legal_name,
                'cnpj' => $processo->company?->formatted_cnpj,
                'status' => $processo->status->value,
                'status_label' => $processo->status->label(),
                // Eixo operacional: a linha em vistoria distribui para a lista
                // de vistoriadores, não para a lista geral de analistas.
                'analysis_status' => $processo->analysis_status?->value,
                'analysis_stage' => $processo->analysis_stage?->value,
                'analysis_stage_label' => $processo->analysis_stage?->label(),
                'sector' => $processo->sector?->name,
                'assigned_user_id' => $processo->assigned_user_id,
                'assigned_to' => $processo->assignedTo?->name,
                'analysis_due_at' => $processo->analysis_due_at?->toIso8601String(),
                // Redistribuir só antes da conclusão (regra única do enum).
                'pode_redistribuir' => $processo->assigned_user_id !== null
                    && ($processo->analysis_status?->permiteRedistribuicao() ?? false),
            ]);

        // Só quem tramita (distribuir-processos — gestor/apoio) distribui — e só
        // para ele faz sentido carregar a lista de analistas do(s) setor(es) que
        // alimenta o seletor. O analista apenas assume, então recebe a lista
        // vazia (minimização do payload). Assumir é de quem analisa
        // (analisar-processos) — o apoio tramita, mas não assume.
        $podeDistribuir = $request->user()->can('distribuir-processos');
        $podeAssumir = $request->user()->can('analisar-processos');

        $analistas = $podeDistribuir
            ? User::query()
                ->permission('analisar-processos')
                ->whereHas('sectors', fn ($query) => $query->whereIn('sectors.id', $sectorIds))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $analista): array => [
                    'id' => $analista->id,
                    'name' => $analista->name,
                ])
                ->all()
            : [];

        // Vistoriadores do(s) setor(es): o seletor da distribuição usa ESTA
        // lista quando a linha está no eixo de vistoria (a regra é enforced
        // pelo DistribuicaoService; aqui é a conveniência da tela).
        $vistoriadores = $podeDistribuir
            ? User::query()
                ->permission('preencher-ficha-vistoria')
                ->whereHas('sectors', fn ($query) => $query->whereIn('sectors.id', $sectorIds))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $vistoriador): array => [
                    'id' => $vistoriador->id,
                    'name' => $vistoriador->name,
                ])
                ->all()
            : [];

        $this->audit->log('analise', 'consulta-caixa', 'Consulta da caixa do setor', [
            'setores' => $sectorIds->all(),
        ]);

        return Inertia::render('gestao/caixa-setor/index', [
            'processos' => $processos,
            'visao' => $visao,
            'contadores' => $contadores,
            'filtros' => [
                'per_page' => $perPage,
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'podeDistribuir' => $podeDistribuir,
            'podeAssumir' => $podeAssumir,
            'analistas' => $analistas,
            'vistoriadores' => $vistoriadores,
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
            // Motivo real da falha (nunca genérico): vínculo de setor OU a
            // regra da vistoria (só vistoriador recebe processo em vistoria).
            $motivos = collect($resumo['falhas'])->pluck('motivo')->unique()->implode(' ');

            $response->with('warning', count($resumo['falhas']).' processo(s) não distribuído(s): '.$motivos);
        }

        return $response;
    }

    /**
     * Redistribui um processo já atribuído para outra analista do setor (apoio/
     * gestor). O service bloqueia após a conclusão da análise e mantém o prazo
     * original; a falha volta como aviso controlado, nunca silenciosa.
     */
    public function redistribuir(DistribuirProcessoRequest $request): RedirectResponse
    {
        /** @var User $analista */
        $analista = User::query()->findOrFail($request->integer('analista_id'));

        $processo = ViabilityRequest::query()
            ->whereIn('id', $request->input('request_ids'))
            ->where('status', ViabilityRequestStatus::EmAnalise->value)
            ->firstOrFail();

        try {
            $this->distribuicao->redistribuir($processo, $analista, $request->user());
        } catch (DistribuicaoException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', "Processo redistribuído para {$analista->name}.");
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

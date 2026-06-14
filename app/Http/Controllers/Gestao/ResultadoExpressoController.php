<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\DecisionOutcome;
use App\Http\Controllers\Controller;
use App\Http\Resources\ViabilityDecisionResource;
use App\Models\Activity;
use App\Models\ViabilityRequest;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Consulta do resultado do fluxo expresso na retaguarda (HU-076/HU-078): lista
 * filtrável das solicitações DECIDIDAS (deferida/indeferida) e o detalhe
 * imutável da ViabilityDecision (veredito consolidado, decisão por CNAE,
 * versões de regra, fundamentação, número TVL e motivo). SOMENTE LEITURA — a
 * decisão é append-only (09-02/09-05) e o cidadão é atendido pelo Regin; aqui a
 * SEDUR audita e explica a decisão automática.
 *
 * Gate por consultar-solicitacoes (REUSO — a decisão é parte da solicitação;
 * NÃO se criou permissão nova, decisão a validar com a SEDUR). Espelha o
 * RiscoController (server-driven: filtros/paginação/auditoria da consulta).
 */
class ResultadoExpressoController extends Controller
{
    /** Itens por página aceitos — reusa o padrão do console (ui.cnaes.per_page). */
    private const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    public function __construct(private AuditService $audit) {}

    /**
     * Lista as solicitações com decisão (join em viability_decisions — só
     * decididas), com filtros por resultado, busca (protocolo/TVL) e período
     * (decided_at), ordenadas pela decisão mais recente e paginadas no servidor.
     */
    public function index(Request $request): Response
    {
        $perPage = (int) $request->input('per_page');
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true)
            ? $perPage
            : (int) Settings::get('ui.cnaes.per_page', 15);

        $outcome = $request->string('outcome')->toString();
        $outcomes = array_map(fn (DecisionOutcome $o): string => $o->value, DecisionOutcome::cases());
        $outcomeFiltro = in_array($outcome, $outcomes, true) ? $outcome : '';

        $dataDe = $this->dataFiltro($request->string('data_de')->toString());
        $dataAte = $this->dataFiltro($request->string('data_ate')->toString());

        $decisoes = ViabilityRequest::query()
            ->join('viability_decisions', 'viability_decisions.viability_request_id', '=', 'viability_requests.id')
            ->with('company')
            ->select([
                'viability_requests.*',
                'viability_decisions.outcome as decision_outcome',
                'viability_decisions.consolidated_result as decision_consolidated',
                'viability_decisions.tvl_product_number as decision_tvl',
                'viability_decisions.decided_at as decision_decided_at',
            ])
            ->when($outcomeFiltro !== '', fn ($query) => $query->where('viability_decisions.outcome', $outcomeFiltro))
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request) {
                $term = (string) $request->string('search')->trim();

                $query->where(function ($inner) use ($term) {
                    $inner->whereLike('viability_requests.protocol_number', "%{$term}%", caseSensitive: false)
                        ->orWhereLike('viability_decisions.tvl_product_number', "%{$term}%", caseSensitive: false);
                });
            })
            ->when($dataDe !== null, fn ($query) => $query->whereDate('viability_decisions.decided_at', '>=', $dataDe))
            ->when($dataAte !== null, fn ($query) => $query->whereDate('viability_decisions.decided_at', '<=', $dataAte))
            ->orderByDesc('viability_decisions.decided_at')
            ->orderByDesc('viability_decisions.id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (ViabilityRequest $solicitacao): array => [
                'id' => $solicitacao->id,
                'protocol_number' => $solicitacao->protocol_number,
                'status' => $solicitacao->status->value,
                'status_label' => $solicitacao->status->label(),
                'empresa' => $solicitacao->company?->trade_name ?: $solicitacao->company?->legal_name,
                'cnpj' => $solicitacao->company?->formatted_cnpj,
                'outcome' => $solicitacao->decision_outcome,
                'outcome_label' => DecisionOutcome::from((string) $solicitacao->decision_outcome)->label(),
                'consolidated_result' => $solicitacao->decision_consolidated,
                'tvl_product_number' => $solicitacao->decision_tvl,
                'decided_at' => $this->paraIso($solicitacao->decision_decided_at),
            ]);

        $this->audit->log(
            logName: 'expresso',
            event: 'consulta-resultado-lista',
            description: 'Consulta da lista de resultados do fluxo expresso',
            properties: [
                'resultado' => $outcomeFiltro ?: null,
                'busca' => $request->string('search')->toString() ?: null,
            ],
            result: 'sucesso',
        );

        return Inertia::render('gestao/resultados-expresso/index', [
            'decisoes' => $decisoes,
            'filtros' => [
                'search' => $request->string('search')->toString(),
                'outcome' => $outcomeFiltro,
                'data_de' => $dataDe ?? '',
                'data_ate' => $dataAte ?? '',
                'per_page' => $perPage,
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'outcomeOptions' => array_map(
                fn (DecisionOutcome $o): array => ['value' => $o->value, 'label' => $o->label()],
                DecisionOutcome::cases(),
            ),
        ]);
    }

    /**
     * Detalha a decisão imutável da solicitação (ViabilityDecisionResource) e o
     * status de transmissão Regin/SEFAZ lido da trilha de integrações. 404 se a
     * solicitação ainda não tem decisão automática (em análise técnica) — sem
     * inventar desfecho.
     */
    public function show(ViabilityRequest $viabilityRequest): Response
    {
        abort_unless($viabilityRequest->decision()->exists(), 404);

        $viabilityRequest->load(['decision.decidedBy', 'company']);

        $decision = $viabilityRequest->decision;
        $decision->setRelation('viabilityRequest', $viabilityRequest);

        $this->audit->log(
            logName: 'expresso',
            event: 'consulta-resultado',
            description: "Consulta do resultado expresso da solicitação #{$viabilityRequest->id}",
            properties: [
                'viability_request_id' => $viabilityRequest->id,
                'protocol_number' => $viabilityRequest->protocol_number,
                'outcome' => $decision->outcome->value,
            ],
            subject: $viabilityRequest,
            result: 'sucesso',
        );

        return Inertia::render('gestao/resultados-expresso/show', [
            'decisao' => (new ViabilityDecisionResource($decision))->resolve(),
            'transmissao' => $this->transmissao($viabilityRequest),
        ]);
    }

    /**
     * Status honesto de cada canal de transmissão (HU-104/HU-110) lido da
     * auditoria de integrações: o resultado mais recente por canal vira o
     * estado exibido — NUNCA "enviado" sem registro de sucesso real.
     *
     * @return array{regin: array<string, mixed>, sefaz: array<string, mixed>}
     */
    private function transmissao(ViabilityRequest $solicitacao): array
    {
        /** @var Collection<int, Activity> $activities */
        $activities = Activity::query()
            ->where('log_name', 'integracoes')
            ->where('subject_type', $solicitacao->getMorphClass())
            ->where('subject_id', $solicitacao->getKey())
            ->whereIn('event', ['regin-parecer', 'sefaz-viabilidade'])
            ->orderByDesc('id')
            ->get();

        return [
            'regin' => $this->statusCanal($activities->firstWhere('event', 'regin-parecer'), 'Regin / Junta Comercial'),
            'sefaz' => $this->statusCanal($activities->firstWhere('event', 'sefaz-viabilidade'), 'SEFAZ municipal'),
        ];
    }

    /**
     * Traduz o resultado auditado de um canal em status exibível. Sem registro,
     * o canal está aguardando processamento (não há fachada de envio).
     *
     * @return array<string, mixed>
     */
    private function statusCanal(?Activity $activity, string $canal): array
    {
        $result = $activity?->result;

        [$status, $label] = match ($result) {
            'sucesso' => ['transmitido', 'Transmitido'],
            'bloqueado' => ['pendente', 'Pendente — canal bloqueado (sem contrato/homologação)'],
            'ignorado' => ['nao_aplicavel', 'Não se aplica a este resultado'],
            default => ['aguardando', 'Aguardando processamento'],
        };

        return [
            'canal' => $canal,
            'status' => $status,
            'label' => $label,
            'result' => $result,
            'registrado_em' => $activity?->created_at?->toIso8601String(),
        ];
    }

    /**
     * Normaliza um filtro de data (YYYY-MM-DD). Strings inválidas viram null
     * (filtro ignorado), evitando erro de driver no whereDate.
     */
    private function dataFiltro(string $valor): ?string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) !== 1) {
            return null;
        }

        return $valor;
    }

    /**
     * Converte a data da decisão (string crua vinda do join, não casteada) para
     * ISO-8601 — o front formata para o fuso pt-BR.
     */
    private function paraIso(?string $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return Carbon::parse($valor)->toIso8601String();
    }
}

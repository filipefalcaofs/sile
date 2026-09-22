<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\AnalysisStatus;
use App\Http\Controllers\Controller;
use App\Models\FineMeshReferral;
use App\Models\ViabilityRequest;
use App\Models\ViabilityServiceType;
use App\Services\Analise\ProcessoQueryService;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Caixa de Malha Fina — a fila do revisor. Universo: processos com
 * in_fine_mesh (≡ existe encaminhamento aberto — invariante do
 * MalhaFinaService, ortogonal ao status, HU-136). Abas: Em malha fina
 * (abertos) e Concluídas (saíram da malha fina com baixa registrada — quem
 * baixou, quando e a observação). Filtros são os das demais caixas
 * (ProcessoQueryService); KPIs derivados do universo. NENHUMA regra nova de
 * entrada: quem coloca o processo aqui é o encaminhar (humano, abuso ou
 * auditoria preditiva). Gated por analisar-malha-fina; a consulta é auditada.
 */
class MalhaFinaCaixaController extends Controller
{
    /** Itens por página aceitos — padrão do console (ui.cnaes.per_page). */
    private const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    public function __construct(
        private AuditService $audit,
        private ProcessoQueryService $processos,
    ) {}

    public function __invoke(Request $request): Response
    {
        $aba = $request->input('aba') === 'concluidas' ? 'concluidas' : 'abertas';

        $perPage = (int) $request->input('per_page');
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true)
            ? $perPage
            : (int) Settings::get('ui.cnaes.per_page', 15);

        $filtros = $this->filtrosDaCaixa($request);

        $abertos = ViabilityRequest::query()->where('in_fine_mesh', true);
        $concluidos = ViabilityRequest::query()
            ->where('in_fine_mesh', false)
            ->whereHas('fineMeshReferrals', fn (Builder $q) => $q->whereNotNull('resolved_at'));

        $kpis = [
            'em_malha_fina' => (clone $abertos)->count(),
            'entradas_mes' => FineMeshReferral::query()->where('created_at', '>=', now()->startOfMonth())->count(),
            'concluidas_mes' => FineMeshReferral::query()
                ->whereNotNull('resolved_at')
                ->where('resolved_at', '>=', now()->startOfMonth())
                ->count(),
            'prazo_vencido' => (clone $abertos)->where('analysis_due_at', '<', now())->count(),
        ];

        $consulta = $this->processos->aplicarFiltros(
            $aba === 'concluidas' ? $concluidos : $abertos,
            $filtros,
        );

        $processos = $consulta
            ->with([
                'company',
                'requester:id,name',
                'sector:id,name',
                'assignedTo:id,name',
                'serviceType:id,name',
                'fineMeshReferrals' => fn ($q) => $q
                    ->with(['referredBy:id,name', 'resolvedBy:id,name'])
                    ->orderByDesc('id'),
            ])
            ->orderBy('analysis_due_at')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (ViabilityRequest $processo): array => $this->linha($processo, $aba));

        $this->audit->log('analise', 'malha-fina-consulta', 'Consulta da caixa de malha fina', [
            'aba' => $aba,
            'filtros' => array_filter($filtros, fn ($v): bool => $v !== ''),
            'total' => $processos->total(),
        ]);

        return Inertia::render('gestao/malha-fina/index', [
            'processos' => $processos,
            'kpis' => $kpis,
            'abas' => [
                ['id' => 'abertas', 'label' => 'Em malha fina', 'total' => $kpis['em_malha_fina']],
                ['id' => 'concluidas', 'label' => 'Concluídas', 'total' => (clone $concluidos)->count()],
            ],
            'filtros' => $filtros + ['aba' => $aba, 'per_page' => $perPage],
            'servicoOptions' => $this->servicoOptions(),
            'analysisStatusOptions' => AnalysisStatus::options(),
            'categoriaOptions' => $this->categoriaOptions(),
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    /**
     * Linha da caixa. Aba abertas: o encaminhamento ABERTO mais recente (quem
     * encaminhou, motivo, quando entrou). Aba concluídas: a baixa mais
     * recente (quem baixou, quando, observação). O processo é sempre o
     * processo original — a linha aponta para a ficha de análise existente.
     *
     * @return array<string, mixed>
     */
    private function linha(ViabilityRequest $processo, string $aba): array
    {
        $encaminhamento = $aba === 'concluidas'
            ? $processo->fineMeshReferrals->first(fn (FineMeshReferral $r): bool => $r->resolved_at !== null)
            : $processo->fineMeshReferrals->first(fn (FineMeshReferral $r): bool => $r->resolved_at === null);

        return [
            'id' => $processo->id,
            'protocol_number' => $processo->protocol_number,
            'bap' => $processo->external_reference,
            'protocoled_at' => $processo->protocoled_at?->toIso8601String(),
            'empresa' => $processo->company?->trade_name ?: $processo->company?->legal_name,
            'requerente' => $processo->requester?->name,
            'servico' => $processo->serviceType?->name,
            'status' => $processo->status->value,
            'status_label' => $processo->status->label(),
            'analysis_status' => $processo->analysis_status?->value,
            'analysis_status_label' => $processo->analysis_status?->label(),
            'setor' => $processo->sector?->name,
            'responsavel' => $processo->assignedTo?->name,
            'analysis_due_at' => $processo->analysis_due_at?->toIso8601String(),
            'entrada_malha_fina' => $encaminhamento?->created_at?->toIso8601String(),
            'encaminhado_por' => $encaminhamento?->referredBy?->name,
            'motivo' => $encaminhamento?->reason,
            'concluido_em' => $encaminhamento?->resolved_at?->toIso8601String(),
            'concluido_por' => $encaminhamento?->resolvedBy?->name,
            'observacao' => $encaminhamento?->resolution_note,
            'ficha_url' => route('gestao.processos.ficha.show', ['viabilityRequest' => $processo->id]),
        ];
    }

    /**
     * Filtros de pesquisa da caixa (subconjunto do SAPS), crus da query
     * string — o ProcessoQueryService normaliza e ignora os vazios.
     *
     * @return array<string, string>
     */
    private function filtrosDaCaixa(Request $request): array
    {
        $filtros = [];

        foreach (ProcessoQueryService::CHAVES_FILTRO_CAIXA as $chave) {
            $filtros[$chave] = $request->string($chave)->toString();
        }

        return $filtros;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function servicoOptions(): array
    {
        return ViabilityServiceType::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($servico): array => ['value' => (string) $servico->id, 'label' => $servico->name])
            ->all();
    }

    /**
     * Categorias da caixa SEM "Malha Fina" — redundante aqui: tudo na aba
     * abertas já é malha fina por definição (e na aba concluídas, nada é).
     *
     * @return list<array{value: string, label: string}>
     */
    private function categoriaOptions(): array
    {
        $opcoes = [];

        foreach (ProcessoQueryService::CATEGORIAS as $value => $label) {
            if ($value === 'malha_fina') {
                continue;
            }

            $opcoes[] = ['value' => $value, 'label' => $label];
        }

        return $opcoes;
    }
}

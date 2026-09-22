<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\AnalysisStatus;
use App\Enums\InspectionStatus;
use App\Http\Controllers\Controller;
use App\Models\ViabilityRequest;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Consulta de vistorias — a fila de trabalho do vistoriador. Universo: os
 * processos no eixo operacional de vistoria (AnalysisStatus Vistoriar/
 * Vistoriado). A situação é DERIVADA da ficha (nunca inventada): a designar
 * (ninguém abriu a ficha), em campo (ficha em preenchimento), concluída.
 * KPIs do universo total; abas e busca server-driven. Gated por
 * preencher-ficha-vistoria; a consulta é auditada (RN-002).
 */
class VistoriaConsultaController extends Controller
{
    /** Itens por página aceitos — padrão do console (ui.cnaes.per_page). */
    private const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    public function __construct(private AuditService $audit) {}

    public function __invoke(Request $request): Response
    {
        $situacao = (string) $request->input('situacao', 'todas');

        if (! in_array($situacao, ['todas', 'designar', 'campo', 'concluida'], true)) {
            $situacao = 'todas';
        }

        $busca = trim((string) $request->input('busca', ''));
        $perPage = (int) $request->input('per_page');
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true)
            ? $perPage
            : (int) Settings::get('ui.cnaes.per_page', 15);

        $universo = ViabilityRequest::query()
            ->whereIn('analysis_status', [AnalysisStatus::Vistoriar->value, AnalysisStatus::Vistoriado->value]);

        $kpis = $this->kpis($universo);

        $consulta = (clone $universo)
            ->with(['company', 'sector:id,name', 'assignedTo:id,name', 'inspection.vistoriador:id,name']);

        $this->aplicarSituacao($consulta, $situacao);
        $this->aplicarBusca($consulta, $busca);

        $vistorias = $consulta
            ->orderBy('analysis_due_at')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (ViabilityRequest $processo): array => $this->linha($processo));

        $this->audit->log(
            logName: 'vistoria',
            event: 'consulta',
            description: 'Consulta de vistorias',
            properties: [
                'situacao' => $situacao,
                'busca' => $busca !== '' ? $busca : null,
                'total' => $vistorias->total(),
            ],
        );

        return Inertia::render('gestao/vistorias/index', [
            'vistorias' => $vistorias,
            'kpis' => $kpis,
            'abas' => [
                ['id' => 'todas', 'label' => 'Todas', 'total' => $kpis['encaminhadas']],
                ['id' => 'designar', 'label' => 'A designar', 'total' => $kpis['a_designar']],
                ['id' => 'campo', 'label' => 'Em campo', 'total' => $kpis['em_campo']],
                ['id' => 'concluida', 'label' => 'Concluídas', 'total' => $kpis['concluidas']],
            ],
            'filtros' => [
                'situacao' => $situacao,
                'busca' => $busca,
                'per_page' => $perPage,
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    /**
     * KPIs do universo total (não do recorte filtrado) — o painel mostra a
     * fila inteira mesmo com aba/busca aplicadas.
     *
     * @return array{encaminhadas: int, a_designar: int, em_campo: int, concluidas: int, concluidas_mes: int, prazo_vencido: int}
     */
    private function kpis(Builder $universo): array
    {
        $emPreenchimento = InspectionStatus::EmPreenchimento->value;
        $concluida = InspectionStatus::Concluida->value;

        return [
            'encaminhadas' => (clone $universo)->count(),
            'a_designar' => (clone $universo)->whereDoesntHave('inspection')->count(),
            'em_campo' => (clone $universo)->whereHas(
                'inspection',
                fn (Builder $q) => $q->where('status', $emPreenchimento),
            )->count(),
            'concluidas' => (clone $universo)->whereHas(
                'inspection',
                fn (Builder $q) => $q->where('status', $concluida),
            )->count(),
            'concluidas_mes' => (clone $universo)->whereHas(
                'inspection',
                fn (Builder $q) => $q->where('status', $concluida)
                    ->where('concluded_at', '>=', now()->startOfMonth()),
            )->count(),
            // Prazo vencido = SLA do processo estourado e a vistoria ainda não
            // concluída (sem ficha ou ficha em preenchimento).
            'prazo_vencido' => (clone $universo)
                ->where('analysis_due_at', '<', now())
                ->where(function (Builder $q) use ($concluida): void {
                    $q->whereDoesntHave('inspection')
                        ->orWhereHas('inspection', fn (Builder $f) => $f->where('status', '!=', $concluida));
                })
                ->count(),
        ];
    }

    private function aplicarSituacao(Builder $consulta, string $situacao): void
    {
        match ($situacao) {
            'designar' => $consulta->whereDoesntHave('inspection'),
            'campo' => $consulta->whereHas(
                'inspection',
                fn (Builder $q) => $q->where('status', InspectionStatus::EmPreenchimento->value),
            ),
            'concluida' => $consulta->whereHas(
                'inspection',
                fn (Builder $q) => $q->where('status', InspectionStatus::Concluida->value),
            ),
            default => null,
        };
    }

    private function aplicarBusca(Builder $consulta, string $busca): void
    {
        if ($busca === '') {
            return;
        }

        $consulta->where(function (Builder $q) use ($busca): void {
            $q->where('protocol_number', 'like', "%{$busca}%")
                ->orWhere('external_reference', 'like', "%{$busca}%")
                ->orWhere('address_street', 'like', "%{$busca}%")
                ->orWhere('address_neighborhood', 'like', "%{$busca}%")
                ->orWhereHas('company', function (Builder $c) use ($busca): void {
                    $c->where('legal_name', 'like', "%{$busca}%")
                        ->orWhere('trade_name', 'like', "%{$busca}%");
                })
                ->orWhereHas('inspection.vistoriador', function (Builder $v) use ($busca): void {
                    $v->where('name', 'like', "%{$busca}%");
                });
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function linha(ViabilityRequest $processo): array
    {
        $ficha = $processo->inspection;

        [$situacao, $situacaoLabel] = match (true) {
            $ficha === null => ['designar', 'A designar'],
            $ficha->status === InspectionStatus::Concluida => ['concluida', 'Concluída'],
            default => ['em_campo', 'Em campo'],
        };

        return [
            'id' => $processo->id,
            'protocol_number' => $processo->protocol_number,
            'bap' => $processo->external_reference,
            'empresa' => $processo->company?->trade_name ?: $processo->company?->legal_name,
            'imovel' => implode(' - ', array_filter([
                trim(implode(', ', array_filter([$processo->address_street, $processo->address_number]))),
                $processo->address_neighborhood,
            ])),
            'zona' => $processo->zona_codigo,
            'setor' => $processo->sector?->name,
            'situacao' => $situacao,
            'situacao_label' => $situacaoLabel,
            'vistoriador' => $ficha?->vistoriador?->name,
            'responsavel' => $processo->assignedTo?->name,
            'analysis_due_at' => $processo->analysis_due_at?->toIso8601String(),
            'ficha_url' => route('gestao.processos.vistoria.show', ['viabilityRequest' => $processo->id]),
        ];
    }
}

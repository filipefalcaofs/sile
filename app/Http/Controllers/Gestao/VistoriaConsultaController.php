<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\AnalysisStatus;
use App\Enums\InspectionStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestEvento;
use App\Models\ViabilityServiceType;
use App\Services\Analise\DistribuicaoService;
use App\Services\Analise\ProcessoQueryService;
use App\Services\Vistoria\ConsultaVistoriaPdfService;
use App\Services\Vistoria\VistoriaAcesso;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Consulta de vistorias — a fila de trabalho do vistoriador. Universo: os
 * processos no eixo operacional de vistoria (AnalysisStatus Vistoriar/
 * Vistoriado). A situação é DERIVADA da ficha (nunca inventada): a designar
 * (ninguém abriu a ficha), em campo (ficha em preenchimento), concluída.
 * KPIs do universo total; abas e busca server-driven. No padrão da caixa do
 * setor: abas Para distribuir e Distribuídos, os filtros das caixas e a
 * distribuição aos vistoriadores (a gravação é do POST da caixa do setor).
 * Gated por preencher-ficha-vistoria ou distribuir-processos; a consulta é
 * auditada (RN-002).
 */
class VistoriaConsultaController extends Controller
{
    /** Itens por página aceitos — padrão do console (ui.cnaes.per_page). */
    private const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    public function __construct(
        private AuditService $audit,
        private ProcessoQueryService $processos,
        private DistribuicaoService $distribuicao,
        private ConsultaVistoriaPdfService $pdf,
        private VistoriaAcesso $acesso,
    ) {}

    /**
     * PDF da capa de consulta para levar à visita: só processo no eixo de
     * vistoria ou com ficha. Somente leitura; a emissão é auditada (RN-002).
     */
    public function imprimir(Request $request, ViabilityRequest $viabilityRequest): HttpResponse
    {
        $naVistoria = in_array($viabilityRequest->analysis_status, [AnalysisStatus::Vistoriar, AnalysisStatus::Vistoriado], true)
            || $viabilityRequest->inspection()->exists();

        abort_unless($naVistoria, 404);
        abort_unless($this->acesso->podeConsultar($viabilityRequest, $request->user()), 403);

        $this->audit->log(
            logName: 'vistoria',
            event: 'consulta-imprimir',
            description: "Impressão da consulta do processo #{$viabilityRequest->id} para vistoria",
            properties: [
                'viability_request_id' => $viabilityRequest->id,
                'protocol_number' => $viabilityRequest->protocol_number,
            ],
            subject: $viabilityRequest,
            personalData: true,
        );

        return $this->pdf->render($viabilityRequest, $request->user())
            ->download($this->pdf->nomeDoArquivo($viabilityRequest));
    }

    public function __invoke(Request $request): Response
    {
        $situacao = (string) $request->input('situacao', 'todas');

        if (! in_array($situacao, ['todas', 'designar', 'distribuidos', 'campo', 'concluida'], true)) {
            $situacao = 'todas';
        }

        $busca = trim((string) $request->input('busca', ''));
        $perPage = (int) $request->input('per_page');
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true)
            ? $perPage
            : (int) Settings::get('ui.cnaes.per_page', 15);

        // Universo: processos no eixo de vistoria OU com ficha de vistoria —
        // a ficha concluída e devolvida à análise (retorno automático) continua
        // visível em "Concluídas" mesmo fora do eixo.
        $universo = ViabilityRequest::query()
            ->where(function (Builder $q): void {
                $q->whereIn('analysis_status', [AnalysisStatus::Vistoriar->value, AnalysisStatus::Vistoriado->value])
                    ->orWhereHas('inspection');
            });

        // Quem não distribui vê só os processos dele, como na Caixa de entrada.
        $universo = $this->acesso->escopar($universo, $request->user());

        $filtros = $this->filtrosDaCaixa($request);

        $universo = $this->processos->aplicarFiltros($universo, $filtros);

        $kpis = $this->kpis($universo);

        $consulta = (clone $universo)
            ->with(['company', 'sector:id,name', 'assignedTo:id,name', 'inspection.vistoriador:id,name', 'serviceType:id,name', 'eventos']);

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
                'filtros' => array_filter($filtros, fn ($valor): bool => $valor !== ''),
                'total' => $vistorias->total(),
            ],
        );

        $usuario = $request->user();
        $podeDistribuir = $usuario->can('distribuir-processos');
        $qualquerSetor = $this->distribuicao->distribuiEmQualquerSetor($usuario);

        return Inertia::render('gestao/vistorias/index', [
            'vistorias' => $vistorias,
            'kpis' => $kpis,
            'abas' => array_values(array_filter([
                ['id' => 'todas', 'label' => 'Todas', 'total' => $kpis['encaminhadas']],
                $podeDistribuir ? ['id' => 'designar', 'label' => 'Para distribuir', 'total' => $kpis['a_designar']] : null,
                ['id' => 'distribuidos', 'label' => 'Distribuídos', 'total' => $kpis['distribuidos']],
                ['id' => 'campo', 'label' => 'Em campo', 'total' => $kpis['em_campo']],
                ['id' => 'concluida', 'label' => 'Concluídas', 'total' => $kpis['concluidas']],
            ])),
            'filtros' => $filtros + [
                'situacao' => $situacao,
                'busca' => $busca,
                'per_page' => $perPage,
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'servicoOptions' => $this->servicoOptions(),
            'analysisStatusOptions' => AnalysisStatus::options(),
            'categoriaOptions' => $this->categoriaOptions(),
            'analistaOptions' => $this->processos->analistasParaFiltro($usuario),
            'podeDistribuir' => $podeDistribuir,
            'distribuiEmQualquerSetor' => $qualquerSetor,
            'vistoriadores' => $podeDistribuir ? $this->vistoriadores($usuario, $qualquerSetor) : [],
        ]);
    }

    /**
     * KPIs do universo total (não do recorte filtrado) — o painel mostra a
     * fila inteira mesmo com aba/busca aplicadas.
     *
     * @return array{encaminhadas: int, a_designar: int, distribuidos: int, em_campo: int, concluidas: int, concluidas_mes: int, prazo_vencido: int}
     */
    private function kpis(Builder $universo): array
    {
        $emPreenchimento = InspectionStatus::EmPreenchimento->value;
        $concluida = InspectionStatus::Concluida->value;

        return [
            'encaminhadas' => (clone $universo)->count(),
            'a_designar' => $this->semVistoriador(clone $universo)->count(),
            'distribuidos' => $this->comVistoriadorAtribuido(clone $universo)->count(),
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
            'designar' => $this->semVistoriador($consulta),
            'distribuidos' => $this->comVistoriadorAtribuido($consulta),
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

    /**
     * Para distribuir: ninguém abriu a ficha e nenhum vistoriador recebeu o processo.
     *
     * @param  Builder<ViabilityRequest>  $consulta
     * @return Builder<ViabilityRequest>
     */
    private function semVistoriador(Builder $consulta): Builder
    {
        return $consulta->whereDoesntHave('inspection')->whereNull('assigned_user_id');
    }

    /**
     * Distribuídos: o processo já foi entregue a um vistoriador e a ficha ainda não foi concluída.
     *
     * @param  Builder<ViabilityRequest>  $consulta
     * @return Builder<ViabilityRequest>
     */
    private function comVistoriadorAtribuido(Builder $consulta): Builder
    {
        return $consulta
            ->whereNotNull('assigned_user_id')
            ->where(fn (Builder $q) => $q
                ->whereDoesntHave('inspection')
                ->orWhereHas('inspection', fn (Builder $f) => $f->where('status', '!=', InspectionStatus::Concluida->value)));
    }

    /**
     * Filtros de pesquisa das caixas, crus da query string — o
     * ProcessoQueryService normaliza e ignora os vazios.
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
     * Vistoriadores que recebem o processo: os do(s) setor(es) do usuário, ou
     * todos para quem distribui em qualquer setor.
     *
     * @return list<array{id: int, name: string, setores: list<array{id: int, name: string}>}>
     */
    private function vistoriadores(User $usuario, bool $qualquerSetor): array
    {
        $setores = $usuario->sectors()->pluck('sectors.id');

        return User::query()
            ->permission('preencher-ficha-vistoria')
            ->role(['Vistoriador', 'administrador'])
            ->when(! $qualquerSetor, fn (Builder $q) => $q->whereHas('sectors', fn ($s) => $s->whereIn('sectors.id', $setores)))
            ->with('sectors:id,name')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $vistoriador): array => [
                'id' => $vistoriador->id,
                'name' => $vistoriador->name,
                'setores' => $vistoriador->sectors
                    ->sortBy('name')
                    ->map(fn ($setor): array => ['id' => $setor->id, 'name' => $setor->name])
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function servicoOptions(): array
    {
        $eventos = ViabilityRequestEvento::query()
            ->selectRaw('codigo, min(nome) as nome')
            ->groupBy('codigo')
            ->orderBy('nome')
            ->get()
            ->map(fn ($evento): array => ['value' => ProcessoQueryService::PREFIXO_EVENTO.$evento->codigo, 'label' => $evento->nome]);

        $tipos = ViabilityServiceType::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($servico): array => ['value' => (string) $servico->id, 'label' => $servico->name]);

        return $eventos->concat($tipos)->values()->all();
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
            $ficha === null && $processo->assigned_user_id === null => ['designar', 'Para distribuir'],
            $ficha === null => ['distribuido', 'Distribuído'],
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
            'servicos' => $processo->eventos->isNotEmpty()
                ? $processo->eventos->pluck('nome')->values()->all()
                : array_values(array_filter([$processo->serviceType?->name])),
            'zona' => $processo->zona_codigo,
            'setor' => $processo->sector?->name,
            'sector_id' => $processo->sector_id,
            'situacao' => $situacao,
            'situacao_label' => $situacaoLabel,
            'pode_redistribuir' => in_array($situacao, ['distribuido', 'em_campo'], true),
            'vistoriador' => $ficha?->vistoriador?->name,
            'responsavel' => $processo->assignedTo?->name,
            'analysis_due_at' => $processo->analysis_due_at?->toIso8601String(),
            'ficha_url' => route('gestao.processos.vistoria.show', ['viabilityRequest' => $processo->id]),
            'consulta_url' => route('gestao.vistorias.consulta', ['viabilityRequest' => $processo->id]),
        ];
    }
}

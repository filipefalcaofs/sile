<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\AbuseAlertStatus;
use App\Enums\AbuseSeverity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\ResolverAbuseAlertRequest;
use App\Models\PredictiveAnomaly;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Painel humano da Auditoria Preditiva de Processos Expressos (Módulo 3) — REVISÃO,
 * nunca punição. Lista filtrável/paginada das anomalias (espelha o AbusoController),
 * o indicador de EFETIVIDADE (confirmadas ÷ geradas) para calibrar o modelo, e as
 * ações de confirmar/descartar com justificativa OBRIGATÓRIA (reuso de
 * {@see ResolverAbuseAlertRequest}), tudo auditado (RN-002). ANTI-FACHADA: confirmar/
 * descartar muda SÓ o status da ANOMALIA — NUNCA transiciona o status do processo,
 * indefere/cassa nem mexe na malha fina já criada (ortogonal). Gated por
 * gerenciar-alertas-abuso (mesma governança antifraude); 403 auditado no ponto único.
 */
class AuditoriaPreditivaController extends Controller
{
    /** @var list<int> */
    private const PER_PAGE_OPTIONS = [10, 20, 50, 100];

    public function __construct(
        private AuditService $audit,
    ) {}

    public function index(Request $request): Response
    {
        $perPage = $this->perPage($request);

        $severity = $request->string('severity')->toString();
        $severities = array_map(fn (AbuseSeverity $s): string => $s->value, AbuseSeverity::cases());
        $severityFiltro = in_array($severity, $severities, true) ? $severity : '';

        $status = $request->string('status')->toString();
        $statuses = array_map(fn (AbuseAlertStatus $s): string => $s->value, AbuseAlertStatus::cases());
        $statusFiltro = in_array($status, $statuses, true) ? $status : '';

        $dataDe = $this->dataFiltro($request->string('data_de')->toString());
        $dataAte = $this->dataFiltro($request->string('data_ate')->toString());

        $anomalias = PredictiveAnomaly::query()
            ->with(['viabilityRequest', 'resolvedBy'])
            ->when($severityFiltro !== '', fn ($query) => $query->where('severity', $severityFiltro))
            ->when($statusFiltro !== '', fn ($query) => $query->where('status', $statusFiltro))
            ->when($dataDe !== null, fn ($query) => $query->whereDate('detected_at', '>=', $dataDe))
            ->when($dataAte !== null, fn ($query) => $query->whereDate('detected_at', '<=', $dataAte))
            ->orderByDesc('score')
            ->orderByDesc('detected_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (PredictiveAnomaly $anomalia): array => $this->map($anomalia));

        $this->audit->log(
            logName: 'ia',
            event: 'consulta-auditoria-preditiva',
            description: 'Consulta do painel de auditoria preditiva',
            properties: ['filtros' => array_filter([
                'severity' => $severityFiltro ?: null,
                'status' => $statusFiltro ?: null,
                'data_de' => $dataDe,
                'data_ate' => $dataAte,
            ], fn ($valor): bool => $valor !== null && $valor !== '')],
        );

        return Inertia::render('gestao/auditoria-preditiva/index', [
            'anomalias' => $anomalias,
            'efetividade' => $this->efetividade(),
            'ligado' => Settings::enabled('ia_auditoria_preditiva'),
            'filtros' => [
                'severity' => $severityFiltro,
                'status' => $statusFiltro,
                'data_de' => $dataDe ?? '',
                'data_ate' => $dataAte ?? '',
                'per_page' => $perPage,
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'severityOptions' => array_map(
                fn (AbuseSeverity $s): array => ['value' => $s->value, 'label' => $s->label()],
                AbuseSeverity::cases(),
            ),
            'statusOptions' => array_map(
                fn (AbuseAlertStatus $s): array => ['value' => $s->value, 'label' => $s->label()],
                AbuseAlertStatus::cases(),
            ),
        ]);
    }

    public function confirmar(ResolverAbuseAlertRequest $request, PredictiveAnomaly $predictiveAnomaly): RedirectResponse
    {
        return $this->resolver($request, $predictiveAnomaly, AbuseAlertStatus::Confirmado);
    }

    public function descartar(ResolverAbuseAlertRequest $request, PredictiveAnomaly $predictiveAnomaly): RedirectResponse
    {
        return $this->resolver($request, $predictiveAnomaly, AbuseAlertStatus::Descartado);
    }

    /**
     * Resolução humana: grava status + resolvedBy/resolvedAt + justificativa na
     * ANOMALIA e audita. ANTI-FACHADA: não transiciona o processo, não indefere/
     * cassa e não mexe na malha fina já criada (ortogonal).
     */
    private function resolver(ResolverAbuseAlertRequest $request, PredictiveAnomaly $anomalia, AbuseAlertStatus $status): RedirectResponse
    {
        $justificativa = (string) $request->validated('justification');

        $anomalia->update([
            'status' => $status,
            'resolved_by_user_id' => $request->user()->id,
            'resolved_at' => now(),
            'justification' => $justificativa,
        ]);

        [$event, $rotulo] = $status === AbuseAlertStatus::Confirmado
            ? ['confirmar-anomalia-preditiva', 'confirmada']
            : ['descartar-anomalia-preditiva', 'descartada'];

        $this->audit->log(
            logName: 'ia',
            event: $event,
            description: "Anomalia preditiva #{$anomalia->id} {$rotulo} (processo #{$anomalia->viability_request_id}).",
            properties: [
                'predictive_anomaly_id' => $anomalia->id,
                'viability_request_id' => $anomalia->viability_request_id,
                'status' => $status->value,
                'justification' => $justificativa,
            ],
            subject: $anomalia,
        );

        return back()->with('status', "Anomalia {$rotulo}.");
    }

    /**
     * Efetividade do modelo (confirmadas ÷ geradas), para calibração. Sem
     * anomalias, a taxa é null — nunca um número fabricado.
     *
     * @return array{gerados: int, confirmados: int, descartados: int, abertos: int, taxa: float|null}
     */
    private function efetividade(): array
    {
        $contagens = PredictiveAnomaly::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->get();

        $resumo = ['gerados' => 0, 'confirmados' => 0, 'descartados' => 0, 'abertos' => 0];

        foreach ($contagens as $linha) {
            $status = $linha->status instanceof AbuseAlertStatus ? $linha->status : AbuseAlertStatus::from((string) $linha->status);
            $total = (int) $linha->total;

            $balde = match ($status) {
                AbuseAlertStatus::Confirmado => 'confirmados',
                AbuseAlertStatus::Descartado => 'descartados',
                AbuseAlertStatus::Aberto => 'abertos',
            };

            $resumo['gerados'] += $total;
            $resumo[$balde] += $total;
        }

        return $resumo + [
            'taxa' => $resumo['gerados'] > 0 ? round($resumo['confirmados'] / $resumo['gerados'] * 100, 1) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function map(PredictiveAnomaly $anomalia): array
    {
        return [
            'id' => $anomalia->id,
            'score' => $anomalia->score,
            'severity' => $anomalia->severity->value,
            'severity_label' => $anomalia->severity->label(),
            'status' => $anomalia->status->value,
            'status_label' => $anomalia->status->label(),
            'factors' => $anomalia->factors ?? [],
            'protocolo' => $anomalia->viabilityRequest?->protocol_number,
            'viability_request_id' => $anomalia->viability_request_id,
            'encaminhado_malha_fina' => $anomalia->fine_mesh_referral_id !== null,
            'detected_at' => $anomalia->detected_at?->toIso8601String(),
            'resolvido_por' => $anomalia->resolvedBy?->name,
            'resolved_at' => $anomalia->resolved_at?->toIso8601String(),
            'justificativa' => $anomalia->justification,
        ];
    }

    private function perPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page');

        return in_array($perPage, self::PER_PAGE_OPTIONS, true)
            ? $perPage
            : (int) Settings::get('ui.auditoria.per_page', 20);
    }

    private function dataFiltro(string $valor): ?string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) !== 1) {
            return null;
        }

        return $valor;
    }
}

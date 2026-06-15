<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\AbuseAlertStatus;
use App\Enums\AbuseSeverity;
use App\Http\Controllers\Controller;
use App\Http\Resources\AbuseAlertResource;
use App\Models\AbuseAlert;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Painel humano de alertas de abuso (HU-149) na retaguarda — revisão, NUNCA
 * punição. Lista filtrável e paginada dos abuse_alerts (server-driven, espelha o
 * ResultadoExpressoController), o indicador de EFETIVIDADE (confirmados ÷ gerados,
 * geral e por regra, sobre os alertas reais — RN-005) para calibrar regras, e as
 * ações de confirmar/descartar com justificativa OBRIGATÓRIA (RN-003), tudo
 * auditado (RN-002). Anti-fachada CA-02: confirmar/descartar muda SÓ o status do
 * ALERTA — NUNCA transiciona o status do processo, indefere/cassa nem mexe na
 * malha fina já criada pelo motor (ortogonal). Gated por gerenciar-alertas-abuso;
 * o 403 é auditado no ponto único (bootstrap/app.php). Somente leitura +
 * resolução; o motor (12-06/12-08) não é tocado. Tela em 12-11.
 */
class AbusoController extends Controller
{
    /** Itens por página aceitos — inclui o default parametrizável (ui.auditoria.per_page = 20). */
    private const PER_PAGE_OPTIONS = [10, 20, 50, 100];

    public function __construct(
        private AuditService $audit,
    ) {}

    /**
     * Lista os alertas com filtros (rule_key, severity, status, período por
     * detected_at), paginados no servidor e mapeados pelo AbuseAlertResource,
     * mais o indicador de efetividade (global — calibração de regras, RN-005). A
     * consulta é auditada (RN-002).
     */
    public function index(Request $request): Response
    {
        $perPage = $this->perPage($request);

        $ruleKey = $request->string('rule_key')->trim()->toString();

        $severity = $request->string('severity')->toString();
        $severities = array_map(fn (AbuseSeverity $s): string => $s->value, AbuseSeverity::cases());
        $severityFiltro = in_array($severity, $severities, true) ? $severity : '';

        $status = $request->string('status')->toString();
        $statuses = array_map(fn (AbuseAlertStatus $s): string => $s->value, AbuseAlertStatus::cases());
        $statusFiltro = in_array($status, $statuses, true) ? $status : '';

        $dataDe = $this->dataFiltro($request->string('data_de')->toString());
        $dataAte = $this->dataFiltro($request->string('data_ate')->toString());

        $alertas = AbuseAlert::query()
            ->with(['viabilityRequest', 'resolvedBy'])
            ->when($ruleKey !== '', fn ($query) => $query->whereLike('rule_key', "%{$ruleKey}%", caseSensitive: false))
            ->when($severityFiltro !== '', fn ($query) => $query->where('severity', $severityFiltro))
            ->when($statusFiltro !== '', fn ($query) => $query->where('status', $statusFiltro))
            ->when($dataDe !== null, fn ($query) => $query->whereDate('detected_at', '>=', $dataDe))
            ->when($dataAte !== null, fn ($query) => $query->whereDate('detected_at', '<=', $dataAte))
            ->orderByDesc('detected_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (AbuseAlert $alerta): array => (new AbuseAlertResource($alerta))->resolve());

        $this->audit->log(
            logName: 'abuso',
            event: 'consulta-alertas',
            description: 'Consulta do painel de alertas de abuso',
            properties: ['filtros' => array_filter([
                'rule_key' => $ruleKey ?: null,
                'severity' => $severityFiltro ?: null,
                'status' => $statusFiltro ?: null,
                'data_de' => $dataDe,
                'data_ate' => $dataAte,
            ], fn ($valor): bool => $valor !== null && $valor !== '')],
        );

        return Inertia::render('gestao/abuso/index', [
            'alertas' => $alertas,
            'efetividade' => $this->efetividade(),
            'filtros' => [
                'rule_key' => $ruleKey,
                'severity' => $severityFiltro,
                'status' => $statusFiltro,
                'data_de' => $dataDe ?? '',
                'data_ate' => $dataAte ?? '',
                'per_page' => $perPage,
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'ruleKeyOptions' => $this->ruleKeyOptions(),
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

    /**
     * Efetividade da detecção (RN-005): confirmados ÷ gerados, geral e por
     * rule_key, calculada sobre TODOS os alertas reais (calibração de regras,
     * independente dos filtros da lista). Sem alertas, a taxa é null — nunca um
     * número inventado.
     *
     * @return array{geral: array<string, mixed>, por_regra: list<array<string, mixed>>}
     */
    private function efetividade(): array
    {
        $contagens = AbuseAlert::query()
            ->selectRaw('rule_key, status, count(*) as total')
            ->groupBy('rule_key', 'status')
            ->get();

        $geral = ['gerados' => 0, 'confirmados' => 0, 'descartados' => 0, 'abertos' => 0];

        /** @var array<string, array{gerados: int, confirmados: int, descartados: int, abertos: int}> $porRegra */
        $porRegra = [];

        foreach ($contagens as $linha) {
            $ruleKey = (string) $linha->rule_key;
            $total = (int) $linha->total;
            $status = $linha->status instanceof AbuseAlertStatus ? $linha->status : AbuseAlertStatus::from((string) $linha->status);

            $porRegra[$ruleKey] ??= ['gerados' => 0, 'confirmados' => 0, 'descartados' => 0, 'abertos' => 0];

            $balde = match ($status) {
                AbuseAlertStatus::Confirmado => 'confirmados',
                AbuseAlertStatus::Descartado => 'descartados',
                AbuseAlertStatus::Aberto => 'abertos',
            };

            $geral['gerados'] += $total;
            $geral[$balde] += $total;
            $porRegra[$ruleKey]['gerados'] += $total;
            $porRegra[$ruleKey][$balde] += $total;
        }

        ksort($porRegra);

        $porRegraSaida = [];

        foreach ($porRegra as $ruleKey => $contagem) {
            $porRegraSaida[] = ['rule_key' => $ruleKey] + $contagem + [
                'taxa' => $this->taxa($contagem['confirmados'], $contagem['gerados']),
            ];
        }

        return [
            'geral' => $geral + ['taxa' => $this->taxa($geral['confirmados'], $geral['gerados'])],
            'por_regra' => $porRegraSaida,
        ];
    }

    /**
     * Percentual de efetividade (confirmados ÷ gerados × 100, 1 casa). Sem
     * gerados, retorna null (não há base — nunca um número fabricado).
     */
    private function taxa(int $confirmados, int $gerados): ?float
    {
        if ($gerados === 0) {
            return null;
        }

        return round($confirmados / $gerados * 100, 1);
    }

    /**
     * Opções de rule_key para o filtro: as chaves REAIS presentes no ledger
     * (data-driven), nunca uma lista hardcoded de detectores.
     *
     * @return list<string>
     */
    private function ruleKeyOptions(): array
    {
        return AbuseAlert::query()
            ->distinct()
            ->orderBy('rule_key')
            ->pluck('rule_key')
            ->all();
    }

    private function perPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page');

        return in_array($perPage, self::PER_PAGE_OPTIONS, true)
            ? $perPage
            : (int) Settings::get('ui.auditoria.per_page', 20);
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
}

<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use App\Models\AccessLog;
use App\Models\Activity;
use App\Services\Auditoria\AuditTrailQueryService;
use App\Services\Relatorios\Export\ReportExporter;
use App\Services\Relatorios\Export\Sources\AcessosReportSource;
use App\Services\Relatorios\Export\Sources\AtividadesReportSource;
use App\Services\Relatorios\ReportFilters;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Consulta e exportação da trilha de auditoria (HU-098/100/101) na retaguarda,
 * server-driven sobre a espinha activity_log (espelha o ProcessoController). O
 * index roteia a fonte (atividade/alteracoes/acessos), pagina no servidor e
 * AUDITA a própria consulta; com ?formato= delega ao contrato único de
 * exportação (HU-131/RN-009): {@see ReportExporter} aplica os MESMOS filtros
 * (RN-005/RN-007), audita (meta-auditoria personal_data, CA-02) e escolhe o
 * caminho síncrono/assíncrono. As fontes {@see AtividadesReportSource}/
 * {@see AcessosReportSource} preservam colunas, nome de arquivo e a guarda de
 * volume técnica do CSV histórico — agora também em XLSX/PDF pelo mesmo
 * mecanismo. Gated por consultar-auditoria; o 403 é auditado no ponto único
 * (bootstrap/app.php).
 */
class AuditoriaController extends Controller
{
    /** Itens por página aceitos — inclui o default parametrizável (ui.auditoria.per_page = 20). */
    private const PER_PAGE_OPTIONS = [10, 20, 50, 100];

    /** Fontes da trilha unificada: activity_log (atividade/alteracoes) + access_logs (acessos). */
    private const FONTES = [
        'atividade' => 'Trilha de atividades',
        'alteracoes' => 'Histórico de alterações',
        'acessos' => 'Histórico de acessos',
    ];

    public function __construct(
        private AuditTrailQueryService $trilha,
        private AuditService $audit,
    ) {}

    /**
     * Consulta filtrável e paginada da trilha (HU-098/100). Com ?formato=
     * (csv/xlsx/pdf), delega ao export (HU-101/131). A consulta é auditada —
     * meta-auditoria CA-02.
     */
    public function index(Request $request): Response|HttpResponse
    {
        if (in_array($request->string('formato')->lower()->toString(), ['csv', 'xlsx', 'pdf'], true)) {
            return $this->export($request);
        }

        $fonte = $this->fonte($request);
        $filtros = $this->filtros($request);
        $perPage = $this->perPage($request);

        $registros = $this->paginar($fonte, $filtros, $perPage);

        $this->audit->log(
            logName: 'auditoria',
            event: 'consulta-trilha',
            description: 'Consulta da trilha de auditoria',
            properties: ['fonte' => $fonte, 'filtros' => $this->filtrosPreenchidos($filtros)],
            personalData: true,
        );

        return Inertia::render('gestao/auditoria/index', [
            'registros' => $registros,
            'fonte' => $fonte,
            'filtros' => $filtros + ['per_page' => $perPage],
            'fonteOptions' => $this->fonteOptions(),
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    /**
     * Exportação do conjunto filtrado (HU-101/131), respeitando a fonte e os
     * MESMOS filtros do index. Delega ao contrato único (RN-009): o
     * {@see ReportExporter} audita (meta-auditoria personal_data, CA-02), valida
     * o formato e escolhe o caminho síncrono/assíncrono — preservando colunas,
     * nome de arquivo e a guarda de volume (auditoria.export.max_linhas via
     * maxRows). O `fonte` viaja no bag para o source certo reconstruir o recorte
     * no assíncrono (RN-005/RN-007). Default csv preserva o comportamento atual.
     */
    public function export(Request $request): HttpResponse
    {
        $fonte = $this->fonte($request);

        $source = $fonte === 'acessos'
            ? app(AcessosReportSource::class)
            : app(AtividadesReportSource::class);

        $bag = $this->filtros($request) + ['fonte' => $fonte];

        return app(ReportExporter::class)->export(
            $source,
            ReportFilters::fromArray($bag),
            $this->formato($request),
            $request->user(),
        );
    }

    /**
     * Formato de exportação solicitado (?formato=csv|xlsx|pdf), normalizado;
     * valor ausente ou desconhecido cai em csv — preserva o comportamento
     * histórico do /export (sempre CSV).
     */
    private function formato(Request $request): string
    {
        $formato = $request->string('formato')->lower()->toString();

        return in_array($formato, ['csv', 'xlsx', 'pdf'], true) ? $formato : 'csv';
    }

    /**
     * Paginação server-side por fonte. A trilha (atividade/alteracoes) usa o
     * ActivityResource; os acessos têm mapeamento mínimo próprio (≠ shape da
     * activity).
     *
     * @param  array<string, mixed>  $filtros
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function paginar(string $fonte, array $filtros, int $perPage): LengthAwarePaginator
    {
        if ($fonte === 'acessos') {
            return $this->trilha->acessos($filtros)
                ->paginate($perPage)
                ->withQueryString()
                ->through(fn (AccessLog $log): array => $this->acessoLinha($log));
        }

        $query = $fonte === 'alteracoes'
            ? $this->trilha->apenasAlteracoes($filtros)
            : $this->trilha->filtered($filtros);

        return $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Activity $activity): array => (new ActivityResource($activity))->resolve());
    }

    /**
     * Mapeamento mínimo de um access_log para a lista (fonte secundária).
     *
     * @return array<string, mixed>
     */
    private function acessoLinha(AccessLog $log): array
    {
        return [
            'id' => $log->id,
            'created_at' => $log->created_at?->toIso8601String(),
            'event' => $log->event,
            'usuario' => $log->user?->name,
            'email' => $log->email,
            'ip_address' => $log->ip_address,
            'channel' => $log->channel,
        ];
    }

    private function fonte(Request $request): string
    {
        $fonte = $request->string('fonte')->toString();

        return array_key_exists($fonte, self::FONTES) ? $fonte : 'atividade';
    }

    /**
     * Coleta os filtros da query string (strings cruas; o AuditTrailQueryService
     * normaliza e ignora os vazios).
     *
     * @return array<string, string>
     */
    private function filtros(Request $request): array
    {
        $chaves = ['data_de', 'data_ate', 'usuario_id', 'usuario', 'entidade_tipo', 'entidade_id', 'log_name', 'event', 'resultado'];

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
            : (int) Settings::get('ui.auditoria.per_page', 20);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function fonteOptions(): array
    {
        $opcoes = [];

        foreach (self::FONTES as $value => $label) {
            $opcoes[] = ['value' => $value, 'label' => $label];
        }

        return $opcoes;
    }
}

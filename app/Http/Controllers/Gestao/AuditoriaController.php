<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use App\Models\AccessLog;
use App\Models\Activity;
use App\Services\Auditoria\AuditTrailQueryService;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Consulta e exportação da trilha de auditoria (HU-098/100/101) na retaguarda,
 * server-driven sobre a espinha activity_log (espelha o ProcessoController). O
 * index roteia a fonte (atividade/alteracoes/acessos), pagina no servidor e
 * AUDITA a própria consulta; com ?formato=csv delega ao export, que aplica os
 * MESMOS filtros em streaming (chunk) com guarda de volume técnica e também é
 * auditado. A consulta da trilha pode expor PII — por isso a meta-auditoria
 * marca personal_data (CA-02, relevante p/ LGPD: quem viu a trilha de quem).
 * Gated por consultar-auditoria; o 403 é auditado no ponto único
 * (bootstrap/app.php). Export pleno XLSX/PDF → HU-131/Fase 15 (bloqueado honesto).
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
     * Consulta filtrável e paginada da trilha (HU-098/100). Com ?formato=csv,
     * delega ao export (HU-101). A consulta é auditada — meta-auditoria CA-02.
     */
    public function index(Request $request): Response|StreamedResponse
    {
        if ($request->string('formato')->lower()->toString() === 'csv') {
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
     * CSV do conjunto filtrado (HU-101), respeitando a fonte e os MESMOS filtros
     * do index, em streaming (fputcsv + chunk) com guarda de volume técnica
     * (auditoria.export.max_linhas). A exportação é auditada — meta-auditoria
     * CA-02 (personal_data). Export pleno (XLSX/PDF) é HU-131/Fase 15.
     */
    public function export(Request $request): StreamedResponse
    {
        $fonte = $this->fonte($request);
        $filtros = $this->filtros($request);
        $maxLinhas = (int) config('sile.auditoria.export.max_linhas', 50000);

        $this->audit->log(
            logName: 'auditoria',
            event: 'exporta-trilha-csv',
            description: 'Exportação CSV da trilha de auditoria',
            properties: ['fonte' => $fonte, 'filtros' => $this->filtrosPreenchidos($filtros)],
            personalData: true,
        );

        if ($fonte === 'acessos') {
            return $this->exportarAcessos($this->trilha->acessos($filtros), $maxLinhas);
        }

        $query = $fonte === 'alteracoes'
            ? $this->trilha->apenasAlteracoes($filtros)
            : $this->trilha->filtered($filtros);

        return $this->exportarAtividades($query, $maxLinhas);
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
     * @param  Builder<Activity>  $query
     */
    private function exportarAtividades(Builder $query, int $maxLinhas): StreamedResponse
    {
        $colunas = ['Data/hora', 'Fonte', 'Ação', 'Descrição', 'Usuário', 'Em nome de', 'Entidade', 'Entidade ID', 'Resultado', 'Versão de regras', 'IP', 'Canal'];

        return response()->streamDownload(function () use ($query, $colunas, $maxLinhas): void {
            $saida = fopen('php://output', 'w');
            fputcsv($saida, $colunas);

            $emitidas = 0;

            $query->chunk(200, function ($atividades) use ($saida, &$emitidas, $maxLinhas): bool {
                foreach ($atividades as $atividade) {
                    if ($emitidas >= $maxLinhas) {
                        return false;
                    }

                    $dados = (new ActivityResource($atividade))->resolve();

                    fputcsv($saida, [
                        $dados['created_at'],
                        $dados['log_name'],
                        $dados['event'],
                        $dados['description'],
                        $dados['causer']['nome'] ?? null,
                        $dados['acting_for']['nome'] ?? null,
                        $dados['subject']['type'] ?? null,
                        $dados['subject']['id'] ?? null,
                        $dados['result'],
                        $dados['rules_version'],
                        $dados['ip_address'],
                        $dados['channel'],
                    ]);

                    $emitidas++;
                }

                return $emitidas < $maxLinhas;
            });

            fclose($saida);
        }, 'auditoria.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @param  Builder<AccessLog>  $query
     */
    private function exportarAcessos(Builder $query, int $maxLinhas): StreamedResponse
    {
        $colunas = ['Data/hora', 'Evento', 'Usuário', 'E-mail', 'IP', 'Canal'];

        return response()->streamDownload(function () use ($query, $colunas, $maxLinhas): void {
            $saida = fopen('php://output', 'w');
            fputcsv($saida, $colunas);

            $emitidas = 0;

            $query->chunk(200, function ($acessos) use ($saida, &$emitidas, $maxLinhas): bool {
                foreach ($acessos as $acesso) {
                    if ($emitidas >= $maxLinhas) {
                        return false;
                    }

                    fputcsv($saida, [
                        $acesso->created_at?->toIso8601String(),
                        $acesso->event,
                        $acesso->user?->name,
                        $acesso->email,
                        $acesso->ip_address,
                        $acesso->channel,
                    ]);

                    $emitidas++;
                }

                return $emitidas < $maxLinhas;
            });

            fclose($saida);
        }, 'auditoria-acessos.csv', ['Content-Type' => 'text/csv']);
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

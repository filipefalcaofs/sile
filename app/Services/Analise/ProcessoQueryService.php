<?php

namespace App\Services\Analise;

use App\Enums\AnalysisCategory;
use App\Enums\ViabilityRequestStatus;
use App\Models\User;
use App\Models\ViabilityRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Construção da query da consulta de processos (HU-082) e da fila do analista
 * (HU-144). Centraliza os filtros completos do SAPS (grupo de status, status,
 * número do processo, BAP, produto TVL, serviço, setor, datas, inscrição, nome,
 * CNPJ, CEP, logradouro, bairro) MAIS o filtro por analista responsável e por
 * categoria DERIVADA (Expresso/Semi-Expresso a partir de analysis_category;
 * Malha Fina de in_fine_mesh; Sede de Escritório de is_virtual_office — RN-005),
 * com whereLike caseSensitive:false (case-insensitive em PostgreSQL e SQLite) e
 * eager-load de company/decision para evitar N+1. A paginação e o CSV ficam no
 * controller; aqui só se monta o Builder reutilizável (consulta e fila).
 */
class ProcessoQueryService
{
    /**
     * Categorias de consulta (HU-082 RN-005) — NÃO persistidas: derivam de
     * analysis_category (expresso/semi_expresso) + as flags in_fine_mesh (malha
     * fina) e is_virtual_office (sede de escritório). value => rótulo pt-BR.
     */
    public const CATEGORIAS = [
        'expresso' => 'Expresso',
        'semi_expresso' => 'Semi-expresso',
        'malha_fina' => 'Malha Fina',
        'sede_escritorio' => 'Sede de Escritório',
    ];

    /**
     * Grupo de status (filtro macro do SAPS) → conjunto de status do enum.
     *
     * @var array<string, list<string>>
     */
    private const GRUPOS_STATUS = [
        'rascunho' => ['rascunho'],
        'em_andamento' => ['protocolada', 'aguardando_bap', 'em_analise', 'em_pendencia'],
        'concluido' => ['deferida', 'indeferida'],
        'cancelado' => ['cancelada'],
    ];

    /** Status que compõem a fila de trabalho do analista (HU-144). */
    private const STATUS_FILA = [
        ViabilityRequestStatus::EmAnalise->value,
        ViabilityRequestStatus::EmPendencia->value,
    ];

    /**
     * Monta o Builder filtrado da consulta (HU-082), ordenado do mais recente
     * para o mais antigo. Cada filtro só entra quando informado.
     *
     * @param  array<string, mixed>  $filtros
     * @return Builder<ViabilityRequest>
     */
    public function filtered(array $filtros): Builder
    {
        return ViabilityRequest::query()
            ->with(['company', 'sector:id,name', 'assignedTo:id,name', 'decision'])
            ->when($this->valor($filtros, 'grupo'), fn (Builder $q, string $grupo) => $this->aplicarGrupo($q, $grupo))
            ->when($this->valor($filtros, 'status'), fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($this->valor($filtros, 'protocolo'), fn (Builder $q, string $v) => $q->whereLike('protocol_number', "%{$v}%", caseSensitive: false))
            ->when($this->valor($filtros, 'bap'), fn (Builder $q, string $v) => $q->whereLike('external_reference', "%{$v}%", caseSensitive: false))
            ->when($this->valor($filtros, 'produto_tvl'), fn (Builder $q, string $v) => $q->whereHas('decision', fn ($d) => $d->whereLike('tvl_product_number', "%{$v}%", caseSensitive: false)))
            ->when($this->inteiro($filtros, 'servico'), fn (Builder $q, int $id) => $q->where('service_type_id', $id))
            ->when($this->inteiro($filtros, 'setor'), fn (Builder $q, int $id) => $q->where('sector_id', $id))
            ->when($this->inteiro($filtros, 'analista'), fn (Builder $q, int $id) => $q->where('assigned_user_id', $id))
            ->when($this->valor($filtros, 'inscricao'), fn (Builder $q, string $v) => $q->whereLike('property_registration', "%{$v}%", caseSensitive: false))
            ->when($this->valor($filtros, 'cep'), fn (Builder $q, string $v) => $q->whereLike('address_zip', "%{$v}%", caseSensitive: false))
            ->when($this->valor($filtros, 'logradouro'), fn (Builder $q, string $v) => $q->whereLike('address_street', "%{$v}%", caseSensitive: false))
            ->when($this->valor($filtros, 'bairro'), fn (Builder $q, string $v) => $q->whereLike('address_neighborhood', "%{$v}%", caseSensitive: false))
            ->when($this->valor($filtros, 'nome'), fn (Builder $q, string $v) => $this->aplicarNome($q, $v))
            ->when($this->valor($filtros, 'cnpj'), fn (Builder $q, string $v) => $q->whereHas('company', fn ($c) => $c->whereLike('cnpj', "%{$v}%", caseSensitive: false)))
            ->when($this->data($filtros, 'data_de'), fn (Builder $q, string $d) => $q->whereDate('protocoled_at', '>=', $d))
            ->when($this->data($filtros, 'data_ate'), fn (Builder $q, string $d) => $q->whereDate('protocoled_at', '<=', $d))
            ->when($this->categoria($filtros), fn (Builder $q, string $cat) => $this->aplicarCategoria($q, $cat))
            ->orderByDesc('id');
    }

    /**
     * Monta o Builder da fila do analista (HU-144), ordenado por prazo
     * (analysis_due_at — coluna indexada de 10-02) ascendente. Só os status de
     * trabalho (em análise / em pendência); o escopo (meus/setor) vem do
     * escopo().
     *
     * @return Builder<ViabilityRequest>
     */
    public function fila(User $user, string $modo): Builder
    {
        return $this->escopo($user, $modo)
            ->with(['company', 'sector:id,name', 'assignedTo:id,name', 'decision'])
            ->whereIn('status', self::STATUS_FILA)
            ->orderBy('analysis_due_at')
            ->orderBy('id');
    }

    /**
     * Contadores da fila por status (HU-144), no MESMO escopo (meus/setor):
     * aguardando análise (em_analise sem analista), em análise (atribuído), em
     * pendência e vencendo hoje (prazo <= fim do dia).
     *
     * @return array{aguardando_analise: int, em_analise: int, em_pendencia: int, vencendo_hoje: int}
     */
    public function contadores(User $user, string $modo): array
    {
        $emAnalise = ViabilityRequestStatus::EmAnalise->value;
        $emPendencia = ViabilityRequestStatus::EmPendencia->value;

        return [
            'aguardando_analise' => $this->escopo($user, $modo)->where('status', $emAnalise)->whereNull('assigned_user_id')->count(),
            'em_analise' => $this->escopo($user, $modo)->where('status', $emAnalise)->whereNotNull('assigned_user_id')->count(),
            'em_pendencia' => $this->escopo($user, $modo)->where('status', $emPendencia)->count(),
            'vencendo_hoje' => $this->escopo($user, $modo)
                ->whereIn('status', self::STATUS_FILA)
                ->whereNotNull('analysis_due_at')
                ->where('analysis_due_at', '<=', now()->endOfDay())
                ->count(),
        ];
    }

    /**
     * Visão agregada do setor para o gestor (HU-144 CA-03): carga por analista
     * (quantos processos de trabalho cada um carrega) e total de processos em
     * vermelho (prazo estourado) nos setores do gestor. Ponte com a HU-130/147.
     *
     * @return array{carga: list<array{analista_id: int, analista: string, total: int}>, vermelhos: int}
     */
    public function visaoSetor(User $user): array
    {
        $sectorIds = $user->sectors()->pluck('sectors.id');

        $carga = ViabilityRequest::query()
            ->join('users', 'users.id', '=', 'viability_requests.assigned_user_id')
            ->whereIn('viability_requests.sector_id', $sectorIds)
            ->whereIn('viability_requests.status', self::STATUS_FILA)
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total')
            ->get([
                'users.id as analista_id',
                'users.name as analista',
                DB::raw('count(*) as total'),
            ])
            ->map(fn ($linha): array => [
                'analista_id' => (int) $linha->analista_id,
                'analista' => (string) $linha->analista,
                'total' => (int) $linha->total,
            ])
            ->all();

        $vermelhos = ViabilityRequest::query()
            ->whereIn('sector_id', $sectorIds)
            ->whereIn('status', self::STATUS_FILA)
            ->whereNotNull('analysis_due_at')
            ->where('analysis_due_at', '<', now())
            ->count();

        return ['carga' => $carga, 'vermelhos' => $vermelhos];
    }

    /**
     * Escopo da fila por modo: `meus` (atribuídos ao usuário) ou `setor`
     * (processos das caixas do(s) setor(es) do usuário — respeita o vínculo
     * analista↔setor, RN-005). Sem filtro de status nem ordenação (reaproveitado
     * pela fila e pelos contadores).
     *
     * @return Builder<ViabilityRequest>
     */
    private function escopo(User $user, string $modo): Builder
    {
        $query = ViabilityRequest::query();

        if ($modo === 'setor') {
            return $query->whereIn('sector_id', $user->sectors()->pluck('sectors.id'));
        }

        return $query->where('assigned_user_id', $user->id);
    }

    /**
     * Categorias derivadas de um processo (HU-082 RN-005), reutilizada pelo
     * ProcessoResource. Um processo pode ter mais de uma (ex.: malha fina E
     * expresso) — a malha fina e a sede são ortogonais à categoria do motor.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function categoriasDe(ViabilityRequest $request): array
    {
        $categorias = [];

        if ($request->in_fine_mesh) {
            $categorias[] = ['value' => 'malha_fina', 'label' => self::CATEGORIAS['malha_fina']];
        }

        if ($request->is_virtual_office) {
            $categorias[] = ['value' => 'sede_escritorio', 'label' => self::CATEGORIAS['sede_escritorio']];
        }

        if ($request->analysis_category instanceof AnalysisCategory) {
            $value = $request->analysis_category->value;
            $categorias[] = ['value' => $value, 'label' => self::CATEGORIAS[$value] ?? $request->analysis_category->label()];
        }

        return $categorias;
    }

    /**
     * @param  Builder<ViabilityRequest>  $query
     * @return Builder<ViabilityRequest>
     */
    private function aplicarGrupo(Builder $query, string $grupo): Builder
    {
        $statuses = self::GRUPOS_STATUS[$grupo] ?? null;

        return $statuses === null ? $query : $query->whereIn('status', $statuses);
    }

    /**
     * @param  Builder<ViabilityRequest>  $query
     * @return Builder<ViabilityRequest>
     */
    private function aplicarCategoria(Builder $query, string $categoria): Builder
    {
        return match ($categoria) {
            'malha_fina' => $query->where('in_fine_mesh', true),
            'sede_escritorio' => $query->where('is_virtual_office', true),
            'expresso' => $query->where('analysis_category', AnalysisCategory::Expresso->value),
            'semi_expresso' => $query->where('analysis_category', AnalysisCategory::SemiExpresso->value),
            default => $query,
        };
    }

    /**
     * Nome do requerente ou da empresa (razão social / nome fantasia).
     *
     * @param  Builder<ViabilityRequest>  $query
     * @return Builder<ViabilityRequest>
     */
    private function aplicarNome(Builder $query, string $nome): Builder
    {
        return $query->where(function (Builder $inner) use ($nome) {
            $inner->whereHas('company', fn ($c) => $c
                ->whereLike('legal_name', "%{$nome}%", caseSensitive: false)
                ->orWhereLike('trade_name', "%{$nome}%", caseSensitive: false))
                ->orWhereHas('requester', fn ($r) => $r->whereLike('name', "%{$nome}%", caseSensitive: false));
        });
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function valor(array $filtros, string $chave): ?string
    {
        $valor = isset($filtros[$chave]) ? trim((string) $filtros[$chave]) : '';

        return $valor === '' ? null : $valor;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function inteiro(array $filtros, string $chave): ?int
    {
        $valor = $this->valor($filtros, $chave);

        return $valor !== null && ctype_digit($valor) ? (int) $valor : null;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function data(array $filtros, string $chave): ?string
    {
        $valor = $this->valor($filtros, $chave);

        return $valor !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) === 1 ? $valor : null;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function categoria(array $filtros): ?string
    {
        $valor = $this->valor($filtros, 'categoria');

        return $valor !== null && array_key_exists($valor, self::CATEGORIAS) ? $valor : null;
    }
}

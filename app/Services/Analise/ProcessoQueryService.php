<?php

namespace App\Services\Analise;

use App\Enums\AnalysisCategory;
use App\Enums\AnalysisRecordStatus;
use App\Enums\AnalysisStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestEvento;
use App\Services\Servicos\ServicoNomenclaturaResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Construção da query da consulta de processos (HU-078) e da fila do analista
 * (HU-134). Centraliza os filtros completos do SAPS (grupo de status, status,
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
     * Categorias de consulta (HU-078 RN-005) — NÃO persistidas: derivam de
     * analysis_category (expresso/semi_expresso) + as flags in_fine_mesh (malha
     * fina) e is_virtual_office (sede de escritório). value => rótulo pt-BR.
     */
    public const CATEGORIAS = [
        'expresso' => 'Expresso',
        'semi_expresso' => 'Semi-expresso',
        'malha_fina' => 'Malha Fina',
        'sede_escritorio' => 'Sede de Escritório',
        'abrigado_escritorio' => 'Abrigado de Virtual',
    ];

    /** Filtros oferecidos nas caixas (setor e analista) — subconjunto do SAPS. */
    /** Prefixo do valor do filtro `servico` que aponta para um evento da JUCEB, e não para o tipo interno. */
    public const PREFIXO_EVENTO = 'e';

    /** Filtro "Serviço" pelo nome parametrizado (De x Para), normalizado. */
    public const PREFIXO_NOME_SERVICO = 'n';

    public const CHAVES_FILTRO_CAIXA = ['analysis_status', 'servico', 'protocolo', 'bap', 'data_de', 'data_ate', 'nome', 'cnpj', 'bairro', 'categoria', 'analista', 'cnae', 'complemento'];

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

    /** Status que compõem a fila de trabalho do analista (HU-134). */
    private const STATUS_FILA = [
        ViabilityRequestStatus::EmAnalise->value,
        ViabilityRequestStatus::EmPendencia->value,
    ];

    /**
     * Monta o Builder filtrado da consulta (HU-078), ordenado do mais recente
     * para o mais antigo. Cada filtro só entra quando informado.
     *
     * @param  array<string, mixed>  $filtros
     * @return Builder<ViabilityRequest>
     */
    public function filtered(array $filtros): Builder
    {
        $query = ViabilityRequest::query()
            ->with(['company', 'sector:id,name', 'assignedTo:id,name', 'conclusaoEncaminhadaPara:id,name', 'decision', 'encaminhamentoAnalise', 'serviceType:id,name', 'eventos']);

        return $this->aplicarOrdem($this->aplicarFiltros($query, $filtros), $this->valor($filtros, 'ordem'));
    }

    /**
     * Aplica os filtros de campo (SAPS + analista + categoria) a um Builder já
     * escopado. Cada filtro só entra quando informado. Reutilizado pela consulta
     * (filtered), pela caixa do setor e pela fila do analista.
     *
     * @param  Builder<ViabilityRequest>  $query
     * @param  array<string, mixed>  $filtros
     * @return Builder<ViabilityRequest>
     */
    public function aplicarFiltros(Builder $query, array $filtros): Builder
    {
        $eventosDoServico = $this->eventosDoServico($filtros);

        return $query
            ->when($this->valor($filtros, 'grupo'), fn (Builder $q, string $grupo) => $this->aplicarGrupo($q, $grupo))
            ->when($this->valor($filtros, 'status'), fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($this->valor($filtros, 'analysis_status'), fn (Builder $q, string $s) => $q->where('analysis_status', $s))
            ->when($this->valor($filtros, 'busca'), fn (Builder $q, string $v) => $this->aplicarBusca($q, $v))
            ->when($this->valor($filtros, 'fluxo'), fn (Builder $q, string $fluxo) => $this->aplicarFluxo($q, $fluxo))
            ->when($this->valor($filtros, 'protocolo'), function (Builder $q, string $v): Builder {
                return $q->where(function (Builder $inner) use ($v) {
                    $inner->whereLike('protocol_number', "%{$v}%", caseSensitive: false)
                        ->orWhereLike('external_reference', "%{$v}%", caseSensitive: false);
                });
            })
            ->when($this->valor($filtros, 'bap'), fn (Builder $q, string $v) => $q->whereLike('external_reference', "%{$v}%", caseSensitive: false))
            ->when($this->valor($filtros, 'produto_tvl'), fn (Builder $q, string $v) => $q->whereHas('decision', fn ($d) => $d->whereLike('tvl_product_number', "%{$v}%", caseSensitive: false)))
            ->when($this->inteiro($filtros, 'servico'), fn (Builder $q, int $id) => $q->where('service_type_id', $id))
            ->when($eventosDoServico !== null, fn (Builder $q) => $q->whereHas('eventos', fn ($e) => $this->aplicarEventos($e, $eventosDoServico)))
            ->when($this->inteiro($filtros, 'setor'), fn (Builder $q, int $id) => $q->where('sector_id', $id))
            ->when($this->inteiro($filtros, 'analista'), fn (Builder $q, int $id) => $q->where('assigned_user_id', $id))
            ->when($this->valor($filtros, 'inscricao'), fn (Builder $q, string $v) => $q->whereLike('property_registration', "%{$v}%", caseSensitive: false))
            ->when($this->valor($filtros, 'cep'), fn (Builder $q, string $v) => $q->whereLike('address_zip', "%{$v}%", caseSensitive: false))
            ->when($this->valor($filtros, 'logradouro'), fn (Builder $q, string $v) => $q->whereLike('address_street', "%{$v}%", caseSensitive: false))
            ->when($this->valor($filtros, 'bairro'), fn (Builder $q, string $v) => $q->whereLike('address_neighborhood', "%{$v}%", caseSensitive: false))
            ->when($this->valor($filtros, 'nome'), fn (Builder $q, string $v) => $this->aplicarNome($q, $v))
            ->when($this->valor($filtros, 'cnpj'), fn (Builder $q, string $v) => $this->aplicarDocumento($q, $v))
            ->when($this->valor($filtros, 'cnae'), fn (Builder $q, string $v) => $this->aplicarCnae($q, $v))
            ->when($this->valor($filtros, 'complemento'), fn (Builder $q, string $v) => $q->whereLike('address_complement', "%{$v}%", caseSensitive: false))
            ->when($this->data($filtros, 'data_de'), fn (Builder $q, string $d) => $q->whereDate('protocoled_at', '>=', $d))
            ->when($this->data($filtros, 'data_ate'), fn (Builder $q, string $d) => $q->whereDate('protocoled_at', '<=', $d))
            ->when($this->categoria($filtros), fn (Builder $q, string $cat) => $this->aplicarCategoria($q, $cat));
    }

    /**
     * Totais das abas da consulta. O recorte (busca e filtros avançados) entra;
     * grupo, status e situação da análise ficam de fora, porque são a própria aba.
     *
     * @param  array<string, mixed>  $filtros
     * @return array{todos: int, em_analise: int, distribuir: int, pendencia: int, concluido: int}
     */
    public function contadoresConsulta(array $filtros): array
    {
        $semAba = $filtros;
        $semAba['grupo'] = '';
        $semAba['status'] = '';
        $semAba['analysis_status'] = '';

        return [
            'todos' => $this->filtered($semAba)->count(),
            'em_analise' => $this->filtered(array_merge($semAba, ['status' => ViabilityRequestStatus::EmAnalise->value]))->count(),
            'distribuir' => $this->filtered(array_merge($semAba, ['analysis_status' => 'para_distribuir']))->count(),
            'pendencia' => $this->filtered(array_merge($semAba, ['status' => ViabilityRequestStatus::EmPendencia->value]))->count(),
            'concluido' => $this->filtered(array_merge($semAba, ['grupo' => 'concluido']))->count(),
        ];
    }

    /**
     * Monta o Builder da caixa de entrada do analista (HU-134), ordenado por
     * prazo (analysis_due_at — coluna indexada de 10-02) ascendente. Só os
     * processos atribuídos ao usuário, nos status de trabalho (em análise / em
     * pendência).
     *
     * @return Builder<ViabilityRequest>
     */
    public function fila(User $user): Builder
    {
        return $this->escopo($user)
            ->with(['company', 'sector:id,name', 'assignedTo:id,name', 'conclusaoEncaminhadaPara:id,name', 'decision', 'encaminhamentoAnalise', 'serviceType:id,name', 'eventos'])
            ->whereIn('status', self::STATUS_FILA)
            ->orderByRaw('case when analysis_status = ? then 0 else 1 end', [AnalysisStatus::ConviteRespondido->value])
            ->orderBy('analysis_due_at')
            ->orderBy('id');
    }

    /**
     * Processos que o analista analisou: com ficha finalizada por ele, com
     * decisão registrada por ele ou que ele tramitou para Para conclusão,
     * Encaminhado ou Análise concluída (a distribuição feita a partir de Para
     * distribuir não conta). Do mais recente para o mais antigo.
     *
     * @return Builder<ViabilityRequest>
     */
    public function analisados(User $user): Builder
    {
        return $this->analisadosPor($user)
            ->with(['company', 'sector:id,name', 'assignedTo:id,name', 'conclusaoEncaminhadaPara:id,name', 'decision', 'encaminhamentoAnalise', 'serviceType:id,name', 'eventos'])
            ->orderByDesc('id');
    }

    /**
     * Quantos processos o analista analisou nos últimos `$dias` dias.
     */
    public function contadorAnalisados(User $user, int $dias): int
    {
        $desde = now()->subDays($dias)->startOfDay();

        return $this->analisadosPor($user, $desde)->count();
    }

    /**
     * @return Builder<ViabilityRequest>
     */
    private function analisadosPor(User $user, ?\DateTimeInterface $desde = null): Builder
    {
        return ViabilityRequest::query()->where(function (Builder $q) use ($user, $desde): void {
            $q->whereHas('analysisRecords', fn (Builder $ficha) => $ficha
                ->where('analyst_user_id', $user->id)
                ->where('status', AnalysisRecordStatus::Finalizada->value)
                ->when($desde, fn (Builder $f) => $f->where('finalized_at', '>=', $desde)))
                ->orWhereHas('decision', fn (Builder $decisao) => $decisao
                    ->where('decided_by_user_id', $user->id)
                    ->when($desde, fn (Builder $d) => $d->where('decided_at', '>=', $desde)))
                ->orWhereHas('analysisStatusTransitions', fn (Builder $transicao) => $transicao
                    ->where('actor_user_id', $user->id)
                    ->whereIn('to_status', [
                        AnalysisStatus::ParaConclusao->value,
                        AnalysisStatus::Encaminhado->value,
                        AnalysisStatus::AnaliseConcluida->value,
                    ])
                    ->where(fn (Builder $origem) => $origem
                        ->whereNull('from_status')
                        ->orWhere('from_status', '!=', AnalysisStatus::ParaDistribuir->value))
                    ->when($desde, fn (Builder $t) => $t->where('created_at', '>=', $desde)));
        });
    }

    /**
     * Contadores da caixa de entrada por status (HU-134), no escopo do usuário:
     * aguardando análise (tudo o que está na caixa), em análise (os que já têm
     * ficha aberta por alguém — a pré-análise do motor nasce sem analista e não
     * conta), em pendência e vencendo hoje (prazo <= fim do dia).
     *
     * @return array{aguardando_analise: int, em_analise: int, em_pendencia: int, vencendo_hoje: int}
     */
    public function contadores(User $user): array
    {
        $emPendencia = ViabilityRequestStatus::EmPendencia->value;

        return [
            'aguardando_analise' => $this->escopo($user)->whereIn('status', self::STATUS_FILA)->count(),
            'em_analise' => $this->escopo($user)
                ->whereIn('status', self::STATUS_FILA)
                ->whereHas('analysisRecords', fn (Builder $ficha) => $ficha->whereNotNull('analyst_user_id'))
                ->count(),
            'em_pendencia' => $this->escopo($user)->where('status', $emPendencia)->count(),
            'vencendo_hoje' => $this->escopo($user)
                ->whereIn('status', self::STATUS_FILA)
                ->whereNotNull('analysis_due_at')
                ->where('analysis_due_at', '<=', now()->endOfDay())
                ->count(),
        ];
    }

    /**
     * Analistas do(s) setor(es) do usuário, para o filtro "Analista" das telas de
     * consulta. Quem distribui em qualquer setor, ou não tem setor vinculado,
     * enxerga todos os analistas.
     *
     * @return list<array{value: string, label: string}>
     */
    public function analistasParaFiltro(User $user): array
    {
        $setores = $user->sectors()->pluck('sectors.id');
        $todos = $user->can('distribuir-qualquer-setor') || $setores->isEmpty();

        return User::query()
            ->permission(['analisar-processos', 'preencher-ficha-vistoria'])
            ->when(! $todos, fn (Builder $q) => $q->whereHas('sectors', fn ($s) => $s->whereIn('sectors.id', $setores)))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $analista): array => ['value' => (string) $analista->id, 'label' => $analista->name])
            ->all();
    }

    /**
     * Visão agregada do setor para o gestor (HU-134 CA-03): carga por analista
     * (quantos processos de trabalho cada um carrega) e total de processos em
     * vermelho (prazo estourado) nos setores do gestor. Ponte com a HU-121/136.
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
     * Escopo da caixa de entrada: processos atribuídos ao usuário, menos os já
     * enviados para conclusão (saem da caixa de quem analisou e vão para a aba
     * "Para Conclusão" do setor), mais os que estão para conclusão com ele —
     * o destinatário escolhido no envio, ou quem foi distribuído ou assumiu na
     * aba. Sem filtro de status nem ordenação (reaproveitado pela fila e pelos
     * contadores).
     *
     * @return Builder<ViabilityRequest>
     */
    private function escopo(User $user): Builder
    {
        $paraConclusao = AnalysisStatus::ParaConclusao->value;

        return ViabilityRequest::query()->where(fn (Builder $caixa) => $caixa
            ->where(fn (Builder $atribuidos) => $atribuidos
                ->where('assigned_user_id', $user->id)
                ->where(fn (Builder $query) => $query
                    ->whereNull('analysis_status')
                    ->orWhere('analysis_status', '!=', $paraConclusao)))
            ->orWhere(fn (Builder $conclusao) => $conclusao
                ->where('analysis_status', $paraConclusao)
                ->where('conclusao_encaminhada_para_user_id', $user->id)));
    }

    /**
     * Categorias derivadas de um processo (HU-078 RN-005), reutilizada pelo
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

        if ($request->ehAbrigadoDeEscritorioVirtual()) {
            $categorias[] = ['value' => 'abrigado_escritorio', 'label' => self::CATEGORIAS['abrigado_escritorio']];
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
    /**
     * Busca única da consulta: protocolo, BAP, TVL, CNPJ ou nome da empresa.
     *
     * @param  Builder<ViabilityRequest>  $query
     * @return Builder<ViabilityRequest>
     */
    /**
     * Fluxo efetivo: decisão expressa, processo ainda na análise, ou decisão
     * da análise técnica. Não usa analysis_category — o motor não grava essa coluna.
     *
     * @param  Builder<ViabilityRequest>  $query
     * @return Builder<ViabilityRequest>
     */
    private function aplicarFluxo(Builder $query, string $fluxo): Builder
    {
        return match ($fluxo) {
            'expresso' => $query->whereHas('decision', fn (Builder $decisao) => $decisao->where('flow', 'expresso')),
            'em_analise' => $query->where('status', ViabilityRequestStatus::EmAnalise->value),
            'analise_tecnica' => $query->whereHas('decision', fn (Builder $decisao) => $decisao->where('flow', 'analise_tecnica')),
            default => $query,
        };
    }

    /**
     * @param  Builder<ViabilityRequest>  $query
     * @return Builder<ViabilityRequest>
     */
    private function aplicarBusca(Builder $query, string $busca): Builder
    {
        return $query->where(function (Builder $inner) use ($busca) {
            $inner->whereLike('protocol_number', "%{$busca}%", caseSensitive: false)
                ->orWhereLike('external_reference', "%{$busca}%", caseSensitive: false)
                ->orWhereHas('company', fn (Builder $empresa) => $empresa
                    ->whereLike('legal_name', "%{$busca}%", caseSensitive: false)
                    ->orWhereLike('trade_name', "%{$busca}%", caseSensitive: false)
                    ->orWhereLike('cnpj', "%{$busca}%", caseSensitive: false))
                ->orWhereHas('decision', fn (Builder $decisao) => $decisao
                    ->whereLike('tvl_product_number', "%{$busca}%", caseSensitive: false));
        });
    }

    /**
     * @param  Builder<ViabilityRequest>  $query
     * @return Builder<ViabilityRequest>
     */
    private function aplicarOrdem(Builder $query, ?string $ordem): Builder
    {
        return match ($ordem) {
            'prazo' => $query
                ->orderByRaw('case when analysis_due_at is null then 1 else 0 end')
                ->orderBy('analysis_due_at')
                ->orderByDesc('id'),
            'status' => $query->orderBy('status')->orderByDesc('id'),
            default => $query->orderByDesc('id'),
        };
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
            'abrigado_escritorio' => $query->where(fn (Builder $q) => $q
                ->where('wants_virtual_office_tenant', true)
                ->orWhereHas('decision', fn (Builder $decisao) => $decisao->where('is_virtual_office_tenant', true))),
            'expresso' => $query->where('analysis_category', AnalysisCategory::Expresso->value),
            'semi_expresso' => $query->where('analysis_category', AnalysisCategory::SemiExpresso->value),
            default => $query,
        };
    }

    /**
     * Documento do requerente: CNPJ da empresa ou CPF do requerente, no mesmo
     * campo. A comparação ignora a pontuação digitada.
     *
     * @param  Builder<ViabilityRequest>  $query
     * @return Builder<ViabilityRequest>
     */
    private function aplicarDocumento(Builder $query, string $documento): Builder
    {
        $digitos = preg_replace('/\D/', '', $documento) ?? '';

        if ($digitos === '') {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $inner) use ($digitos) {
            $inner->whereHas('company', fn ($c) => $c->whereLike('cnpj', "%{$digitos}%", caseSensitive: false))
                ->orWhereHas('requester', fn ($r) => $r->whereLike('cpf', "%{$digitos}%", caseSensitive: false));
        });
    }

    /**
     * CNAE do processo, pelo código (com ou sem pontuação) ou por parte da descrição.
     *
     * @param  Builder<ViabilityRequest>  $query
     * @return Builder<ViabilityRequest>
     */
    private function aplicarCnae(Builder $query, string $cnae): Builder
    {
        $digitos = preg_replace('/\D/', '', $cnae) ?? '';

        return $query->whereHas('cnaes', function (Builder $inner) use ($cnae, $digitos) {
            $inner->where(function (Builder $q) use ($cnae, $digitos) {
                $q->whereLike('cnaes.description', "%{$cnae}%", caseSensitive: false);

                if ($digitos !== '') {
                    $q->orWhereLike('cnaes.code', "%{$digitos}%", caseSensitive: false);
                }
            });
        });
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
     * Eventos do filtro "Serviço": pelo nome parametrizado
     * ("nviabilidade de localizacao"), com código e condição, ou pelos códigos,
     * formato anterior ("e244", "e101,102"), sem olhar a condição. Nome sem
     * evento recebido não traz processo.
     *
     * @param  array<string, mixed>  $filtros
     * @return list<array{codigo: string, condicao?: ?string}>|null
     */
    private function eventosDoServico(array $filtros): ?array
    {
        $valor = $this->valor($filtros, 'servico');

        if ($valor !== null && str_starts_with($valor, self::PREFIXO_NOME_SERVICO)) {
            return app(ServicoNomenclaturaResolver::class)->eventosDoNome(substr($valor, strlen(self::PREFIXO_NOME_SERVICO)));
        }

        if ($valor === null || ! str_starts_with($valor, self::PREFIXO_EVENTO)) {
            return null;
        }

        $codigos = array_values(array_filter(
            array_map('trim', explode(',', substr($valor, strlen(self::PREFIXO_EVENTO)))),
            fn (string $codigo): bool => $codigo !== '',
        ));

        return $codigos === [] ? null : array_map(fn (string $codigo): array => ['codigo' => $codigo], $codigos);
    }

    /**
     * @param  Builder<ViabilityRequestEvento>  $query
     * @param  list<array{codigo: string, condicao?: ?string}>  $eventos
     */
    private function aplicarEventos(Builder $query, array $eventos): void
    {
        if ($eventos === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $grupo) use ($eventos): void {
            foreach ($eventos as $evento) {
                $grupo->orWhere(function (Builder $um) use ($evento): void {
                    $um->where('codigo', $evento['codigo']);

                    if (array_key_exists('condicao', $evento)) {
                        $evento['condicao'] === null ? $um->whereNull('condicao') : $um->where('condicao', $evento['condicao']);
                    }
                });
            }
        });
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

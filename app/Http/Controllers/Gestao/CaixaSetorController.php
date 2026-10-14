<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\AnalysisStatus;
use App\Enums\ViabilityRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\DistribuirEmLoteRequest;
use App\Http\Requests\Gestao\DistribuirProcessoRequest;
use App\Models\Activity;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityServiceType;
use App\Services\Analise\CargaAnalistaService;
use App\Services\Analise\DistribuicaoException;
use App\Services\Analise\DistribuicaoInteligenteService;
use App\Services\Analise\DistribuicaoService;
use App\Services\Analise\ProcessoQueryService;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Caixa do setor da análise técnica (HU-080/081): a fila da distribuição. O
 * analista vê e ASSUME os processos em_analise do(s) seu(s) setor(es)
 * (analisar-processos); o gestor DISTRIBUI — single ou lote (RN-007) — a um
 * analista do setor (distribuir-processos), e a caixa NÃO tira o processo do
 * setor (RN-004). O apoio (distribuir-qualquer-setor) vê a caixa de todos os
 * setores e entrega a qualquer analista; o processo passa para o setor dele.
 * A delegação real é do DistribuicaoService (SLA recalculado + auditoria
 * síncrona por processo); 403 é auditado no ponto único (bootstrap/app.php).
 * Index server-driven espelhando o ResultadoExpressoController; a tela é 10-16.
 */
class CaixaSetorController extends Controller
{
    /** Itens por página aceitos — reusa o padrão do console (ui.cnaes.per_page). */
    private const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    public function __construct(
        private DistribuicaoService $distribuicao,
        private AuditService $audit,
        private ProcessoQueryService $processos,
        private CargaAnalistaService $carga,
        private DistribuicaoInteligenteService $inteligente,
    ) {}

    /**
     * Lista os processos em_analise dos setores do usuário (RN-004 — visibilidade
     * por setor), em duas visões: "para_distribuir" (padrão — sem responsável) e
     * "distribuidos" (já atribuídos, com a flag pode_redistribuir). Ordenados
     * pelo prazo (analysis_due_at) e paginados no servidor. A consulta é
     * auditada (RN-002).
     */
    public function index(Request $request): Response
    {
        $perPage = (int) $request->input('per_page');
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true)
            ? $perPage
            : (int) Settings::get('ui.cnaes.per_page', 15);

        $sectorIds = $request->user()->sectors()->pluck('sectors.id');
        $qualquerSetor = $this->distribuicao->distribuiEmQualquerSetor($request->user());

        // Visões da caixa (abas): "para_distribuir" (padrão — sem responsável)
        // e "distribuidos" (acompanhamento, com a analista atribuída).
        $visao = $request->input('visao') === 'distribuidos' ? 'distribuidos' : 'para_distribuir';

        $filtros = $this->filtrosDaCaixa($request);

        $base = $this->naCaixaDoUsuario(ViabilityRequest::query(), $request->user())
            ->where('status', ViabilityRequestStatus::EmAnalise->value);

        $base = $this->processos->aplicarFiltros($base, $filtros);

        $contadores = [
            'para_distribuir' => (clone $base)->whereNull('assigned_user_id')->count(),
            'distribuidos' => (clone $base)->whereNotNull('assigned_user_id')->count(),
        ];

        $processos = $base
            ->when(
                $visao === 'para_distribuir',
                fn ($query) => $query->whereNull('assigned_user_id'),
                fn ($query) => $query->whereNotNull('assigned_user_id'),
            )
            ->with(['company', 'serviceType:id,name', 'cnaes', 'sector:id,name', 'assignedTo:id,name'])
            ->orderBy('analysis_due_at')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (ViabilityRequest $processo): array => [
                'id' => $processo->id,
                'protocol_number' => $processo->protocol_number,
                // Processo SEDUR e endereço (usabilidade SEDUR 19/09, item 05).
                'bap' => $processo->external_reference,
                'imovel' => implode(' - ', array_filter([
                    trim(implode(', ', array_filter([$processo->address_street, $processo->address_number]))),
                    $processo->address_neighborhood,
                ])),
                'servico' => $processo->serviceType?->name,
                'cnaes' => $processo->cnaes
                    ->sortByDesc(fn ($cnae): bool => (bool) $cnae->pivot->is_primary)
                    ->map(fn ($cnae): array => [
                        'codigo' => $cnae->formatted_code,
                        'descricao' => $cnae->description,
                        'principal' => (bool) $cnae->pivot->is_primary,
                    ])
                    ->values()
                    ->all(),
                'empresa' => $processo->company?->trade_name ?: $processo->company?->legal_name,
                'cnpj' => $processo->company?->formatted_cnpj,
                'status' => $processo->status->value,
                'status_label' => $processo->status->label(),
                // Eixo operacional: a linha em vistoria distribui para a lista
                // de vistoriadores, não para a lista geral de analistas.
                'analysis_status' => $processo->analysis_status?->value,
                'analysis_stage' => $processo->analysis_stage?->value,
                'analysis_stage_label' => $processo->analysis_stage?->label(),
                'sector_id' => $processo->sector_id,
                'sector' => $processo->sector?->name,
                'assigned_user_id' => $processo->assigned_user_id,
                'assigned_to' => $processo->assignedTo?->name,
                'analysis_due_at' => $processo->analysis_due_at?->toIso8601String(),
                // Redistribuir só antes da conclusão (regra única do enum).
                'pode_redistribuir' => $processo->assigned_user_id !== null
                    && ($processo->analysis_status?->permiteRedistribuicao() ?? false),
            ]);

        // Só quem tramita (distribuir-processos — gestor/apoio) distribui — e só
        // para ele faz sentido carregar a lista de analistas do(s) setor(es) que
        // alimenta o seletor. O analista apenas assume, então recebe a lista
        // vazia (minimização do payload). Assumir é de quem analisa
        // (analisar-processos) — o apoio tramita, mas não assume.
        $podeDistribuir = $request->user()->can('distribuir-processos');
        $podeAssumir = $request->user()->can('analisar-processos');

        $analistas = $podeDistribuir
            ? $this->destinatarios('analisar-processos', $sectorIds, $qualquerSetor)
            : [];

        // Vistoriadores do(s) setor(es): o seletor da distribuição usa ESTA
        // lista quando a linha está no eixo de vistoria (a regra é enforced
        // pelo DistribuicaoService; aqui é a conveniência da tela).
        $vistoriadores = $podeDistribuir
            ? $this->destinatarios('preencher-ficha-vistoria', $sectorIds, $qualquerSetor)
            : [];

        $this->audit->log('analise', 'consulta-caixa', 'Consulta da caixa do setor', [
            'setores' => $qualquerSetor ? 'todos' : $sectorIds->all(),
            'filtros' => array_filter($filtros, fn ($v): bool => $v !== ''),
        ]);

        return Inertia::render('gestao/caixa-setor/index', [
            'processos' => $processos,
            'visao' => $visao,
            'contadores' => $contadores,
            'filtros' => $filtros + ['per_page' => $perPage],
            'servicoOptions' => $this->servicoOptions(),
            'analysisStatusOptions' => AnalysisStatus::options(),
            'categoriaOptions' => $this->categoriaOptions(),
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'podeDistribuir' => $podeDistribuir,
            'podeAssumir' => $podeAssumir,
            'distribuiEmQualquerSetor' => $qualquerSetor,
            'analistas' => $analistas,
            'vistoriadores' => $vistoriadores,
        ]);
    }

    /**
     * Central de Distribuição (Fase 2): a partir dos processos selecionados na
     * caixa (ids), mostra a carga ativa de cada analista do(s) setor(es) desses
     * processos para o Apoio decidir a distribuição. Só processos EM ANÁLISE, sem
     * responsável, do(s) setor(es) do usuário (RN-004). A gravação é o POST
     * distribuir já existente. A decisão do destinatário é do Apoio — aqui só se
     * mede a carga (nunca se sugere). A consulta é auditada.
     */
    public function central(Request $request): Response|RedirectResponse
    {
        $sectorIds = $request->user()->sectors()->pluck('sectors.id');
        $qualquerSetor = $this->distribuicao->distribuiEmQualquerSetor($request->user());

        $ids = collect(explode(',', (string) $request->string('ids')))
            ->map(fn ($id): int => (int) trim($id))
            ->filter()
            ->unique()
            ->values();

        $processos = $this->naCaixaDoUsuario(ViabilityRequest::query(), $request->user())
            ->whereIn('id', $ids)
            ->where('status', ViabilityRequestStatus::EmAnalise->value)
            ->whereNull('assigned_user_id')
            ->with(['sector:id,name'])
            ->orderBy('analysis_due_at')
            ->orderBy('id')
            ->get()
            ->map(fn (ViabilityRequest $processo): array => [
                'id' => $processo->id,
                'sector_id' => $processo->sector_id,
                'bap' => $processo->external_reference,
                'protocol_number' => $processo->protocol_number,
                'imovel' => implode(' - ', array_filter([
                    trim(implode(', ', array_filter([$processo->address_street, $processo->address_number]))),
                    $processo->address_neighborhood,
                ])),
                'analysis_stage_label' => $processo->analysis_stage?->label(),
            ])
            ->all();

        // Setores efetivos dos processos válidos (interseção com os do usuário).
        $setoresDosProcessos = ViabilityRequest::query()
            ->whereIn('id', collect($processos)->pluck('id'))
            ->pluck('sector_id')
            ->unique()
            ->values();

        if (! $qualquerSetor && $setoresDosProcessos->count() > 1) {
            return redirect('/gestao/caixa-setor')->with('error', 'Selecione processos de um único setor por vez para distribuir.');
        }

        $analistas = $this->carga->cargaDosSetores($qualquerSetor ? null : $setoresDosProcessos);
        $totalEmAnalise = collect($analistas)->sum('total');

        $setoresDosAnalistas = collect($this->destinatarios('analisar-processos', $setoresDosProcessos, $qualquerSetor))
            ->pluck('setores', 'id');
        $analistas = array_map(fn (array $analista): array => $analista + [
            'setores' => $setoresDosAnalistas[$analista['analista_id']] ?? [],
        ], $analistas);

        $setoresHistorico = $setoresDosProcessos->filter()->isNotEmpty() ? $setoresDosProcessos->filter() : $sectorIds;

        $historico = Activity::query()
            ->where('log_name', 'analise')
            ->where('event', 'distribuir-lote')
            ->whereIn('properties->sector_id', $setoresHistorico->map(fn ($id): int => (int) $id)->all())
            ->with('causer')
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn (Activity $a): array => [
                'em' => $a->created_at?->toIso8601String(),
                'ator' => (string) ($a->properties['ator_nome'] ?? optional($a->causer)->name ?? '—'),
                'analista' => (string) ($a->properties['analista_nome'] ?? '—'),
                'qtd' => (int) ($a->properties['qtd'] ?? 0),
            ])
            ->all();

        $this->audit->log('analise', 'consulta-central', 'Consulta da central de distribuição', [
            'setores' => $setoresDosProcessos->all(),
            'processos' => collect($processos)->pluck('id')->all(),
        ]);

        return Inertia::render('gestao/caixa-setor/central', [
            'processos' => $processos,
            'analistas' => $analistas,
            'totalSelecionados' => count($processos),
            'totalEmAnalise' => $totalEmAnalise,
            'historico' => $historico,
            'distribuiEmQualquerSetor' => $qualquerSetor,
        ]);
    }

    /**
     * Proposta de distribuição em lote. Não tramita: calcula uma sugestão
     * equilibrada pela carga ativa e devolve para o Apoio revisar.
     */
    public function distribuicaoInteligente(Request $request): Response|RedirectResponse
    {
        $qualquerSetor = $this->distribuicao->distribuiEmQualquerSetor($request->user());

        $consulta = $this->naCaixaDoUsuario(ViabilityRequest::query(), $request->user())
            ->where('status', ViabilityRequestStatus::EmAnalise->value)
            ->whereNull('assigned_user_id');

        if ($request->boolean('todos')) {
            $consulta = $this->processos->aplicarFiltros($consulta, $this->filtrosDaCaixa($request));
        } else {
            $ids = collect(explode(',', (string) $request->string('ids')))
                ->map(fn ($id): int => (int) trim($id))
                ->filter()
                ->unique()
                ->values();
            $consulta->whereIn('id', $ids);
        }

        $models = $consulta
            ->orderBy('analysis_due_at')
            ->orderBy('id')
            ->get();

        $setores = $models->pluck('sector_id')->unique()->values();

        if (! $qualquerSetor && $setores->count() > 1) {
            return redirect('/gestao/caixa-setor')->with('error', 'Selecione processos de um único setor por vez para distribuir.');
        }

        if ($models->isEmpty()) {
            return redirect('/gestao/caixa-setor')->with('error', 'Nenhum processo elegível para distribuir.');
        }

        $carga = collect($this->carga->cargaDosSetores($qualquerSetor ? null : $setores))->keyBy('analista_id');
        $usuarios = User::query()
            ->with('sectors:id')
            ->whereIn('id', $carga->keys())
            ->get();

        $analistas = $usuarios->map(fn (User $usuario): array => [
            'analista_id' => $usuario->id,
            'analista' => $usuario->name,
            'total' => (int) ($carga[$usuario->id]['total'] ?? 0),
            'pode_vistoria' => $usuario->can('preencher-ficha-vistoria'),
            'setores' => $usuario->sectors->pluck('id')->map(fn ($id): int => (int) $id)->all(),
        ])->all();

        $processos = $models->map(fn (ViabilityRequest $processo): array => [
            'id' => $processo->id,
            'sector_id' => (int) $processo->sector_id,
            'analysis_status' => $processo->analysis_status?->value,
            'analysis_due_at' => $processo->analysis_due_at?->toIso8601String(),
            'bap' => $processo->external_reference,
            'protocol_number' => $processo->protocol_number,
        ])->all();

        $sugestao = $this->inteligente->sugerir($processos, $analistas, $qualquerSetor);

        $this->audit->log('analise', 'consulta-distribuicao-inteligente', 'Consulta da sugestão de distribuição inteligente', [
            'setores' => $setores->all(),
            'processos' => $models->pluck('id')->all(),
        ]);

        return Inertia::render('gestao/caixa-setor/distribuicao-inteligente', [
            'criterio' => $sugestao['criterio'],
            'alocacoes' => $sugestao['alocacoes'],
            'naoAlocados' => $sugestao['nao_alocados'],
            'totalSelecionados' => $models->count(),
        ]);
    }

    /**
     * Distribui um ou mais processos (lote — RN-007) a um analista do setor. A
     * validação estrutural é do DistribuirProcessoRequest. O lote inteiro é
     * rejeitado se algum item não estiver disponível na caixa do ator; falhas de
     * regra do destinatário permanecem isoladas no DistribuicaoService.
     */
    public function distribuir(DistribuirProcessoRequest $request): RedirectResponse
    {
        /** @var User $analista */
        $analista = User::query()->findOrFail($request->integer('analista_id'));

        $pedidos = collect($request->input('request_ids'))
            ->map(fn ($id): int => (int) $id)
            ->unique();

        $processos = $this->naCaixaDoUsuario(ViabilityRequest::query(), $request->user())
            ->whereIn('id', $pedidos)
            ->where('status', ViabilityRequestStatus::EmAnalise->value)
            ->whereNull('assigned_user_id')
            ->get();

        if ($processos->count() !== $pedidos->count()) {
            return back()->with('error', 'Alguns processos não estão disponíveis para distribuição na sua caixa.');
        }

        $setorEscolhido = $request->filled('sector_id') ? $request->integer('sector_id') : null;

        $resumo = $this->distribuicao->distribuirLote($processos, $analista, $request->user(), $setorEscolhido);

        $response = back()->with('status', "{$resumo['ok']} processo(s) enviado(s) para a caixa de entrada de {$analista->name}.");

        if ($resumo['falhas'] !== []) {
            // Motivo real da falha (nunca genérico): vínculo de setor OU a
            // regra da vistoria (só vistoriador recebe processo em vistoria).
            $motivos = collect($resumo['falhas'])->pluck('motivo')->unique()->implode(' ');

            $response->with('warning', count($resumo['falhas']).' processo(s) não distribuído(s): '.$motivos);
        }

        return $response;
    }

    /**
     * Distribuição em lote entre VÁRIOS analistas (Fase 3): recebe alocações
     * {analista, processos} e as executa numa única operação. Revalida no escopo
     * do ator (RN-004): todos os processos precisam estar na caixa do ator (setor
     * do usuário), EM ANÁLISE e sem responsável; sem sobreposição de ids entre
     * grupos; união = conjunto pedido. Lote inválido é rejeitado INTEIRO — nada é
     * gravado. Registra um resumo por analista (evento distribuir-lote) para o
     * histórico. A decisão de quem recebe o quê é do Apoio.
     */
    public function distribuirLote(DistribuirEmLoteRequest $request): RedirectResponse
    {
        $qualquerSetor = $this->distribuicao->distribuiEmQualquerSetor($request->user());
        $alocacoes = $request->input('alocacoes');

        // Todos os ids pedidos, achatados; rejeita sobreposição entre grupos.
        $todosIds = collect($alocacoes)->flatMap(fn (array $a): array => $a['request_ids'])->map(fn ($id): int => (int) $id);
        if ($todosIds->count() !== $todosIds->unique()->count()) {
            return back()->with('error', 'Um mesmo processo foi alocado para mais de um analista.');
        }

        try {
            $resultado = DB::transaction(function () use ($request, $qualquerSetor, $alocacoes, $todosIds): array {
                $processos = $this->naCaixaDoUsuario(ViabilityRequest::query(), $request->user())
                    ->whereIn('id', $todosIds->unique())
                    ->where('status', ViabilityRequestStatus::EmAnalise->value)
                    ->whereNull('assigned_user_id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if ($processos->count() !== $todosIds->unique()->count()) {
                    return ['error' => 'Alguns processos não estão disponíveis para distribuição na sua caixa.'];
                }

                if (! $qualquerSetor && $processos->pluck('sector_id')->unique()->count() > 1) {
                    return ['error' => 'Distribua processos de um único setor por vez.'];
                }

                $grupos = [];
                foreach ($alocacoes as $alocacao) {
                    /** @var User|null $analista */
                    $analista = User::query()->find($alocacao['analista_id']);
                    $requests = collect($alocacao['request_ids'])
                        ->map(fn ($id): ViabilityRequest => $processos[(int) $id]);
                    $sectorIdDoGrupo = (int) $requests->first()->sector_id;

                    if ($analista === null || ! $analista->can('analisar-processos')) {
                        return ['error' => 'O destinatário selecionado não possui permissão para analisar processos.'];
                    }

                    if ($qualquerSetor) {
                        try {
                            foreach ($requests as $processo) {
                                $this->distribuicao->setorDeDestino($processo, $analista, true);
                            }
                        } catch (DistribuicaoException $e) {
                            return ['error' => $e->getMessage()];
                        }
                    } elseif (! $analista->sectors()->where('sectors.id', $sectorIdDoGrupo)->exists()) {
                        return ['error' => 'O destinatário selecionado não está vinculado ao setor dos processos.'];
                    }

                    $grupoTemVistoria = $requests->contains(
                        fn (ViabilityRequest $processo): bool => in_array(
                            $processo->analysis_status,
                            [AnalysisStatus::Vistoriar, AnalysisStatus::Vistoriado],
                            true,
                        ),
                    );

                    if ($grupoTemVistoria && ! $analista->can('preencher-ficha-vistoria')) {
                        return ['error' => 'O destinatário selecionado não possui permissão para preencher ficha de vistoria.'];
                    }

                    $grupos[] = ['analista' => $analista, 'requests' => $requests];
                }

                $resumo = $this->distribuicao->distribuirMultiplo($grupos, $request->user());

                if ($resumo['falhas'] !== []) {
                    throw new \RuntimeException('A distribuição múltipla retornou falhas após a pré-validação.');
                }

                foreach ($grupos as $grupo) {
                    $qtd = $grupo['requests']->count();
                    $this->audit->log('analise', 'distribuir-lote', "Distribuição em lote de {$qtd} processo(s) para {$grupo['analista']->name}.", [
                        'analista_id' => $grupo['analista']->id,
                        'analista_nome' => $grupo['analista']->name,
                        'qtd' => $qtd,
                        'sector_id' => $grupo['requests']->first()->sector_id,
                        'ator_id' => $request->user()->id,
                    ]);
                }

                if ($request->input('origem') === 'inteligente') {
                    $this->audit->log('analise', 'distribuir-inteligente', 'Distribuição inteligente em lote confirmada pelo apoio.', [
                        'ator_id' => $request->user()->id,
                        'ator_nome' => $request->user()->name,
                        'qtd' => $todosIds->unique()->count(),
                        'sector_id' => $processos->first()->sector_id,
                        'proposta' => $request->input('proposta', []),
                        'confirmada' => collect($alocacoes)->map(fn (array $alocacao): array => [
                            'analista_id' => (int) $alocacao['analista_id'],
                            'request_ids' => array_map(intval(...), $alocacao['request_ids']),
                        ])->all(),
                        'alteracoes' => $request->input('alteracoes', []),
                    ]);
                }

                return ['total' => $resumo['ok']];
            });
        } catch (\RuntimeException) {
            return back()->with('error', 'Não foi possível concluir a distribuição; nada foi alterado.');
        }

        if (isset($resultado['error'])) {
            return back()->with('error', $resultado['error']);
        }

        return redirect('/gestao/caixa-setor')
            ->with('status', "{$resultado['total']} processo(s) enviado(s) para a caixa de entrada dos analistas.");
    }

    /**
     * Redistribui um processo já atribuído para outra analista do setor (apoio/
     * gestor). O service bloqueia após a conclusão da análise e mantém o prazo
     * original; a falha volta como aviso controlado, nunca silenciosa.
     */
    public function redistribuir(DistribuirProcessoRequest $request): RedirectResponse
    {
        /** @var User $analista */
        $analista = User::query()->findOrFail($request->integer('analista_id'));

        $processo = $this->naCaixaDoUsuario(ViabilityRequest::query(), $request->user())
            ->whereIn('id', $request->input('request_ids'))
            ->where('status', ViabilityRequestStatus::EmAnalise->value)
            ->firstOrFail();

        $setorEscolhido = $request->filled('sector_id') ? $request->integer('sector_id') : null;

        try {
            $this->distribuicao->redistribuir($processo, $analista, $request->user(), $setorEscolhido);
        } catch (DistribuicaoException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', "Processo redistribuído para {$analista->name}.");
    }

    /**
     * O analista assume um processo da caixa do seu setor (HU-081). Fora dos seus
     * setores, a ação é negada de forma controlada (aviso), nunca silenciosa.
     */
    public function assumir(Request $request, ViabilityRequest $viabilityRequest): RedirectResponse
    {
        try {
            $this->distribuicao->assumir($viabilityRequest, $request->user());
        } catch (DistribuicaoException) {
            return back()->with('error', 'Você só pode assumir processos da caixa dos seus setores.');
        }

        return back()->with('status', 'Processo assumido.');
    }

    /**
     * Recorte da caixa (RN-004): os setores do usuário. Quem distribui em
     * qualquer setor vê todos os processos, inclusive os que estão sem setor.
     *
     * @param  Builder<ViabilityRequest>  $query
     * @return Builder<ViabilityRequest>
     */
    private function naCaixaDoUsuario(Builder $query, User $user): Builder
    {
        if ($this->distribuicao->distribuiEmQualquerSetor($user)) {
            return $query;
        }

        return $query->whereIn('sector_id', $user->sectors()->pluck('sectors.id'));
    }

    /**
     * Destinatários do seletor da distribuição, com os setores de cada um: o
     * apoio escolhe o setor quando o analista está em mais de um.
     *
     * @param  iterable<int, int>  $sectorIds
     * @return list<array{id: int, name: string, setores: list<array{id: int, name: string}>}>
     */
    private function destinatarios(string $permissao, iterable $sectorIds, bool $qualquerSetor): array
    {
        return User::query()
            ->permission($permissao)
            ->when(! $qualquerSetor, fn ($query) => $query->whereHas('sectors', fn ($setores) => $setores->whereIn('sectors.id', $sectorIds)))
            ->with('sectors:id,name')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $usuario): array => [
                'id' => $usuario->id,
                'name' => $usuario->name,
                'setores' => $usuario->sectors
                    ->sortBy('name')
                    ->map(fn ($setor): array => ['id' => $setor->id, 'name' => $setor->name])
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    /**
     * Filtros de pesquisa da caixa (subconjunto do SAPS), crus da query string —
     * o ProcessoQueryService normaliza e ignora os vazios.
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
}

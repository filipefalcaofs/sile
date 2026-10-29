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
use App\Services\Analise\CargaAnalistaService;
use App\Services\Analise\DistribuicaoException;
use App\Services\Analise\DistribuicaoInteligenteService;
use App\Services\Analise\DistribuicaoService;
use App\Services\Analise\ProcessoQueryService;
use App\Services\Servicos\ServicoNomenclaturaResolver;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Caixa do setor da análise técnica (HU-076/077): a fila da distribuição. O
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
        $visao = match ($request->input('visao')) {
            'distribuidos' => 'distribuidos',
            'para_conclusao' => 'para_conclusao',
            default => 'para_distribuir',
        };

        $filtros = $this->filtrosDaCaixa($request);

        $paraConclusao = AnalysisStatus::ParaConclusao->value;

        $base = $this->naCaixaDoUsuario(ViabilityRequest::query(), $request->user())
            ->whereIn('status', ViabilityRequestStatus::naCaixaDoSetor());

        $base = $this->processos->aplicarFiltros($base, $filtros);

        // Para conclusão é cross-setor para quem distribui: a conclusão não fica presa ao setor.
        // Com destinatário (distribuído, assumido ou escolhido no envio) o processo sai da aba
        // e fica na caixa de entrada dele.
        $baseConclusao = $this->processos->aplicarFiltros(
            $this->paraConclusaoDoUsuario(ViabilityRequest::query(), $request->user())
                ->whereIn('status', ViabilityRequestStatus::naCaixaDoSetor())
                ->where('analysis_status', $paraConclusao)
                ->whereNull('conclusao_encaminhada_para_user_id'),
            $filtros,
        );
        $foraDaConclusao = fn ($query) => $query->where(
            fn ($q) => $q->whereNull('analysis_status')->orWhere('analysis_status', '!=', $paraConclusao),
        );
        // Distribuídos inclui o Para conclusão que já tem destinatário: fora daqui ele
        // não aparece em aba nenhuma e não poderia ser redistribuído.
        $distribuidos = fn ($query) => $query->where(fn ($q) => $q
            ->where(fn ($emAndamento) => $foraDaConclusao($emAndamento->whereNotNull('assigned_user_id')))
            ->orWhere(fn ($conclusao) => $conclusao
                ->where('analysis_status', $paraConclusao)
                ->whereNotNull('conclusao_encaminhada_para_user_id')));

        $contadores = [
            'para_distribuir' => $foraDaConclusao((clone $base)->whereNull('assigned_user_id'))->count(),
            'distribuidos' => $distribuidos(clone $base)->count(),
            'para_conclusao' => (clone $baseConclusao)->count(),
        ];

        $processos = ($visao === 'para_conclusao' ? $baseConclusao : $base)
            ->when($visao === 'para_distribuir', fn ($query) => $foraDaConclusao($query->whereNull('assigned_user_id')))
            ->when($visao === 'distribuidos', fn ($query) => $distribuidos($query))
            ->with(['company', 'serviceType:id,name', 'eventos', 'cnaes', 'sector:id,name', 'assignedTo:id,name', 'conclusaoEncaminhadaPara:id,name', 'conclusaoIndicadaPara:id,name'])
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
                // Serviço legível: nome parametrizado dos eventos da JUCEB; sem eles, o tipo interno.
                'servicos' => app(ServicoNomenclaturaResolver::class)->servicos($processo),
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
                'tipo_imovel' => $processo->tipo_imovel ?? $processo->tipo_imovel_cadastro,
                'status_tramitacao' => $processo->rotuloStatusTramitacao(),
                'data_entrada' => $processo->protocoled_at?->toIso8601String(),
                // Eixo operacional: a linha em vistoria distribui para a lista
                // de vistoriadores, não para a lista geral de analistas.
                'analysis_status' => $processo->analysis_status?->value,
                'analysis_stage' => $processo->analysis_stage?->value,
                'analysis_stage_label' => $processo->analysis_stage?->label(),
                'sector_id' => $processo->sector_id,
                'sector' => $processo->sector?->name,
                'assigned_user_id' => $processo->assigned_user_id,
                'assigned_to' => $processo->assignedTo?->name,
                'encaminhado_para' => $processo->conclusaoEncaminhadaPara?->name,
                'indicado_para' => $processo->analysis_status === AnalysisStatus::ParaConclusao ? $processo->conclusaoIndicadaPara?->name : null,
                'analysis_due_at' => $processo->analysis_due_at?->toIso8601String(),
                // Redistribuir só antes da conclusão (regra única do enum).
                'pode_redistribuir' => ($processo->assigned_user_id !== null || $processo->conclusao_encaminhada_para_user_id !== null)
                    && ($processo->analysis_status?->permiteRedistribuicao() ?? false),
            ]);

        // Só quem tramita (distribuir-processos — gestor/apoio) distribui — e só
        // para ele faz sentido carregar a lista de analistas do(s) setor(es) que
        // alimenta o seletor. O analista apenas assume, então recebe a lista
        // vazia (minimização do payload). Assumir é de quem analisa
        // (analisar-processos) — o apoio tramita, mas não assume.
        $podeDistribuir = $request->user()->can('distribuir-processos');
        $podeAssumir = $request->user()->can('analisar-processos');

        $distribuiEmQualquerSetor = $qualquerSetor || ($podeDistribuir && $visao === 'para_conclusao');

        $analistas = $podeDistribuir
            ? $this->destinatarios('analisar-processos', $sectorIds, $distribuiEmQualquerSetor)
            : [];

        // Vistoriadores do(s) setor(es): o seletor da distribuição usa ESTA
        // lista quando a linha está no eixo de vistoria (a regra é enforced
        // pelo DistribuicaoService; aqui é a conveniência da tela).
        $vistoriadores = $podeDistribuir
            ? $this->destinatarios('preencher-ficha-vistoria', $sectorIds, $qualquerSetor, ['Vistoriador', 'administrador'])
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
            'analistaOptions' => $this->processos->analistasParaFiltro($request->user()),
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'podeDistribuir' => $podeDistribuir,
            'podeAssumir' => $podeAssumir,
            'distribuiEmQualquerSetor' => $distribuiEmQualquerSetor,
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
            ->whereIn('status', ViabilityRequestStatus::naCaixaDoSetor())
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
            return redirect($this->urlDaCaixa($request))->with('error', 'Selecione processos de um único setor por vez para distribuir.');
        }

        $analistas = $this->carga->cargaDosSetores($qualquerSetor ? null : $setoresDosProcessos);
        $totalEmAnalise = collect($analistas)->sum('total');

        $destinatarios = collect($this->destinatarios('analisar-processos', $setoresDosProcessos, $qualquerSetor))->keyBy('id');
        $analistas = array_map(fn (array $analista): array => $analista + [
            'setores' => $destinatarios[$analista['analista_id']]['setores'] ?? [],
            'perfis' => $destinatarios[$analista['analista_id']]['perfis'] ?? [],
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
                'ator' => (string) ($a->properties['ator_nome'] ?? optional($a->causer)->name ?? '-'),
                'analista' => (string) ($a->properties['analista_nome'] ?? '-'),
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
            'retorno' => $this->retornoDaCaixa($request),
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
            ->whereIn('status', ViabilityRequestStatus::naCaixaDoSetor())
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
            return redirect($this->urlDaCaixa($request))->with('error', 'Selecione processos de um único setor por vez para distribuir.');
        }

        if ($models->isEmpty()) {
            return redirect($this->urlDaCaixa($request))->with('error', 'Nenhum processo elegível para distribuir.');
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
            'retorno' => $this->retornoDaCaixa($request),
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

        $processos = ViabilityRequest::query()
            ->whereIn('id', $pedidos)
            ->whereIn('status', ViabilityRequestStatus::naCaixaDoSetor())
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $naCaixa) => $this->naCaixaDoUsuario($naCaixa, $request->user())
                    ->where($this->semResponsavelOuParaConclusao(...)))
                ->orWhere(fn (Builder $conclusao) => $this->paraConclusaoDoUsuario($conclusao, $request->user())
                    ->where('analysis_status', AnalysisStatus::ParaConclusao->value)))
            ->get();

        if ($processos->count() !== $pedidos->count()) {
            return back()->with('error', $this->motivoDeIndisponibilidade($pedidos, $processos->pluck('id'), $request->user()));
        }

        $setorEscolhido = $request->filled('sector_id') ? $request->integer('sector_id') : null;

        $resumo = $this->distribuicao->distribuirLote($processos, $analista, $request->user(), $setorEscolhido);

        // Motivo real da falha (nunca genérico): vínculo de setor OU a
        // regra da vistoria (só vistoriador recebe processo em vistoria).
        $motivos = collect($resumo['falhas'])->pluck('motivo')->unique()->implode(' ');

        if ($resumo['ok'] === 0) {
            return back()->with('warning', 'Nenhum processo foi distribuído. '.$motivos);
        }

        // A central envia o retorno da caixa e volta a ela já com o aviso; o modal da
        // caixa não envia e permanece onde está.
        $response = ($request->has('retorno') ? redirect($this->urlDaCaixa($request)) : back())
            ->with('status', "{$resumo['ok']} processo(s) enviado(s) para a caixa de entrada de {$analista->name}.");

        if ($resumo['falhas'] !== []) {
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
                    ->whereIn('status', ViabilityRequestStatus::naCaixaDoSetor())
                    ->where($this->semResponsavelOuParaConclusao(...))
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if ($processos->count() !== $todosIds->unique()->count()) {
                    return ['error' => $this->motivoDeIndisponibilidade($todosIds->unique(), $processos->keys(), $request->user())];
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
                        fn (ViabilityRequest $processo): bool => $processo->analysis_status === AnalysisStatus::Vistoriar,
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

        return redirect($this->urlDaCaixa($request))
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
            ->whereIn('status', ViabilityRequestStatus::naCaixaDoSetor())
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
     * O analista assume um processo da caixa do seu setor (HU-077). Fora dos seus
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
     * Distribuível: sem responsável, ou na aba "Para conclusão", onde o apoio
     * também reatribui quem vai concluir.
     *
     * @param  Builder<ViabilityRequest>  $query
     */
    /**
     * Explica, processo a processo, por que não pode ser distribuído pelo ator.
     *
     * @param  Collection<int, int>  $pedidos
     * @param  Collection<int, int>  $disponiveis
     */
    private function motivoDeIndisponibilidade(Collection $pedidos, Collection $disponiveis, User $ator): string
    {
        $faltantes = $pedidos->diff($disponiveis)->values();
        $encontrados = ViabilityRequest::query()
            ->with(['sector:id,name', 'assignedTo:id,name'])
            ->whereIn('id', $faltantes)
            ->get()
            ->keyBy('id');
        $setoresDoAtor = $ator->sectors()->pluck('sectors.id')->map(fn ($id): int => (int) $id);

        $motivos = $faltantes->map(function (int $id) use ($encontrados, $setoresDoAtor): string {
            $processo = $encontrados->get($id);

            if ($processo === null) {
                return "O processo #{$id} não foi encontrado.";
            }

            $nome = $processo->external_reference ?: ($processo->protocol_number ?: "#{$id}");

            if (! in_array($processo->status->value, ViabilityRequestStatus::naCaixaDoSetor(), true)) {
                return "{$nome}: não está em análise (situação atual: {$processo->status->label()}).";
            }

            if ($processo->assigned_user_id !== null && $processo->analysis_status !== AnalysisStatus::ParaConclusao) {
                $responsavel = $processo->assignedTo?->name ?? 'outro analista';

                return "{$nome}: já está distribuído para {$responsavel}. Use a redistribuição.";
            }

            if (! $setoresDoAtor->contains((int) $processo->sector_id)) {
                $setor = $processo->sector?->name ?? 'sem setor';

                return "{$nome}: pertence ao setor {$setor}, que não é um dos seus setores. Peça o vínculo ao setor ou a permissão de distribuir em qualquer setor.";
            }

            return "{$nome}: não está disponível na sua caixa.";
        });

        return 'Não foi possível distribuir. '.$motivos->implode(' ');
    }

    private function semResponsavelOuParaConclusao(Builder $query): void
    {
        $query->whereNull('assigned_user_id')
            ->orWhere('analysis_status', AnalysisStatus::ParaConclusao->value);
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
     * Recorte da aba Para conclusão: quem distribui vê os processos de todos os
     * setores, porque a conclusão não depende do setor de origem.
     *
     * @param  Builder<ViabilityRequest>  $query
     * @return Builder<ViabilityRequest>
     */
    private function paraConclusaoDoUsuario(Builder $query, User $user): Builder
    {
        if ($user->can('distribuir-processos')) {
            return $query;
        }

        return $this->naCaixaDoUsuario($query, $user);
    }

    /**
     * Destinatários do seletor da distribuição, com os setores de cada um: o
     * apoio escolhe o setor quando o analista está em mais de um.
     *
     * @param  iterable<int, int>  $sectorIds
     * @return list<array{id: int, name: string, perfis: list<string>, setores: list<array{id: int, name: string}>}>
     */
    private function destinatarios(string $permissao, iterable $sectorIds, bool $qualquerSetor, ?array $perfis = null): array
    {
        return User::query()
            ->permission($permissao)
            ->when($perfis !== null, fn ($query) => $query->role($perfis))
            ->when(! $qualquerSetor, fn ($query) => $query->whereHas('sectors', fn ($setores) => $setores->whereIn('sectors.id', $sectorIds)))
            ->with(['sectors:id,name', 'roles:id,name'])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $usuario): array => [
                'id' => $usuario->id,
                'name' => $usuario->name,
                'perfis' => $usuario->roles->pluck('name')->sort()->values()->all(),
                'setores' => $usuario->sectors
                    ->sortBy('name')
                    ->map(fn ($setor): array => ['id' => $setor->id, 'name' => $setor->name])
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    /**
     * Query string da caixa (filtros, aba e paginação) a que a central e a
     * distribuição inteligente voltam, para o usuário não refazer os filtros.
     * Só chaves conhecidas e não vazias: nunca ecoa entrada arbitrária.
     */
    private function retornoDaCaixa(Request $request): string
    {
        parse_str((string) $request->input('retorno', ''), $bruto);

        $permitidas = [...ProcessoQueryService::CHAVES_FILTRO_CAIXA, 'visao', 'per_page'];
        $params = [];

        foreach ($permitidas as $chave) {
            $valor = $bruto[$chave] ?? null;

            if (is_string($valor) && $valor !== '') {
                $params[$chave] = $valor;
            }
        }

        return http_build_query($params);
    }

    private function urlDaCaixa(Request $request): string
    {
        $query = $this->retornoDaCaixa($request);

        return '/gestao/caixa-setor'.($query === '' ? '' : '?'.$query);
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
        return app(ServicoNomenclaturaResolver::class)->opcoesDoFiltro();
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

<?php

namespace App\Services\Relatorios;

use App\Enums\ViabilityRequestStatus;
use App\Models\Sector;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Expresso\BusinessDeadlineCalculator;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Painel da operação (home da gestão): compõe os serviços de relatório já
 * existentes sobre dado real da janela. Contagem zerada é fato; taxa, variação
 * e tempo médio ficam null sem base — nunca um número fabricado.
 */
class PainelOperacaoService
{
    /** @var list<int> */
    public const JANELAS = [7, 30, 90];

    private const STATUS_EM_ANALISE = [
        ViabilityRequestStatus::EmAnalise->value,
        ViabilityRequestStatus::EmPendencia->value,
    ];

    private const STATUS_FORA_ESTOQUE = [
        ViabilityRequestStatus::Rascunho->value,
        ViabilityRequestStatus::Cancelada->value,
        ViabilityRequestStatus::Deferida->value,
        ViabilityRequestStatus::Indeferida->value,
    ];

    public function __construct(
        private readonly IndicadoresViabilidadeService $indicadores,
        private readonly ExpressoQuedaService $quedas,
        private readonly SlaVencimentosService $sla,
        private readonly PendenciasRelatorioService $pendencias,
        private readonly ComunicacoesFalhaService $falhas,
        private readonly TempoAnaliseService $tempos,
        private readonly ProdutividadeAnalistaService $produtividade,
        private readonly BusinessDeadlineCalculator $prazos,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function montar(int $janelaDias, bool $nominal): array
    {
        $periodo = $this->filtrosDaJanela($janelaDias);
        $anterior = $this->filtrosAnteriores($janelaDias);
        $agora = ReportFilters::fromArray([]);
        $expressa = $this->quedas->taxaRespostaExpressa($periodo);
        $decisoes = $this->indicadores->decisoesPorFlow($periodo);
        $protocolos = $this->indicadores->volumeProtocolos($periodo);
        $protocolosAnteriores = $this->indicadores->volumeProtocolos($anterior);
        $sla = $this->sla->resumo($agora);
        $detalheSla = $this->sla->detalheAcao($agora);
        $serie = $this->indicadores->serieFluxo($periodo);
        $estoque = $this->sla->estoqueTotal();
        $saldo = $protocolos - $decisoes['total'];
        $cumprimento = $this->sla->cumprimento($periodo);
        $tempoDecisao = $this->tempoAteDecisao($periodo);
        $falhas = $this->falhas->resumo($periodo);
        $pendencias = $this->pendencias->resumo($agora);

        return [
            'janela_dias' => $janelaDias,
            'janelas' => self::JANELAS,
            'data_de' => $periodo->from()?->toDateString(),
            'data_ate' => $periodo->to()?->toDateString(),
            'gerado_em' => now()->toIso8601String(),
            'protocolos' => $protocolos,
            'protocolos_periodo_anterior' => $protocolosAnteriores,
            'variacao_protocolos' => $protocolosAnteriores > 0
                ? round(($protocolos - $protocolosAnteriores) / $protocolosAnteriores * 100, 1)
                : null,
            'decisoes' => $decisoes,
            'estoque_total' => $estoque,
            'atrasados' => $sla['vencidos'],
            'saldo_periodo' => $saldo,
            'pico_entrada' => $serie === [] ? null : max(array_column($serie, 'entrada')),
            'projecao_30_dias' => $janelaDias > 0
                ? (int) round($estoque + ($saldo / $janelaDias) * 30)
                : null,
            'taxa_expressa' => $expressa['taxa'],
            'meta_expressa' => $expressa['meta'],
            'serie_fluxo' => $serie,
            'expresso' => [
                'respondidas' => $expressa['respondidas'],
                'elegiveis' => $expressa['elegiveis'],
                'taxa' => $expressa['taxa'],
                'meta' => $expressa['meta'],
                'motivos' => array_map(
                    fn (array $motivo): array => [
                        'rotulo' => $motivo['rotulo'],
                        'total' => $motivo['total'],
                    ],
                    $this->quedas->rankingMotivos($periodo),
                ),
            ],
            'tempo_decisao' => [
                'media_minutos' => $tempoDecisao['media_minutos'],
                'amostras' => $tempoDecisao['amostras'],
                'meta_dias' => $this->metaDias('analise.sla.analise_dias', (int) config('viabiliza.analise.sla.analise_dias', 10)),
                'dentro_prazo_percentual' => $cumprimento['taxa'],
            ],
            'acoes' => $this->acoes($sla, $detalheSla, $pendencias['vencidas'], $falhas),
            'etapas' => $this->etapas($periodo),
            'aging' => $this->agingDesdeProtocolo(),
            'carga' => [
                'nominal' => $nominal,
                'setores' => $this->cargaSetores($periodo),
                'analistas' => $this->cargaAnalistas($periodo, $nominal),
            ],
        ];
    }

    private function filtrosDaJanela(int $janelaDias): ReportFilters
    {
        return ReportFilters::fromArray([
            'data_de' => now()->subDays($janelaDias)->toDateString(),
            'data_ate' => now()->toDateString(),
        ]);
    }

    private function filtrosAnteriores(int $janelaDias): ReportFilters
    {
        $inicioAtual = now()->subDays($janelaDias)->startOfDay();
        $fimAnterior = $inicioAtual->copy()->subDay();

        return ReportFilters::fromArray([
            'data_de' => $fimAnterior->copy()->subDays($janelaDias)->toDateString(),
            'data_ate' => $fimAnterior->toDateString(),
        ]);
    }

    /**
     * @param  array{em_andamento: int, vencidos: int, vencendo: int, janela_vencimento_dias: int}  $sla
     * @param  array{vencendo_com_analista: int, vencendo_sem_analista: int, vencido_mais_antigo_dias: int|null}  $detalhe
     * @param  array{falharam: int, bloqueadas: int, por_canal: list<array{canal: string, canal_label: string, total: int}>}  $falhas
     * @return list<array{chave: string, rotulo: string, valor: int, nota: string, acao: string, tom: string}>
     */
    private function acoes(array $sla, array $detalhe, int $pendenciasVencidas, array $falhas): array
    {
        $janela = $sla['janela_vencimento_dias'];
        $unidade = $janela === 1 ? 'dia' : 'dias';
        $notaFalhas = $falhas['por_canal'] === []
            ? 'nenhuma notificação falhou ou foi bloqueada no período'
            : 'no período: '.implode(' · ', array_map(
                fn (array $canal): string => $canal['total'].' '.$canal['canal_label'],
                $falhas['por_canal'],
            ));

        return [
            [
                'chave' => 'atrasados',
                'rotulo' => 'Prazo vencido',
                'valor' => $sla['vencidos'],
                'nota' => $this->notaAtraso($detalhe['vencido_mais_antigo_dias']),
                'acao' => 'Abrir lista priorizada',
                'tom' => 'critico',
            ],
            [
                'chave' => 'vencendo',
                'rotulo' => "Vence em {$janela} {$unidade}",
                'valor' => $sla['vencendo'],
                'nota' => $sla['vencendo'] === 0
                    ? "nenhum processo vence nos próximos {$janela} {$unidade}"
                    : $detalhe['vencendo_com_analista'].' com analista · '.$detalhe['vencendo_sem_analista'].' sem responsável',
                'acao' => 'Revisar prioridades',
                'tom' => 'alerta',
            ],
            [
                'chave' => 'distribuir',
                'rotulo' => 'Sem responsável',
                'valor' => $this->semResponsavel(),
                'nota' => 'aguardando distribuição na caixa do setor',
                'acao' => 'Distribuir agora',
                'tom' => 'acao',
            ],
            [
                'chave' => 'pendencias',
                'rotulo' => 'Pendência vencida',
                'valor' => $pendenciasVencidas,
                'nota' => 'exigência aberta cujo prazo de resposta do requerente já venceu',
                'acao' => 'Tratar pendências',
                'tom' => 'neutro',
            ],
            [
                'chave' => 'falhas',
                'rotulo' => 'Falha técnica',
                'valor' => $falhas['falharam'] + $falhas['bloqueadas'],
                'nota' => $notaFalhas,
                'acao' => 'Ver ocorrências',
                'tom' => 'falha',
            ],
        ];
    }

    private function notaAtraso(?int $dias): string
    {
        $base = 'processos com o prazo de análise ultrapassado';

        if ($dias === null) {
            return $base;
        }

        if ($dias < 1) {
            return $base.'; o mais antigo venceu hoje';
        }

        $unidade = $dias === 1 ? 'dia' : 'dias';

        return $base."; o mais antigo há {$dias} {$unidade}";
    }

    private function semResponsavel(): int
    {
        return ViabilityRequest::query()
            ->where('status', ViabilityRequestStatus::EmAnalise->value)
            ->whereNull('assigned_user_id')
            ->count();
    }

    /**
     * @return array{media_minutos: int|null, amostras: int}
     */
    private function tempoAteDecisao(ReportFilters $f): array
    {
        $soma = 0;
        $amostras = 0;

        $linhas = ViabilityDecision::query()
            ->join('viability_requests as vr', 'vr.id', '=', 'viability_decisions.viability_request_id')
            ->whereNotNull('vr.protocoled_at')
            ->when($f->from(), fn (Builder $q, Carbon $from): Builder => $q->where('viability_decisions.decided_at', '>=', $from))
            ->when($f->to(), fn (Builder $q, Carbon $to): Builder => $q->where('viability_decisions.decided_at', '<=', $to))
            ->select('viability_decisions.decided_at', 'vr.protocoled_at as protocoled_at')
            ->cursor();

        foreach ($linhas as $linha) {
            $soma += $this->prazos->businessDurationBetween(
                Carbon::parse((string) $linha->protocoled_at),
                Carbon::parse((string) $linha->decided_at),
            );
            $amostras++;
        }

        return [
            'media_minutos' => $amostras > 0 ? (int) round($soma / $amostras) : null,
            'amostras' => $amostras,
        ];
    }

    /**
     * @return list<array{chave: string, label: string, media_dias: float|null, meta_dias: int|null, amostras: int}>
     */
    private function etapas(ReportFilters $f): array
    {
        $porEtapa = collect($this->tempos->tempoPorEtapa($f)['etapas'])->keyBy('etapa');
        $tvl = $this->tempos->tempoEmissaoTvl($f);
        $metaAnalise = $this->metaDias('analise.sla.analise_dias', (int) config('viabiliza.analise.sla.analise_dias', 10));
        $metaPendencia = $this->metaDias(
            'analise.pendencia.prazo_resposta_dias',
            (int) config('viabiliza.analise.pendencia.prazo_resposta_dias', 15),
        );

        $linhas = [
            ['chave' => 'espera', 'label' => 'Espera (encaminhamento e Junta)', 'etapa' => 'espera', 'meta' => null],
            ['chave' => 'analise', 'label' => 'Análise técnica', 'etapa' => 'analise', 'meta' => $metaAnalise],
            ['chave' => 'pendencia', 'label' => 'Espera pelo requerente', 'etapa' => 'pendencia', 'meta' => $metaPendencia],
        ];

        $resultado = array_map(function (array $linha) use ($porEtapa): array {
            $medida = $porEtapa->get($linha['etapa']);
            $minutos = $medida['media_minutos'] ?? null;

            return [
                'chave' => $linha['chave'],
                'label' => $linha['label'],
                'media_dias' => $minutos === null ? null : round($minutos / 1440, 1),
                'meta_dias' => $linha['meta'],
                'amostras' => (int) ($medida['amostras'] ?? 0),
            ];
        }, $linhas);

        $resultado[] = [
            'chave' => 'emissao_tvl',
            'label' => 'Emissão da viabilidade',
            'media_dias' => $tvl['media_minutos'] === null ? null : round($tvl['media_minutos'] / 1440, 1),
            'meta_dias' => null,
            'amostras' => $tvl['amostras'],
        ];

        return $resultado;
    }

    /**
     * @return list<array{faixa: string, label: string, total: int}>
     */
    private function agingDesdeProtocolo(): array
    {
        $limite5 = now()->copy()->subDays(5)->startOfDay();
        $limite10 = now()->copy()->subDays(10)->startOfDay();
        $limite20 = now()->copy()->subDays(20)->startOfDay();

        $totais = ViabilityRequest::query()
            ->whereNotIn('status', self::STATUS_FORA_ESTOQUE)
            ->selectRaw(
                'sum(case when protocoled_at is not null and protocoled_at >= ? then 1 else 0 end) as faixa_0_5,
                sum(case when protocoled_at is not null and protocoled_at >= ? and protocoled_at < ? then 1 else 0 end) as faixa_6_10,
                sum(case when protocoled_at is not null and protocoled_at >= ? and protocoled_at < ? then 1 else 0 end) as faixa_11_20,
                sum(case when protocoled_at is not null and protocoled_at < ? then 1 else 0 end) as faixa_acima_20',
                [$limite5, $limite10, $limite5, $limite20, $limite10, $limite20],
            )
            ->first();

        return [
            ['faixa' => '0_5', 'label' => '0 a 5 dias', 'total' => (int) ($totais->faixa_0_5 ?? 0)],
            ['faixa' => '6_10', 'label' => '6 a 10 dias', 'total' => (int) ($totais->faixa_6_10 ?? 0)],
            ['faixa' => '11_20', 'label' => '11 a 20 dias', 'total' => (int) ($totais->faixa_11_20 ?? 0)],
            ['faixa' => 'acima_20', 'label' => 'Mais de 20 dias', 'total' => (int) ($totais->faixa_acima_20 ?? 0)],
        ];
    }

    /**
     * @return list<array{nome: string, analistas: int, em_maos: int, por_analista: float|null, atrasados: int, decididos: int}>
     */
    private function cargaSetores(ReportFilters $periodo): array
    {
        $agora = now();
        $cargas = ViabilityRequest::query()
            ->whereIn('status', self::STATUS_EM_ANALISE)
            ->whereNotNull('sector_id')
            ->groupBy('sector_id')
            ->selectRaw(
                'sector_id, count(*) as em_maos, sum(case when analysis_due_at is not null and analysis_due_at < ? then 1 else 0 end) as atrasados',
                [$agora],
            )
            ->get()
            ->keyBy(fn ($linha): int => (int) $linha->sector_id);

        $decididos = ViabilityDecision::query()
            ->join('viability_requests as vr', 'vr.id', '=', 'viability_decisions.viability_request_id')
            ->whereNotNull('vr.sector_id')
            ->when($periodo->from(), fn (Builder $q, Carbon $from): Builder => $q->where('viability_decisions.decided_at', '>=', $from))
            ->when($periodo->to(), fn (Builder $q, Carbon $to): Builder => $q->where('viability_decisions.decided_at', '<=', $to))
            ->groupBy('vr.sector_id')
            ->get([
                'vr.sector_id as sector_id',
                DB::raw('count(*) as total'),
            ])
            ->mapWithKeys(fn ($linha): array => [(int) $linha->sector_id => (int) $linha->total]);

        $analistasPorSetor = [];

        User::query()
            ->permission('analisar-processos')
            ->with('sectors:id')
            ->get(['id', 'name'])
            ->each(function (User $analista) use (&$analistasPorSetor): void {
                foreach ($analista->sectors as $setor) {
                    $analistasPorSetor[$setor->id] = ($analistasPorSetor[$setor->id] ?? 0) + 1;
                }
            });

        $ids = $cargas->keys()
            ->merge($decididos->keys())
            ->merge(array_keys($analistasPorSetor))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return Sector::query()
            ->whereIn('id', $ids)
            ->get(['id', 'name'])
            ->map(function (Sector $setor) use ($cargas, $decididos, $analistasPorSetor): array {
                $carga = $cargas->get($setor->id);
                $emMaos = (int) ($carga?->em_maos ?? 0);
                $analistas = (int) ($analistasPorSetor[$setor->id] ?? 0);

                return [
                    'nome' => $setor->name,
                    'analistas' => $analistas,
                    'em_maos' => $emMaos,
                    'por_analista' => $analistas > 0 ? round($emMaos / $analistas, 1) : null,
                    'atrasados' => (int) ($carga?->atrasados ?? 0),
                    'decididos' => (int) ($decididos->get($setor->id) ?? 0),
                ];
            })
            ->sortBy([
                ['em_maos', 'desc'],
                ['nome', 'asc'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{rotulo: string, setor: string|null, em_maos: int, atrasados: int, decididas: int, deferidas: int, indeferidas: int}>
     */
    private function cargaAnalistas(ReportFilters $periodo, bool $nominal): array
    {
        $agora = now();
        $decisoes = $this->produtividade
            ->decisoesAgregadas($periodo, $nominal)
            ->orderByDesc('decididas')
            ->orderBy('analista_id')
            ->get()
            ->keyBy(fn ($linha): int => (int) $linha->analista_id);

        $cargas = ViabilityRequest::query()
            ->whereIn('status', self::STATUS_EM_ANALISE)
            ->whereNotNull('assigned_user_id')
            ->groupBy('assigned_user_id')
            ->selectRaw(
                'assigned_user_id as analista_id, count(*) as em_maos, sum(case when analysis_due_at is not null and analysis_due_at < ? then 1 else 0 end) as atrasados',
                [$agora],
            )
            ->get()
            ->keyBy(fn ($linha): int => (int) $linha->analista_id);

        $ids = $decisoes->keys()->merge($cargas->keys())->map(fn ($id): int => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $nomes = $nominal
            ? User::query()->whereIn('id', $ids)->get(['id', 'name'])->mapWithKeys(fn (User $usuario): array => [$usuario->id => $usuario->name])
            : collect();

        $setores = [];

        foreach (DB::table('sector_user')
            ->join('sectors', 'sectors.id', '=', 'sector_user.sector_id')
            ->whereIn('sector_user.user_id', $ids)
            ->orderBy('sectors.name')
            ->get(['sector_user.user_id', 'sectors.name as nome']) as $linha) {
            $id = (int) $linha->user_id;

            if (! isset($setores[$id])) {
                $setores[$id] = (string) $linha->nome;
            }
        }

        $linhas = $ids->map(function (int $id) use ($decisoes, $cargas, $nomes, $setores, $nominal): array {
            $decisao = $decisoes->get($id);
            $carga = $cargas->get($id);

            return [
                'rotulo' => $nominal ? (string) ($decisao?->nome ?? $nomes->get($id) ?? 'Analista') : '',
                'setor' => $setores[$id] ?? null,
                'em_maos' => (int) ($carga?->em_maos ?? 0),
                'atrasados' => (int) ($carga?->atrasados ?? 0),
                'decididas' => (int) ($decisao?->decididas ?? 0),
                'deferidas' => (int) ($decisao?->deferidas ?? 0),
                'indeferidas' => (int) ($decisao?->indeferidas ?? 0),
            ];
        })->sortBy([
            ['em_maos', 'desc'],
            ['decididas', 'desc'],
        ])->values();

        return $linhas->map(function (array $linha, int $indice) use ($nominal): array {
            if (! $nominal) {
                $linha['rotulo'] = 'Analista #'.($indice + 1);
            }

            return $linha;
        })->all();
    }

    private function metaDias(string $chave, int $fallback): int
    {
        return (int) Settings::get($chave, $fallback);
    }
}

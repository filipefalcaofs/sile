<?php

namespace App\Services\Relatorios;

use App\Enums\DecisionOutcome;
use App\Models\ViabilityRequest;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * Observatório de Saturação Locacional (Módulo 2). Mede, por AGREGAÇÃO SQL real,
 * a concentração de estabelecimentos DEFERIDOS por bairro×CNAE e compara com a
 * capacidade recomendada (parâmetro HU-014 administrável: mapa código CNAE →
 * limite), classificando cada par como ok/saturando/saturado pelos limiares
 * parametrizados. Insumo de política urbana da SEDUR (controle de adensamento e
 * externalidades — Estatuto da Cidade).
 *
 * O recorte é pelo bairro oficial materializado na identificação do imóvel
 * (`bairro_oficial`, camada GeoSalvador), com o bairro digitado de reserva,
 * como no {@see GeoBairroIndicadorService}. CNAE sem capacidade definida vira
 * "sem_capacidade" — nunca inventa um percentual de saturação. Route-free e
 * sem estado; população = solicitações com decisão DEFERIDA (estabelecimento
 * efetivamente autorizado).
 */
class SaturacaoService
{
    /** Linhas devolvidas para a tabela da tela. */
    public const LIMITE_TABELA = 500;

    /** Pares exibidos no gráfico de maiores saturações. */
    public const TOP_SATURACOES = 15;

    public function __construct(private readonly ClassificacaoFluxoService $classificacao) {}

    /**
     * Saturação por par bairro×CNAE (estabelecimentos deferidos vs capacidade),
     * do maior volume para o menor.
     *
     * @return list<array{bairro: string, cnae: string, ativos: int, capacidade: int|null, percentual: float|null, situacao: string}>
     */
    public function porBairroCnae(ReportFilters $f, int $limite = self::LIMITE_TABELA): array
    {
        return array_slice($this->pares($f), 0, $limite);
    }

    /**
     * Resumo do recorte por situação (para os KPIs do painel), sobre TODOS os
     * pares — não só os que cabem na tabela.
     *
     * @return array{pares_avaliados: int, saturados: int, saturando: int, sem_capacidade: int}
     */
    public function resumo(ReportFilters $f): array
    {
        return $this->resumir($this->pares($f));
    }

    /**
     * Tudo o que a tela usa, a partir de uma única agregação: resumo sobre
     * todos os pares, tabela limitada por volume e ranking pelo percentual.
     *
     * @return array{resumo: array{pares_avaliados: int, saturados: int, saturando: int, sem_capacidade: int}, porBairroCnae: list<array<string, mixed>>, maioresSaturacoes: list<array<string, mixed>>}
     */
    public function painel(ReportFilters $f, int $limiteTabela = self::LIMITE_TABELA): array
    {
        $pares = $this->pares($f);

        return [
            'resumo' => $this->resumir($pares),
            'porBairroCnae' => array_slice($pares, 0, $limiteTabela),
            'maioresSaturacoes' => $this->maioresSaturacoes($pares),
        ];
    }

    /**
     * Pares com capacidade definida, do maior percentual para o menor.
     *
     * @param  list<array{bairro: string, cnae: string, ativos: int, capacidade: int|null, percentual: float|null, situacao: string}>  $pares
     * @return list<array{bairro: string, cnae: string, ativos: int, capacidade: int|null, percentual: float|null, situacao: string}>
     */
    private function maioresSaturacoes(array $pares): array
    {
        $comCapacidade = array_values(array_filter($pares, fn (array $par): bool => $par['percentual'] !== null));

        usort($comCapacidade, fn (array $a, array $b): int => [$b['percentual'], $b['ativos'], $a['bairro'], $a['cnae']]
            <=> [$a['percentual'], $a['ativos'], $b['bairro'], $b['cnae']]);

        return array_slice($comCapacidade, 0, self::TOP_SATURACOES);
    }

    /**
     * @param  list<array{situacao: string}>  $pares
     * @return array{pares_avaliados: int, saturados: int, saturando: int, sem_capacidade: int}
     */
    private function resumir(array $pares): array
    {
        $saturados = 0;
        $saturando = 0;
        $semCapacidade = 0;

        foreach ($pares as $par) {
            match ($par['situacao']) {
                'saturado' => $saturados++,
                'saturando' => $saturando++,
                'sem_capacidade' => $semCapacidade++,
                default => null,
            };
        }

        return [
            'pares_avaliados' => count($pares),
            'saturados' => $saturados,
            'saturando' => $saturando,
            'sem_capacidade' => $semCapacidade,
        ];
    }

    /**
     * Todos os pares bairro×CNAE do recorte, classificados.
     *
     * @return list<array{bairro: string, cnae: string, ativos: int, capacidade: int|null, percentual: float|null, situacao: string}>
     */
    private function pares(ReportFilters $f): array
    {
        $capacidades = $this->capacidades();
        $alerta = (float) Settings::get('relatorios.saturacao.alerta_percentual', 80);
        $bloqueio = (float) Settings::get('relatorios.saturacao.bloqueio_percentual', 100);
        $deferida = DecisionOutcome::Deferida->value;

        return $this->baseQuery($f)
            ->whereNotNull(DB::raw(BairroCanonico::CHAVE))
            ->join('viability_decisions as vd', function (JoinClause $join) use ($deferida): void {
                $join->on('vd.viability_request_id', '=', 'viability_requests.id')
                    ->where('vd.outcome', $deferida);
            })
            ->join('viability_request_cnaes as vrc', 'vrc.viability_request_id', '=', 'viability_requests.id')
            ->join('cnaes as c', 'c.id', '=', 'vrc.cnae_id')
            ->when($f->cnaeCodigo(), fn (Builder $q, string $codigo): Builder => $q->where('c.code', $codigo))
            ->groupBy(DB::raw(BairroCanonico::CHAVE), 'c.code')
            ->orderByDesc('ativos')
            ->orderBy('bairro')
            ->orderBy('c.code')
            ->get([
                DB::raw('min('.BairroCanonico::ROTULO.') as bairro'),
                'c.code as cnae_code',
                DB::raw('count(distinct viability_requests.id) as ativos'),
            ])
            ->map(function ($linha) use ($capacidades, $alerta, $bloqueio): array {
                $ativos = (int) $linha->ativos;
                $capacidade = $capacidades[$linha->cnae_code] ?? null;
                $percentual = $capacidade !== null && $capacidade > 0
                    ? round($ativos / $capacidade * 100, 1)
                    : null;

                return [
                    'bairro' => (string) $linha->bairro,
                    'cnae' => (string) $linha->cnae_code,
                    'ativos' => $ativos,
                    'capacidade' => $capacidade,
                    'percentual' => $percentual,
                    'situacao' => $this->situacao($percentual, $alerta, $bloqueio),
                ];
            })
            ->all();
    }

    /**
     * Solicitações que formam os pares do recorte: deferidas, com bairro e com
     * o CNAE filtrado (base da exportação da tela).
     *
     * @return Builder<ViabilityRequest>
     */
    public function solicitacoes(ReportFilters $f): Builder
    {
        return $this->baseQuery($f)
            ->whereNotNull(DB::raw(BairroCanonico::CHAVE))
            ->whereHas('decision', fn (Builder $d): Builder => $d->where('outcome', DecisionOutcome::Deferida->value))
            ->whereHas('cnaes', fn (Builder $c): Builder => $c->when($f->cnaeCodigo(), fn (Builder $q, string $codigo): Builder => $q->where('cnaes.code', $codigo)));
    }

    /**
     * Limiares de saturação vigentes (para a UI exibir os cortes aplicados).
     *
     * @return array{alerta: float, bloqueio: float}
     */
    public function limiares(): array
    {
        return [
            'alerta' => (float) Settings::get('relatorios.saturacao.alerta_percentual', 80),
            'bloqueio' => (float) Settings::get('relatorios.saturacao.bloqueio_percentual', 100),
        ];
    }

    /**
     * Mapa código CNAE → capacidade recomendada (parâmetro HU-014). Aceita tanto
     * a forma decodificada (array) quanto string JSON, e descarta entradas não
     * numéricas (robustez do dado administrável).
     *
     * @return array<string, int>
     */
    private function capacidades(): array
    {
        $bruto = Settings::get('relatorios.saturacao.capacidades', []);

        if (is_string($bruto)) {
            $bruto = json_decode($bruto, true) ?: [];
        }

        if (! is_array($bruto)) {
            return [];
        }

        $mapa = [];

        foreach ($bruto as $codigo => $capacidade) {
            if (is_numeric($capacidade) && (int) $capacidade > 0) {
                $mapa[(string) $codigo] = (int) $capacidade;
            }
        }

        return $mapa;
    }

    private function situacao(?float $percentual, float $alerta, float $bloqueio): string
    {
        if ($percentual === null) {
            return 'sem_capacidade';
        }

        if ($percentual >= $bloqueio) {
            return 'saturado';
        }

        if ($percentual >= $alerta) {
            return 'saturando';
        }

        return 'ok';
    }

    /**
     * Builder base: população filtrada pelos filtros comuns via `when()`,
     * espelhando o {@see IndicadoresViabilidadeService}. Colunas qualificadas
     * para conviver com os joins de decisões e CNAEs.
     *
     * @return Builder<ViabilityRequest>
     */
    private function baseQuery(ReportFilters $f): Builder
    {
        return ViabilityRequest::query()
            ->whereNotNull('viability_requests.protocoled_at')
            ->when($f->from(), fn (Builder $q, $from): Builder => $q->where('viability_requests.protocoled_at', '>=', $from))
            ->when($f->to(), fn (Builder $q, $to): Builder => $q->where('viability_requests.protocoled_at', '<=', $to))
            ->when($f->setorId(), fn (Builder $q, int $id): Builder => $q->where('viability_requests.sector_id', $id))
            ->when($f->bairro(), fn (Builder $q, string $b): Builder => $q->whereLike(DB::raw(BairroCanonico::CHAVE), "%{$b}%", caseSensitive: false))
            ->when($f->categoria(), fn (Builder $q, string $c): Builder => $this->aplicarCategoria($q, $c));
    }

    /**
     * @param  Builder<ViabilityRequest>  $query
     * @return Builder<ViabilityRequest>
     */
    private function aplicarCategoria(Builder $query, string $categoria): Builder
    {
        return match ($categoria) {
            'malha_fina' => $query->where('viability_requests.in_fine_mesh', true),
            'sede_escritorio' => $query->where('viability_requests.is_virtual_office', true),
            // Pela forma de conclusão: processo ainda não concluído fica fora.
            'expresso' => $this->classificacao->aplicar($query, ClassificacaoFluxoService::AUTOMATICA),
            'semi_expresso' => $this->classificacao->aplicar($query, ClassificacaoFluxoService::SEMI_EXPRESSA),
            default => $query,
        };
    }
}

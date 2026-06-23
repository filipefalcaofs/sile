<?php

namespace App\Services\Relatorios;

use App\Enums\AnalysisCategory;
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
 * Degradação HONESTA (entrega funcional sem fachada): a zona urbanística oficial
 * (GIS) está pendente; o recorte é por `address_neighborhood`. CNAE sem
 * capacidade definida vira "sem_capacidade" — nunca inventa um percentual de
 * saturação. Route-free e sem estado; população = solicitações com decisão
 * DEFERIDA (estabelecimento efetivamente autorizado).
 */
class SaturacaoService
{
    /**
     * Saturação por par bairro×CNAE (estabelecimentos deferidos vs capacidade).
     *
     * @return list<array{bairro: string, cnae: string, ativos: int, capacidade: int|null, percentual: float|null, situacao: string}>
     */
    public function porBairroCnae(ReportFilters $f, int $limite = 500): array
    {
        $capacidades = $this->capacidades();
        $alerta = (float) Settings::get('relatorios.saturacao.alerta_percentual', 80);
        $bloqueio = (float) Settings::get('relatorios.saturacao.bloqueio_percentual', 100);
        $deferida = DecisionOutcome::Deferida->value;

        return $this->baseQuery($f)
            ->whereNotNull('viability_requests.address_neighborhood')
            ->join('viability_decisions as vd', function (JoinClause $join) use ($deferida): void {
                $join->on('vd.viability_request_id', '=', 'viability_requests.id')
                    ->where('vd.outcome', $deferida);
            })
            ->join('viability_request_cnaes as vrc', 'vrc.viability_request_id', '=', 'viability_requests.id')
            ->join('cnaes as c', 'c.id', '=', 'vrc.cnae_id')
            ->groupBy('viability_requests.address_neighborhood', 'c.code')
            ->orderByDesc('ativos')
            ->orderBy('viability_requests.address_neighborhood')
            ->limit($limite)
            ->get([
                'viability_requests.address_neighborhood as bairro',
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
     * Resumo do recorte por situação (para os KPIs do painel).
     *
     * @return array{pares_avaliados: int, saturados: int, saturando: int, sem_capacidade: int}
     */
    public function resumo(ReportFilters $f): array
    {
        $linhas = $this->porBairroCnae($f);

        $saturados = 0;
        $saturando = 0;
        $semCapacidade = 0;

        foreach ($linhas as $linha) {
            match ($linha['situacao']) {
                'saturado' => $saturados++,
                'saturando' => $saturando++,
                'sem_capacidade' => $semCapacidade++,
                default => null,
            };
        }

        return [
            'pares_avaliados' => count($linhas),
            'saturados' => $saturados,
            'saturando' => $saturando,
            'sem_capacidade' => $semCapacidade,
        ];
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
            ->when($f->bairro(), fn (Builder $q, string $b): Builder => $q->whereLike('viability_requests.address_neighborhood', "%{$b}%", caseSensitive: false))
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
            'expresso' => $query->where('viability_requests.analysis_category', AnalysisCategory::Expresso->value),
            'semi_expresso' => $query->where('viability_requests.analysis_category', AnalysisCategory::SemiExpresso->value),
            default => $query,
        };
    }
}

<?php

namespace App\Services\Benchmark;

use App\Models\BenchmarkIngestion;
use App\Models\BenchmarkMunicipalityMetric;
use App\Support\Capitais;
use Illuminate\Database\Eloquent\Builder;

/**
 * Posição de Salvador no país, ranking das capitais e série histórica, sobre
 * o agregado dos microdados federais.
 *
 * A regra que governa tudo: posição só existe quando a competência tem as 27
 * UFs ingeridas com sucesso. Com cobertura parcial, a posição seria calculada
 * contra um país incompleto — melhor devolver a lacuna, com a lista do que
 * faltou, do que publicar um número que parece verdade.
 */
class BenchmarkRankingService
{
    /**
     * @return array{posicao: ?int, total: int, media: ?float, ufs_ausentes: list<string>, cobertura_completa: bool}
     */
    public function posicaoNacional(int $ano, int $mes): array
    {
        $ausentes = $this->ufsAusentes($ano, $mes);

        $salvador = $this->metricaDeSalvador($ano, $mes);

        if ($ausentes !== [] || $salvador === null) {
            return [
                'posicao' => null,
                'total' => 0,
                'media' => $salvador?->media,
                'ufs_ausentes' => $ausentes,
                'cobertura_completa' => $ausentes === [],
            ];
        }

        $base = $this->competencia($ano, $mes)->whereNotNull('media');

        $maisRapidos = (clone $base)->where('media', '<', $salvador->media)->count();

        return [
            'posicao' => $maisRapidos + 1,
            'total' => (clone $base)->count(),
            'media' => $salvador->media,
            'ufs_ausentes' => [],
            'cobertura_completa' => true,
        ];
    }

    /**
     * As 27 capitais, ordenadas pelo tempo. Capital sem dado na competência
     * aparece com media e posicao nulas — lacuna explícita, nunca omissão nem
     * zero.
     *
     * @return list<array{uf: string, municipio: string, media: ?float, p50: ?float, p90: ?float, n: int, posicao: ?int, e_salvador: bool}>
     */
    public function rankingCapitais(int $ano, int $mes): array
    {
        $metricas = $this->competencia($ano, $mes)
            ->get()
            ->filter(fn (BenchmarkMunicipalityMetric $m): bool => Capitais::ehCapital($m->uf, $m->municipio))
            ->keyBy(fn (BenchmarkMunicipalityMetric $m): string => $m->uf);

        $linhas = [];

        foreach (Capitais::todas() as $uf => $municipio) {
            $metrica = $metricas->get($uf);

            $linhas[] = [
                'uf' => $uf,
                'municipio' => $municipio,
                'media' => $metrica?->media,
                'p50' => $metrica?->p50,
                'p90' => $metrica?->p90,
                'n' => (int) ($metrica?->n ?? 0),
                'posicao' => null,
                'e_salvador' => $uf === Capitais::UF_SALVADOR,
            ];
        }

        return $this->posicionar($linhas);
    }

    /**
     * Um ponto por competência ingerida, em ordem cronológica. Buracos não são
     * preenchidos: mês sem ingestão simplesmente não aparece.
     *
     * @return list<array{ano: int, mes: int, media: ?float, p50: ?float, p90: ?float, n: int}>
     */
    public function serieSalvador(?int $anoInicial = null): array
    {
        return BenchmarkMunicipalityMetric::query()
            ->where('uf', Capitais::UF_SALVADOR)
            ->where('municipio', Capitais::MUNICIPIO_SALVADOR)
            ->when($anoInicial !== null, fn (Builder $q) => $q->where('ano', '>=', $anoInicial))
            ->orderBy('ano')
            ->orderBy('mes')
            ->get()
            ->map(fn (BenchmarkMunicipalityMetric $m): array => [
                'ano' => $m->ano,
                'mes' => $m->mes,
                'media' => $m->media,
                'p50' => $m->p50,
                'p90' => $m->p90,
                'n' => $m->n,
            ])
            ->all();
    }

    /**
     * Competência mais recente em que todas as 27 UFs foram ingeridas com
     * sucesso — a única que pode servir de referência para o ranking.
     *
     * @return array{ano: int, mes: int}|null
     */
    public function ultimaCompetenciaCompleta(): ?array
    {
        $competencias = BenchmarkIngestion::query()
            ->sucesso()
            ->selectRaw('ano, mes, count(distinct uf) as ufs')
            ->groupBy('ano', 'mes')
            ->havingRaw('count(distinct uf) >= ?', [count(Capitais::ufs())])
            ->orderByDesc('ano')
            ->orderByDesc('mes')
            ->first();

        return $competencias === null
            ? null
            : ['ano' => (int) $competencias->ano, 'mes' => (int) $competencias->mes];
    }

    public function metricaDeSalvador(int $ano, int $mes): ?BenchmarkMunicipalityMetric
    {
        return $this->competencia($ano, $mes)
            ->where('uf', Capitais::UF_SALVADOR)
            ->where('municipio', Capitais::MUNICIPIO_SALVADOR)
            ->first();
    }

    /**
     * UFs que não têm ingestão de sucesso na competência.
     *
     * @return list<string>
     */
    public function ufsAusentes(int $ano, int $mes): array
    {
        $presentes = BenchmarkIngestion::query()
            ->sucesso()
            ->where(['ano' => $ano, 'mes' => $mes])
            ->pluck('uf')
            ->all();

        return array_values(array_diff(Capitais::ufs(), $presentes));
    }

    /**
     * Atribui posição por tempo crescente, com empate recebendo a mesma
     * posição. Linhas sem média ficam no fim, sem posição.
     *
     * @param  list<array<string, mixed>>  $linhas
     * @return list<array<string, mixed>>
     */
    private function posicionar(array $linhas): array
    {
        usort($linhas, static function (array $a, array $b): int {
            if ($a['media'] === null || $b['media'] === null) {
                return ($a['media'] === null ? 1 : 0) <=> ($b['media'] === null ? 1 : 0);
            }

            return $a['media'] <=> $b['media'];
        });

        $posicao = 0;
        $anterior = null;

        foreach ($linhas as $indice => $linha) {
            if ($linha['media'] === null) {
                continue;
            }

            $posicao++;

            if ($anterior !== null && abs((float) $linha['media'] - $anterior) < 0.0001) {
                $linhas[$indice]['posicao'] = $linhas[$indice - 1]['posicao'];

                continue;
            }

            $linhas[$indice]['posicao'] = $posicao;
            $anterior = (float) $linha['media'];
        }

        return $linhas;
    }

    /**
     * @return Builder<BenchmarkMunicipalityMetric>
     */
    private function competencia(int $ano, int $mes): Builder
    {
        return BenchmarkMunicipalityMetric::query()->where(['ano' => $ano, 'mes' => $mes]);
    }
}

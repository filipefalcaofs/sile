<?php

namespace App\Services\Relatorios\Bi;

use Illuminate\Support\Carbon;

/**
 * Estatística descritiva e agrupamento de séries diárias para os indicadores do BI.
 */
final class BiEstatistica
{
    /**
     * Média, mediana e percentil 90 por interpolação linear. Sem amostras tudo é
     * null — nunca um zero fabricado.
     *
     * @param  list<float|int>  $valores
     * @return array{amostras: int, media: float|null, mediana: float|null, p90: float|null, minimo: float|null, maximo: float|null}
     */
    public static function resumo(array $valores): array
    {
        $total = count($valores);

        if ($total === 0) {
            return ['amostras' => 0, 'media' => null, 'mediana' => null, 'p90' => null, 'minimo' => null, 'maximo' => null];
        }

        sort($valores);

        return [
            'amostras' => $total,
            'media' => round(array_sum($valores) / $total, 2),
            'mediana' => round(self::percentil($valores, 50), 2),
            'p90' => round(self::percentil($valores, 90), 2),
            'minimo' => round((float) $valores[0], 2),
            'maximo' => round((float) $valores[$total - 1], 2),
        ];
    }

    /**
     * @param  list<float|int>  $ordenados
     */
    private static function percentil(array $ordenados, int $p): float
    {
        $posicao = (count($ordenados) - 1) * $p / 100;
        $baixo = (int) floor($posicao);
        $alto = (int) ceil($posicao);

        return $ordenados[$baixo] + ($ordenados[$alto] - $ordenados[$baixo]) * ($posicao - $baixo);
    }

    public static function percentual(int $parte, int $total): ?float
    {
        return $total > 0 ? round($parte / $total * 100, 1) : null;
    }

    /**
     * Rótulo do período de um dia (YYYY-MM-DD): o próprio dia, a segunda-feira da
     * semana ou o mês (YYYY-MM).
     */
    public static function periodo(string $dia, string $agrupamento): string
    {
        return match ($agrupamento) {
            'mes' => substr($dia, 0, 7),
            'semana' => Carbon::parse($dia)->startOfWeek()->toDateString(),
            default => substr($dia, 0, 10),
        };
    }

    /**
     * Soma métricas de linhas diárias no agrupamento pedido, mantendo as chaves
     * de quebra (ex.: cnae, bairro) como parte da identidade da linha.
     *
     * @param  iterable<object>  $linhas  Cada linha traz `dia`, as quebras e as métricas.
     * @param  list<string>  $quebras
     * @param  list<string>  $metricas
     * @return list<array<string, mixed>>
     */
    public static function agrupar(iterable $linhas, string $agrupamento, array $quebras, array $metricas): array
    {
        $acumulado = [];

        foreach ($linhas as $linha) {
            $periodo = self::periodo((string) $linha->dia, $agrupamento);
            $chave = $periodo;

            foreach ($quebras as $quebra) {
                $chave .= '|'.$linha->{$quebra};
            }

            if (! isset($acumulado[$chave])) {
                $acumulado[$chave] = ['periodo' => $periodo];

                foreach ($quebras as $quebra) {
                    $acumulado[$chave][$quebra] = (string) $linha->{$quebra};
                }

                foreach ($metricas as $metrica) {
                    $acumulado[$chave][$metrica] = 0;
                }
            }

            foreach ($metricas as $metrica) {
                $acumulado[$chave][$metrica] += (int) $linha->{$metrica};
            }
        }

        ksort($acumulado);

        return array_values($acumulado);
    }
}

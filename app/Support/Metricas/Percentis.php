<?php

namespace App\Support\Metricas;

/**
 * Distribuição de uma série de medidas — média, percentis e máximo.
 *
 * Usado pelas duas camadas de medição de tempo de viabilidade: a telemetria
 * interna (horas úteis apuradas pelo próprio VIABILIZA) e o benchmark federal
 * (microdados da REDESIM). Compartilhar o cálculo é o que garante que os dois
 * lados de qualquer comparação usem a mesma convenção.
 *
 * O percentil é interpolado linearmente sobre o vetor ordenado — posição
 * k = (n - 1) * p / 100 — e não em SQL, porque `percentile_cont` não existe no
 * SQLite da suíte.
 *
 * Série vazia devolve n = 0 e todas as medidas nulas: ausência de dado nunca
 * vira zero.
 */
final readonly class Percentis
{
    private function __construct(
        public int $n,
        public ?float $media,
        public ?float $p50,
        public ?float $p75,
        public ?float $p90,
        public ?float $p95,
        public ?float $p99,
        public ?float $maximo,
    ) {}

    /**
     * @param  iterable<int|float>  $valores
     */
    public static function de(iterable $valores): self
    {
        $serie = [];

        foreach ($valores as $valor) {
            $serie[] = (float) $valor;
        }

        if ($serie === []) {
            return new self(0, null, null, null, null, null, null, null);
        }

        sort($serie);

        return new self(
            n: count($serie),
            media: array_sum($serie) / count($serie),
            p50: self::percentil($serie, 50),
            p75: self::percentil($serie, 75),
            p90: self::percentil($serie, 90),
            p95: self::percentil($serie, 95),
            p99: self::percentil($serie, 99),
            maximo: $serie[count($serie) - 1],
        );
    }

    /**
     * @return array{n: int, media: ?float, p50: ?float, p75: ?float, p90: ?float, p95: ?float, p99: ?float, maximo: ?float}
     */
    public function toArray(): array
    {
        return [
            'n' => $this->n,
            'media' => $this->media,
            'p50' => $this->p50,
            'p75' => $this->p75,
            'p90' => $this->p90,
            'p95' => $this->p95,
            'p99' => $this->p99,
            'maximo' => $this->maximo,
        ];
    }

    /**
     * @param  non-empty-list<float>  $serie  já ordenada
     */
    private static function percentil(array $serie, int $p): float
    {
        $posicao = (count($serie) - 1) * $p / 100;
        $inferior = (int) floor($posicao);
        $superior = (int) ceil($posicao);

        if ($inferior === $superior) {
            return $serie[$inferior];
        }

        return $serie[$inferior] + ($serie[$superior] - $serie[$inferior]) * ($posicao - $inferior);
    }
}

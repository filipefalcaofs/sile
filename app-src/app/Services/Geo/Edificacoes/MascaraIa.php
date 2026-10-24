<?php

namespace App\Services\Geo\Edificacoes;

/**
 * Contorno devolvido pelo serviço de inferência, em pixels do recorte.
 */
final readonly class MascaraIa
{
    public const OK = 'ok';

    public const SEM_OBJETO = 'sem_objeto';

    /**
     * @param  list<array{0: float|int, 1: float|int}>  $anelPixel
     * @param  list<array{anel: list<array{0: float, 1: float}>, score: ?float, encosta: bool}>  $candidatos  as máscaras que o serviço devolveu
     */
    public function __construct(
        public string $status,
        public array $anelPixel,
        public ?float $score,
        public string $modelo,
        public array $candidatos = [],
    ) {}
}

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
     */
    public function __construct(
        public string $status,
        public array $anelPixel,
        public ?float $score,
        public string $modelo,
    ) {}
}

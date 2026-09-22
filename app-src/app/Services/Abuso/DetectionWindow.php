<?php

namespace App\Services\Abuso;

use Carbon\CarbonImmutable;

/**
 * Janela de análise (HU-149) — intervalo imutável [start, end] derivado de
 * `abuso.janela_dias` (HU-014). Os detectores recebem a MESMA janela no ciclo,
 * garantindo que "fora da janela não conta" para o limiar (anti-fachada).
 */
final readonly class DetectionWindow
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {}

    /**
     * Janela dos últimos N dias a partir de uma referência (default: agora).
     */
    public static function lastDays(int $days, ?CarbonImmutable $reference = null): self
    {
        $end = $reference ?? CarbonImmutable::now();

        return new self($end->subDays($days), $end);
    }
}

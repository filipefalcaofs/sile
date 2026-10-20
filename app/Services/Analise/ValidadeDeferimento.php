<?php

namespace App\Services\Analise;

use App\Enums\TvlKind;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Tipo do deferimento escolhido na conclusão (Deferido = definitiva,
 * Provisório, Pré-operacional) e o prazo da validade em meses. Definitiva
 * não tem validade.
 */
final readonly class ValidadeDeferimento
{
    public function __construct(public TvlKind $tipo, public ?int $meses) {}

    public static function de(?string $tipo, int|string|null $meses): ?self
    {
        if ($tipo === null || $tipo === '') {
            return null;
        }

        $kind = TvlKind::from($tipo);

        if ($kind === TvlKind::Definitiva || $meses === null || $meses === '') {
            return new self($kind, null);
        }

        return new self($kind, (int) $meses);
    }

    public function validoAte(CarbonInterface $base): ?CarbonImmutable
    {
        if ($this->meses === null) {
            return null;
        }

        return $base->toImmutable()->addMonthsNoOverflow($this->meses)->startOfDay();
    }
}

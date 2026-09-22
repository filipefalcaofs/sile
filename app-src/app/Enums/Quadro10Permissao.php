<?php

namespace App\Enums;

/**
 * Permissão da atividade econômica numa zona urbanística segundo o Quadro 10 da
 * LOUOS (HU-039) — dado da regra versionada, não decisão hardcoded.
 * `PermitidoCondicionado` remete a uma condicionante urbanística (campo
 * condicionante_ref do Quadro 10) que o motor resolve no consolidado.
 */
enum Quadro10Permissao: string
{
    case Permitido = 'permitido';
    case PermitidoCondicionado = 'permitido_condicionado';
    case Proibido = 'proibido';

    public function label(): string
    {
        return match ($this) {
            self::Permitido => 'Permitido',
            self::PermitidoCondicionado => 'Permitido condicionado',
            self::Proibido => 'Proibido',
        };
    }

    /**
     * Aceita o enum interno e os sinais da matriz oficial do Quadro 10
     * (S / N / S(c) / Sim / Não).
     */
    public static function tryFromSinal(string $valor): ?self
    {
        $normalizado = mb_strtolower(trim($valor));
        $normalizado = (string) preg_replace('/\s+/', '', $normalizado);

        return match ($normalizado) {
            'permitido', 's', 'sim' => self::Permitido,
            'permitido_condicionado', 'permitidocondicionado',
            's(c)', 'sc', 's(c).', 's(a)', 'sa', 's(b)', 'sb' => self::PermitidoCondicionado,
            'proibido', 'n', 'nao', 'não' => self::Proibido,
            default => self::tryFrom($valor),
        };
    }
}

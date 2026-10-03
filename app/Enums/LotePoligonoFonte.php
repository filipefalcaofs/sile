<?php

namespace App\Enums;

/**
 * Origem da geometria do lote, em ordem de precedência: o endereçamento
 * licenciado da SEDUR manda; sem ele vale o lote do cadastro municipal
 * (SEMAP/GeoSalvador) pela inscrição; por último o polígono que o requerente
 * desenhou no REGIN.
 */
enum LotePoligonoFonte: string
{
    case Enderecamento = 'enderecamento';
    case Semap = 'semap';
    case Regin = 'regin';

    /**
     * Da mais forte para a mais fraca.
     *
     * @return list<self>
     */
    public static function precedencia(): array
    {
        return [self::Enderecamento, self::Semap, self::Regin];
    }

    public function oficial(): bool
    {
        return $this !== self::Regin;
    }

    public function label(): string
    {
        return match ($this) {
            self::Enderecamento => 'Endereçamento SEDUR',
            self::Semap => 'Cadastro municipal (SEMAP)',
            self::Regin => 'Declarado no REGIN',
        };
    }
}

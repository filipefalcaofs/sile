<?php

namespace App\Enums;

/**
 * Regra que decidiu a zona urbanística do processo: o ponto da inscrição ou a
 * maior porção do polígono do endereçamento (art. 18, § 2º, da Lei 9.148/2016).
 */
enum ZonaFonte: string
{
    case Ponto = 'ponto';
    case EnderecamentoMaiorPorcao = 'enderecamento_maior_porcao';

    public function label(): string
    {
        return match ($this) {
            self::Ponto => 'Ponto da inscrição',
            self::EnderecamentoMaiorPorcao => 'Maior porção do lote',
        };
    }
}

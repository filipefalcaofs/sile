<?php

namespace App\Enums;

/**
 * Regra que decidiu a zona urbanística do processo: o ponto da inscrição, a
 * maior porção do polígono do endereçamento (art. 18, § 2º, da Lei 9.148/2016)
 * ou a linha da centralidade linear (ZCLMu/ZCLMe) por cima do lote (HU.182).
 */
enum ZonaFonte: string
{
    case Ponto = 'ponto';
    case EnderecamentoMaiorPorcao = 'enderecamento_maior_porcao';
    case CentralidadeLinear = 'centralidade_linear';

    public function label(): string
    {
        return match ($this) {
            self::Ponto => 'Ponto da inscrição',
            self::EnderecamentoMaiorPorcao => 'Maior porção do lote',
            self::CentralidadeLinear => 'Centralidade linear (linha sobre o lote)',
        };
    }
}

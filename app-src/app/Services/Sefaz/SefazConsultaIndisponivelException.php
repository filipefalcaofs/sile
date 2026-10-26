<?php

namespace App\Services\Sefaz;

use RuntimeException;

/**
 * A consulta de viabilidade por CNPJ à SEFAZ não pôde ser feita (fora do ar ou
 * sem endpoint contratado). Quem consulta repete dentro da janela e, esgotada
 * ela, indefere por estabilidade sistêmica.
 */
class SefazConsultaIndisponivelException extends RuntimeException
{
    public function __construct(public readonly ?string $cnpj = null, ?string $motivo = null)
    {
        parent::__construct($motivo ?? 'Consulta de viabilidade por CNPJ à SEFAZ indisponível.');
    }
}

<?php

namespace App\Services\Realty;

use Exception;

/**
 * Indisponibilidade da resolução por inscrição imobiliária: a base de lotes /
 * Cadastro Multifinalitário está PENDENTE da SEDUR (HU-033/HU-106). O provider
 * real degrada honestamente — NUNCA inventa um ponto. A Fase 13 troca o binding
 * pelo provider conveniado (Cadastro/SEFAZ).
 */
class PropertyRegistryUnavailableException extends Exception
{
    public function __construct(
        public readonly string $inscricao,
        public readonly ?string $motivo = null,
    ) {
        parent::__construct(
            'Resolução por inscrição imobiliária indisponível: a base de lotes (Cadastro) está pendente da SEDUR.',
        );
    }
}

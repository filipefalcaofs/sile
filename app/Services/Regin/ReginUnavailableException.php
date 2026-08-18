<?php

namespace App\Services\Regin;

use RuntimeException;

/**
 * Indisponibilidade do integrador Regin/Junta Comercial (HU-104): o contrato e a
 * homologação estão PENDENTES (Fase 13). O provider real degrada honestamente —
 * NUNCA simula a transmissão do parecer. O listener que comunica o resultado
 * (09-08) captura esta exceção e AUDITA a pendência de integração, jamais um
 * sucesso fictício.
 */
class ReginUnavailableException extends RuntimeException
{
    public function __construct(
        public readonly ?string $protocolNumber = null,
        public readonly ?string $motivo = null,
    ) {
        parent::__construct(
            'Comunicação do parecer ao Regin/Junta indisponível: contrato/homologação pendente (Fase 13).',
        );
    }
}

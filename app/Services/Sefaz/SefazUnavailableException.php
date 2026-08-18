<?php

namespace App\Services\Sefaz;

use RuntimeException;

/**
 * Indisponibilidade do envio à SEFAZ municipal (HU-110): o contrato e a
 * homologação estão PENDENTES (Fase 13). O provider real degrada honestamente —
 * NUNCA simula o envio. O listener que envia o deferimento (09-09) captura esta
 * exceção e AUDITA a pendência de integração, jamais um sucesso fictício.
 */
class SefazUnavailableException extends RuntimeException
{
    public function __construct(
        public readonly ?string $protocolNumber = null,
        public readonly ?string $motivo = null,
    ) {
        parent::__construct(
            'Envio da viabilidade à SEFAZ municipal indisponível: contrato/homologação pendente (Fase 13).',
        );
    }
}

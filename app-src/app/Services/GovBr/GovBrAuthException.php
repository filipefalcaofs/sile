<?php

namespace App\Services\GovBr;

use RuntimeException;

/**
 * Falha na autenticação via Login Único GOV.BR (HU-151).
 *
 * `$userMessage` é a mensagem segura exibida ao cidadão; a mensagem da
 * exception carrega o detalhe técnico para log/auditoria, nunca para a UI.
 */
class GovBrAuthException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $userMessage = 'Não foi possível entrar com a conta GOV.BR. Tente novamente ou use o login com e-mail e senha.',
    ) {
        parent::__construct($message);
    }
}

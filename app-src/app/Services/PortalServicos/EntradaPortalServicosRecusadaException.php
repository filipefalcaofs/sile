<?php

namespace App\Services\PortalServicos;

use RuntimeException;

/**
 * A sessão do Portal de Serviços é válida, mas a regra do Viabiliza não deixa
 * entrar (conta inativa, nível GOV.BR baixo, CPF inválido). A mensagem ao
 * cidadão fica separada do motivo interno.
 */
class EntradaPortalServicosRecusadaException extends RuntimeException
{
    public function __construct(string $motivoInterno, public readonly string $mensagemUsuario)
    {
        parent::__construct($motivoInterno);
    }
}

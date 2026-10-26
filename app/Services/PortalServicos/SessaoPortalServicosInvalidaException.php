<?php

namespace App\Services\PortalServicos;

use RuntimeException;

/**
 * O Portal de Serviços não reconhece mais a sessão (token expirado, recusado
 * ou sem CPF): o cidadão precisa entrar de novo pelo portal.
 */
class SessaoPortalServicosInvalidaException extends RuntimeException {}

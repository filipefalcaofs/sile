<?php

namespace App\Services\PortalServicos;

use RuntimeException;

/**
 * O BFF do Portal de Serviços não respondeu (rede, timeout ou erro 5xx): não
 * dá para afirmar nada sobre a sessão do cidadão.
 */
class PortalServicosIndisponivelException extends RuntimeException {}

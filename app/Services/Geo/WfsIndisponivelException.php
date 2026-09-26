<?php

namespace App\Services\Geo;

use RuntimeException;

/**
 * Página do WFS do GeoServer SEDUR que não respondeu no contrato esperado.
 * A importação é abortada inteira: nunca se publica uma camada parcial.
 */
class WfsIndisponivelException extends RuntimeException {}

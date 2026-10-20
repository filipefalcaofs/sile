<?php

namespace App\Services\Geo\Edificacoes;

use RuntimeException;

/**
 * Serviço de inferência sem URL configurada, fora do ar ou com resposta inválida.
 */
class InferenciaIndisponivelException extends RuntimeException {}

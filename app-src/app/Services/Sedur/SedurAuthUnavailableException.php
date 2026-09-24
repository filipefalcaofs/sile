<?php

namespace App\Services\Sedur;

use RuntimeException;

/**
 * Indisponibilidade ou resposta inválida da API institucional SEDUR. A
 * mensagem nunca carrega senha, token ou corpo bruto da resposta.
 */
class SedurAuthUnavailableException extends RuntimeException {}

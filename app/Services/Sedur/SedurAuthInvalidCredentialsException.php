<?php

namespace App\Services\Sedur;

use RuntimeException;

/**
 * A API institucional rejeitou a credencial corporativa (401/403). Distinta
 * de indisponibilidade para a auditoria separar "senha errada" de "API fora".
 */
class SedurAuthInvalidCredentialsException extends RuntimeException {}

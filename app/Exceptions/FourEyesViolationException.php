<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Violação da publicação por quatro olhos (HU-019/HU-020): o publicador de uma
 * versão de domínio sensível não pode ser o autor do rascunho.
 */
class FourEyesViolationException extends RuntimeException {}

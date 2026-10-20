<?php

namespace App\Services\Renovacao;

use RuntimeException;

/** Não há endereço utilizável para a inscrição: ausente, desconhecida ou cadastro sem endereço. */
class EnderecoImovelNaoEncontradoException extends RuntimeException {}

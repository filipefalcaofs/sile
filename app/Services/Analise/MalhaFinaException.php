<?php

namespace App\Services\Analise;

use RuntimeException;

/**
 * Encaminhamento à malha fina inválido (HU-136 RN-002): o motivo é obrigatório.
 * Nada é gravado (nem o fine_mesh_referrals nem a flag in_fine_mesh) e o caller
 * traduz a exceção em 422 — não encaminha silenciosamente sem justificativa.
 */
class MalhaFinaException extends RuntimeException
{
    public static function motivoObrigatorio(): self
    {
        return new self('O motivo do encaminhamento à malha fina é obrigatório (RN-002).');
    }
}

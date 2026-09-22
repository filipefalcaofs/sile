<?php

namespace App\Services\Solicitacao;

use App\Enums\ViabilityRequestStatus;
use RuntimeException;

/**
 * Cancelamento bloqueado porque o estado atual da solicitação não está entre os
 * estados canceláveis parametrizáveis (HU-070 CA-03). Carrega o estado atual e a
 * lista de estados canceláveis vigente para o aviso ao requerente — comunicado,
 * nunca silencioso. O caller (CancelamentoSolicitacaoController) traduz em
 * flash.error; o status e a timeline permanecem intactos.
 */
class CancelamentoNaoPermitidoException extends RuntimeException
{
    /**
     * @param  list<string>  $estadosCancelaveis  Estados canceláveis vigentes (parâmetro).
     */
    public function __construct(
        public readonly ViabilityRequestStatus $status,
        public readonly array $estadosCancelaveis,
        string $message,
    ) {
        parent::__construct($message);
    }

    /**
     * @param  list<string>  $estadosCancelaveis
     */
    public static function para(ViabilityRequestStatus $status, array $estadosCancelaveis): self
    {
        return new self(
            $status,
            $estadosCancelaveis,
            "Não é possível cancelar uma solicitação no estado \"{$status->label()}\".",
        );
    }
}

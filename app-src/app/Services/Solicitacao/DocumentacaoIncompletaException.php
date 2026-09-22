<?php

namespace App\Services\Solicitacao;

use RuntimeException;

/**
 * Protocolo bloqueado por documento obrigatório faltante (HU-067). Carrega a
 * lista dos requisitos pendentes para o aviso ao requerente — comunicado,
 * nunca silencioso. O caller (ProtocoloController) traduz em flash.error; o
 * status da solicitação permanece intacto e nenhum número é consumido.
 */
class DocumentacaoIncompletaException extends RuntimeException
{
    /**
     * @param  list<string>  $requisitos  Nomes dos requisitos documentais faltantes.
     */
    public function __construct(public readonly array $requisitos, string $message)
    {
        parent::__construct($message);
    }

    /**
     * @param  list<string>  $requisitos
     */
    public static function paraRequisitos(array $requisitos): self
    {
        $lista = implode(', ', $requisitos);

        return new self(
            $requisitos,
            "Não é possível protocolar: anexe os documentos obrigatórios pendentes ({$lista}).",
        );
    }
}

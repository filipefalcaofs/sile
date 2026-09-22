<?php

namespace App\Services\Solicitacao;

use RuntimeException;

/**
 * Protocolo bloqueado por dados mínimos ausentes (HU-068 FA-01): empresa,
 * imóvel/polígono, área utilizada ou atividade principal (CNAE). Carrega os
 * campos pendentes para o aviso ao requerente — comunicado, nunca silencioso.
 * Lançada ANTES de gerar o número (nenhum protocolo é consumido).
 */
class SolicitacaoIncompletaException extends RuntimeException
{
    /**
     * @param  list<string>  $campos  Rótulos pt-BR dos dados obrigatórios ausentes.
     */
    public function __construct(public readonly array $campos, string $message)
    {
        parent::__construct($message);
    }

    /**
     * @param  list<string>  $campos
     */
    public static function paraCampos(array $campos): self
    {
        $lista = implode(', ', $campos);

        return new self(
            $campos,
            "Não é possível protocolar: complete os dados obrigatórios da solicitação ({$lista}).",
        );
    }
}

<?php

namespace App\Services\Ai;

/**
 * Resultado SANITIZADO de um teste de conexão de provedor de IA.
 *
 * Contém SÓ um booleano e uma mensagem categorizada em pt-BR — NUNCA o corpo da
 * resposta do provedor, headers, a exceção crua ou a credencial (RN-009 / LGPD).
 * É o único formato que sai do client para o controller/UI/auditoria.
 */
class ConnectionResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly string $mensagem,
    ) {}

    public static function sucesso(string $mensagem = 'Conexão bem-sucedida.'): self
    {
        return new self(true, $mensagem);
    }

    public static function falha(string $mensagem): self
    {
        return new self(false, $mensagem);
    }

    /**
     * @return array{ok: bool, mensagem: string}
     */
    public function toArray(): array
    {
        return ['ok' => $this->ok, 'mensagem' => $this->mensagem];
    }
}

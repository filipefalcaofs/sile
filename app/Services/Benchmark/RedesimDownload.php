<?php

namespace App\Services\Benchmark;

/**
 * Resultado de uma tentativa de baixar os microdados de uma competência.
 *
 * Falha é valor de retorno, não exceção: a ingestão precisa registrar a
 * competência que não veio, com o motivo, para que a tela mostre a lacuna em
 * vez de um número estimado.
 */
final readonly class RedesimDownload
{
    private function __construct(
        public bool $sucesso,
        public string $url,
        public ?string $conteudo = null,
        public int $bytes = 0,
        public ?string $hash = null,
        public ?string $motivo = null,
    ) {}

    public static function recebido(string $url, string $conteudo): self
    {
        return new self(
            sucesso: true,
            url: $url,
            conteudo: $conteudo,
            bytes: strlen($conteudo),
            hash: hash('sha256', $conteudo),
        );
    }

    public static function falhou(string $url, string $motivo): self
    {
        return new self(sucesso: false, url: $url, motivo: $motivo);
    }
}

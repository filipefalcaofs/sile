<?php

namespace App\Support;

/**
 * E-mail provisório de quem chega sem e-mail utilizável (REGIN, entrada pelo
 * Portal de Serviços): único por CPF e num domínio que nunca recebe mensagem.
 * O cidadão troca pelo e-mail real em Meus dados.
 */
final class EmailProvisorio
{
    private const DOMINIO = '@solicitante.viabiliza.local';

    public static function para(string $cpf): string
    {
        return $cpf.self::DOMINIO;
    }

    public static function eh(?string $email): bool
    {
        return $email !== null && str_ends_with(strtolower($email), self::DOMINIO);
    }
}

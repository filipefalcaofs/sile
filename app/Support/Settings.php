<?php

namespace App\Support;

/**
 * Ponto único de leitura dos parâmetros de negócio do SILE.
 *
 * Na Fase 1 delega para config/sile.php (defaults versionados). Na Fase 2
 * (HU-014) o backend passa a ser banco administrável por interface sem
 * alterar nenhum call site — apenas esta classe muda.
 */
class Settings
{
    public static function get(string $key, mixed $default = null): mixed
    {
        return config("sile.{$key}", $default);
    }
}

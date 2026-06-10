<?php

namespace App\Support;

use App\Models\Parameter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;

/**
 * Ponto único de leitura dos parâmetros de negócio do SILE.
 *
 * Backend da HU-014: banco (tabela parameters) com cache por chave e
 * invalidação na gravação do Parameter — efeito sem deploy (CA-05). Ordem
 * de resolução: cache → banco (value ?? default do catálogo) → config/sile.php
 * → default do call site. Sem banco migrado (build Docker, CI, testes Unit)
 * a QueryException cai no fallback de config — promessa da Fase 1 cumprida
 * sem alterar nenhum call site.
 */
class Settings
{
    public static function get(string $key, mixed $default = null): mixed
    {
        $fallback = config("sile.{$key}", $default);

        try {
            return Cache::remember(
                "sile.parameters.{$key}",
                (int) config('sile.parameters.cache_ttl', 300),
                function () use ($key, $fallback) {
                    $parameter = Parameter::query()->where('key', $key)->first();

                    return $parameter === null
                        ? $fallback
                        : ($parameter->typedValue() ?? $fallback);
                },
            );
        } catch (QueryException) {
            return $fallback;
        }
    }

    public static function enabled(string $feature): bool
    {
        return (bool) static::get("features.{$feature}", false);
    }
}

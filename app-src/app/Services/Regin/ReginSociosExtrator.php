<?php

namespace App\Services\Regin;

use App\Rules\ValidCpf;
use Illuminate\Support\Facades\Validator;

/**
 * CPFs dos sócios no RUC do REGIN (GROUPRUC_PROF.RUC_PROF). Nos envelopes
 * reais (análise de 15/10/2026) o grupo traz só sócios; o nome do campo de
 * documento não foi confirmado, por isso vale qualquer chave com CPF ou CGC
 * que tenha um CPF válido. Item com CRC preenchido é contador e fica de fora.
 */
final class ReginSociosExtrator
{
    /**
     * @param  array<string, mixed>  $rowset
     * @return list<string>
     */
    public static function cpfs(array $rowset): array
    {
        $itens = $rowset['GROUPRUC_PROF']['RUC_PROF'] ?? null;

        if (! is_array($itens)) {
            return [];
        }

        if (! array_is_list($itens)) {
            $itens = [$itens];
        }

        $cpfs = [];

        foreach ($itens as $item) {
            if (! is_array($item) || self::ehContador($item)) {
                continue;
            }

            foreach ($item as $chave => $valor) {
                $chave = strtoupper((string) $chave);

                if (! is_scalar($valor) || (! str_contains($chave, 'CPF') && ! str_contains($chave, 'CGC'))) {
                    continue;
                }

                $digitos = preg_replace('/\D/', '', (string) $valor) ?? '';

                if (strlen($digitos) === 11 && Validator::make(['cpf' => $digitos], ['cpf' => [new ValidCpf]])->passes()) {
                    $cpfs[$digitos] = true;
                }
            }
        }

        $lista = array_keys($cpfs);
        sort($lista);

        return array_map('strval', $lista);
    }

    /**
     * @param  array<array-key, mixed>  $item
     */
    private static function ehContador(array $item): bool
    {
        foreach ($item as $chave => $valor) {
            if (str_contains(strtoupper((string) $chave), 'CRC') && is_scalar($valor) && trim((string) $valor) !== '') {
                return true;
            }
        }

        return false;
    }
}

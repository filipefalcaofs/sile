<?php

namespace App\Services\Tratamento;

/**
 * Categoria da empresa para as regras da planilha: órgão público (natureza
 * jurídica do grupo 1, Administração Pública, na tabela da Receita) ou outra.
 * Sem natureza jurídica informada, a categoria fica desconhecida.
 */
final class CategoriaEmpresa
{
    public const ORGAO_PUBLICO = 'orgao_publico';

    public const OUTRA = 'outra';

    public static function deNaturezaJuridica(?string $codigo, ?string $descricao = null): ?string
    {
        $codigo = trim((string) $codigo);
        $descricao = trim((string) $descricao);

        if ($codigo === '' && preg_match('/^\s*(\d{3})\s*-?\s*(\d)\b/u', $descricao, $m) === 1) {
            $codigo = $m[1].$m[2];
        }

        $digitos = (string) preg_replace('/\D/', '', $codigo);

        if (strlen($digitos) === 4) {
            return str_starts_with($digitos, '1') ? self::ORGAO_PUBLICO : self::OUTRA;
        }

        if ($descricao === '') {
            return null;
        }

        return preg_match('/\b(órg[aã]o p[úu]blico|administra[cç][aã]o p[úu]blica|autarquia|funda[cç][aã]o p[úu]blica|poder (executivo|legislativo|judici[aá]rio)|munic[ií]pio|estado de|uni[aã]o)\b/iu', $descricao) === 1
            ? self::ORGAO_PUBLICO
            : self::OUTRA;
    }
}

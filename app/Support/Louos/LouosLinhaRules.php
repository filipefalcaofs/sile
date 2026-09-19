<?php

namespace App\Support\Louos;

use App\Enums\Quadro10Permissao;
use Illuminate\Validation\Rule;

/**
 * Regras de validação de linha dos Quadros LOUOS, parametrizadas por prefixo
 * para uso tanto na publicação versionada (prefixo 'alteracoes.*.') quanto
 * no CRUD de rascunho (prefixo vazio).
 *
 * Quadros aceitos: quadro10, quadro11a.
 */
class LouosLinhaRules
{
    /**
     * @return array<string, mixed>
     */
    public static function forQuadro(string $quadro, string $prefix = ''): array
    {
        return match ($quadro) {
            'quadro10' => [
                $prefix.'zona' => ['required', 'string', 'max:50'],
                $prefix.'grupo_uso' => ['required', 'string', 'max:50'],
                $prefix.'subgrupo' => ['nullable', 'string', 'max:50'],
                $prefix.'permissao' => ['required', Rule::enum(Quadro10Permissao::class)],
                $prefix.'condicionante_ref' => ['nullable', 'string', 'max:50'],
                $prefix.'base_legal' => ['nullable', 'string'],
                $prefix.'observacao' => ['nullable', 'string'],
            ],
            'quadro11a' => [
                $prefix.'classe_via' => ['required', 'string', 'max:50'],
                $prefix.'grupo_uso' => ['nullable', 'string', 'max:50'],
                $prefix.'condicoes' => ['nullable', 'array'],
                $prefix.'base_legal' => ['nullable', 'string'],
                $prefix.'observacao' => ['nullable', 'string'],
            ],
            default => [],
        };
    }
}

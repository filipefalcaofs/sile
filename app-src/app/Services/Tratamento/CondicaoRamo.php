<?php

namespace App\Services\Tratamento;

/**
 * Condições de um ramo curado da planilha, combinadas por E:
 * - `perguntas`: por número, `{"sim": bool}` ou `{"opcoes": [..], "sem": [..]}` (alguma das
 *   opções marcada e nenhuma das vetadas);
 * - `categoria`: `orgao_publico` ou `outra`;
 * - `tipo_imovel`: `residencial`, `galpao_container`, `dirige` ou `comum`;
 * - `faixa`: `ate_1250` ou `acima_1250`;
 * - `glp_kg`: `{"ate": n}` ou `{"acima": n}` sobre o armazenamento de GLP.
 */
final class CondicaoRamo
{
    /**
     * @param  array<string, mixed>  $condicoes
     */
    public static function atende(array $condicoes, ContextoRamo $contexto): bool
    {
        foreach ($condicoes as $tipo => $valor) {
            $ok = match ($tipo) {
                'perguntas' => self::perguntas((array) $valor, $contexto),
                'categoria' => $contexto->categoriaEmpresa === $valor,
                'tipo_imovel' => self::tipoImovel((string) $valor, $contexto),
                'faixa' => $contexto->faixa === $valor,
                'glp_kg' => self::glp((array) $valor, $contexto),
                default => false,
            };

            if (! $ok) {
                return false;
            }
        }

        return true;
    }

    /**
     * Quantas condições o ramo tem: o mais específico vence.
     *
     * @param  array<string, mixed>  $condicoes
     */
    public static function peso(array $condicoes): int
    {
        $peso = 0;

        foreach ($condicoes as $tipo => $valor) {
            $peso += $tipo === 'perguntas' ? count((array) $valor) : 1;
        }

        return $peso;
    }

    /**
     * @param  array<string, array<string, mixed>>  $perguntas
     */
    private static function perguntas(array $perguntas, ContextoRamo $contexto): bool
    {
        foreach ($perguntas as $numero => $esperado) {
            if (! array_key_exists((int) $numero, $contexto->respostas)) {
                return false;
            }

            $resposta = RespostaPlanilha::de($contexto->respostas[(int) $numero]);

            if (array_key_exists('sim', $esperado) && $resposta->sim() !== $esperado['sim']) {
                return false;
            }

            if (array_key_exists('opcoes', $esperado) && ! $resposta->algumaDe(array_map(intval(...), (array) $esperado['opcoes']))) {
                return false;
            }

            if (array_key_exists('sem', $esperado) && $resposta->algumaDe(array_map(intval(...), (array) $esperado['sem']))) {
                return false;
            }
        }

        return true;
    }

    private static function tipoImovel(string $esperado, ContextoRamo $contexto): bool
    {
        $codigo = $contexto->tipoImovel?->dirigeRegra() === true ? $contexto->tipoImovel->normalized : null;

        return match ($esperado) {
            'residencial' => $codigo === 'edificacao_residencial',
            'galpao_container' => in_array($codigo, ['galpao', 'container'], true),
            'dirige' => $codigo !== null,
            'comum' => $codigo === null,
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $limite
     */
    private static function glp(array $limite, ContextoRamo $contexto): bool
    {
        $quilos = ArmazenamentoGlp::quilos($contexto->respostas);

        if ($quilos === null) {
            return false;
        }

        if (array_key_exists('ate', $limite)) {
            return $quilos <= (int) $limite['ate'];
        }

        return array_key_exists('acima', $limite) && $quilos > (int) $limite['acima'];
    }
}

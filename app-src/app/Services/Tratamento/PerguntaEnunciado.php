<?php

namespace App\Services\Tratamento;

/**
 * Lê o enunciado de uma pergunta da planilha: tipo (sim_nao, opcao_unica,
 * multipla) e opções numeradas ("1.") ou com letra ("A."). Enunciado sem
 * opções segue como Sim/Não, o comportamento anterior.
 */
final class PerguntaEnunciado
{
    /**
     * @return array{tipo: string, opcoes: list<array{rotulo: string, texto: string}>}
     */
    public static function analisar(string $enunciado): array
    {
        $opcoes = [];

        foreach (preg_split('/\R/u', $enunciado) ?: [] as $linha) {
            if (preg_match('/^\s*(\d+|[A-Z])\s*[\.\)]\s*(.+?)\s*$/u', $linha, $m) === 1) {
                $opcoes[] = ['rotulo' => $m[1], 'texto' => $m[2]];
            }
        }

        if ($opcoes === []) {
            return ['tipo' => 'sim_nao', 'opcoes' => []];
        }

        if (self::eSimNao($opcoes)) {
            return ['tipo' => 'sim_nao', 'opcoes' => $opcoes];
        }

        $multipla = count($opcoes) > 2
            && preg_match('/selecione quais|identifique entre as op[cç][õo]es/iu', $enunciado) === 1;

        return ['tipo' => $multipla ? 'multipla' : 'opcao_unica', 'opcoes' => $opcoes];
    }

    /**
     * Pergunta que só aparece depois de outra resposta ("Exibir apenas SE
     * RESPONDIDO SIM na pergunta 2"): número da pergunta e a resposta esperada.
     *
     * @return array{pergunta: int, sim: bool}|null
     */
    public static function dependencia(string $enunciado): ?array
    {
        $padrao = '/(?:exibir apenas se respondido|se respondido|se a resposta for)\s+["“”]?(sim|n[aã]o)["“”]?\s+na[s]?\s+pergunta[s]?\s+(\d+)/iu';

        if (preg_match($padrao, $enunciado, $m) !== 1) {
            return null;
        }

        return ['pergunta' => (int) $m[2], 'sim' => mb_strtolower($m[1]) === 'sim'];
    }

    /**
     * @param  list<array{rotulo: string, texto: string}>  $opcoes
     */
    private static function eSimNao(array $opcoes): bool
    {
        return count($opcoes) === 2
            && preg_match('/^sim\b/iu', $opcoes[0]['texto']) === 1
            && preg_match('/^n[aã]o\b/iu', $opcoes[1]['texto']) === 1;
    }
}

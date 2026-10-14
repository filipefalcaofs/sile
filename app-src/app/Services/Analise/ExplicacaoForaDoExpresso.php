<?php

namespace App\Services\Analise;

/**
 * Diz, em linguagem de analista, por que uma atividade (CNAE) não segue o
 * fluxo expresso. Lê só o encaminhamento do motor de risco (nível, dimensão
 * decisiva, motivo e gatilhos), sem decidir nada.
 */
final class ExplicacaoForaDoExpresso
{
    /**
     * @param  array<string, mixed>  $encaminhamento
     */
    public static function longa(string $cnaeFormatado, array $encaminhamento, ?string $grupo = null, ?string $codigoLouos = null): string
    {
        $gatilhos = self::gatilhos($encaminhamento);

        if ($gatilhos !== []) {
            return 'Fora do fluxo expresso: '.implode('; ', $gatilhos).'. Atividade com gatilho acionado não é decidida automaticamente; o analista confirma ou altera o veredito do motor.';
        }

        $nivel = self::nivel($encaminhamento);
        $dimensao = (string) ($encaminhamento['dimensao_decisiva'] ?? '');

        if ($nivel === null) {
            $motivo = trim((string) ($encaminhamento['motivo'] ?? ''));

            return 'Fora do fluxo expresso: '.($motivo !== '' ? $motivo : 'a classificação de risco da atividade não está parametrizada').'. O motor não decide sozinho; o analista decide.';
        }

        $enquadramento = array_filter([
            $grupo !== null && $grupo !== '' ? "grupo {$grupo}" : null,
            $codigoLouos !== null && $codigoLouos !== '' ? "LOUOS {$codigoLouos}" : null,
        ]);
        $complemento = $enquadramento === [] ? '' : ' ('.implode(', ', $enquadramento).')';

        return "Fora do fluxo expresso: a atividade{$complemento} tem risco {$nivel}, definido pela ".self::origem($dimensao)
            .'. Esse nível não é decidido automaticamente: o motor já indica o veredito, e o analista o confirma ou altera.';
    }

    /**
     * Versão curta, para o motivo do encaminhamento exibido nas listas.
     *
     * @param  array<string, mixed>  $encaminhamento
     */
    public static function curta(string $cnaeFormatado, array $encaminhamento): string
    {
        $gatilhos = self::gatilhos($encaminhamento);

        if ($gatilhos !== []) {
            return "CNAE {$cnaeFormatado}: {$gatilhos[0]}";
        }

        $nivel = self::nivel($encaminhamento);

        if ($nivel === null) {
            return "CNAE {$cnaeFormatado}: risco não parametrizado";
        }

        return "CNAE {$cnaeFormatado}: risco {$nivel} ("
            .self::origem((string) ($encaminhamento['dimensao_decisiva'] ?? '')).')';
    }

    /**
     * @param  array<string, mixed>  $encaminhamento
     * @return list<string>
     */
    private static function gatilhos(array $encaminhamento): array
    {
        $textos = [];

        foreach ((array) ($encaminhamento['gatilhos_acionados'] ?? []) as $gatilho) {
            $texto = trim((string) (is_array($gatilho) ? ($gatilho['motivo'] ?? $gatilho['codigo'] ?? '') : ''));

            if ($texto !== '') {
                $textos[] = $texto;
            }
        }

        return array_values(array_unique($textos));
    }

    /**
     * @param  array<string, mixed>  $encaminhamento
     */
    private static function nivel(array $encaminhamento): ?string
    {
        $nivel = $encaminhamento['nivel'] ?? null;

        return is_string($nivel) && $nivel !== '' ? str_replace('_', ' ', $nivel) : null;
    }

    private static function origem(string $dimensao): string
    {
        return match ($dimensao) {
            'risco_tratamento' => 'planilha de tratamento vigente',
            'sanitario' => 'classificação de risco sanitário',
            'municipal' => 'classificação de risco municipal',
            default => 'classificação de risco vigente',
        };
    }
}

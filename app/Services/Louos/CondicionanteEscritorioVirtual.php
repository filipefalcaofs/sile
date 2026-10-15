<?php

namespace App\Services\Louos;

/**
 * Regra 42 da planilha: quem não presta serviço de escritório virtual (pergunta 4
 * respondida "Não") no CNAE 8211-3/00 recebe a condicionante que explica por que
 * o escritório virtual não está autorizado, conforme a atividade marcada na
 * pergunta 5. A condicionante da sede (pergunta 4 "Sim") nasce no deferimento.
 */
final class CondicionanteEscritorioVirtual
{
    private const PERGUNTA_SEDE = 4;

    private const PERGUNTA_ATIVIDADES = 5;

    private const OPCAO_COWORKING = 5;

    /**
     * @param  array<int, mixed>  $respostas
     */
    public function texto(string $cnae, array $respostas): ?string
    {
        $gatilho = (string) config('viabiliza.analise.escritorio_virtual.cnae_gatilho_sede');

        if ($this->digitos($cnae) !== $this->digitos($gatilho) || ($respostas[self::PERGUNTA_SEDE] ?? null) !== false) {
            return null;
        }

        $opcoes = array_map('intval', (array) ($respostas[self::PERGUNTA_ATIVIDADES] ?? []));

        if ($opcoes === []) {
            return null;
        }

        return array_diff($opcoes, [self::OPCAO_COWORKING]) !== []
            ? (string) config('viabiliza.analise.escritorio_virtual.condicionante_apoio_administrativo')
            : (string) config('viabiliza.analise.escritorio_virtual.condicionante_coworking');
    }

    private function digitos(string $cnae): string
    {
        return (string) preg_replace('/\D/', '', $cnae);
    }
}

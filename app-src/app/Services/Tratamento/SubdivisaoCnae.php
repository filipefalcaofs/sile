<?php

namespace App\Services\Tratamento;

/**
 * Opções da pergunta 1 por CNAE: a coluna "Subdivisão CNAE" da aba CNAES da
 * planilha lista, para cada CNAE, as atividades entre as quais o requerente
 * escolhe. As regras citam essas opções pelo número (ex.: regras 40 e 43).
 */
final class SubdivisaoCnae
{
    /**
     * CNAEs em que cada opção leva a um enquadramento diferente e só uma vale
     * (organizações religiosas, regra 43). Nos demais a pergunta aceita mais de
     * uma opção; quando misturam, vale a mais restritiva.
     *
     * @var list<string>
     */
    private const OPCAO_UNICA = ['9491-0/00'];

    /**
     * @return list<array{rotulo: string, texto: string}>
     */
    public static function opcoes(?string $subdivisao): array
    {
        $opcoes = [];

        foreach (preg_split('/\R/u', (string) $subdivisao) ?: [] as $linha) {
            if (preg_match('/^\s*(\d+)\s*[.)]\s*(.+?)\s*$/u', $linha, $m) === 1) {
                $opcoes[] = ['rotulo' => $m[1], 'texto' => $m[2]];
            }
        }

        return $opcoes;
    }

    public static function tipo(string $cnaeFormatado): string
    {
        return in_array($cnaeFormatado, self::OPCAO_UNICA, true) ? 'opcao_unica' : 'multipla';
    }
}

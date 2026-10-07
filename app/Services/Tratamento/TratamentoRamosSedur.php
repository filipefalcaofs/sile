<?php

namespace App\Services\Tratamento;

use App\Models\RuleVersion;
use App\Models\TratamentoRegraRamo;
use RuntimeException;

/**
 * Ramos curados das regras em prosa da planilha (29, 30, 31, 37, 38, 39, 53 e
 * 55), transcritos do texto da planilha e das respostas da SEDUR de
 * 07/10/2026. Cada item do arquivo guarda a frase de origem.
 */
class TratamentoRamosSedur
{
    private const PREFIXO = 'sedur|';

    public function carregar(RuleVersion $versao, string $path): int
    {
        $dados = json_decode((string) file_get_contents($path), true);

        if (! is_array($dados) || ! is_array($dados['ramos'] ?? null)) {
            throw new RuntimeException('Arquivo de ramos curados da SEDUR inválido.');
        }

        $chaves = [];

        foreach ($dados['ramos'] as $ramo) {
            $acao = array_filter([
                'mensagem' => $ramo['mensagem'] ?? null,
                'subgrupo' => $ramo['subgrupo'] ?? null,
                'frase' => $ramo['frase'] ?? null,
            ], fn (mixed $valor): bool => $valor !== null);

            $chave = self::PREFIXO.$ramo['regra'].'|'.substr(md5(json_encode([$ramo['condicoes'], $ramo['codigo_louos'] ?? null, $ramo['fluxo'], $ramo['bloqueia'] ?? false, $acao])), 0, 12);
            $chaves[] = $chave;

            TratamentoRegraRamo::query()->updateOrCreate(
                ['rule_version_id' => $versao->getKey(), 'chave' => $chave],
                [
                    'regra' => $ramo['regra'],
                    'pergunta' => null,
                    'resposta' => null,
                    'faixa' => null,
                    'tipo_dirige' => null,
                    'condicoes' => $ramo['condicoes'],
                    'codigo_louos' => $ramo['codigo_louos'] ?? null,
                    'fluxo' => $ramo['fluxo'],
                    'bloqueia' => $ramo['bloqueia'] ?? false,
                    'acao' => $acao === [] ? null : $acao,
                ],
            );
        }

        TratamentoRegraRamo::query()
            ->where('rule_version_id', $versao->getKey())
            ->where('chave', 'like', self::PREFIXO.'%')
            ->whereNotIn('chave', $chaves)
            ->delete();

        // O ramo curado substitui o antigo da mesma regra: o antigo lê a
        // pergunta vinculada como Sim/Não e, com opções, casaria errado.
        TratamentoRegraRamo::query()
            ->where('rule_version_id', $versao->getKey())
            ->whereIn('regra', array_values(array_unique(array_column($dados['ramos'], 'regra'))))
            ->whereNull('condicoes')
            ->delete();

        return count($chaves);
    }
}

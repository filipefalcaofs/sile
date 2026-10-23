<?php

namespace App\Services\Sefaz;

/**
 * Simulador da consulta para a homologação: lê as viabilidades de
 * `viabiliza.sefaz.consulta.simulador`. É configuração de ambiente, nunca
 * o padrão; produção usa o provider real quando o endpoint existir.
 */
class SimuladoSefazViabilidadeConsultaGateway implements SefazViabilidadeConsultaGateway
{
    public function consultarPorCnpj(string $cnpj): ?ViabilidadeVinculada
    {
        $digitos = (string) preg_replace('/\D/', '', $cnpj);

        if (in_array($digitos, (array) config('viabiliza.sefaz.consulta.simulador.indisponiveis', []), true)) {
            throw new SefazConsultaIndisponivelException($digitos, 'Simulador: SEFAZ fora do ar para este CNPJ.');
        }

        $dados = config("viabiliza.sefaz.consulta.simulador.viabilidades.{$digitos}");

        if (! is_array($dados)) {
            return null;
        }

        return new ViabilidadeVinculada((string) ($dados['numero'] ?? ''), array_values((array) ($dados['cnaes'] ?? [])));
    }
}

<?php

namespace App\Services\Relatorios\Export;

use Illuminate\Support\Carbon;

/**
 * Rotula os filtros aplicados da tela para PDF (card de filtros) e Excel
 * (aba PARÂMETROS), de modo que os dois formatos mostrem o mesmo conjunto.
 */
final class ReportFilterLabels
{
    private const FILTROS_PADRAO = ['Período', 'Bairro', 'Categoria', 'Status'];

    private const ROTULOS_FILTRO = [
        'bairro' => 'Bairro', 'categoria' => 'Categoria', 'status' => 'Status', 'setor' => 'Setor',
        'analista' => 'Analista', 'fluxo' => 'Fluxo', 'nome' => 'Empresa', 'cnpj' => 'CNPJ',
        'protocolo' => 'Processo', 'busca' => 'Busca', 'ordem' => 'Ordem', 'grupo' => 'Grupo',
        'logradouro' => 'Logradouro', 'cep' => 'CEP', 'inscricao' => 'Inscrição',
    ];

    /**
     * Filtros da tela como pares rótulo/valor para o card do relatório: os
     * padrões (Período, Bairro, Categoria, Status) sempre aparecem, com valor
     * null ("Todos") quando não aplicados, seguidos dos demais aplicados.
     *
     * @param  array<string, scalar>  $filtros
     * @return list<array{0: string, 1: string|null}>
     */
    public static function pares(array $filtros): array
    {
        $data = static fn (mixed $v): string => preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $v) === 1
            ? Carbon::parse((string) $v)->format('d/m/Y')
            : (string) $v;

        $mapa = array_fill_keys(self::FILTROS_PADRAO, null);

        $de = $filtros['data_de'] ?? null;
        $ate = $filtros['data_ate'] ?? null;
        if ($de !== null || $ate !== null) {
            $mapa['Período'] = $de !== null && $ate !== null
                ? $data($de).' a '.$data($ate)
                : ($de !== null ? 'a partir de '.$data($de) : 'até '.$data($ate));
        }

        foreach ($filtros as $chave => $valor) {
            if (in_array($chave, ['data_de', 'data_ate'], true)) {
                continue;
            }

            $rotulo = self::ROTULOS_FILTRO[$chave] ?? ucfirst(str_replace('_', ' ', (string) $chave));
            $mapa[$rotulo] = (string) $valor;
        }

        $pares = [];
        foreach ($mapa as $rotulo => $valor) {
            $pares[] = [$rotulo, $valor];
        }

        return $pares;
    }
}

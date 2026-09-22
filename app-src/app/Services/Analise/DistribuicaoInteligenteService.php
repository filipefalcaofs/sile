<?php

namespace App\Services\Analise;

/**
 * Sugestão de distribuição em lote (não tramita). Atribui cada processo, do
 * prazo mais curto para o mais longo, ao analista elegível que ficaria com a
 * menor carga depois de recebê-lo. Empate de carga: nome e, depois, id.
 * O Apoio revisa e só a confirmação grava.
 */
class DistribuicaoInteligenteService
{
    public const CRITERIO = 'Distribuição calculada com base na carga ativa dos analistas elegíveis e no objetivo de equilibrar a quantidade de processos após a distribuição.';

    /**
     * @param  list<array{id: int, sector_id: int, analysis_status: ?string, analysis_due_at: ?string, bap: ?string, protocol_number: ?string}>  $processos
     * @param  list<array{analista_id: int, analista: string, total: int, pode_vistoria: bool, setores: list<int>}>  $analistas
     * @return array{
     *     criterio: string,
     *     alocacoes: list<array{analista_id: int, analista: string, atual: int, recebera: int, apos: int, processos: list<array{id: int, bap: ?string, protocol_number: ?string, elegiveis: list<int>}>}>,
     *     nao_alocados: list<array{id: int, bap: ?string, protocol_number: ?string, motivo: string}>
     * }
     */
    public function sugerir(array $processos, array $analistas): array
    {
        $ordenados = $processos;
        usort($ordenados, function (array $a, array $b): int {
            $prazoA = $a['analysis_due_at'] ?? '';
            $prazoB = $b['analysis_due_at'] ?? '';

            if ($prazoA === '' && $prazoB !== '') {
                return 1;
            }

            if ($prazoB === '' && $prazoA !== '') {
                return -1;
            }

            if ($prazoA !== $prazoB) {
                return $prazoA <=> $prazoB;
            }

            return $a['id'] <=> $b['id'];
        });

        $projetada = [];
        foreach ($analistas as $analista) {
            $projetada[$analista['analista_id']] = $analista['total'];
        }

        $porAnalista = [];
        foreach ($analistas as $analista) {
            $porAnalista[$analista['analista_id']] = [];
        }

        $naoAlocados = [];
        $envolvidos = [];

        foreach ($ordenados as $processo) {
            $elegiveis = $this->elegiveis($processo, $analistas);

            if ($elegiveis === []) {
                $naoAlocados[] = [
                    'id' => $processo['id'],
                    'bap' => $processo['bap'],
                    'protocol_number' => $processo['protocol_number'],
                    'motivo' => 'Nenhum analista elegível para este processo.',
                ];

                continue;
            }

            $escolhido = $elegiveis[0];
            foreach ($elegiveis as $candidato) {
                $cargaEscolhido = $projetada[$escolhido['analista_id']];
                $cargaCandidato = $projetada[$candidato['analista_id']];

                if ($cargaCandidato < $cargaEscolhido) {
                    $escolhido = $candidato;

                    continue;
                }

                if ($cargaCandidato === $cargaEscolhido && $this->antes($candidato, $escolhido)) {
                    $escolhido = $candidato;
                }
            }

            $projetada[$escolhido['analista_id']]++;
            $porAnalista[$escolhido['analista_id']][] = [
                'id' => $processo['id'],
                'bap' => $processo['bap'],
                'protocol_number' => $processo['protocol_number'],
                'elegiveis' => array_map(fn (array $item): int => $item['analista_id'], $elegiveis),
            ];
            $envolvidos[$escolhido['analista_id']] = true;
            foreach ($elegiveis as $elegivel) {
                $envolvidos[$elegivel['analista_id']] = true;
            }
        }

        $alocacoes = [];
        foreach ($analistas as $analista) {
            if (! isset($envolvidos[$analista['analista_id']])) {
                continue;
            }

            $recebera = count($porAnalista[$analista['analista_id']]);
            $alocacoes[] = [
                'analista_id' => $analista['analista_id'],
                'analista' => $analista['analista'],
                'atual' => $analista['total'],
                'recebera' => $recebera,
                'apos' => $analista['total'] + $recebera,
                'processos' => $porAnalista[$analista['analista_id']],
            ];
        }

        usort($alocacoes, fn (array $a, array $b): int => strcasecmp($a['analista'], $b['analista']) ?: $a['analista_id'] <=> $b['analista_id']);

        return [
            'criterio' => self::CRITERIO,
            'alocacoes' => $alocacoes,
            'nao_alocados' => $naoAlocados,
        ];
    }

    /**
     * @param  array{id: int, sector_id: int, analysis_status: ?string}  $processo
     * @param  list<array{analista_id: int, analista: string, total: int, pode_vistoria: bool, setores: list<int>}>  $analistas
     * @return list<array{analista_id: int, analista: string, total: int, pode_vistoria: bool, setores: list<int>}>
     */
    private function elegiveis(array $processo, array $analistas): array
    {
        $vistoria = in_array($processo['analysis_status'], ['vistoriar', 'vistoriado'], true);

        return array_values(array_filter(
            $analistas,
            fn (array $analista): bool => in_array($processo['sector_id'], $analista['setores'], true)
                && (! $vistoria || $analista['pode_vistoria']),
        ));
    }

    /**
     * @param  array{analista_id: int, analista: string}  $candidato
     * @param  array{analista_id: int, analista: string}  $escolhido
     */
    private function antes(array $candidato, array $escolhido): bool
    {
        $nome = strcasecmp($candidato['analista'], $escolhido['analista']);

        if ($nome !== 0) {
            return $nome < 0;
        }

        return $candidato['analista_id'] < $escolhido['analista_id'];
    }
}

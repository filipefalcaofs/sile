<?php

namespace App\Services\Louos;

use App\Models\LouosQuadro11CondicaoVia;
use App\Models\RuleVersion;
use RuntimeException;
use SplFileObject;

/**
 * Importação do Quadro 11A da LOUOS (condições de instalação pela via —
 * HU-017/HU-018/HU-040/HU-041) a partir de um CSV versionado em
 * database/data/louos/. O "Quadro 11" não existe na publicação oficial da
 * SEDUR — apenas 11A e 11B. Formato esperado: classe_via,grupo_uso,condicoes,
 * base_legal; o campo `condicoes` é uma lista de itens separados por ';',
 * gravados como JSON array de strings (ou null quando vazio).
 *
 * Idempotente: upsert por (rule_version_id, classe_via, grupo_uso) — o
 * grupo_uso ausente é gravado como '' para o ON CONFLICT casar no re-import;
 * o upsert NÃO toca 'observacao'. JSON inválido nunca é inserido.
 */
class LouosQuadro11ImportService
{
    private const EXPECTED_HEADER = [
        'classe_via',
        'grupo_uso',
        'condicoes',
        'base_legal',
    ];

    /**
     * @return array{lidos: int, importados: int, atualizados: int, rejeitados: array<int, string>, total: int}
     */
    public function import(RuleVersion $version, string $csvPath): array
    {
        $file = new SplFileObject($csvPath, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY);

        $header = null;
        $rejected = [];
        $read = 0;
        $rows = [];

        foreach ($file as $line) {
            if ($line === false || $line === [null]) {
                continue;
            }

            if ($header === null) {
                $header = array_map(fn ($value) => trim((string) $value), $line);

                if ($header !== self::EXPECTED_HEADER) {
                    throw new RuntimeException(
                        "Cabeçalho inesperado em {$csvPath}: esperado classe_via,grupo_uso,condicoes,base_legal (Quadro 11A da Lei 9.148/2016).",
                    );
                }

                continue;
            }

            if (count($line) !== count(self::EXPECTED_HEADER)) {
                continue;
            }

            $data = array_combine(
                self::EXPECTED_HEADER,
                array_map(fn ($value) => trim((string) $value), $line),
            );

            $read++;

            if ($data['classe_via'] === '') {
                $rejected[] = sprintf('linha %d: classe_via é obrigatória', $read);

                continue;
            }

            $condicoes = null;

            if ($data['condicoes'] !== '') {
                $itens = array_values(array_filter(array_map(
                    fn (string $item) => trim($item),
                    explode(';', $data['condicoes']),
                ), fn (string $item) => $item !== ''));

                $condicoes = $itens === [] ? null : json_encode($itens, JSON_UNESCAPED_UNICODE);
            }

            $rows[] = [
                'rule_version_id' => $version->getKey(),
                'classe_via' => $data['classe_via'],
                'grupo_uso' => $data['grupo_uso'],
                'condicoes' => $condicoes,
                'base_legal' => $data['base_legal'] === '' ? null : $data['base_legal'],
            ];
        }

        $existing = LouosQuadro11CondicaoVia::query()
            ->where('rule_version_id', $version->getKey())
            ->get(['classe_via', 'grupo_uso'])
            ->map(fn (LouosQuadro11CondicaoVia $c): string => $c->classe_via.'|'.(string) $c->grupo_uso)
            ->flip();

        $imported = 0;
        $updated = 0;

        foreach ($rows as $row) {
            isset($existing[$row['classe_via'].'|'.$row['grupo_uso']]) ? $updated++ : $imported++;
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            LouosQuadro11CondicaoVia::query()->upsert(
                $chunk,
                ['rule_version_id', 'classe_via', 'grupo_uso'],
                ['condicoes', 'base_legal'],
            );
        }

        return [
            'lidos' => $read,
            'importados' => $imported,
            'atualizados' => $updated,
            'rejeitados' => $rejected,
            'total' => count($rows),
        ];
    }
}

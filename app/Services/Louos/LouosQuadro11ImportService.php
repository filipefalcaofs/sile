<?php

namespace App\Services\Louos;

use App\Models\LouosQuadro11CondicaoVia;
use App\Models\RuleVersion;
use JsonException;
use RuntimeException;
use SplFileObject;

/**
 * Import dos Quadros 11 e 11A da LOUOS (condições de instalação pela via —
 * HU-017/HU-018/HU-040/HU-041) a partir de um ÚNICO CSV versionado em
 * database/data/louos/. A coluna `quadro` (11 | 11a) distingue os dois domínios;
 * cada chamada importa só as linhas do quadro informado para a versão de regra
 * correspondente (rule_version_id, domínio louos_quadro11 ou louos_quadro11a).
 *
 * ESCOPO HONESTO: dado MODELADO derivado da Lei nº 9.148/2016 (o atributo de
 * classificação viária real e a correspondência "Quadro 11"↔11B pendem
 * confirmação SEDUR). SUBSTITUÍVEL pela carga oficial sem mudar a lógica.
 *
 * Idempotente: upsert por (rule_version_id, classe_via, grupo_uso) — o grupo_uso
 * ausente é gravado como '' para o ON CONFLICT casar no re-import; o upsert NÃO
 * toca 'observacao'. `condicoes` é JSON validado na carga; JSON inválido é
 * rejeitado (relatório), nunca inserido.
 */
class LouosQuadro11ImportService
{
    private const EXPECTED_HEADER = [
        'quadro',
        'classe_via',
        'grupo_uso',
        'condicoes',
        'base_legal',
    ];

    /**
     * @return array{lidos: int, importados: int, atualizados: int, rejeitados: array<int, string>, total: int}
     */
    public function import(RuleVersion $version, string $csvPath, string $quadro): array
    {
        $quadroAlvo = mb_strtolower(trim($quadro));

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
                        "Cabeçalho inesperado em {$csvPath}: esperado quadro,classe_via,grupo_uso,condicoes,base_legal (Quadros 11/11A da Lei 9.148/2016).",
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

            // Só as linhas do quadro informado (11 ou 11a) entram nesta versão.
            if (mb_strtolower($data['quadro']) !== $quadroAlvo) {
                continue;
            }

            $read++;

            if ($data['classe_via'] === '') {
                $rejected[] = sprintf('linha %d: classe_via é obrigatória', $read);

                continue;
            }

            $condicoes = null;

            if ($data['condicoes'] !== '') {
                try {
                    $decoded = json_decode($data['condicoes'], true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException $e) {
                    $rejected[] = sprintf(
                        "classe '%s' / grupo '%s': condições com JSON inválido (%s)",
                        $data['classe_via'],
                        $data['grupo_uso'],
                        $e->getMessage(),
                    );

                    continue;
                }

                $condicoes = json_encode($decoded, JSON_UNESCAPED_UNICODE);
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

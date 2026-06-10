<?php

namespace App\Services;

use App\Models\Cnae;
use RuntimeException;
use SplFileObject;

/**
 * Import real da estrutura oficial CNAE-Subclasses 2.3 (IBGE/CONCLA) a
 * partir do CSV versionado em database/data/ (HU-011 CA-01).
 *
 * Idempotente: upsert por code. O upsert NÃO toca 'active' — desativações
 * administradas pela HU-011 sobrevivem a re-imports. A auditoria do import
 * é o log explícito do relatório no seeder (1.331 activities por linha
 * seriam ruído), não model events.
 */
class CnaeImportService
{
    private const EXPECTED_HEADER = [
        'section_code',
        'section_description',
        'division_code',
        'division_description',
        'group_code',
        'group_description',
        'class_code',
        'class_description',
        'subclass_code',
        'subclass_description',
    ];

    /**
     * A publicação oficial IBGE/CONCLA da versão 2.3 cita 1.332 subclasses;
     * o arquivo de estrutura traz 1.331 — divergência documentada abaixo.
     */
    private const OFFICIAL_PUBLICATION_COUNT = 1332;

    private const KNOWN_DIVERGENCE = [
        '9900-8/00' => 'Subclasse presente na publicação oficial IBGE/CONCLA, ausente do arquivo de estrutura; não consta do Decreto 32.636/2020. Cadastro manual via CRUD se necessário.',
    ];

    /**
     * @return array{lidos: int, importados: int, atualizados: int, rejeitados: array<int, string>, esperado_publicacao: int, divergencia: array<string, string>}
     */
    public function import(string $csvPath): array
    {
        $file = new SplFileObject($csvPath, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY);

        $header = null;
        $rows = [];
        $rejected = [];
        $seenCodes = [];
        $read = 0;

        foreach ($file as $line) {
            if ($line === false || $line === [null]) {
                continue;
            }

            if ($header === null) {
                $header = array_map(fn ($value) => trim((string) $value), $line);

                if ($header !== self::EXPECTED_HEADER) {
                    throw new RuntimeException(
                        "Cabeçalho inesperado em {$csvPath}: esperado o layout oficial cnaes-subclasses-2-3.",
                    );
                }

                continue;
            }

            $read++;

            if (count($line) !== count(self::EXPECTED_HEADER)) {
                $rejected[] = sprintf('linha %d: número de colunas inválido', $read);

                continue;
            }

            $data = array_combine(
                self::EXPECTED_HEADER,
                array_map(fn ($value) => trim((string) $value), $line),
            );

            $originalCode = $data['subclass_code'];
            $code = preg_replace('/\D/', '', $originalCode);

            if (preg_match('/^\d{7}$/', $code) !== 1) {
                $rejected[] = sprintf("código '%s' inválido (esperado o padrão DDDD-D/SS)", $originalCode);

                continue;
            }

            if ($data['subclass_description'] === '') {
                $rejected[] = sprintf("código '%s' sem denominação", $originalCode);

                continue;
            }

            if (isset($seenCodes[$code])) {
                $rejected[] = sprintf("código '%s' duplicado no arquivo", $originalCode);

                continue;
            }

            $seenCodes[$code] = true;

            $rows[] = [
                'code' => $code,
                'description' => $data['subclass_description'],
                'section_code' => $data['section_code'],
                'section_description' => $data['section_description'],
                'division_code' => $data['division_code'],
                'division_description' => $data['division_description'],
                'group_code' => $data['group_code'],
                'group_description' => $data['group_description'],
                'class_code' => $data['class_code'],
                'class_description' => $data['class_description'],
            ];
        }

        $existing = Cnae::query()->pluck('code')->flip();

        $imported = 0;
        $updated = 0;

        foreach ($rows as $row) {
            isset($existing[$row['code']]) ? $updated++ : $imported++;
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            Cnae::query()->upsert($chunk, ['code'], [
                'description',
                'section_code',
                'section_description',
                'division_code',
                'division_description',
                'group_code',
                'group_description',
                'class_code',
                'class_description',
            ]);
        }

        return [
            'lidos' => $read,
            'importados' => $imported,
            'atualizados' => $updated,
            'rejeitados' => $rejected,
            'esperado_publicacao' => self::OFFICIAL_PUBLICATION_COUNT,
            'divergencia' => self::KNOWN_DIVERGENCE,
        ];
    }
}

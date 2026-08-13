<?php

namespace App\Services\EscritorioVirtual;

use App\Models\RuleVersion;
use App\Models\VirtualOfficeActivityCnae;
use RuntimeException;
use SplFileObject;

/**
 * Import versionado da Lista EV (CNAEs permitidos para ABRIGADO de escritório
 * virtual, RN-EV-05/07) a partir do snapshot CSV commitado em
 * database/data/escritorio-virtual/ — espelha RiscoSanitarioImportService.
 *
 * Fonte oficial: endpoint SEDUR AtividadesPermitidasEmEscritorioVirtual.php;
 * este CSV é o snapshot vigente até a integração live com o endpoint.
 *
 * Idempotente: upsert por (rule_version_id, cnae_code) — sem model events
 * (a auditoria é o log explícito do relatório no seeder).
 */
class EscritorioVirtualCnaeImportService
{
    private const EXPECTED_HEADER = ['cnae_code', 'cnae_description'];

    /**
     * @return array{importados: int}
     */
    public function import(RuleVersion $version, string $csvPath): array
    {
        $file = new SplFileObject($csvPath, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY);

        $header = null;

        /** @var array<string, array{cnae_code: string, cnae_description: ?string}> $rows */
        $rows = [];

        foreach ($file as $line) {
            if ($line === false || $line === [null]) {
                continue;
            }

            if ($header === null) {
                $header = array_map(fn ($value) => trim((string) $value), $line);

                if ($header !== self::EXPECTED_HEADER) {
                    throw new RuntimeException(
                        "Cabeçalho inesperado em {$csvPath}: esperado cnae_code,cnae_description.",
                    );
                }

                continue;
            }

            $data = array_combine($header, array_map(fn ($value) => trim((string) $value), $line));

            $code = preg_replace('/\D/', '', $data['cnae_code']) ?? '';

            if ($code === '') {
                continue;
            }

            $rows[$code] = [
                'rule_version_id' => $version->getKey(),
                'cnae_code' => $code,
                'cnae_description' => $data['cnae_description'] !== '' ? $data['cnae_description'] : null,
            ];
        }

        foreach (array_chunk(array_values($rows), 500) as $chunk) {
            VirtualOfficeActivityCnae::query()->upsert(
                $chunk,
                ['rule_version_id', 'cnae_code'],
                ['cnae_description'],
            );
        }

        return ['importados' => count($rows)];
    }
}

<?php

namespace App\Services\Risco;

use App\Enums\RiscoMunicipal;
use App\Models\RiskClassification;
use App\Models\RuleVersion;
use InvalidArgumentException;
use RuntimeException;
use SplFileObject;

/**
 * Import real da classificação de risco MUNICIPAL (Decreto nº 32.636/2020) a
 * partir do CSV oficial versionado em database/data/risco/ (HU-020/HU-047),
 * espelhando CnaeImportService. A classificação fica ligada a uma versão de
 * regra (rule_version_id) — dado versionado, nunca código.
 *
 * Idempotente: upsert por (rule_version_id, cnae_code); o upsert NÃO toca
 * 'observacao' — anotações dos mantenedores (06-06) sobrevivem a re-imports.
 * Nível desconhecido é REJEITADO (relatório), nunca inventado. A auditoria do
 * import é o log explícito do relatório no seeder (1.331 activities por linha
 * seriam ruído), não model events.
 */
class RiscoMunicipalImportService
{
    private const EXPECTED_HEADER = [
        'cnae',
        'descricao',
        'condicionantes',
        'risco_municipal_unificado',
    ];

    /**
     * @return array{lidos: int, importados: int, atualizados: int, rejeitados: array<int, string>, por_nivel: array{baixo_a: int, baixo_b: int, alto: int}, total: int}
     */
    public function import(RuleVersion $version, string $csvPath): array
    {
        $file = new SplFileObject($csvPath, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY);

        $header = null;
        $rows = [];
        $rejected = [];
        $seenCodes = [];
        $read = 0;
        $porNivel = ['baixo_a' => 0, 'baixo_b' => 0, 'alto' => 0];

        foreach ($file as $line) {
            if ($line === false || $line === [null]) {
                continue;
            }

            if ($header === null) {
                $header = array_map(fn ($value) => trim((string) $value), $line);

                if ($header !== self::EXPECTED_HEADER) {
                    throw new RuntimeException(
                        "Cabeçalho inesperado em {$csvPath}: esperado o layout do Decreto 32.636/2020 (cnae,descricao,condicionantes,risco_municipal_unificado).",
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

            $originalCode = $data['cnae'];
            $code = preg_replace('/\D/', '', $originalCode);

            if (preg_match('/^\d{7}$/', $code) !== 1) {
                $rejected[] = sprintf("código '%s' inválido (esperado o padrão DDDD-D/SS)", $originalCode);

                continue;
            }

            if (isset($seenCodes[$code])) {
                $rejected[] = sprintf("código '%s' duplicado no arquivo", $originalCode);

                continue;
            }

            try {
                $risco = RiscoMunicipal::fromDecreto($data['risco_municipal_unificado']);
            } catch (InvalidArgumentException $e) {
                $rejected[] = sprintf("código '%s': %s", $originalCode, $e->getMessage());

                continue;
            }

            $seenCodes[$code] = true;
            $porNivel[$risco->value]++;

            $rows[] = [
                'rule_version_id' => $version->getKey(),
                'cnae_code' => $code,
                'risco_municipal' => $risco->value,
                'condicionantes' => json_encode(
                    $this->parseCondicionantes($data['condicionantes']),
                    JSON_UNESCAPED_UNICODE,
                ),
            ];
        }

        $existing = RiskClassification::query()
            ->where('rule_version_id', $version->getKey())
            ->pluck('cnae_code')
            ->flip();

        $imported = 0;
        $updated = 0;

        foreach ($rows as $row) {
            isset($existing[$row['cnae_code']]) ? $updated++ : $imported++;
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            RiskClassification::query()->upsert(
                $chunk,
                ['rule_version_id', 'cnae_code'],
                ['risco_municipal', 'condicionantes'],
            );
        }

        return [
            'lidos' => $read,
            'importados' => $imported,
            'atualizados' => $updated,
            'rejeitados' => $rejected,
            'por_nivel' => $porNivel,
            'total' => count($rows),
        ];
    }

    /**
     * Explode a coluna de condicionantes do Decreto (itens separados por '|'),
     * removendo o marcador de lista ('- ') e itens vazios. Linha sem
     * condicionante (string vazia ou só o marcador) vira [].
     *
     * @return array<int, string>
     */
    private function parseCondicionantes(string $raw): array
    {
        $raw = trim($raw);

        if ($raw === '') {
            return [];
        }

        $items = [];

        foreach (explode('|', $raw) as $part) {
            $part = preg_replace('/^[-−–]\s*/u', '', trim($part));
            $part = trim((string) $part);

            if ($part !== '') {
                $items[] = $part;
            }
        }

        return $items;
    }
}

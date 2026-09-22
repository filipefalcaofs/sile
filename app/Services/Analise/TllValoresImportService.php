<?php

namespace App\Services\Analise;

use App\Enums\RuleDomain;
use App\Enums\RuleVersionStatus;
use App\Models\RuleVersion;
use App\Models\TllValor;
use App\Services\Rules\RuleVersionService;
use RuntimeException;
use SplFileObject;

/**
 * Carga oficial da tabela TLL por exercício a partir do CSV derivado de
 * taxas_tll_2026.xlsx (Simplifica). Publica o exercício como vigente do
 * domínio tll_valores. Idempotente: upsert por (codigo, exercício, especificação).
 */
class TllValoresImportService
{
    public const EXERCICIO = 2026;

    public const SOURCE = 'Simplifica TLL 2026 (taxas_tll_2026.xlsx)';

    private const EXPECTED_HEADER = ['codigo', 'especificacao', 'hash_atividade', 'valor'];

    private const EXPECTED_COUNT = 29;

    public function __construct(private RuleVersionService $ruleVersions) {}

    /**
     * @return array{lidos: int, importados: int, atualizados: int, rejeitados: list<string>, esperado: int}
     */
    public function import(string $csvPath): array
    {
        $file = new SplFileObject($csvPath, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY);

        $header = null;
        $rejected = [];
        $read = 0;
        $imported = 0;
        $updated = 0;
        $rows = [];

        foreach ($file as $line) {
            if ($line === false || $line === [null]) {
                continue;
            }

            if ($header === null) {
                $header = array_map(fn ($value) => trim((string) $value), $line);

                if ($header !== self::EXPECTED_HEADER) {
                    throw new RuntimeException(
                        "Cabeçalho inesperado em {$csvPath}: esperado codigo,especificacao,hash_atividade,valor.",
                    );
                }

                continue;
            }

            $read++;

            if (count($line) < count(self::EXPECTED_HEADER)) {
                $rejected[] = sprintf('linha %d: número de colunas inválido', $read);

                continue;
            }

            $data = array_combine(
                self::EXPECTED_HEADER,
                array_map(fn ($value) => trim((string) $value), array_slice($line, 0, 4)),
            );

            if ($data === false || $data['codigo'] === '' || $data['hash_atividade'] === '' || $data['valor'] === '') {
                $rejected[] = sprintf('linha %d: código, hash ou valor vazio', $read);

                continue;
            }

            if (! is_numeric($data['valor']) || (float) $data['valor'] < 0) {
                $rejected[] = sprintf('linha %d: valor inválido', $read);

                continue;
            }

            $rows[] = $data;
        }

        if ($read !== self::EXPECTED_COUNT) {
            throw new RuntimeException(
                'Carga TLL 2026 divergente: esperado '.self::EXPECTED_COUNT." linhas, lidas {$read}.",
            );
        }

        $versao = $this->publicarExercicio();

        foreach ($rows as $data) {
            $valor = TllValor::query()->updateOrCreate(
                [
                    'codigo_tll' => $data['codigo'],
                    'exercicio' => self::EXERCICIO,
                    'especificacao' => $data['especificacao'],
                ],
                [
                    'valor' => number_format((float) $data['valor'], 2, '.', ''),
                    'codigo_tll_sefaz' => $data['hash_atividade'],
                    'rule_version_id' => $versao->id,
                    'active' => true,
                ],
            );

            if ($valor->wasRecentlyCreated) {
                $imported++;
            } else {
                $updated++;
            }
        }

        return [
            'lidos' => $read,
            'importados' => $imported,
            'atualizados' => $updated,
            'rejeitados' => $rejected,
            'esperado' => self::EXPECTED_COUNT,
        ];
    }

    private function publicarExercicio(): RuleVersion
    {
        $versao = $this->ruleVersions->openDraft(
            RuleDomain::TllValores,
            (string) self::EXERCICIO,
            self::SOURCE,
        );

        if ($versao->status === RuleVersionStatus::Rascunho) {
            return $this->ruleVersions->publish($versao);
        }

        return $versao;
    }
}

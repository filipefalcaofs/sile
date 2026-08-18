<?php

namespace App\Services\Louos;

use App\Enums\Quadro10Permissao;
use App\Models\LouosQuadro10Permissao;
use App\Models\RuleVersion;
use RuntimeException;
use SplFileObject;

/**
 * Import do Quadro 10 da LOUOS (permissão por zona — HU-016/HU-039) a partir do
 * CSV versionado em database/data/louos/, espelhando RiscoMunicipalImportService.
 * As permissões ficam ligadas a uma versão de regra (rule_version_id, domínio
 * louos_quadro10) — dado versionado.
 *
 * ESCOPO HONESTO: dado MODELADO derivado da Lei nº 9.148/2016 (estrutura real;
 * carga oficial por zona pendente SEDUR — SIGIS/CA 2000). O motor degrada para
 * pendente sem a zona real (05-04), nunca inventa permissão. SUBSTITUÍVEL pela
 * planilha oficial sem mudar a lógica.
 *
 * Idempotente: upsert por (rule_version_id, zona, grupo_uso, subgrupo) — o
 * subgrupo ausente é gravado como '' para o ON CONFLICT casar no re-import; o
 * upsert NÃO toca 'observacao' (anotações do mantenedor sobrevivem). A permissão
 * é validada contra o enum Quadro10Permissao: valor desconhecido é rejeitado
 * (relatório), nunca inserido.
 */
class LouosQuadro10ImportService
{
    private const EXPECTED_HEADER = [
        'zona',
        'grupo_uso',
        'subgrupo',
        'permissao',
        'condicionante_ref',
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
                        "Cabeçalho inesperado em {$csvPath}: esperado zona,grupo_uso,subgrupo,permissao,condicionante_ref,base_legal (Quadro 10 da Lei 9.148/2016).",
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

            if ($data['zona'] === '' || $data['grupo_uso'] === '') {
                $rejected[] = sprintf('linha %d: zona e grupo_uso são obrigatórios', $read);

                continue;
            }

            $permissao = Quadro10Permissao::tryFrom($data['permissao']);

            if ($permissao === null) {
                $rejected[] = sprintf(
                    "zona '%s' / grupo '%s': permissão '%s' desconhecida (esperado permitido|permitido_condicionado|proibido)",
                    $data['zona'],
                    $data['grupo_uso'],
                    $data['permissao'],
                );

                continue;
            }

            $rows[] = [
                'rule_version_id' => $version->getKey(),
                'zona' => $data['zona'],
                'grupo_uso' => $data['grupo_uso'],
                'subgrupo' => $data['subgrupo'],
                'permissao' => $permissao->value,
                'condicionante_ref' => $data['condicionante_ref'] === '' ? null : $data['condicionante_ref'],
                'base_legal' => $data['base_legal'] === '' ? null : $data['base_legal'],
            ];
        }

        $existing = LouosQuadro10Permissao::query()
            ->where('rule_version_id', $version->getKey())
            ->get(['zona', 'grupo_uso', 'subgrupo'])
            ->map(fn (LouosQuadro10Permissao $p): string => $this->chave($p->zona, $p->grupo_uso, (string) $p->subgrupo))
            ->flip();

        $imported = 0;
        $updated = 0;

        foreach ($rows as $row) {
            isset($existing[$this->chave($row['zona'], $row['grupo_uso'], $row['subgrupo'])]) ? $updated++ : $imported++;
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            LouosQuadro10Permissao::query()->upsert(
                $chunk,
                ['rule_version_id', 'zona', 'grupo_uso', 'subgrupo'],
                ['permissao', 'condicionante_ref', 'base_legal'],
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

    private function chave(string $zona, string $grupoUso, string $subgrupo): string
    {
        return $zona.'|'.$grupoUso.'|'.$subgrupo;
    }
}

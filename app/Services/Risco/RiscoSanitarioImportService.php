<?php

namespace App\Services\Risco;

use App\Enums\RiscoSanitario;
use App\Models\RuleVersion;
use App\Models\SanitaryRiskClassification;
use InvalidArgumentException;
use RuntimeException;
use SplFileObject;

/**
 * Import real da classificação de risco SANITÁRIO (planilha unificada CNAE da
 * Vigilância Sanitária) a partir do CSV oficial versionado em
 * database/data/risco/ (HU-019/HU-047/HU-048), espelhando
 * RiscoMunicipalImportService. A dimensão sanitária é SEPARADA da municipal
 * (tabela própria) e a classificação fica ligada a uma versão de regra
 * (rule_version_id) — dado versionado, nunca código.
 *
 * A planilha enumera sub-atividades: a mesma subclasse CNAE pode aparecer em
 * mais de uma linha, às vezes com níveis divergentes. O risco sanitário NUNCA
 * é rebaixado silenciosamente — prevalece o nível mais restritivo (severity) e
 * a divergência vira um aviso auditável (jamais perda silenciosa).
 *
 * SEM condicionantes: o e-mail SEDUR de 21/09/2026 (item 4) retirou do
 * Viabiliza toda validação de condicionantes da VISA — o import grava só a
 * classificação, nunca a pergunta/reclassificação.
 *
 * Idempotente: upsert por (rule_version_id, cnae_code) — sem model events (a
 * auditoria é o log explícito do relatório no seeder).
 */
class RiscoSanitarioImportService
{
    private const EXPECTED_HEADER = [
        'CNAE',
        'Descrição do CNAE',
        'Fator Multiplicador',
        'Risco',
        'Há condicionante?',
        'Condicionante',
        'Pergunta relacionada à condicionante',
        'Pergunta complementar (direcionadora)',
        'Autorizado para Escritório Virtual?',
        'Autorizado para MEI?',
        'Macroárea',
        'Exige RT?',
        'Exige PBA?',
        'Documentação específica por CNAE',
        'Base Legal documentação específica por CNAE',
    ];

    private const MARCADORES_VAZIOS = ['', '−', '-', '–'];

    /**
     * @return array{lidos: int, classificacoes: int, rejeitados: array<int, string>, avisos: array<int, string>, por_nivel: array{baixo: int, medio: int, alto: int}}
     */
    public function import(RuleVersion $version, string $csvPath): array
    {
        $file = new SplFileObject($csvPath, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY);

        $header = null;
        $read = 0;
        $rejected = [];
        $avisos = [];

        /** @var array<string, array{risco: RiscoSanitario, macroarea: ?string, autorizado_escritorio_virtual: bool, autorizado_mei: bool, exige_rt: bool}> $best */
        $best = [];
        /** @var array<string, array<int, string>> $levelsSeen */
        $levelsSeen = [];

        foreach ($file as $line) {
            if ($line === false || $line === [null]) {
                continue;
            }

            if ($header === null) {
                $header = array_map(fn ($value) => trim((string) $value), $line);

                if ($header !== self::EXPECTED_HEADER) {
                    throw new RuntimeException(
                        "Cabeçalho inesperado em {$csvPath}: esperado o layout da planilha unificada CNAE da Vigilância Sanitária (15 colunas, iniciando em CNAE,Descrição do CNAE,...).",
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

            $originalCode = $data['CNAE'];
            $code = preg_replace('/\D/', '', $originalCode);

            if (preg_match('/^\d{7}$/', $code) !== 1) {
                $rejected[] = sprintf("código '%s' inválido (esperado o padrão DDDD-D/SS)", $originalCode);

                continue;
            }

            try {
                $risco = RiscoSanitario::fromVisa($data['Risco']);
            } catch (InvalidArgumentException $e) {
                $rejected[] = sprintf("código '%s': %s", $originalCode, $e->getMessage());

                continue;
            }

            $levelsSeen[$code][] = $risco->value;

            if (! isset($best[$code]) || $risco->severity() > $best[$code]['risco']->severity()) {
                $macroarea = $data['Macroárea'];

                $best[$code] = [
                    'risco' => $risco,
                    'macroarea' => $macroarea !== '' ? $macroarea : null,
                    'autorizado_escritorio_virtual' => $this->iniciaComSim($data['Autorizado para Escritório Virtual?']),
                    'autorizado_mei' => $this->iniciaComSim($data['Autorizado para MEI?']),
                    'exige_rt' => $this->iniciaComSim($data['Exige RT?']),
                ];
            }
        }

        foreach ($levelsSeen as $code => $niveis) {
            $distintos = array_values(array_unique($niveis));

            if (count($niveis) > 1 && count($distintos) > 1) {
                $avisos[] = sprintf(
                    "código '%s': níveis sanitários divergentes na planilha (%s) — mantido o mais restritivo (%s)",
                    $code,
                    implode(', ', $distintos),
                    $best[$code]['risco']->value,
                );
            }
        }

        $porNivel = ['baixo' => 0, 'medio' => 0, 'alto' => 0];

        foreach ($best as $entry) {
            $porNivel[$entry['risco']->value]++;
        }

        $this->upsertClassificacoes($version, $best);

        return [
            'lidos' => $read,
            'classificacoes' => count($best),
            'rejeitados' => $rejected,
            'avisos' => $avisos,
            'por_nivel' => $porNivel,
        ];
    }

    /**
     * @param  array<string, array{risco: RiscoSanitario, macroarea: ?string, autorizado_escritorio_virtual: bool, autorizado_mei: bool, exige_rt: bool}>  $best
     */
    private function upsertClassificacoes(RuleVersion $version, array $best): void
    {
        $rows = [];

        foreach ($best as $code => $entry) {
            $rows[] = [
                'rule_version_id' => $version->getKey(),
                'cnae_code' => $code,
                'risco_sanitario' => $entry['risco']->value,
                'macroarea' => $entry['macroarea'],
                'autorizado_escritorio_virtual' => $entry['autorizado_escritorio_virtual'],
                'autorizado_mei' => $entry['autorizado_mei'],
                'exige_rt' => $entry['exige_rt'],
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            SanitaryRiskClassification::query()->upsert(
                $chunk,
                ['rule_version_id', 'cnae_code'],
                ['risco_sanitario', 'macroarea', 'autorizado_escritorio_virtual', 'autorizado_mei', 'exige_rt'],
            );
        }
    }

    /**
     * Trata como verdadeiro qualquer valor que comece por 'sim' (a planilha usa
     * 'Sim', 'Sim, caso seja Alto Risco', etc.). 'Não'/'' → falso.
     */
    private function iniciaComSim(string $raw): bool
    {
        return str_starts_with(mb_strtolower(trim($raw)), 'sim');
    }
}

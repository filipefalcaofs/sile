<?php

namespace App\Support;

use App\Enums\AnalysisCategory;
use App\Enums\Quadro10Permissao;
use App\Enums\ResultadoViabilidade;
use App\Enums\RiscoMunicipal;
use App\Enums\RiscoSanitario;
use BackedEnum;

/**
 * Catálogo único de rótulos dos enums de negócio exibidos no front. O
 * endpoint /gestao/metadados e a prop Inertia `vocabulario` leem daqui —
 * nenhuma tela deve reescrever Baixo/Médio/Alto ou os vereditos da LOUOS.
 */
class Vocabulario
{
    /**
     * @return array{
     *     risco_municipal: list<array{value: string, label: string}>,
     *     risco_sanitario: list<array{value: string, label: string}>,
     *     analysis_category: list<array{value: string, label: string}>,
     *     resultado_viabilidade: list<array{value: string, label: string}>,
     *     quadro10_permissao: list<array{value: string, label: string}>
     * }
     */
    public static function catalog(): array
    {
        return [
            'risco_municipal' => self::fromEnum(RiscoMunicipal::class),
            'risco_sanitario' => self::fromEnum(RiscoSanitario::class),
            'analysis_category' => self::fromEnum(AnalysisCategory::class),
            'resultado_viabilidade' => self::fromEnum(ResultadoViabilidade::class),
            'quadro10_permissao' => self::fromEnum(Quadro10Permissao::class),
        ];
    }

    /**
     * @return list<string>
     */
    public static function exportFormatos(): array
    {
        $raw = Settings::get(
            'relatorios.export.formatos_habilitados',
            config('sile.relatorios.export.formatos_habilitados', ['csv', 'xlsx', 'pdf']),
        );

        if (! is_array($raw)) {
            return ['csv', 'xlsx', 'pdf'];
        }

        return array_values(array_filter(
            $raw,
            fn (mixed $formato): bool => is_string($formato) && in_array($formato, ['csv', 'xlsx', 'pdf'], true),
        ));
    }

    /**
     * @param  class-string<BackedEnum>  $enum
     * @return list<array{value: string, label: string}>
     */
    private static function fromEnum(string $enum): array
    {
        return array_map(
            fn (BackedEnum $case): array => [
                'value' => (string) $case->value,
                'label' => method_exists($case, 'label') ? $case->label() : (string) $case->value,
            ],
            $enum::cases(),
        );
    }
}

<?php

namespace App\Services\Relatorios\Export\Sources;

use App\Http\Controllers\Gestao\CnaeController;
use App\Models\Cnae;
use App\Services\Relatorios\Export\ReportDefinition;
use App\Services\Relatorios\Export\ReportExporter;
use App\Services\Relatorios\Export\ReportSource;
use App\Services\Relatorios\ReportFilters;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fonte da listagem de CNAEs (HU-011) para o contrato único de exportação
 * (HU-131/RN-009): o index do {@see CnaeController}
 * ganha um branch ?formato= delegando ao {@see ReportExporter}
 * sem rota nova. O conjunto exportado é EXATAMENTE o filtrado da tela (RN-005) —
 * busca (código por prefixo de dígitos OU denominação, case-insensitive),
 * situação e ordenação reproduzidas a partir do BAG do {@see ReportFilters}.
 *
 * Reconstrutível SÓ pelo bag (INVARIANTE do {@see ReportSource}): nenhum estado
 * de filtro no construtor, então o GerarExportacaoJob reconstrói o mesmo recorte
 * no assíncrono via `app(self::class)->definition(ReportFilters::fromArray($bag))`.
 * personalData=false (catálogo público).
 */
final class CnaesReportSource implements ReportSource
{
    /** Colunas ordenáveis aceitas — espelha a whitelist do CnaeController. */
    private const SORTABLE_COLUMNS = ['code', 'description'];

    public function definition(ReportFilters $filtros): ReportDefinition
    {
        $search = (string) ($filtros->get('search') ?? '');
        $active = (string) ($filtros->get('active') ?? '');

        $sort = (string) ($filtros->get('sort') ?? '');
        $sort = in_array($sort, self::SORTABLE_COLUMNS, true) ? $sort : 'code';

        $direction = (string) ($filtros->get('direction') ?? '');
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'asc';

        return new ReportDefinition(
            titulo: 'CNAEs',
            colunas: [
                ['key' => 'code', 'label' => 'Código'],
                ['key' => 'description', 'label' => 'Denominação'],
                ['key' => 'active', 'label' => 'Situação'],
                ['key' => 'class_code', 'label' => 'Classe'],
            ],
            // RN-005: a MESMA query filtrada do CnaeController::index, lida do bag.
            builder: fn (): Builder => Cnae::query()
                ->when($search !== '', function ($query) use ($search): void {
                    $digits = preg_replace('/\D/', '', $search);

                    $query->where(function ($inner) use ($search, $digits): void {
                        if ($digits !== '') {
                            $inner->where('code', 'like', "{$digits}%");
                        }

                        $inner->orWhereLike('description', "%{$search}%", caseSensitive: false);
                    });
                })
                ->when(in_array($active, ['0', '1'], true), fn ($query) => $query->where('active', $active === '1'))
                ->orderBy($sort, $direction)
                ->orderBy('code'),
            mapRow: fn (Cnae $cnae): array => [
                $cnae->formatted_code,
                $cnae->description,
                $cnae->active ? 'Ativo' : 'Inativo',
                $cnae->class_code,
            ],
            filtrosAplicados: $filtros->aplicados(),
            logName: 'cnaes',
            event: 'exporta-cnaes',
            personalData: false,
            arquivoBase: 'cnaes',
        );
    }
}

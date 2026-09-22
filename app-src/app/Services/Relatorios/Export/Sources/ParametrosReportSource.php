<?php

namespace App\Services\Relatorios\Export\Sources;

use App\Http\Controllers\Gestao\ParameterController;
use App\Models\Parameter;
use App\Services\Relatorios\Export\ReportDefinition;
use App\Services\Relatorios\Export\ReportExporter;
use App\Services\Relatorios\Export\ReportSource;
use App\Services\Relatorios\ReportFilters;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fonte do catálogo de parâmetros (HU-014) para o contrato único de exportação
 * (HU-131/RN-009): o index do {@see ParameterController}
 * ganha um branch ?formato= delegando ao {@see ReportExporter}
 * sem rota nova. O catálogo não tem filtros de listagem, então o conjunto
 * exportado é o catálogo inteiro ordenado por grupo/chave (RN-005 — mesma ordem
 * da tela).
 *
 * RN-009 (parametrização): o valor de um parâmetro SENSÍVEL nunca sai em claro —
 * o {@see Parameter} o guarda criptografado; o mapRow emite o marcador
 * `[sensível]` SEM ler o valor (igual à tela, que recebe null para sensível).
 * personalData=false (configuração, não dado pessoal). Reconstrutível só pelo bag.
 */
final class ParametrosReportSource implements ReportSource
{
    public function definition(ReportFilters $filtros): ReportDefinition
    {
        return new ReportDefinition(
            titulo: 'Parâmetros',
            colunas: [
                ['key' => 'group', 'label' => 'Grupo'],
                ['key' => 'key', 'label' => 'Chave'],
                ['key' => 'type', 'label' => 'Tipo'],
                ['key' => 'description', 'label' => 'Descrição'],
                ['key' => 'value', 'label' => 'Valor'],
                ['key' => 'default_value', 'label' => 'Padrão'],
                ['key' => 'updated_at', 'label' => 'Atualizado em'],
            ],
            // RN-005: a MESMA ordenação por grupo/chave da tela do catálogo.
            builder: fn (): Builder => Parameter::query()
                ->orderBy('group')
                ->orderBy('key'),
            mapRow: fn (Parameter $parameter): array => [
                $parameter->group,
                $parameter->key,
                $parameter->type,
                $parameter->description,
                // RN-009: sensível nunca em claro — marcador, sem ler o valor.
                $parameter->sensitive ? '[sensível]' : $parameter->value,
                $parameter->default_value,
                $parameter->updated_at?->toIso8601String(),
            ],
            filtrosAplicados: $filtros->aplicados(),
            logName: 'parametros',
            event: 'exporta-parametros',
            personalData: false,
            arquivoBase: 'parametros',
        );
    }
}

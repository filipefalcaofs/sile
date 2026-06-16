<?php

namespace App\Http\Requests\Gestao;

use App\Services\Relatorios\ReportFilters;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtros comuns das telas de relatório (HU-122..131): o vocabulário que os
 * indicadores route-free consomem (período/setor/bairro/CNAE/categoria/analista).
 * A autorização é o middleware permission:consultar-relatorios da rota — aqui só
 * a validação. {@see toReportFilters()} normaliza os filtros no BAG serializável
 * que alimenta os serviços E o contrato de exportação (RN-005): o whitelisting
 * fino fica em cada ReportSource; o request só garante tipos válidos.
 */
class RelatorioFiltersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'data_de' => ['nullable', 'date'],
            'data_ate' => ['nullable', 'date'],
            'setor' => ['nullable', 'integer'],
            'analista' => ['nullable', 'integer'],
            'bairro' => ['nullable', 'string'],
            'cnae' => ['nullable', 'string'],
            'categoria' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'data_de' => 'data inicial',
            'data_ate' => 'data final',
            'setor' => 'setor',
            'analista' => 'analista',
            'bairro' => 'bairro',
            'cnae' => 'CNAE',
            'categoria' => 'categoria',
        ];
    }

    /**
     * Converte os filtros validados no BAG serializável do contrato de
     * exportação. O round-trip do bag é a fonte única do filtro no síncrono e no
     * assíncrono (RN-005).
     */
    public function toReportFilters(): ReportFilters
    {
        return ReportFilters::fromArray($this->validated());
    }
}

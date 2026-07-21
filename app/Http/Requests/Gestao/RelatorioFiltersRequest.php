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
            // Relatório sede × abrigados de escritório virtual (Plano R1): recorte
            // por nº do produto TVL da sede OU pela inscrição imobiliária. Precisa
            // entrar no validated() para viajar no bag (RN-005 no assíncrono).
            'sede' => ['nullable', 'string'],
            'inscricao' => ['nullable', 'string'],
            // Tela R2 — Tempo de Emissão de TVL (SAPS): serviço (service_type_id),
            // resultado (deferida/indeferida) e tipo (viabilidade/revisao). A
            // Revisão via REDESIM não é homologada — o valor é aceito só para a
            // degradação honesta (o serviço nunca simula dados de revisão).
            'servico' => ['nullable', 'integer'],
            'resultado' => ['nullable', 'string', 'in:deferida,indeferida'],
            'tipo' => ['nullable', 'string', 'in:viabilidade,revisao'],
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
            'sede' => 'sede',
            'inscricao' => 'inscrição imobiliária',
            'servico' => 'serviço',
            'resultado' => 'resultado',
            'tipo' => 'tipo',
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

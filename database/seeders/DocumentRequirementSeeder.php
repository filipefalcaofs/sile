<?php

namespace Database\Seeders;

use App\Models\DocumentRequirement;
use App\Services\Solicitacao\DocumentRequirementResolver;
use Illuminate\Database\Seeder;

/**
 * Requisitos documentais BASE da solicitação (HU-067) com CÓDIGOS ESTÁVEIS — são
 * os condicionais que o DocumentRequirementResolver (08-08) referencia por code:
 * foto da fachada (sempre obrigatória) e termo de concessão de uso (exigido em
 * área pública). O contrato de locação entra como documento comum OPCIONAL.
 *
 * A obrigatoriedade POR CNAE (pivot cnae_document_requirement) nasce VAZIA: a
 * planilha oficial da SEDUR ainda não chegou. Degradação honesta — o resolver
 * valida os obrigatórios-base mesmo sem a carga por CNAE; quando a planilha
 * chegar, muda a carga, nunca a lógica.
 *
 * Idempotente: firstOrCreate por code preserva os ajustes do administrador
 * (nome, obrigatoriedade, ativação) em cada re-seed.
 */
class DocumentRequirementSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::catalog() as $requirement) {
            DocumentRequirement::firstOrCreate(
                ['code' => $requirement['code']],
                [
                    'name' => $requirement['name'],
                    'description' => $requirement['description'] ?? null,
                    'required' => $requirement['required'],
                    'active' => true,
                ],
            );
        }
    }

    /**
     * @return list<array{code: string, name: string, required: bool, description?: string}>
     */
    private static function catalog(): array
    {
        return [
            [
                'code' => DocumentRequirementResolver::CODE_FACHADA,
                'name' => 'Foto da fachada do imóvel',
                'required' => true,
                'description' => 'Fotografia atual da fachada do imóvel onde a atividade será exercida (sempre obrigatória).',
            ],
            [
                'code' => DocumentRequirementResolver::CODE_CONCESSAO,
                'name' => 'Termo de concessão de uso',
                'required' => true,
                'description' => 'Termo de concessão de uso, obrigatório quando o imóvel está em área pública (is_public_area).',
            ],
            [
                'code' => 'contrato-locacao',
                'name' => 'Contrato de locação',
                'required' => false,
                'description' => 'Contrato de locação do imóvel, quando aplicável (documento comum, opcional).',
            ],
        ];
    }
}

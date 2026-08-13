<?php

namespace Database\Seeders;

use App\Models\ViabilityServiceType;
use Illuminate\Database\Seeder;

/**
 * Catálogo MÍNIMO de tipos de serviço da solicitação de viabilidade (HU-061
 * RN-005). São os tipos conhecidos hoje, com códigos ESTÁVEIS — a lista oficial
 * completa é pendência SEDUR e substitui/estende este seed SEM deploy (pelo CRUD
 * administrável do 08-03). Catálogo administrável, NÃO regra de negócio: nada
 * aqui decide o fluxo, só popula a seleção do requerente.
 *
 * Idempotente: firstOrCreate por code preserva os ajustes do administrador
 * (nome, dica de fluxo, ativação) em cada re-seed.
 */
class ViabilityServiceTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::catalog() as $type) {
            ViabilityServiceType::firstOrCreate(
                ['code' => $type['code']],
                [
                    'name' => $type['name'],
                    'flow_hint' => $type['flow_hint'] ?? null,
                    'active' => true,
                ],
            );
        }
    }

    /**
     * @return list<array{code: string, name: string, flow_hint?: string}>
     */
    private static function catalog(): array
    {
        return [
            ['code' => 'viabilidade-1-estabelecimento', 'name' => 'Viabilidade — primeiro estabelecimento'],
            ['code' => 'alteracao-endereco', 'name' => 'Alteração de endereço'],
            ['code' => 'alteracao-atividade', 'name' => 'Alteração de atividade'],
            ['code' => 'renovacao-tvl', 'name' => 'Renovação de TVL'],
        ];
    }
}

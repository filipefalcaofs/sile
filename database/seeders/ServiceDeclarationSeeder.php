<?php

namespace Database\Seeders;

use App\Models\ServiceDeclaration;
use App\Models\ViabilityServiceType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Declarações obrigatórias da renovação de TVL. Idempotente por tipo+versão.
 * A gestão publica versões novas; a anterior recebe valid_to pela tela.
 */
class ServiceDeclarationSeeder extends Seeder
{
    public function run(): void
    {
        // Em instalação fresh, o CatalogoOficialSeeder roda dentro de uma
        // migração anterior à criação desta tabela; o seeder volta a rodar
        // depois, quando o schema completo existir.
        if (! Schema::hasTable('service_declarations')) {
            return;
        }

        $tipo = ViabilityServiceType::query()->where('code', 'renovacao-tvl')->first();

        if ($tipo === null) {
            return;
        }

        $declaracoes = [
            1 => 'Declaro que o endereço e as atividades da viabilidade permanecem os mesmos e que não serão alterados nesta renovação.',
            2 => 'Declaro que os condicionantes do TVL provisório estão atendidos no momento da renovação.',
        ];

        foreach ($declaracoes as $version => $body) {
            ServiceDeclaration::query()->firstOrCreate(
                [
                    'service_type_id' => $tipo->id,
                    'version' => $version,
                ],
                [
                    'body' => $body,
                    'required' => true,
                    'valid_from' => now(),
                    'valid_to' => null,
                ],
            );
        }
    }
}

<?php

use Database\Seeders\ServiceDeclarationSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Em produção o ServiceDeclarationSeeder nunca rodou e a etapa Declarações da
 * renovação saiu vazia. Só cadastra quando o tipo ainda não tem nenhuma
 * declaração, para não ressuscitar versões que a gestão expirou.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_declarations') || ! Schema::hasTable('viability_service_types')) {
            return;
        }

        $tipoId = DB::table('viability_service_types')->where('code', 'renovacao-tvl')->value('id');

        if ($tipoId === null || DB::table('service_declarations')->where('service_type_id', $tipoId)->exists()) {
            return;
        }

        (new ServiceDeclarationSeeder)->run();
    }

    public function down(): void
    {
        // Dados cadastrados pela gestão não são removidos.
    }
};

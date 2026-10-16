<?php

use App\Support\Migration\IdentidadeSeparada;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        IdentidadeSeparada::retargetarRequerentes();
        IdentidadeSeparada::anularReferenciasDeNaoCidadaos('viability_queries', 'user_id');
        IdentidadeSeparada::retargetar('viability_queries', 'user_id', 'cidadao_id', true, 'set null');
        IdentidadeSeparada::retargetar('service_declaration_acceptances', 'user_id', 'cidadao_id', false, 'cascade');
    }

    public function down(): void
    {
        // Reversão consolidada na migration final (Tarefa 12).
    }
};

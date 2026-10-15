<?php

use App\Support\Migration\IdentidadeSeparada;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        IdentidadeSeparada::retargetar('viability_requests', 'requester_user_id', 'requester_cidadao_id', false, 'restrict');
        IdentidadeSeparada::retargetar('viability_queries', 'user_id', 'cidadao_id', true, 'set null');
        IdentidadeSeparada::retargetar('service_declaration_acceptances', 'user_id', 'cidadao_id', false, 'cascade');
    }

    public function down(): void
    {
        // Reversão consolidada na migration final (Tarefa 12).
    }
};

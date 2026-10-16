<?php

use App\Support\Migration\IdentidadeSeparada;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** Ordem obrigatória: o tipo é decidido pelas contas de cidadão (`consultaContasDeCidadao`) antes de qualquer retarget. */
    public function up(): void
    {
        IdentidadeSeparada::paraMorph('viability_requests', 'created_by_user_id', 'created_by');
        IdentidadeSeparada::paraMorph('viability_requests', 'cancelled_by_user_id', 'cancelled_by');
        IdentidadeSeparada::paraMorph('viability_request_documents', 'uploaded_by_user_id', 'uploaded_by');
        IdentidadeSeparada::paraMorph('viability_request_transitions', 'actor_user_id', 'actor');
        IdentidadeSeparada::paraMorph('access_logs', 'user_id', 'actor');
        IdentidadeSeparada::paraMorph('legal_term_acceptances', 'user_id', 'accepted_by');
        IdentidadeSeparada::paraMorph('communications', 'recipient_user_id', 'recipient');
    }

    public function down(): void
    {
        // Reversão consolidada na migration final.
    }
};

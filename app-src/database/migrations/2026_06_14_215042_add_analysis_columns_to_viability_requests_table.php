<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Colunas da análise técnica (EP10) em viability_requests — migration ADITIVA
     * (só adiciona, não toca nas existentes; alterar coluna exigiria repetir
     * todos os atributos). Ficam FORA do fillable do model (como protocoled_at/
     * bap_*): são escritas por serviços dedicados (distribuição HU-080/081, SLA
     * HU-144), nunca pelo cidadão.
     *
     * - sector_id/assigned_user_id: a caixa e o analista responsável (índices p/
     *   a fila e a consulta HU-082). assigned_at = quando assumiu.
     * - analysis_category (expresso/semi_expresso): categoria de consulta (índice).
     * - in_fine_mesh: flag de malha fina ORTOGONAL ao status (HU-136 — índice).
     * - analysis_stage/_started_at: etapa atual do SLA (distribuicao/analise).
     * - analysis_due_at: vencimento do SLA (índice — base da fila ordenada HU-144).
     */
    public function up(): void
    {
        Schema::table('viability_requests', function (Blueprint $table) {
            $table->foreignId('sector_id')->nullable()->index()->constrained('sectors')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->string('analysis_category')->nullable()->index();
            $table->boolean('in_fine_mesh')->default(false)->index();
            $table->string('analysis_stage')->nullable();
            $table->timestamp('analysis_stage_started_at')->nullable();
            $table->timestamp('analysis_due_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('viability_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sector_id');
            $table->dropConstrainedForeignId('assigned_user_id');
            $table->dropColumn([
                'assigned_at',
                'analysis_category',
                'in_fine_mesh',
                'analysis_stage',
                'analysis_stage_started_at',
                'analysis_due_at',
            ]);
        });
    }
};

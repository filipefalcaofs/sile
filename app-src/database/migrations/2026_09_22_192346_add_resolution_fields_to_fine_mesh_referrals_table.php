<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Baixa da malha fina (Caixa de Malha Fina): quem baixou e a observação
     * opcional da conclusão. resolved_at já existia (HU-136); estas colunas
     * tornam a aba Concluídas da caixa auto-suficiente e a rastreabilidade
     * explícita no registro (a auditoria segue no activity_log).
     */
    public function up(): void
    {
        Schema::table('fine_mesh_referrals', function (Blueprint $table) {
            $table->foreignId('resolved_by_user_id')->nullable()->after('resolved_at')->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable()->after('resolved_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('fine_mesh_referrals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resolved_by_user_id');
            $table->dropColumn('resolution_note');
        });
    }
};

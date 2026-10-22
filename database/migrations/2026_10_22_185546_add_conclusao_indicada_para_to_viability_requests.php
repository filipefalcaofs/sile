<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quem o analista indicou ao encaminhar o processo para a chefia. É só uma
     * sugestão para o Apoio: o processo continua sem destinatário na aba Para
     * conclusão, e a distribuição é do Apoio.
     */
    public function up(): void
    {
        Schema::table('viability_requests', function (Blueprint $table): void {
            $table->foreignId('conclusao_indicada_para_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('viability_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('conclusao_indicada_para_user_id');
        });
    }
};

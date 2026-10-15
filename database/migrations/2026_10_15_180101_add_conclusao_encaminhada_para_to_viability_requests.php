<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Destinatário opcional informado pelo analista ao enviar o processo para
     * conclusão. Fica separado de assigned_user_id, que continua sendo quem
     * analisou.
     */
    public function up(): void
    {
        Schema::table('viability_requests', function (Blueprint $table): void {
            $table->foreignId('conclusao_encaminhada_para_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('viability_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('conclusao_encaminhada_para_user_id');
        });
    }
};

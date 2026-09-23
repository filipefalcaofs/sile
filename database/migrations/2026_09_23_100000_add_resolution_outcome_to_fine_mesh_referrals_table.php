<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Resultado escolhido na baixa da malha fina (mesma lista da conclusão
     * de análise). Null nas baixas anteriores a este campo.
     */
    public function up(): void
    {
        Schema::table('fine_mesh_referrals', function (Blueprint $table) {
            $table->string('resolution_outcome')->nullable()->after('resolution_note');
        });
    }

    public function down(): void
    {
        Schema::table('fine_mesh_referrals', function (Blueprint $table) {
            $table->dropColumn('resolution_outcome');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Liga a linha de TLL à versão de regra do exercício (domínio tll_valores).
     * Nullable: cadastro manual pelo CRUD continua sem versão; nullOnDelete
     * preserva a linha se a versão for removida (histórico do valor).
     */
    public function up(): void
    {
        Schema::table('tll_valores', function (Blueprint $table) {
            $table->foreignId('rule_version_id')->nullable()->constrained('rule_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tll_valores', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rule_version_id');
        });
    }
};

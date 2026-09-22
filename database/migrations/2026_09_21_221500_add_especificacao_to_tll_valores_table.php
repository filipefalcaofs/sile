<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A tabela oficial TLL 2026 reusa o código 6.00 em duas linhas (ISENTA e
     * residual), distintas pela especificação e pelo hash SEFAZ. A unique
     * passa a ser (codigo_tll, exercicio, especificacao).
     */
    public function up(): void
    {
        Schema::table('tll_valores', function (Blueprint $table) {
            $table->dropUnique(['codigo_tll', 'exercicio']);
            $table->string('especificacao')->default('');
            $table->unique(['codigo_tll', 'exercicio', 'especificacao']);
        });
    }

    public function down(): void
    {
        Schema::table('tll_valores', function (Blueprint $table) {
            $table->dropUnique(['codigo_tll', 'exercicio', 'especificacao']);
            $table->dropColumn('especificacao');
            $table->unique(['codigo_tll', 'exercicio']);
        });
    }
};

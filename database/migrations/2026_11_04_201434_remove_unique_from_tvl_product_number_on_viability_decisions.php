<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alteração sem mudança de endereço mantém o número da viabilidade anterior
     * (SEDUR, 04/11), então o mesmo número passa a existir em mais de uma
     * decisão. O índice simples continua servindo às buscas por número.
     */
    public function up(): void
    {
        Schema::table('viability_decisions', function (Blueprint $table) {
            $table->dropUnique(['tvl_product_number']);
            $table->index('tvl_product_number');
        });
    }

    public function down(): void
    {
        Schema::table('viability_decisions', function (Blueprint $table) {
            $table->dropIndex(['tvl_product_number']);
            $table->unique('tvl_product_number');
        });
    }
};

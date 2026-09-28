<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tipo e data de validade do TVL na decisão. A renovação não gera número
     * novo: o analista só define se a validade liberada é provisória ou
     * definitiva e até quando vale.
     */
    public function up(): void
    {
        Schema::table('viability_decisions', function (Blueprint $table): void {
            $table->string('tvl_kind')->nullable()->after('tvl_product_number');
            $table->date('tvl_valid_until')->nullable()->after('tvl_kind');
        });
    }

    public function down(): void
    {
        Schema::table('viability_decisions', function (Blueprint $table): void {
            $table->dropColumn(['tvl_kind', 'tvl_valid_until']);
        });
    }
};

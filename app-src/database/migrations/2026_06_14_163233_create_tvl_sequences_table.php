<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contador transacional do número de produto TVL, um por ano (year unique).
     * Espelha protocol_sequences: o TvlNumberGenerator incrementa last_number
     * sob lockForUpdate dentro da transação da decisão — concorrência-segura
     * (prova real em @group postgis; em SQLite o lock é no-op e a unicidade é
     * lógica, com unique(tvl_product_number) como defesa final).
     */
    public function up(): void
    {
        Schema::create('tvl_sequences', function (Blueprint $table) {
            $table->id();
            $table->integer('year')->unique();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tvl_sequences');
    }
};

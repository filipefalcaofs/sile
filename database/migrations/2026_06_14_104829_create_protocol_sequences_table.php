<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contador transacional do número de protocolo, um por ano (year unique).
     * O ProtocolNumberGenerator incrementa last_number sob lockForUpdate dentro
     * da transação de protocolo — concorrência-segura (prova real em @group
     * postgis; em SQLite o lock é no-op e a unicidade é lógica).
     */
    public function up(): void
    {
        Schema::create('protocol_sequences', function (Blueprint $table) {
            $table->id();
            $table->integer('year')->unique();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('protocol_sequences');
    }
};

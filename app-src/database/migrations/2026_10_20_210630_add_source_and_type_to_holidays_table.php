<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Origem e tipo do feriado. source = 'api' marca o que veio da sincronização
     * com a FeriadosAPI (nulo = cadastro manual/seeder); type guarda
     * nacional/estadual/municipal para que só o feriado municipal caracterize o
     * calendário oficial de Salvador.
     */
    public function up(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            $table->string('source', 20)->nullable()->after('active');
            $table->string('type', 20)->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            $table->dropColumn(['source', 'type']);
        });
    }
};

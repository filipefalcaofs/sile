<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration ADITIVA sobre a espinha activity_log (Fase 12, HU-100/HU-102).
 *
 * Não recria nem altera colunas existentes: apenas adiciona a coluna
 * personal_data (marca de acesso a dado pessoal — LGPD) e os índices que a
 * consulta unificada da trilha precisa para paginar por período/fonte/ação em
 * escala. log_name single, result e rules_version já são indexados na criação;
 * aqui adicionamos created_at, o composto (log_name, created_at), event e
 * personal_data, sem duplicar os existentes.
 *
 * Sem ->after() (específico de MySQL; a posição da coluna é irrelevante e o
 * banco de teste é SQLite). No down() os índices caem antes da coluna para o
 * drop ser seguro em todos os drivers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->boolean('personal_data')->nullable();

            $table->index('created_at');
            $table->index(['log_name', 'created_at']);
            $table->index('event');
            $table->index('personal_data');
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['log_name', 'created_at']);
            $table->dropIndex(['event']);
            $table->dropIndex(['personal_data']);

            $table->dropColumn('personal_data');
        });
    }
};

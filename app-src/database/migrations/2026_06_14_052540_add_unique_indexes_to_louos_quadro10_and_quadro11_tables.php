<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Índices únicos que habilitam o upsert idempotente dos imports dos Quadros
     * 10 e 11/11A (o ON CONFLICT precisa de alvo único em SQLite/PostgreSQL). O
     * 05-01 criou só os índices de leitura. As colunas anuláveis do alvo
     * (subgrupo no Quadro 10, grupo_uso no Quadro 11) são gravadas como '' pelo
     * import — NULL seria tratado como distinto e quebraria o re-import.
     */
    public function up(): void
    {
        Schema::table('louos_quadro10_permissoes', function (Blueprint $table) {
            $table->unique(['rule_version_id', 'zona', 'grupo_uso', 'subgrupo']);
        });

        Schema::table('louos_quadro11_condicoes_via', function (Blueprint $table) {
            $table->unique(['rule_version_id', 'classe_via', 'grupo_uso']);
        });
    }

    public function down(): void
    {
        Schema::table('louos_quadro10_permissoes', function (Blueprint $table) {
            $table->dropUnique(['rule_version_id', 'zona', 'grupo_uso', 'subgrupo']);
        });

        Schema::table('louos_quadro11_condicoes_via', function (Blueprint $table) {
            $table->dropUnique(['rule_version_id', 'classe_via', 'grupo_uso']);
        });
    }
};

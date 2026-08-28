<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Duas listas de atividade em vez de uma (pacote normativo SEDUR 20.08.26):
 * Anexo A do Decreto 35.062/2021 vale para a SEDE, Anexo B para o ABRIGADO.
 * O mesmo CNAE pode constar dos dois, entao a unicidade passa a incluir o
 * anexo. As linhas ja carregadas sao do Anexo B (Lista EV original).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('virtual_office_activity_cnaes', function (Blueprint $table): void {
            $table->string('anexo', 1)->default('B')->after('rule_version_id');
        });

        Schema::table('virtual_office_activity_cnaes', function (Blueprint $table): void {
            $table->dropUnique(['rule_version_id', 'cnae_code']);
            $table->unique(['rule_version_id', 'anexo', 'cnae_code']);
        });
    }

    public function down(): void
    {
        Schema::table('virtual_office_activity_cnaes', function (Blueprint $table): void {
            $table->dropUnique(['rule_version_id', 'anexo', 'cnae_code']);
            $table->unique(['rule_version_id', 'cnae_code']);
            $table->dropColumn('anexo');
        });
    }
};

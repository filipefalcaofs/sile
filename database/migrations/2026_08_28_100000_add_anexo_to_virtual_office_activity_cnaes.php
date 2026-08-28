<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

    /**
     * Como o Anexo A e subconjunto do Anexo B, apos o seeder existem pares
     * (rule_version_id, 'A', cnae_code) e (rule_version_id, 'B', cnae_code)
     * com o mesmo cnae_code. Recriar unique(['rule_version_id','cnae_code'])
     * antes de remover essas linhas violaria a unicidade. Por isso: dropa o
     * unique novo, remove as linhas do Anexo A (elas so existem por causa
     * desta migration — o estado anterior tinha apenas o Anexo B, entao
     * remove-las e seguro e restaura o estado pre-migration), dropa a
     * coluna anexo e so entao recria o unique antigo.
     */
    public function down(): void
    {
        Schema::table('virtual_office_activity_cnaes', function (Blueprint $table): void {
            $table->dropUnique(['rule_version_id', 'anexo', 'cnae_code']);
        });

        DB::table('virtual_office_activity_cnaes')->where('anexo', 'A')->delete();

        Schema::table('virtual_office_activity_cnaes', function (Blueprint $table): void {
            $table->dropColumn('anexo');
        });

        Schema::table('virtual_office_activity_cnaes', function (Blueprint $table): void {
            $table->unique(['rule_version_id', 'cnae_code']);
        });
    }
};

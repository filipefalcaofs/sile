<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Classe do source que gerou a exportação: com ela o download refaz o
     * arquivo quando o contêiner que atende o link não tem o que o worker
     * gravou no disco local dele.
     */
    public function up(): void
    {
        Schema::table('export_files', function (Blueprint $table) {
            $table->string('source')->nullable()->after('format');
        });
    }

    public function down(): void
    {
        Schema::table('export_files', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};

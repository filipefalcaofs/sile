<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O Quadro 11 não existe na publicação oficial da LOUOS. Remove versões e
     * linhas tipadas do domínio fantasma louos_quadro11. O Quadro 11A permanece.
     */
    public function up(): void
    {
        $ids = DB::table('rule_versions')
            ->where('domain', 'louos_quadro11')
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        if (Schema::hasTable('louos_quadro11_condicoes_via')) {
            DB::table('louos_quadro11_condicoes_via')
                ->whereIn('rule_version_id', $ids)
                ->delete();
        }

        DB::table('rule_versions')->whereIn('id', $ids)->delete();
    }

    public function down(): void
    {
        // Domínio removido de propósito — não recria o Quadro 11.
    }
};

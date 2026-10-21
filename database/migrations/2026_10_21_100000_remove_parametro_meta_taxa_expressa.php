<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $ids = DB::table('parameters')->where('key', 'relatorios.expresso.meta_taxa')->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('parameter_proposals')->whereIn('parameter_id', $ids)->delete();
        DB::table('parameters')->whereIn('id', $ids)->delete();
    }

    public function down(): void
    {
        // A taxa expressa depende do enquadramento dos CNAEs que a JUCEB envia; não há meta a restaurar.
    }
};

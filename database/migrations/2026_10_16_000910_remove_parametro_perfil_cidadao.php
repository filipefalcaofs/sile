<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $ids = DB::table('parameters')->where('key', 'portal.perfil_cidadao')->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('parameter_proposals')->whereIn('parameter_id', $ids)->delete();
        DB::table('parameters')->whereIn('id', $ids)->delete();
    }

    public function down(): void
    {
        // O papel `cidadao` deixou de existir: o cidadão é a própria tabela `cidadaos`.
        // O re-seed não recoloca o parâmetro.
    }
};

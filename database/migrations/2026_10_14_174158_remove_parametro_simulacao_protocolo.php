<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $ids = DB::table('parameters')->where('key', 'features.simulacao_protocolo')->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('parameter_proposals')->whereIn('parameter_id', $ids)->delete();
        DB::table('parameters')->whereIn('id', $ids)->delete();
    }

    public function down(): void
    {
        // O simulador REGIN foi retirado; a flag não volta ao catálogo.
    }
};

<?php

use App\Support\Migration\IdentidadeSeparada;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        IdentidadeSeparada::copiarContasDeCidadao();
    }

    public function down(): void
    {
        DB::table('cidadaos')->delete();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Separa "aparece na ficha de vistoria" de "o REGIN reconhece": o catálogo casa
 * o código do tipo com o texto que o REGIN envia, então um tipo só da vistoria
 * (Shopping, Box...) passaria a decidir processo no expresso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_types', function (Blueprint $table) {
            $table->boolean('regin_reconhece')->default(true)->after('drives_rule');
        });
    }

    public function down(): void
    {
        Schema::table('property_types', function (Blueprint $table) {
            $table->dropColumn('regin_reconhece');
        });
    }
};

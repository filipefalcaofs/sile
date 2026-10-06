<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treatment_enquadramentos', function (Blueprint $table) {
            $table->json('metadados')->nullable()->after('vagas');
        });
    }

    public function down(): void
    {
        Schema::table('treatment_enquadramentos', function (Blueprint $table) {
            $table->dropColumn('metadados');
        });
    }
};

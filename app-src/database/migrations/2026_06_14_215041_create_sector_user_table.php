<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vínculo N:N analista↔setor (HU-138 RN-005) — administrável pelo gestor. O
     * unique(sector_id,user_id) impede duplicar o mesmo analista num setor. Em
     * cascade nos dois lados: remover o setor ou o usuário desfaz o vínculo (o
     * histórico das atribuições vive na auditoria/viability_requests, não aqui).
     */
    public function up(): void
    {
        Schema::create('sector_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sector_id')->constrained('sectors')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unique(['sector_id', 'user_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sector_user');
    }
};

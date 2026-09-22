<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabela de CNAEs (HU-011): código em dígitos (unique, não PK — FKs
     * futuras apontam para id), hierarquia oficial desnormalizada e flag
     * active para desativação lógica. Pronta para receber as dimensões de
     * risco/condicionantes da Fase 6 sem retrabalho estrutural.
     */
    public function up(): void
    {
        Schema::create('cnaes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 7)->unique();
            $table->string('description');
            $table->string('section_code', 1);
            $table->string('section_description');
            $table->string('division_code', 2);
            $table->string('division_description');
            $table->string('group_code', 5);
            $table->string('group_description');
            $table->string('class_code', 7);
            $table->string('class_description');
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cnaes');
    }
};

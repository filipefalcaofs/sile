<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trava de inscrição imobiliária pela SEDE ativa (RN-EV-03). Enquanto houver um
 * lock ativo para uma inscrição, ela está vinculada a uma sede de escritório
 * virtual. O M3 (desvinculação) desativa o lock; o M2 (abrigado) o consulta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('virtual_office_inscription_locks', function (Blueprint $table): void {
            $table->id();
            $table->string('property_registration');
            $table->foreignId('sede_viability_request_id')->constrained('viability_requests')->cascadeOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamp('locked_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->index(['property_registration', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('virtual_office_inscription_locks');
    }
};

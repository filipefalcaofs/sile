<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aceite versionado: qual texto, qual versão, quem e quando.
     */
    public function up(): void
    {
        Schema::create('service_declaration_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_declaration_id')->constrained('service_declarations')->cascadeOnDelete();
            $table->foreignId('viability_request_id')->constrained('viability_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->timestamp('accepted_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_declaration_acceptances');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Histórico de transições de estado (FONTE da timeline HU-069 e ganchos
     * EP09/EP10). Cada transição da ViabilityRequestStateMachine grava uma
     * linha (from/to/reason/public_label/actor) — a timeline ordena por
     * created_at. public_label é o rótulo amigável ao cidadão (RN-004).
     */
    public function up(): void
    {
        Schema::create('viability_request_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('viability_request_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('reason')->nullable();
            $table->string('public_label')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('viability_request_transitions');
    }
};

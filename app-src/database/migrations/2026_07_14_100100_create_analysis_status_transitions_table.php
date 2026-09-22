<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Timeline INTERNA do eixo operacional (espelha viability_request_transitions,
 * sem public_label — não é a timeline do cidadão). Escrita pela
 * AnalysisStatusStateMachine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_status_transitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('viability_request_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('reason')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['viability_request_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_status_transitions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Envelope da JUCEB sem solicitante grava requester_user_id nulo, em vez de
 * apontar para um usuário qualquer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viability_requests', function (Blueprint $table): void {
            $table->unsignedBigInteger('requester_user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('viability_requests', function (Blueprint $table): void {
            $table->unsignedBigInteger('requester_user_id')->nullable(false)->change();
        });
    }
};

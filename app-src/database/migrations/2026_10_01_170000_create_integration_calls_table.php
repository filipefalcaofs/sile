<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trilha das integrações HTTP: o que terceiros chamam na nossa API e o que
 * o Viabiliza chama fora. Credencial não entra em claro — o gravador redige.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_calls', function (Blueprint $table): void {
            $table->id();
            $table->string('direction', 16);
            $table->string('integration', 120);
            $table->string('method', 16);
            $table->text('url');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('outcome', 16);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('request_headers')->nullable();
            $table->longText('request_body')->nullable();
            $table->json('response_headers')->nullable();
            $table->longText('response_body')->nullable();
            $table->text('error_message')->nullable();
            $table->boolean('truncated')->default(false);
            $table->string('correlation_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('created_at');
            $table->index(['integration', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_calls');
    }
};

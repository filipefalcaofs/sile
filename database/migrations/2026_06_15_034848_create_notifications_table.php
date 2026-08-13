<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabela padrão do canal `database` NATIVO de Notifications (HU-090) — base
     * da central in-app. Cada notificação enfileira uma linha morph por usuário
     * (notifiable) com `data` (payload) e `read_at` (lida/não-lida via
     * unreadNotifications/markAsRead). Estrutura padrão do Laravel — o histórico
     * por PROCESSO é o ledger dedicado `communications`, não esta tabela.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};

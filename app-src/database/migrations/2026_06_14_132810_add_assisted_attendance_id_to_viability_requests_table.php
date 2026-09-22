<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dimensão "balcão" para relatórios (HU-150 RN-005): a solicitação aberta
     * durante um atendimento presencial carrega o vínculo de atendimento. A
     * origem permanece `portal` (é um fluxo direto do portal operado "em nome
     * de"); o que distingue o balcão é a presença do assisted_attendance_id.
     * nullOnDelete preserva a solicitação se o registro de atendimento sumir.
     */
    public function up(): void
    {
        Schema::table('viability_requests', function (Blueprint $table) {
            $table->foreignId('assisted_attendance_id')
                ->nullable()
                ->after('created_by_user_id')
                ->constrained('assisted_attendances')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('viability_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assisted_attendance_id');
        });
    }
};

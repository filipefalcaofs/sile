<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos do cancelamento de convite (relatório SEDUR 2026-07-09): ao cancelar,
 * o analista registra um parecer (motivo). Nullable — só o convite cancelado usa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_pendencies', function (Blueprint $table): void {
            $table->text('parecer')->nullable()->after('response');
            $table->timestamp('cancelled_at')->nullable()->after('parecer');
            $table->foreignId('cancelled_by_user_id')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('analysis_pendencies', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cancelled_by_user_id');
            $table->dropColumn(['parecer', 'cancelled_at']);
        });
    }
};

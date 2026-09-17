<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regin_recebimentos', function (Blueprint $table): void {
            $table->foreignId('viability_request_id')
                ->nullable()
                ->after('envelope')
                ->constrained('viability_requests')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('regin_recebimentos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('viability_request_id');
        });
    }
};

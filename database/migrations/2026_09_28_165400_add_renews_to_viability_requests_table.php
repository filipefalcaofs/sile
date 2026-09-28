<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Amarra a renovação ao TVL de origem. Alimenta "já há renovação em
     * análise" e "renovada há menos de N meses" sem gerar número novo.
     */
    public function up(): void
    {
        Schema::table('viability_requests', function (Blueprint $table): void {
            $table->foreignId('renews_viability_request_id')
                ->nullable()
                ->after('service_type_id')
                ->constrained('viability_requests')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('viability_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('renews_viability_request_id');
        });
    }
};

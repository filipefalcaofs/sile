<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Amarra a renovação à TVL provisória do sistema legado: o número público
     * (TVL_NUM_TVL_ID) e a foto dos dados lidos no momento do protocolo.
     */
    public function up(): void
    {
        Schema::table('viability_requests', function (Blueprint $table): void {
            $table->unsignedBigInteger('renews_legacy_tvl_number')
                ->nullable()
                ->after('renews_viability_request_id')
                ->index();
            $table->json('legacy_tvl_snapshot')
                ->nullable()
                ->after('renews_legacy_tvl_number');
        });
    }

    public function down(): void
    {
        Schema::table('viability_requests', function (Blueprint $table): void {
            $table->dropIndex(['renews_legacy_tvl_number']);
            $table->dropColumn(['renews_legacy_tvl_number', 'legacy_tvl_snapshot']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Procedência e histórico do polígono do lote: uma linha por tentativa e
     * por fonte, com o resultado, o método de associação e o ponto usado.
     */
    public function up(): void
    {
        Schema::create('lot_polygon_resolutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('viability_request_id')->constrained()->cascadeOnDelete();
            $table->string('source', 20);
            $table->foreignId('building_footprint_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_id', 100)->nullable();
            $table->string('release', 150)->nullable();
            $table->string('status', 20);
            $table->string('method', 40)->nullable();
            $table->boolean('official')->default(false);
            $table->decimal('area_m2', 12, 2)->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->decimal('point_lat', 10, 7)->nullable();
            $table->decimal('point_lng', 10, 7)->nullable();
            $table->string('point_origin', 30)->nullable();
            $table->string('reason', 255)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamps();
            $table->index(['viability_request_id', 'created_at']);
            $table->index(['source', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lot_polygon_resolutions');
    }
};

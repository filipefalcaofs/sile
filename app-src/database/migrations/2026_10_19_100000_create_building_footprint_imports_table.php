<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada carga de edificações complementares (Overture, Microsoft), com a
     * release, o checksum do arquivo e os contadores. Mesma fonte, release e
     * checksum já concluída não importa de novo.
     */
    public function up(): void
    {
        Schema::create('building_footprint_imports', function (Blueprint $table) {
            $table->id();
            $table->string('source', 20);
            $table->string('release', 100);
            $table->string('checksum', 64);
            $table->string('status', 20);
            $table->jsonb('counters')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['source', 'release', 'checksum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('building_footprint_imports');
    }
};

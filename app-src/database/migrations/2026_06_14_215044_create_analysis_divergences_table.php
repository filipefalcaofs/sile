<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Divergências analista×motor (HU-140 → insumo do relatório HU-145) — quando
     * o analista DIVERGE do valor sugerido pelo motor na ficha, a diferença é
     * registrada em linha dedicada (append na finalização) com a justificativa.
     * Tabela própria (não jsonb na ficha) porque o relatório cruza divergências
     * entre processos.
     */
    public function up(): void
    {
        Schema::create('analysis_divergences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_record_id')->index()->constrained('analysis_records')->cascadeOnDelete();
            $table->string('cnae');
            $table->string('field');
            $table->text('suggested_value')->nullable();
            $table->text('final_value')->nullable();
            $table->text('justification')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_divergences');
    }
};

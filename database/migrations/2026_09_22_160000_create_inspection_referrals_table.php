<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Encaminhamentos à vistoria (handoff do analista ao setor de vistoria).
     * Append-only: cada encaminhamento é uma linha com a ORIGEM (setor e
     * analista) registrada para o retorno automático quando a ficha é
     * concluída — a vistoria compõe o processo, sem número novo.
     */
    public function up(): void
    {
        Schema::create('inspection_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('viability_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('encaminhado_por_user_id')->constrained('users');
            $table->foreignId('setor_origem_id')->nullable()->constrained('sectors');
            $table->foreignId('analista_origem_user_id')->nullable()->constrained('users');
            $table->foreignId('setor_vistoria_id')->constrained('sectors');
            $table->text('motivo');
            $table->timestamps();

            $table->index('viability_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_referrals');
    }
};

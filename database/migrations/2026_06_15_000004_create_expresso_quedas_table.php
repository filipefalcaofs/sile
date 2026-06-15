<?php

use App\Services\Expresso\FluxoExpressoService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Captura ESTRUTURADA do motivo/gatilho da queda ao analista (HU-145): cada
     * CNAE que o motor encaminha à análise técnica vira uma linha gravada por
     * {@see FluxoExpressoService::encaminharAnalise} no
     * momento da queda. tipo_gatilho/dimensao guardam o gatilho semi-expresso e
     * a dimensão decisiva REAIS (do RiscoClassificationService); ficam NULL
     * quando o motor degradou (toggle off / veredito pendente sem zona) — nesse
     * caso grava-se 1 linha de nível-processo (cnae NULL) com o motivo textual,
     * NUNCA um gatilho inventado (RN-001 anti-fachada). motivo é o texto
     * estruturado da decisão de roteamento (não digitação livre do usuário).
     *
     * Numeração 000004 (após os índices/contrato de 15-01/02) para nascer cedo
     * e depender só de viability_requests (FK), sem tabelas de outras frentes.
     */
    public function up(): void
    {
        Schema::create('expresso_quedas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('viability_request_id')->constrained()->cascadeOnDelete();
            $table->string('cnae')->nullable();
            $table->string('tipo_gatilho')->nullable();
            $table->string('dimensao')->nullable();
            $table->text('motivo');
            $table->timestamps();

            // Ranking de motivos e série temporal da queda (HU-145): agrega por
            // gatilho ao longo do tempo.
            $table->index(['tipo_gatilho', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expresso_quedas');
    }
};

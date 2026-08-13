<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Histórico imutável de consultas prévias de viabilidade (HU-060). Cada
     * linha é um SNAPSHOT da consulta: a entrada normalizada, o resultado
     * consolidado e as versões de TODAS as regras aplicadas (território/LOUOS/
     * risco) — auditável e reproduzível. Espelha a imutabilidade de access_logs
     * (só created_at, sem updated_at) e o nullOnDelete (consulta anônima não
     * vincula dono; se a conta sumir, o histórico é preservado sem o vínculo).
     * Tabular (json, sem PostGIS) — roda em SQLite sem tocar a baseline.
     */
    public function up(): void
    {
        Schema::create('viability_queries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('entry_type', 20);
            $table->json('input');
            $table->json('result');
            $table->json('rules_versions');
            $table->string('resultado', 30)->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->timestamp('created_at');
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('viability_queries');
    }
};

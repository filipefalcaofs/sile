<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cabeçalho genérico de regras como dados versionados (HU-019/HU-020/HU-053),
     * espelhando geo_layers: a publicação de uma nova versão NÃO apaga a anterior
     * (a vigente é fechada — status substituída, valid_to preenchido). Acrescenta
     * o estado rascunho (sandbox HU-143) e a autoria/publicação para a publicação
     * por quatro olhos. TABULAR — sem PostGIS, roda em SQLite. Idempotente por
     * (domain, version). As tabelas tipadas por domínio (planos 06-02/06-03)
     * referenciam esta versão.
     */
    public function up(): void
    {
        Schema::create('rule_versions', function (Blueprint $table) {
            $table->id();
            $table->string('domain');
            $table->string('version');
            $table->string('status')->default('rascunho');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->string('source')->nullable();
            $table->string('rules_version')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['domain', 'version']);
            $table->index(['domain', 'valid_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rule_versions');
    }
};

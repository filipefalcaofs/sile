<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rule_versions', function (Blueprint $table) {
            $table->string('content_hash', 64)->nullable()->after('source');
            $table->index(['domain', 'content_hash']);
        });

        Schema::table('treatment_enquadramentos', function (Blueprint $table) {
            $table->json('vagas')->nullable()->after('classificacao');
        });

        Schema::table('treatment_regra_ramos', function (Blueprint $table) {
            $table->boolean('bloqueia')->default(false)->after('fluxo');
        });

        Schema::create('treatment_condicionantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_version_id')->constrained('rule_versions')->cascadeOnDelete();
            $table->unsignedTinyInteger('numero');
            $table->text('texto');
            $table->text('observacao')->nullable();
            $table->timestamps();
            $table->unique(['rule_version_id', 'numero']);
        });

        Schema::create('treatment_cnlu_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_version_id')->constrained('rule_versions')->cascadeOnDelete();
            $table->string('codigo', 32);
            $table->text('texto');
            $table->timestamps();
            $table->unique(['rule_version_id', 'codigo']);
        });

        Schema::create('treatment_notas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_version_id')->constrained('rule_versions')->cascadeOnDelete();
            $table->string('chave', 64);
            $table->text('texto');
            $table->timestamps();
            $table->unique(['rule_version_id', 'chave']);
        });

        Schema::create('treatment_pendencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_version_id')->constrained('rule_versions')->cascadeOnDelete();
            $table->unsignedTinyInteger('regra')->nullable();
            $table->text('motivo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_pendencias');
        Schema::dropIfExists('treatment_notas');
        Schema::dropIfExists('treatment_cnlu_itens');
        Schema::dropIfExists('treatment_condicionantes');

        Schema::table('treatment_regra_ramos', function (Blueprint $table) {
            $table->dropColumn('bloqueia');
        });

        Schema::table('treatment_enquadramentos', function (Blueprint $table) {
            $table->dropColumn('vagas');
        });

        Schema::table('rule_versions', function (Blueprint $table) {
            $table->dropIndex(['domain', 'content_hash']);
            $table->dropColumn('content_hash');
        });
    }
};

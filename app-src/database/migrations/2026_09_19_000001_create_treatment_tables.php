<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatment_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_version_id')->constrained('rule_versions')->cascadeOnDelete();
            $table->unsignedTinyInteger('numero');
            $table->text('texto');
            $table->text('referencia')->nullable();
            $table->text('observacao')->nullable();
            $table->timestamps();
            $table->unique(['rule_version_id', 'numero']);
        });

        Schema::create('treatment_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_version_id')->constrained('rule_versions')->cascadeOnDelete();
            $table->unsignedTinyInteger('numero');
            $table->text('tratamento');
            $table->text('referencia')->nullable();
            $table->timestamps();
            $table->unique(['rule_version_id', 'numero']);
        });

        Schema::create('treatment_enquadramentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_version_id')->constrained('rule_versions')->cascadeOnDelete();
            $table->string('cnae', 16);
            $table->string('denominacao')->nullable();
            $table->string('risco');
            $table->unsignedTinyInteger('regra')->nullable();
            $table->string('codigo_louos', 16);
            $table->text('denominacao_louos')->nullable();
            $table->string('subcategoria', 32);
            $table->string('grupo', 16);
            $table->decimal('ate_m2', 12, 2)->nullable();
            $table->string('enquadramento2', 32)->nullable();
            $table->decimal('ate_m2_2', 12, 2)->nullable();
            $table->string('enquadramento3', 32)->nullable();
            $table->decimal('acima_m2', 12, 2)->nullable();
            $table->string('codigo_tll', 16)->nullable();
            $table->text('especificacao_tll')->nullable();
            $table->string('classificacao', 32)->nullable();
            $table->timestamps();
            $table->unique(['rule_version_id', 'cnae', 'codigo_louos', 'subcategoria'], 'treatment_enq_versao_cnae_louos_sub_unique');
            $table->index(['rule_version_id', 'cnae']);
        });

        Schema::create('treatment_cnae_bindings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_version_id')->constrained('rule_versions')->cascadeOnDelete();
            $table->string('cnae', 16);
            $table->unsignedTinyInteger('regra');
            $table->string('codigo_louos', 16);
            $table->json('perguntas')->nullable();
            $table->json('condicionantes')->nullable();
            $table->timestamps();
            $table->unique(['rule_version_id', 'cnae', 'regra', 'codigo_louos'], 'treatment_bind_versao_cnae_regra_louos_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_cnae_bindings');
        Schema::dropIfExists('treatment_enquadramentos');
        Schema::dropIfExists('treatment_rules');
        Schema::dropIfExists('treatment_questions');
    }
};

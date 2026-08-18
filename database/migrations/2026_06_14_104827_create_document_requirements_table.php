<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Requisitos documentais (modelo "Requisito" do SIGVISA — HU-067). A
     * obrigatoriedade por CNAE vem do pivot cnae_document_requirement; required
     * marca o obrigatório-base (ex.: foto da fachada). validation_instructions
     * é gancho para a validação por IA (EP14), inerte até lá.
     */
    public function up(): void
    {
        Schema::create('document_requirements', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('required')->default(true);
            $table->boolean('active')->default(true);
            $table->text('validation_instructions')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_requirements');
    }
};

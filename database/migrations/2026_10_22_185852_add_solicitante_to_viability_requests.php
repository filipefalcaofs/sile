<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nome e CPF de quem pediu a viabilidade, como o REGIN envia quando a empresa
     * ainda não tem CNPJ. Ficam separados da empresa e do usuário requerente.
     */
    public function up(): void
    {
        Schema::table('viability_requests', function (Blueprint $table): void {
            $table->string('solicitante_nome')->nullable();
            $table->string('solicitante_cpf', 11)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('viability_requests', function (Blueprint $table): void {
            $table->dropColumn(['solicitante_nome', 'solicitante_cpf']);
        });
    }
};

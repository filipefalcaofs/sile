<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vínculo 1:1 do usuário local com a conta GOV.BR (HU-151).
     *
     * A identidade é resolvida pelo CPF (`sub` do id_token) contra users.cpf;
     * esta tabela guarda apenas os metadados do vínculo (nível de
     * confiabilidade e datas) — o User permanece enxuto.
     */
    public function up(): void
    {
        Schema::create('gov_br_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('reliability_level', 10)->nullable();
            $table->json('reliability_levels')->nullable();
            $table->timestamp('linked_at');
            $table->timestamp('last_authenticated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_br_accounts');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Textos de declaração por tipo de serviço. A gestão altera sem deploy;
     * o aceite guarda a versão que o cidadão viu.
     */
    public function up(): void
    {
        Schema::create('service_declarations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_type_id')->constrained('viability_service_types')->cascadeOnDelete();
            $table->text('body');
            $table->unsignedInteger('version');
            $table->boolean('required')->default(true);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();
            $table->timestamps();

            $table->unique(['service_type_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_declarations');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comunicação devida à SEFAZ quando uma condição cadastral de escritório
 * virtual muda (`Alteração de Endereço` §4.3.2, RN-EV-09/EV-10): registro
 * consultável e reprocessável, no lugar do texto solto que a auditoria da
 * desvinculação gravava. `viability_request_id` referencia a SEDE afetada.
 * `property_registration_nova`/`endereco_novo` ficam nuláveis porque um
 * encerramento de sede não tem endereço novo — só a mudança de endereço tem.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sefaz_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('viability_request_id')->constrained()->cascadeOnDelete();
            $table->string('event');
            $table->string('cnpj')->nullable();
            $table->string('property_registration_anterior');
            $table->string('property_registration_nova')->nullable();
            $table->string('endereco_anterior');
            $table->string('endereco_novo')->nullable();
            $table->string('status')->default('pendente');
            $table->unsignedInteger('tentativas')->default(0);
            $table->text('erro')->nullable();
            $table->json('retorno')->nullable();
            $table->timestamp('enviada_em')->nullable();
            $table->timestamps();

            // Recorte operacional: pendências por ordem de chegada.
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sefaz_notifications');
    }
};

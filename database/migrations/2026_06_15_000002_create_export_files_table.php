<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Arquivos de exportação gerados pelo caminho ASSÍNCRONO do contrato de
     * exportação (HU-131 / GerarExportacaoJob): cada arquivo grande gerado em
     * segundo plano é uma linha baixável por URL assinada (rota em 15-09) e
     * podável por retenção (15-15). user_id é o dono (destinatário da notificação
     * de pronto); disk/path apontam o arquivo no Storage (disco parametrizado,
     * NUNCA público); format é o formato (csv/xlsx/pdf); row_count é a contagem
     * REAL de linhas escritas; filtros guarda o bag aplicado (auditoria/reexecução).
     *
     * Numeração 000002 (após os índices de 15-01) para nascer cedo na ordem de
     * migração e NÃO depender de tabelas de outras frentes — só de users.
     */
    public function up(): void
    {
        Schema::create('export_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk');
            $table->string('path');
            $table->string('filename');
            $table->string('format');
            $table->unsignedInteger('row_count')->default(0);
            $table->jsonb('filtros')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_files');
    }
};

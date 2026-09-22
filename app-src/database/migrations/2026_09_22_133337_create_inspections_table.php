<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ficha de vistoria do processo. Identificação (tipo, opened_at,
     * vistoriador) gravada na abertura; localização é SNAPSHOT do endereço do
     * processo; polígono nasce do property_polygon_geojson e pode ser
     * redesenhado/validado (área calculada driver-aware). Parecer obrigatório
     * na conclusão; ficha concluída é imutável.
     *
     * Portável (sem coluna geometry): o polígono fica em jsonb, como o
     * property_polygon_geojson da solicitação — a suíte roda em SQLite.
     */
    public function up(): void
    {
        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('viability_request_id')->constrained()->cascadeOnDelete();

            // Identificação da ficha — gravada na abertura, nunca digitada.
            $table->string('tipo')->default('localizacao');
            $table->string('status')->default('em_preenchimento');
            $table->foreignId('vistoriador_user_id')->constrained('users');
            $table->timestamp('opened_at');
            $table->timestamp('concluded_at')->nullable();

            // Localização — snapshot do processo na abertura.
            $table->string('cod_logradouro')->nullable();
            $table->string('logradouro')->nullable();
            $table->string('numero_metrico')->nullable();
            $table->string('bairro')->nullable();
            $table->string('cep', 9)->nullable();
            $table->string('ponto_referencia')->nullable();
            $table->string('zona')->nullable();
            $table->string('via')->nullable();
            $table->boolean('logradouro_correto')->nullable();

            // Polígono da vistoria (redesenhável) + validação.
            $table->jsonb('polygon_geojson')->nullable();
            $table->timestamp('polygon_validated_at')->nullable();
            $table->decimal('polygon_area_m2', 12, 2)->nullable();

            // Dados do imóvel.
            $table->string('tipo_imovel')->nullable();
            $table->jsonb('acessos')->nullable();
            $table->boolean('atividade_em_funcionamento')->nullable();
            $table->string('complemento_tipo')->nullable();
            $table->string('complemento_numero')->nullable();
            $table->decimal('complemento_area_m2', 12, 2)->nullable();
            $table->decimal('area_total_m2', 12, 2)->nullable();

            // Vagas de vistoria.
            $table->unsignedInteger('vagas_veiculo_passeio')->nullable();
            $table->unsignedInteger('vagas_carga_descarga')->nullable();
            $table->boolean('patio_carga_descarga')->nullable();
            $table->boolean('area_embarque_desembarque')->nullable();

            // Características do imóvel.
            $table->decimal('area_terreno_m2', 12, 2)->nullable();
            $table->decimal('area_total_construida_m2', 12, 2)->nullable();
            $table->decimal('area_ocupada_atividade_m2', 12, 2)->nullable();
            $table->decimal('area_carga_descarga_m2', 12, 2)->nullable();
            $table->string('pavimento_edificacao')->nullable();
            $table->string('pavimento_ocupado_atividade')->nullable();
            $table->decimal('recuo_m', 8, 2)->nullable();

            // Entorno — distância em metro linear.
            $table->decimal('entorno_residencial_m', 10, 2)->nullable();
            $table->decimal('entorno_industrial_m', 10, 2)->nullable();
            $table->decimal('entorno_saude_m', 10, 2)->nullable();
            $table->decimal('entorno_comercial_m', 10, 2)->nullable();
            $table->decimal('entorno_institucional_m', 10, 2)->nullable();
            $table->decimal('entorno_especial_m', 10, 2)->nullable();
            $table->decimal('entorno_educacional_m', 10, 2)->nullable();
            $table->decimal('entorno_misto_m', 10, 2)->nullable();
            $table->decimal('entorno_outros_m', 10, 2)->nullable();

            // Infraestrutura.
            $table->string('instalacoes_eletricas')->nullable();
            $table->string('instalacoes_hidrossanitarias')->nullable();
            $table->jsonb('obras')->nullable();
            $table->string('alvara_numero')->nullable();
            $table->unsignedInteger('quantidade_usuarios')->nullable();
            $table->unsignedInteger('num_salas_alunos')->nullable();
            $table->unsignedInteger('num_assentos')->nullable();
            $table->unsignedInteger('unidades_hospedagem')->nullable();
            $table->unsignedInteger('num_leitos')->nullable();

            // Controle ambiental.
            $table->jsonb('equipamentos')->nullable();
            $table->string('equipamentos_outros')->nullable();
            $table->boolean('maquinas_motores')->nullable();
            $table->boolean('sons_ruidos')->nullable();
            $table->string('sons_ruidos_origem')->nullable();

            // Equipamentos de segurança.
            $table->boolean('seg_extintores')->nullable();
            $table->boolean('seg_central_gas')->nullable();
            $table->boolean('seg_hidrantes')->nullable();
            $table->string('seg_outros')->nullable();

            // Conclusão.
            $table->text('observacoes')->nullable();
            $table->text('parecer')->nullable();
            $table->date('data_vistoria')->nullable();
            $table->string('contato_nome')->nullable();
            $table->string('contato_telefone', 20)->nullable();

            $table->timestamps();

            $table->index(['viability_request_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspections');
    }
};

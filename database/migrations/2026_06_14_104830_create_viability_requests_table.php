<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aggregate root da solicitação de viabilidade (EP08) — cabeçalho + imóvel
     * embutido 1:1 (como companies). protocol_number é unique nullable (só após
     * protocolar); status/origin governados pela máquina de estados e pela
     * origem auditada. requester_user_id é o beneficiário; created_by_user_id é
     * o ator real ("em nome de" — HU-150). A simulação (HU-141) é orientativa e
     * fica em snapshot, sem reprocessar no protocolo.
     *
     * Polígono driver-aware (espelha geo_features + GeoJsonLayerImporter da
     * Fase 4): property_polygon_geojson (jsonb) é a FONTE de verdade portável
     * (a suíte roda em SQLite). A coluna geometry derivada property_polygon e
     * seu índice GiST só existem no pgsql — em SQLite quebrariam a migração
     * (Pitfall 1). A derivada é gravada via ST_* no pgsql (plano 08-06) e serve
     * às consultas espaciais reversas das Fases 9/10 (precedentes do imóvel).
     */
    public function up(): void
    {
        Schema::create('viability_requests', function (Blueprint $table) {
            $table->id();

            $table->string('protocol_number')->nullable()->unique();
            $table->string('status')->default('rascunho')->index();
            $table->string('origin')->default('portal');

            $table->foreignId('service_type_id')->nullable()->constrained('viability_service_types')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('requester_user_id')->constrained('users');
            $table->foreignId('created_by_user_id')->constrained('users');

            $table->decimal('used_area_m2', 10, 2)->nullable();
            $table->string('property_registration')->nullable();

            $table->string('address_street')->nullable();
            $table->string('address_number')->nullable();
            $table->string('address_complement')->nullable();
            $table->string('address_neighborhood')->nullable();
            $table->string('address_zip')->nullable();
            $table->string('address_reference')->nullable();

            $table->jsonb('property_polygon_geojson')->nullable();

            $table->boolean('is_virtual_office')->default(false);
            $table->boolean('is_public_area')->default(false);
            $table->boolean('has_independent_access')->default(false);

            $table->jsonb('simulation_snapshot')->nullable();
            $table->jsonb('simulation_rules_versions')->nullable();
            $table->string('simulation_resultado')->nullable();
            $table->timestamp('simulated_at')->nullable();

            $table->boolean('applicant_proceeded_despite')->default(false);
            $table->text('contingency_reason')->nullable();
            $table->string('external_reference')->nullable();

            $table->timestamp('protocoled_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancelled_reason')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });

        // Geometria derivada + índice espacial SÓ no PostgreSQL — em SQLite
        // (:memory: da suíte) geometry/GiST quebrariam a migração (Pitfall 1).
        // A coluna NEM EXISTE em SQLite: a fonte é o GeoJSON jsonb.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE viability_requests ADD COLUMN property_polygon geometry(Polygon, 4326)');
            DB::statement('CREATE INDEX viability_requests_property_polygon_gist ON viability_requests USING GIST (property_polygon)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('viability_requests');
    }
};

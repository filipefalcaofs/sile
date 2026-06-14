<?php

namespace Tests\Feature\Solicitacao;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Esquema da Fase 8 (solicitação de viabilidade) provado em SQLite (:memory:)
 * via RefreshDatabase — é o PHPUnit que prova a migração, sem tocar o banco de
 * dev. A coluna geometry property_polygon e seu índice GiST NÃO são criados em
 * SQLite (driver-aware, espelhando geo_features) — a fonte de verdade portável
 * é property_polygon_geojson (jsonb). A geometria derivada é exercida nos
 * testes @group postgis (Task 3 / plano 08-06).
 */
class SolicitacaoSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_cadastros_existem_com_colunas(): void
    {
        $this->assertTrue(Schema::hasTable('viability_service_types'));
        $this->assertTrue(Schema::hasColumns('viability_service_types', ['code', 'name', 'flow_hint', 'active']));

        $this->assertTrue(Schema::hasTable('document_requirements'));
        $this->assertTrue(Schema::hasColumns('document_requirements', ['code', 'name', 'description', 'required', 'active', 'validation_instructions']));

        $this->assertTrue(Schema::hasTable('cnae_document_requirement'));
        $this->assertTrue(Schema::hasColumns('cnae_document_requirement', ['cnae_id', 'document_requirement_id']));

        $this->assertTrue(Schema::hasTable('protocol_sequences'));
        $this->assertTrue(Schema::hasColumns('protocol_sequences', ['year', 'last_number']));
    }

    public function test_aggregate_viability_requests_existe_com_colunas(): void
    {
        $this->assertTrue(Schema::hasTable('viability_requests'));

        $this->assertTrue(Schema::hasColumns('viability_requests', [
            'protocol_number', 'status', 'origin',
            'service_type_id', 'company_id', 'requester_user_id', 'created_by_user_id',
            'used_area_m2', 'property_registration',
            'address_street', 'address_number', 'address_complement', 'address_neighborhood', 'address_zip', 'address_reference',
            'property_polygon_geojson',
            'is_virtual_office', 'is_public_area', 'has_independent_access',
            'simulation_snapshot', 'simulation_rules_versions', 'simulation_resultado', 'simulated_at',
            'applicant_proceeded_despite', 'contingency_reason', 'external_reference',
            'protocoled_at', 'cancelled_at', 'cancelled_reason', 'cancelled_by_user_id',
        ]));
    }

    public function test_property_polygon_geometry_nao_existe_em_sqlite(): void
    {
        // Geometria derivada é driver-aware: a coluna geometry só existe no
        // pgsql (exercitada em @group postgis). Em SQLite a fonte é o jsonb.
        $this->assertSame('sqlite', DB::getDriverName());
        $this->assertFalse(Schema::hasColumn('viability_requests', 'property_polygon'));
        $this->assertTrue(Schema::hasColumn('viability_requests', 'property_polygon_geojson'));
    }

    public function test_satelites_existem_com_colunas(): void
    {
        $this->assertTrue(Schema::hasTable('viability_request_cnaes'));
        $this->assertTrue(Schema::hasColumns('viability_request_cnaes', ['viability_request_id', 'cnae_id', 'is_primary']));

        $this->assertTrue(Schema::hasTable('viability_request_documents'));
        $this->assertTrue(Schema::hasColumns('viability_request_documents', [
            'viability_request_id', 'requirement_id', 'disk', 'path', 'original_name', 'mime_type', 'size', 'sha256', 'uploaded_by_user_id',
        ]));

        $this->assertTrue(Schema::hasTable('viability_request_transitions'));
        $this->assertTrue(Schema::hasColumns('viability_request_transitions', [
            'viability_request_id', 'from_status', 'to_status', 'reason', 'public_label', 'actor_user_id',
        ]));
    }

    public function test_migracao_em_sqlite_nao_lanca_nem_cria_gist(): void
    {
        // Chegar aqui prova que o RefreshDatabase migrou todas as tabelas em
        // SQLite sem tentar a geometry/GiST (guardadas por driver) — suíte intacta.
        $this->assertSame('sqlite', DB::getDriverName());
        $this->assertTrue(Schema::hasTable('viability_requests'));
    }
}

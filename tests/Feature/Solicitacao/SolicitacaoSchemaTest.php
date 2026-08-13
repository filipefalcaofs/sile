<?php

namespace Tests\Feature\Solicitacao;

use App\Enums\ViabilityRequestOrigin;
use App\Enums\ViabilityRequestStatus;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestDocument;
use App\Models\ViabilityRequestTransition;
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

    public function test_status_tem_estados_ativos_e_ganchos_futuros(): void
    {
        // Ativos nesta fase.
        $this->assertSame('rascunho', ViabilityRequestStatus::Rascunho->value);
        $this->assertSame('protocolada', ViabilityRequestStatus::Protocolada->value);
        $this->assertSame('cancelada', ViabilityRequestStatus::Cancelada->value);

        // Ganchos das Fases 9/10/11/13 — existem como casos, mas a máquina NÃO
        // os transiciona nesta fase (teste no ViabilityRequestStateMachineTest).
        $this->assertSame('aguardando_bap', ViabilityRequestStatus::AguardandoBap->value);
        $this->assertSame('em_analise', ViabilityRequestStatus::EmAnalise->value);
        $this->assertSame('deferida', ViabilityRequestStatus::Deferida->value);
        $this->assertSame('indeferida', ViabilityRequestStatus::Indeferida->value);
        $this->assertSame('em_pendencia', ViabilityRequestStatus::EmPendencia->value);

        $this->assertCount(8, ViabilityRequestStatus::cases());
    }

    public function test_status_tem_label_tecnico_e_publico(): void
    {
        $this->assertSame('Rascunho', ViabilityRequestStatus::Rascunho->label());
        $this->assertSame('Protocolada', ViabilityRequestStatus::Protocolada->label());

        // publicLabel() fala ao cidadão em linguagem simples (HU-069 RN-004) —
        // diferente do label técnico.
        $this->assertNotSame(
            ViabilityRequestStatus::Protocolada->label(),
            ViabilityRequestStatus::Protocolada->publicLabel(),
        );
        $this->assertNotEmpty(ViabilityRequestStatus::AguardandoBap->publicLabel());

        // Todos os casos têm os dois rótulos preenchidos.
        foreach (ViabilityRequestStatus::cases() as $status) {
            $this->assertNotEmpty($status->label());
            $this->assertNotEmpty($status->publicLabel());
        }
    }

    public function test_origin_tem_casos_com_regin_de_gancho(): void
    {
        $this->assertSame('portal', ViabilityRequestOrigin::Portal->value);
        $this->assertSame('contingencia', ViabilityRequestOrigin::Contingencia->value);
        // Regin é gancho (Fase 13) — presente no enum, mas não usado nesta fase.
        $this->assertSame('regin', ViabilityRequestOrigin::Regin->value);

        foreach (ViabilityRequestOrigin::cases() as $origin) {
            $this->assertNotEmpty($origin->label());
        }
    }

    public function test_factory_cria_rascunho_por_padrao(): void
    {
        $request = ViabilityRequest::factory()->create();

        $this->assertSame(ViabilityRequestStatus::Rascunho, $request->status);
        $this->assertSame(ViabilityRequestOrigin::Portal, $request->origin);
        $this->assertNull($request->protocol_number);
        $this->assertIsArray($request->property_polygon_geojson);
        $this->assertNotNull($request->requester_user_id);
        $this->assertNotNull($request->created_by_user_id);
    }

    public function test_factory_protocolada_tem_numero_e_data(): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create();

        $this->assertSame(ViabilityRequestStatus::Protocolada, $request->status);
        $this->assertNotNull($request->protocol_number);
        $this->assertNotNull($request->protocoled_at);
    }

    public function test_factory_cancelada_tem_data_e_motivo(): void
    {
        $request = ViabilityRequest::factory()->cancelled()->create();

        $this->assertSame(ViabilityRequestStatus::Cancelada, $request->status);
        $this->assertNotNull($request->cancelled_at);
        $this->assertNotNull($request->cancelled_reason);
    }

    public function test_factory_contingencia_tem_origem_e_motivo(): void
    {
        $request = ViabilityRequest::factory()->contingency()->create();

        $this->assertSame(ViabilityRequestOrigin::Contingencia, $request->origin);
        $this->assertNotNull($request->contingency_reason);
    }

    public function test_factory_com_cnae_principal(): void
    {
        $request = ViabilityRequest::factory()->draft()->withPrimaryCnae()->create();

        $this->assertSame(1, $request->primaryCnae()->count());
        $this->assertTrue((bool) $request->primaryCnae()->first()->pivot->is_primary);
    }

    public function test_factory_com_cnaes_complementares(): void
    {
        $request = ViabilityRequest::factory()->withCnaes(3)->create();

        $this->assertSame(3, $request->cnaes()->count());
    }

    public function test_mark_simulation_stale_zera_a_simulacao(): void
    {
        $request = ViabilityRequest::factory()->create([
            'simulation_snapshot' => ['resultado' => 'pendente'],
            'simulation_rules_versions' => ['louos' => 'x'],
            'simulation_resultado' => 'pendente',
            'simulated_at' => now(),
        ]);

        $request->markSimulationStale();
        $request->refresh();

        $this->assertNull($request->simulation_snapshot);
        $this->assertNull($request->simulation_rules_versions);
        $this->assertNull($request->simulation_resultado);
        $this->assertNull($request->simulated_at);
    }

    public function test_relacoes_documentos_e_transicoes(): void
    {
        $request = ViabilityRequest::factory()->create();
        ViabilityRequestDocument::factory()->create(['viability_request_id' => $request->id]);
        ViabilityRequestTransition::factory()->create(['viability_request_id' => $request->id]);

        $this->assertSame(1, $request->documents()->count());
        $this->assertSame(1, $request->transitions()->count());
    }
}

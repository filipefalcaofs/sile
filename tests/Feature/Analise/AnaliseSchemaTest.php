<?php

namespace Tests\Feature\Analise;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Fundação de dados da Fase 10 (análise técnica SEDUR): as 8 tabelas novas
 * (setores+pivot, ficha versionada, divergências, pendências, malha fina,
 * textos-padrão, documentos TVL) e as colunas de análise aditadas em
 * viability_requests (atribuição/SLA/categoria/malha fina). Tudo portável
 * (json, sem geometria própria) — a suíte roda em SQLite. A unicidade da
 * revisão da ficha e do verification_code do TVL é provada no AnaliseModelsTest.
 */
class AnaliseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tabela_sectors_existe_com_colunas(): void
    {
        $this->assertTrue(Schema::hasTable('sectors'));
        $this->assertTrue(Schema::hasColumns('sectors', ['name', 'active']));
    }

    public function test_pivot_sector_user_existe_com_vinculo(): void
    {
        $this->assertTrue(Schema::hasTable('sector_user'));
        $this->assertTrue(Schema::hasColumns('sector_user', ['sector_id', 'user_id']));
    }

    public function test_viability_requests_ganha_colunas_de_analise(): void
    {
        $this->assertTrue(Schema::hasColumns('viability_requests', [
            'sector_id',
            'assigned_user_id',
            'assigned_at',
            'analysis_category',
            'in_fine_mesh',
            'analysis_stage',
            'analysis_stage_started_at',
            'analysis_due_at',
        ]));
    }

    public function test_tabela_analysis_records_existe_com_a_ficha_versionada(): void
    {
        $this->assertTrue(Schema::hasTable('analysis_records'));
        $this->assertTrue(Schema::hasColumns('analysis_records', [
            'viability_request_id',
            'revision',
            'status',
            'analyst_user_id',
            'engine_snapshot',
            'engine_rules_versions',
            'engine_available',
            'per_cnae',
            'conditions',
            'parking',
            'parecer',
            'finalized_at',
        ]));
    }

    public function test_tabela_analysis_divergences_existe(): void
    {
        $this->assertTrue(Schema::hasTable('analysis_divergences'));
        $this->assertTrue(Schema::hasColumns('analysis_divergences', [
            'analysis_record_id',
            'cnae',
            'field',
            'suggested_value',
            'final_value',
            'justification',
        ]));
    }

    public function test_tabela_analysis_pendencies_existe(): void
    {
        $this->assertTrue(Schema::hasTable('analysis_pendencies'));
        $this->assertTrue(Schema::hasColumns('analysis_pendencies', [
            'viability_request_id',
            'requested_by_user_id',
            'description',
            'status',
            'due_at',
            'responded_at',
            'response',
        ]));
    }

    public function test_tabela_fine_mesh_referrals_existe(): void
    {
        $this->assertTrue(Schema::hasTable('fine_mesh_referrals'));
        $this->assertTrue(Schema::hasColumns('fine_mesh_referrals', [
            'viability_request_id',
            'referred_by_user_id',
            'reason',
            'resolved_at',
        ]));
    }

    public function test_tabela_standard_texts_existe(): void
    {
        $this->assertTrue(Schema::hasTable('standard_texts'));
        $this->assertTrue(Schema::hasColumns('standard_texts', [
            'category',
            'content',
            'active',
            'version',
        ]));
    }

    public function test_tabela_tvl_documents_existe(): void
    {
        $this->assertTrue(Schema::hasTable('tvl_documents'));
        $this->assertTrue(Schema::hasColumns('tvl_documents', [
            'viability_decision_id',
            'disk',
            'path',
            'verification_code',
            'generated_by_user_id',
            'generated_at',
        ]));
    }
}

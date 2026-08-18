<?php

namespace Tests\Feature\Expresso;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Fundação de dados do fluxo expresso (EP09): a tabela IMUTÁVEL
 * viability_decisions (1:1 com a solicitação), o contador tvl_sequences
 * (espelha protocol_sequences) e as colunas dormentes do relógio do BAP
 * (HU-134) em viability_requests. Portável (json, sem geometria) — roda em
 * SQLite. A unicidade 1:1 e do TVL é provada no ViabilityDecisionTest.
 */
class ExpressoSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tabela_viability_decisions_existe_com_colunas_da_decisao(): void
    {
        $this->assertTrue(Schema::hasTable('viability_decisions'));

        $this->assertTrue(Schema::hasColumns('viability_decisions', [
            'viability_request_id',
            'flow',
            'outcome',
            'consolidated_result',
            'tvl_product_number',
            'per_cnae',
            'rules_versions',
            'fundamentacao',
            'reason',
            'decided_by_user_id',
            'decided_at',
        ]));
    }

    public function test_tabela_tvl_sequences_existe_com_contador_por_ano(): void
    {
        $this->assertTrue(Schema::hasTable('tvl_sequences'));

        $this->assertTrue(Schema::hasColumns('tvl_sequences', [
            'year',
            'last_number',
        ]));
    }

    public function test_viability_requests_ganha_relogio_do_bap(): void
    {
        $this->assertTrue(Schema::hasColumns('viability_requests', [
            'bap_due_at',
            'bap_linked_at',
        ]));
    }
}

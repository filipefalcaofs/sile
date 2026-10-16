<?php

namespace Tests\Feature\Cidadao;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Portão da separação cidadão x servidor: nenhuma coluna de cidadão pode apontar
 * para `users`, e toda chave para `users` precisa estar classificada como de servidor.
 */
class UsersForeignKeysTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @var list<string> tabela.coluna que, por decisão, apontam para users (servidor). */
    private const SERVIDOR = [
        'viability_requests.assigned_user_id',
        'viability_requests.conclusao_encaminhada_para_user_id',
        'analysis_records.analyst_user_id',
        'viability_decisions.decided_by_user_id',
        'inspections.vistoriador_user_id',
        'assisted_attendances.attendant_user_id',
        'tvl_documents.generated_by_user_id',
        'indeferimento_documents.generated_by_user_id',
        'fine_mesh_referrals.referred_by_user_id',
        'fine_mesh_referrals.resolved_by_user_id',
        'inspection_referrals.encaminhado_por_user_id',
        'inspection_referrals.analista_origem_user_id',
        'inspection_attachments.uploaded_by_user_id',
        'analysis_pendencies.requested_by_user_id',
        'analysis_pendencies.cancelled_by_user_id',
        'analysis_status_transitions.actor_user_id',
        'access_requests.decided_by_user_id',
        'access_requests.user_id',
        'sector_user.user_id',
        'user_sector_assignments.user_id',
        'user_sector_assignments.changed_by_user_id',
        'rule_versions.created_by',
        'rule_versions.published_by',
        'parameter_proposals.created_by',
        'parameter_proposals.reviewed_by',
        'ai_suggestions.created_by_user_id',
        'abuse_alerts.resolved_by_user_id',
        'predictive_anomalies.resolved_by_user_id',
        'export_files.user_id',
        'passkeys.user_id',
        'regin_simulacao_execucoes.user_id',
    ];

    public function test_so_colunas_de_servidor_apontam_para_users(): void
    {
        $encontradas = [];

        foreach (Schema::getTableListing(schemaQualified: false) as $tabela) {
            foreach (Schema::getForeignKeys($tabela) as $fk) {
                if ($fk['foreign_table'] === 'users') {
                    foreach ($fk['columns'] as $coluna) {
                        $encontradas[] = "{$tabela}.{$coluna}";
                    }
                }
            }
        }

        sort($encontradas);
        $esperadas = self::SERVIDOR;
        sort($esperadas);

        $this->assertSame($esperadas, $encontradas);
    }
}

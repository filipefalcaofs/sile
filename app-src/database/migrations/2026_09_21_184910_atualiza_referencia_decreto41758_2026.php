<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Relatório SEDUR 21/09/2026 (itens 08–09): o decreto de risco vigente é o
 * Decreto Municipal nº 41.758/2026. Rebatiza a versão vigente do domínio
 * risco_municipal e atualiza a citação administrável (decision_texts) nos
 * bancos já seedados — fresh installs já nascem certos pelo seeder. O
 * histórico de decisões gravadas com a versão anterior NÃO é tocado
 * (reprodução por época). Idempotente e reversível.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('rule_versions')
            ->where('domain', 'risco_municipal')
            ->where('version', 'decreto-32636-2020')
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('rule_versions as nova')
                    ->where('nova.domain', 'risco_municipal')
                    ->where('nova.version', 'decreto-41758-2026');
            })
            ->update([
                'version' => 'decreto-41758-2026',
                'rules_version' => 'decreto-41758-2026',
                'source' => 'Decreto Municipal nº 41.758/2026',
                'updated_at' => now(),
            ]);

        // Só troca quem ainda tem o texto default antigo — edição do admin
        // pela tela Textos decisórios é preservada.
        DB::table('decision_texts')
            ->where('key', 'base_legal.risco_municipal')
            ->where('template', 'Decreto Municipal nº 32.636/2020')
            ->update([
                'template' => 'Decreto Municipal nº 41.758/2026',
                'updated_at' => now(),
            ]);

        DB::table('decision_texts')
            ->where('key', 'explicacao.motivo.risco_nao_registrado')
            ->where('template', 'like', '%32.636/2020%')
            ->update([
                'template' => 'O Decreto nº 41.758/2026 classifica o risco do CNAE e define se o processo vai ao fluxo expresso ou à análise técnica. O nível e o encaminhamento desta decisão não foram gravados.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('rule_versions')
            ->where('domain', 'risco_municipal')
            ->where('version', 'decreto-41758-2026')
            ->update([
                'version' => 'decreto-32636-2020',
                'rules_version' => 'decreto-32636-2020',
                'source' => 'Decreto Municipal nº 32.636/2020 (redação Dec. 38.673/2024)',
                'updated_at' => now(),
            ]);

        DB::table('decision_texts')
            ->where('key', 'base_legal.risco_municipal')
            ->where('template', 'Decreto Municipal nº 41.758/2026')
            ->update([
                'template' => 'Decreto Municipal nº 32.636/2020',
                'updated_at' => now(),
            ]);
    }
};

<?php

namespace Database\Seeders;

use App\Enums\RuleDomain;
use App\Models\RuleVersion;
use App\Services\Risco\RiscoSanitarioImportService;
use App\Services\Rules\RuleVersionService;
use App\Support\Audit\AuditService;
use Illuminate\Database\Seeder;

/**
 * Carga oficial da classificação de risco SANITÁRIO (HU-019/HU-047/HU-048):
 * publica uma versão vigente do domínio risco_sanitario (planilha unificada
 * CNAE da VISA), delega o import real ao service e registra o relatório
 * (contadores + distribuição por nível + avisos) na trilha de auditoria com a
 * versão de regras. Dimensão SEPARADA da municipal — versão e tabela próprias.
 *
 * Idempotente: reusa a versão vigente se já existir (não republica) e o import
 * faz upsert/firstOrCreate — re-seed não duplica.
 */
class RiscoSanitarioSeeder extends Seeder
{
    public function run(): void
    {
        $rules = app(RuleVersionService::class);

        $version = RuleVersion::vigente(RuleDomain::RiscoSanitario)->first();

        if ($version === null) {
            $draft = $rules->openDraft(
                RuleDomain::RiscoSanitario,
                'visa-unificada-2026-04-30',
                'Planilha Unificada CNAE 30.04.26 (Vigilância Sanitária)',
            );

            $version = $rules->publish($draft);
        }

        $report = app(RiscoSanitarioImportService::class)->import(
            $version,
            database_path('data/risco/planilha-unificada-cnae-30-04-26.csv'),
        );

        app(AuditService::class)->log(
            logName: 'risco',
            event: 'importacao-classificacao-sanitaria',
            description: 'Importação da classificação de risco sanitário (planilha unificada CNAE — VISA)',
            properties: $report,
            rulesVersion: 'visa-unificada-2026-04-30',
        );
    }
}

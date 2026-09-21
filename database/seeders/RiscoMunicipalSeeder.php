<?php

namespace Database\Seeders;

use App\Enums\RuleDomain;
use App\Models\RuleVersion;
use App\Services\Risco\RiscoMunicipalImportService;
use App\Services\Rules\RuleVersionService;
use App\Support\Audit\AuditService;
use Illuminate\Database\Seeder;

/**
 * Carga oficial da classificação de risco MUNICIPAL (HU-020/HU-047): publica
 * uma versão vigente do domínio risco_municipal referenciando o Decreto nº
 * 41.758/2026 (decreto vigente declarado pela SEDUR — relatório 21/09/2026),
 * delega o import real ao service e registra o relatório (contadores +
 * distribuição por nível) na trilha de auditoria com a versão de regras.
 *
 * Idempotente: reusa a versão vigente se já existir (não republica) e o import
 * faz upsert por (rule_version_id, cnae_code) — re-seed não duplica.
 */
class RiscoMunicipalSeeder extends Seeder
{
    public function run(): void
    {
        $rules = app(RuleVersionService::class);

        $version = RuleVersion::vigente(RuleDomain::RiscoMunicipal)->first();

        if ($version === null) {
            $draft = $rules->openDraft(
                RuleDomain::RiscoMunicipal,
                'decreto-41758-2026',
                'Decreto Municipal nº 41.758/2026',
            );

            $version = $rules->publish($draft);
        }

        $report = app(RiscoMunicipalImportService::class)->import(
            $version,
            database_path('data/risco/decreto-32636-2020-risco-municipal-unificado-cnae.csv'),
        );

        app(AuditService::class)->log(
            logName: 'risco',
            event: 'importacao-classificacao-municipal',
            description: 'Importação da classificação de risco municipal (Decreto 41.758/2026)',
            properties: $report,
            rulesVersion: 'decreto-41758-2026',
        );
    }
}

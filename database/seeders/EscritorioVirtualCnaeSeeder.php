<?php

namespace Database\Seeders;

use App\Enums\RuleDomain;
use App\Models\RuleVersion;
use App\Services\EscritorioVirtual\EscritorioVirtualCnaeImportService;
use App\Services\Rules\RuleVersionService;
use App\Support\Audit\AuditService;
use Illuminate\Database\Seeder;

/**
 * Carga da Lista EV (CNAEs permitidos para ABRIGADO de escritório virtual,
 * RN-EV-05/07): publica uma versão vigente do domínio
 * atividades_escritorio_virtual e importa o snapshot CSV, registrando o
 * relatório (contadores) na trilha de auditoria com a versão de regras.
 * Espelha RiscoSanitarioSeeder.
 *
 * Fonte oficial: endpoint SEDUR AtividadesPermitidasEmEscritorioVirtual.php;
 * este CSV é o snapshot vigente até o fetch live do endpoint.
 *
 * Idempotente: reusa a versão vigente se já existir (não republica) e o
 * import faz upsert — re-seed não duplica.
 */
class EscritorioVirtualCnaeSeeder extends Seeder
{
    public function run(): void
    {
        $rules = app(RuleVersionService::class);

        $version = RuleVersion::vigente(RuleDomain::AtividadesEscritorioVirtual)->first();

        if ($version === null) {
            $draft = $rules->openDraft(
                RuleDomain::AtividadesEscritorioVirtual,
                'ev-snapshot-2026-07-16',
                'Snapshot Lista EV (endpoint SEDUR AtividadesPermitidasEmEscritorioVirtual.php)',
            );

            $version = $rules->publish($draft);
        }

        $report = app(EscritorioVirtualCnaeImportService::class)->import(
            $version,
            database_path('data/escritorio-virtual/atividades-permitidas.csv'),
        );

        app(AuditService::class)->log(
            logName: 'escritorio-virtual',
            event: 'importacao-lista-ev',
            description: 'Importação da Lista EV (CNAEs permitidos para escritório virtual)',
            properties: $report,
            rulesVersion: 'ev-snapshot-2026-07-16',
        );
    }
}

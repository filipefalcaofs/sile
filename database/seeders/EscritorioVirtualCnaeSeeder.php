<?php

namespace Database\Seeders;

use App\Enums\RuleDomain;
use App\Models\RuleVersion;
use App\Models\VirtualOfficeActivityCnae;
use App\Services\EscritorioVirtual\EscritorioVirtualCnaeImportService;
use App\Services\Rules\RuleVersionService;
use App\Support\Audit\AuditService;
use Illuminate\Database\Seeder;

/**
 * Carga das listas de atividade por anexo do Decreto 35.062/2021 (Anexo A =
 * SEDE, Anexo B = ABRIGADO, RN-EV-05/07): publica uma versão vigente do
 * domínio atividades_escritorio_virtual e importa os dois snapshots CSV,
 * registrando o relatório (contadores) na trilha de auditoria com a versão
 * de regras. Espelha RiscoSanitarioSeeder.
 *
 * Fonte oficial: endpoint SEDUR AtividadesPermitidasEmEscritorioVirtual.php;
 * estes CSVs são o snapshot vigente até o fetch live do endpoint.
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

        $relatorioB = app(EscritorioVirtualCnaeImportService::class)->import(
            $version,
            database_path('data/escritorio-virtual/anexo-b-abrigado.csv'),
            VirtualOfficeActivityCnae::ANEXO_B,
        );

        $relatorioA = app(EscritorioVirtualCnaeImportService::class)->import(
            $version,
            database_path('data/escritorio-virtual/anexo-a-sede.csv'),
            VirtualOfficeActivityCnae::ANEXO_A,
        );

        app(AuditService::class)->log(
            logName: 'escritorio-virtual',
            event: 'importacao-lista-ev',
            description: 'Importação das listas de atividade por anexo (escritório virtual)',
            properties: [
                'anexo_a' => $relatorioA['importados'],
                'anexo_b' => $relatorioB['importados'],
            ],
            rulesVersion: 'ev-snapshot-2026-07-16',
        );
    }
}

<?php

namespace Database\Seeders;

use App\Services\CnaeImportService;
use App\Support\Audit\AuditService;
use Illuminate\Database\Seeder;

/**
 * Carga oficial de CNAEs (HU-011): delega o import real ao service e
 * registra o relatório (contadores + divergência com a publicação oficial)
 * na trilha de auditoria com a versão de regras da fonte.
 */
class CnaeSeeder extends Seeder
{
    public function run(): void
    {
        $report = app(CnaeImportService::class)->import(database_path('data/cnaes-subclasses-2-3.csv'));

        app(AuditService::class)->log(
            logName: 'cnaes',
            event: 'importacao-oficial',
            description: 'Importação da estrutura oficial CNAE-Subclasses 2.3 (IBGE/CONCLA)',
            properties: $report,
            rulesVersion: 'cnae-subclasses-2.3',
        );
    }
}

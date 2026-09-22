<?php

namespace Database\Seeders;

use App\Services\Analise\TllValoresImportService;
use App\Support\Audit\AuditService;
use Illuminate\Database\Seeder;

/**
 * Carga oficial da tabela TLL 2026 (Simplifica). Publica o exercício como
 * vigente do domínio tll_valores — a lógica do DAM não muda, só a carga.
 */
class TllValoresSeeder extends Seeder
{
    public function run(): void
    {
        $report = app(TllValoresImportService::class)->import(
            database_path('data/tll/taxas-tll-2026.csv'),
        );

        app(AuditService::class)->log(
            logName: 'regras',
            event: 'importacao-oficial-tll',
            description: 'Importação da tabela oficial TLL 2026 (Simplifica)',
            properties: $report,
            rulesVersion: (string) TllValoresImportService::EXERCICIO,
        );
    }
}

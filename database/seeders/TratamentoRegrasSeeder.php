<?php

namespace Database\Seeders;

use App\Enums\RuleDomain;
use App\Models\RuleVersion;
use App\Models\TratamentoCnaeBinding;
use App\Models\TratamentoCondicionante;
use App\Models\TratamentoEnquadramento;
use App\Models\TratamentoPergunta;
use App\Models\TratamentoRegra;
use App\Models\TratamentoRegraRamo;
use App\Services\Rules\RuleVersionService;
use App\Services\Tratamento\TratamentoRegrasImportService;
use App\Support\Audit\AuditService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class TratamentoRegrasSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable((new TratamentoCondicionante)->getTable())) {
            return;
        }

        $rules = app(RuleVersionService::class);

        $version = RuleVersion::vigente(RuleDomain::RiscoTratamento)->first();

        if ($version === null) {
            $draft = $rules->openDraft(
                RuleDomain::RiscoTratamento,
                'planilha-20-08-26',
                'Planilha de regras de tratamento 20.08.26',
            );

            $version = $rules->publish($draft);
        }

        $enquadramentosExistem = TratamentoEnquadramento::query()
            ->where('rule_version_id', $version->getKey())
            ->exists();

        // Planilha completa (enquadramentos + ramos curados + condicionantes):
        // nada a fazer. Carga PARCIAL — o caso da versão criada antes do
        // regras-fluxo.csv existir (incidente VIA-2026-000149) — segue para o
        // import, que é upsert idempotente e completa o que falta SEM apagar
        // o dado já em uso pela versão vigente.
        if ($enquadramentosExistem
            && TratamentoRegraRamo::query()->where('rule_version_id', $version->getKey())->exists()
            && TratamentoCondicionante::query()->where('rule_version_id', $version->getKey())->exists()) {
            return;
        }

        if (! $enquadramentosExistem) {
            TratamentoPergunta::query()->where('rule_version_id', $version->getKey())->delete();
            TratamentoRegra::query()->where('rule_version_id', $version->getKey())->delete();
            TratamentoEnquadramento::query()->where('rule_version_id', $version->getKey())->delete();
            TratamentoCnaeBinding::query()->where('rule_version_id', $version->getKey())->delete();
        }

        $report = app(TratamentoRegrasImportService::class)->import(
            $version,
            database_path('data/regras-20-08-26'),
        );

        app(AuditService::class)->log(
            logName: 'tratamento',
            event: 'importacao-planilha',
            description: 'Carga da planilha 20.08.26',
            subject: $version,
            properties: $report,
            result: 'sucesso',
            rulesVersion: $version->version,
        );
    }
}
